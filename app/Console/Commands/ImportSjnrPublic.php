<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\EditorialMember;
use App\Models\JournalIssue;
use App\Models\JournalVolume;
use App\Models\Setting;
use App\Models\User;
use App\Services\JournalPages;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ImportSjnrPublic extends Command
{
    protected $signature = 'journal:import-sjnr-public {directory}
        {--creator= : Existing provenance account ID; no accounts are created or activated}
        {--apply : Import the reviewed public archive on an initialized SJNR preview}';

    protected $description = 'Import the public SJNR archive, original PDFs and journal pages into a separate SJNR preview';

    public function handle(): int
    {
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        if (config('journal.profile') !== 'sjnr'
            || (! app()->environment('testing') && ! str_ends_with($host, '.hostingersite.com'))) {
            $this->components->error('Import requires the SJNR profile on a temporary preview or an isolated testing application.');

            return self::FAILURE;
        }

        try {
            $directory = realpath((string) $this->argument('directory'));
            if ($directory === false || ! is_file($directory.'/manifest.json')
                || filesize($directory.'/manifest.json') > 5_000_000) {
                throw new RuntimeException('A readable public-import directory and bounded manifest are required.');
            }
            $data = json_decode(file_get_contents($directory.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            $files = $this->validate($data, $directory);
            $this->components->info(count($data['articles']).' articles, '.count($data['editorial_members']).' editorial profiles and '.count($data['pages']).' source pages reviewed.');

            if (! $this->option('apply')) {
                $this->line('Preview only. Use --creator=ID --apply after confirming the separate target and source manifest.');

                return self::SUCCESS;
            }

            if (Setting::value('deployment.sjnr_initialized_at') === null) {
                throw new RuntimeException('Initialize the separate SJNR database before importing public content.');
            }
            $creator = User::query()->find($this->option('creator'));
            if ($creator === null) {
                throw new RuntimeException('Select an existing provenance account with --creator. This import never creates accounts.');
            }
            foreach (Article::withTrashed()->get(['id', 'article_number']) as $article) {
                if (! preg_match('/\ASJNR-LEGACY-(\d+)\z/', (string) $article->article_number, $match)
                    || Setting::value('import.sjnr.article.'.$match[1]) === null) {
                    throw new RuntimeException('Unrelated article records exist. Import refused without changing them.');
                }
            }

            $counts = ['articles' => 0, 'existing_articles' => 0, 'authors' => 0, 'editorial_members' => 0, 'pages' => 0, 'preserved_pages' => 0];
            DB::transaction(function () use ($data, $creator, $files, &$counts): void {
                $category = Category::query()->firstOrCreate(['slug' => 'nursing-research'], [
                    'name' => 'Nursing Research', 'description' => 'Original nursing and healthcare research.', 'is_active' => true,
                ]);
                foreach ($data['articles'] as $position => $row) {
                    $number = 'SJNR-LEGACY-'.$row['id'];
                    if (Article::withTrashed()->where('article_number', $number)->exists()) {
                        $counts['existing_articles']++;

                        continue;
                    }
                    if (filled($row['doi']) && Article::withTrashed()->where('doi', $row['doi'])->exists()) {
                        throw new RuntimeException('An unrelated DOI collision requires editorial review.');
                    }
                    $pdfPath = 'articles/sjnr/'.$row['id'].'-'.$row['pdf']['sha256'].'.pdf';
                    $this->storeFile('local', $pdfPath, $files[$row['pdf']['file']], $row['pdf']['sha256']);
                    $issue = $this->issueFor($row, $data['issues']);
                    $body = '<p>The complete published manuscript, including its figures, tables and references, is available in the PDF reader and download.</p>';
                    $article = Article::query()->create([
                        'created_by_id' => $creator->getKey(),
                        'category_id' => $category->getKey(),
                        'journal_issue_id' => $issue?->getKey(),
                        'article_number' => $number,
                        'title' => $row['title'],
                        'abstract' => $row['abstract'] ?? null,
                        'keywords' => $row['keywords'] ?? [],
                        'excerpt' => filled($row['abstract'] ?? null) ? Str::limit($row['abstract'], 300) : 'Read the complete published article, including its figures, tables and references.',
                        'content' => $body,
                        'doi' => $row['doi'],
                        'volume' => $row['volume'],
                        'issue' => $row['issue'],
                        'status' => 'published',
                        'published_at' => CarbonImmutable::parse($row['published_date'])->startOfDay(),
                        'pdf_path' => $pdfPath,
                        'pdf_download_enabled' => true,
                        'is_featured' => $position === 0,
                    ]);
                    $attached = [];
                    foreach ($row['authors'] as $name) {
                        $slug = Str::slug($name);
                        if ($slug === '' || isset($attached[$slug])) {
                            continue;
                        }
                        $attached[$slug] = true;
                        $author = Author::withTrashed()->where('slug', $slug)->first();
                        if ($author?->trashed()) {
                            continue;
                        }
                        if ($author === null) {
                            $author = Author::query()->create(['name' => $name, 'is_active' => true, 'is_verified' => false]);
                            $counts['authors']++;
                        }
                        $article->authors()->attach($author->getKey(), ['sort_order' => count($attached) - 1, 'is_corresponding' => false]);
                    }
                    Setting::put('import.sjnr.article.'.$row['id'], $row, 'imports', false);
                    $counts['articles']++;
                }

                foreach ($data['media'] as $row) {
                    $this->storeFile('public', $row['path'], $files[$row['file']], $row['sha256']);
                }
                foreach ($data['resources'] as $row) {
                    $this->storeFile('public', $row['path'], $files[$row['file']], $row['sha256']);
                }
                foreach ($data['editorial_members'] as $position => $row) {
                    $key = 'import.sjnr.editorial.'.hash('sha256', $row['source_url'].'|'.$row['name'].'|'.$row['role']);
                    if (Setting::value($key) !== null) {
                        continue;
                    }
                    $member = EditorialMember::query()->firstOrCreate(['name' => $row['name'], 'role' => $row['role']], [
                        'group' => 'editorial_board', 'institution' => $row['institution'] ?: null,
                        'photo_path' => $row['photo']['path'] ?? null,
                        'biography' => $row['biography'] ?? null,
                        'sort_order' => $position, 'is_active' => true,
                    ]);
                    Setting::put($key, ['id' => $member->getKey(), 'source' => $row], 'imports', false);
                    if ($member->wasRecentlyCreated) {
                        $counts['editorial_members']++;
                    }
                }
                foreach ($data['pages'] as $row) {
                    $key = 'journal_page.'.$row['slug'];
                    if (Setting::query()->whereKey($key)->exists()) {
                        $counts['preserved_pages']++;

                        continue;
                    }
                    $content = $this->siteContacts($row['content']);
                    Setting::put($key, [
                        'draft' => $content, 'content' => $content, 'published' => (bool) $row['published'],
                        'updated_at' => now()->toISOString(), 'version' => 1,
                    ], 'journal_pages', false);
                    Setting::put('import.sjnr.page.'.$row['slug'], ['source_url' => $row['source_url'], 'title' => $row['title']], 'imports', false);
                    $counts['pages']++;
                }
                Setting::put('import.sjnr.summary', [
                    'imported_at' => now()->toISOString(), 'counts' => $counts,
                    'source' => $data['source'], 'issues' => $data['issues'],
                    'warnings' => $data['warnings'], 'resources' => $data['resources'],
                ], 'imports', false);
                Setting::put('import.sjnr.legacy_routes', $this->legacyRoutes($data['legacy_routes'] ?? [], $data['articles']), 'imports', false);
            });
            $this->line(json_encode($counts, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->components->error($error->getMessage());

            return self::FAILURE;
        }
    }

    /** @return array<string, string> */
    private function validate(mixed $data, string $directory): array
    {
        if (! is_array($data) || ($data['schema_version'] ?? null) !== 1 || ($data['source'] ?? null) !== 'https://sjnrjournal.com'
            || ! array_key_exists('errors', $data) || $data['errors'] !== []) {
            throw new RuntimeException('The public SJNR manifest is invalid or has unresolved collection errors.');
        }
        foreach (['articles', 'issues', 'editorial_members', 'pages', 'media', 'resources', 'warnings'] as $key) {
            if (! isset($data[$key]) || ! is_array($data[$key])) {
                throw new RuntimeException('Manifest arrays are missing.');
            }
        }
        $files = [];
        $ids = [];
        foreach ($data['articles'] as $row) {
            $id = (string) ($row['id'] ?? '');
            if (! ctype_digit($id) || isset($ids[$id])
                || ! preg_match('~\Ahttps://sjnrjournal\.com/journals/'.$id.'/[^/]+/abstract\z~', $row['source_url'] ?? '')
                || ! is_string($row['title'] ?? null) || mb_strlen($row['title']) > 240 || trim($row['title']) === ''
                || ! preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $row['slug'] ?? '')
                || ! is_array($row['authors'] ?? null) || ! isset($row['pdf']) || ! is_array($row['pdf'])) {
                throw new RuntimeException('Invalid or duplicate article metadata.');
            }
            $ids[$id] = true;
            $date = CarbonImmutable::createFromFormat('!Y-m-d', (string) ($row['published_date'] ?? ''));
            if ($date === false || $date->format('Y-m-d') !== $row['published_date'] || $date->isFuture()) {
                throw new RuntimeException('Invalid source publication date.');
            }
            foreach ($row['authors'] as $name) {
                if (! is_string($name) || mb_strlen($name) > 255 || trim($name) === '') {
                    throw new RuntimeException('Invalid author credit.');
                }
            }
            if (($row['doi'] ?? null) !== null && ! preg_match('~\A10\.\d{4,9}/\S+\z~', $row['doi'])) {
                throw new RuntimeException('Invalid source DOI.');
            }
            foreach (['volume', 'issue'] as $key) {
                if (($row[$key] ?? null) !== null && (! is_string($row[$key]) || ! preg_match('/\A\d{1,4}\z/', $row[$key]))) {
                    throw new RuntimeException('Invalid source issue metadata.');
                }
            }
            if (($row['pdf']['file'] ?? null) !== 'pdfs/'.$id.'.pdf') {
                throw new RuntimeException('Unexpected source PDF path.');
            }
            $files[$row['pdf']['file']] = $this->checkedFile($directory, $row['pdf'], 'pdf');
        }
        foreach ($data['media'] as $row) {
            if (! preg_match('~\Aimports/sjnr/(editorial|pages)/[a-f0-9]{64}\.(jpg|png|gif|webp)\z~', $row['path'] ?? '')) {
                throw new RuntimeException('Unexpected media destination.');
            }
            $files[$row['file']] = $this->checkedFile($directory, $row, 'image');
        }
        foreach ($data['resources'] as $row) {
            if (! preg_match('~\Aimports/sjnr/resources/[a-z-]+\.pdf\z~', $row['path'] ?? '')) {
                throw new RuntimeException('Unexpected resource destination.');
            }
            $files[$row['file']] = $this->checkedFile($directory, $row, 'pdf');
        }
        foreach ($data['pages'] as $row) {
            if (! array_key_exists($row['slug'] ?? '', JournalPages::all()) || ! is_string($row['content'] ?? null)
                || mb_strlen($row['content']) > 50000 || ! is_bool($row['published'] ?? null)) {
                throw new RuntimeException('Invalid journal page content.');
            }
            $this->checkedSourceUrl($row['source_url'] ?? '');
        }
        foreach ($data['editorial_members'] as $row) {
            if (! is_string($row['name'] ?? null) || trim($row['name']) === '' || mb_strlen($row['name']) > 255
                || ! in_array($row['role'] ?? null, ['Editor', 'Editor-in-Chief'], true)
                || ! is_string($row['institution'] ?? null) || mb_strlen($row['institution']) > 255) {
                throw new RuntimeException('Invalid editorial profile.');
            }
            $this->checkedSourceUrl($row['source_url'] ?? '');
            if (($row['photo'] ?? null) !== null && ! isset($files[$row['photo']['file'] ?? ''])) {
                throw new RuntimeException('Editorial photograph is missing from the validated media manifest.');
            }
            if (($row['photo'] ?? null) !== null && ! collect($data['media'])->contains(fn ($media): bool => $media['file'] === $row['photo']['file'] && $media['path'] === ($row['photo']['path'] ?? null))) {
                throw new RuntimeException('Editorial photograph destination does not match the validated media manifest.');
            }
        }

        return $files;
    }

    private function checkedSourceUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ($parts['host'] ?? null) !== 'sjnrjournal.com'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new RuntimeException('A provenance URL is outside the public SJNR website.');
        }
    }

    /** @param array<string, mixed> $row */
    private function checkedFile(string $directory, array $row, string $type): string
    {
        $relative = (string) ($row['file'] ?? '');
        $pattern = $type === 'pdf' ? '~\A(?:pdfs/\d+|resources/[a-z-]+)\.pdf\z~' : '~\Aimages/[a-f0-9]{64}\.(jpg|png|gif|webp)\z~';
        $file = realpath($directory.'/'.$relative);
        if (! preg_match($pattern, $relative) || $file === false || ! str_starts_with($file, $directory.DIRECTORY_SEPARATOR)
            || is_link($directory.'/'.$relative) || ! is_file($file) || filesize($file) > 30_000_000
            || ! preg_match('/\A[a-f0-9]{64}\z/', $row['sha256'] ?? '')
            || ! hash_equals($row['sha256'], hash_file('sha256', $file))) {
            throw new RuntimeException('An imported file is missing, outside its directory or has an invalid checksum.');
        }
        $this->checkedSourceUrl($row['source_url'] ?? '');
        if ($type === 'pdf' && file_get_contents($file, false, null, 0, 5) !== '%PDF-') {
            throw new RuntimeException('An imported PDF lacks its expected signature.');
        }
        if ($type === 'image' && getimagesize($file) === false) {
            throw new RuntimeException('An imported photograph is not a recognized image.');
        }

        return $file;
    }

    private function storeFile(string $disk, string $destination, string $file, string $checksum): void
    {
        $storage = Storage::disk($disk);
        if ($storage->exists($destination)) {
            if (! hash_equals($checksum, hash('sha256', $storage->get($destination)))) {
                throw new RuntimeException('An existing file at an import destination has changed; preserving it for review.');
            }

            return;
        }
        $stream = fopen($file, 'rb');
        try {
            if ($stream === false || ! $storage->put($destination, $stream)) {
                throw new RuntimeException('The imported file could not be saved.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function issueFor(array $row, array $issues): ?JournalIssue
    {
        if (blank($row['volume']) || blank($row['issue'])) {
            return null;
        }
        $year = (int) substr($row['published_date'], 0, 4);
        $volume = JournalVolume::query()->firstOrCreate(['number' => $row['volume'], 'year' => $year]);
        $source = collect($issues)->first(fn ($issue): bool => in_array($row['id'], $issue['article_ids'], true));

        $issue = JournalIssue::query()->firstOrCreate(['journal_volume_id' => $volume->getKey(), 'number' => $row['issue']], [
            'title' => $source['label'] ?? null,
            // An issue-wide publication date is not inferred from article dates.
            'publication_date' => null,
            'is_current' => false,
        ]);
        if ($source !== null && isset($source['source_id'])) {
            Setting::put('import.sjnr.issue.'.$source['source_id'], ['id' => $issue->getKey(), 'source' => $source], 'imports', false);
        }

        return $issue;
    }

    private function legacyRoutes(array $rows, array $articles): array
    {
        // Canonical slugs can be shortened, deduplicated or later edited locally.
        // Resolve the source aliases by imported identity instead of assuming its slug survived creation.
        $storedSlugs = Article::query()->whereIn('article_number', array_map(
            fn ($article): string => 'SJNR-LEGACY-'.$article['id'], $articles,
        ))->pluck('slug', 'article_number')->all();
        $articleAliases = [];
        foreach ($articles as $article) {
            $slug = $storedSlugs['SJNR-LEGACY-'.$article['id']] ?? null;
            if (! is_string($slug) || $slug === '') {
                continue;
            }
            foreach ([$article['source_url'], $article['pdf']['source_url']] as $url) {
                $articleAliases[rawurldecode((string) parse_url($url, PHP_URL_PATH))] = $slug;
            }
        }

        $mapped = [];
        foreach ($rows as $row) {
            if (! is_string($row['source_path'] ?? null) || ! str_starts_with($row['source_path'], '/') || str_starts_with($row['source_path'], '//')) {
                continue;
            }
            if (isset($row['public_path']) && is_string($row['public_path']) && str_starts_with($row['public_path'], '/storage/imports/sjnr/')) {
                $mapped[$row['source_path']] = $row['public_path'];

                continue;
            }
            if (! in_array($row['route'] ?? null, ['articles.show', 'articles.pdf', 'about', 'policies.show', 'archive.index', 'archive.issue', 'current-issue', 'editorial-board'], true)) {
                continue;
            }
            $parameters = $row['parameters'] ?? [];
            if (in_array($row['route'], ['articles.show', 'articles.pdf'], true)) {
                $slug = $articleAliases[$row['source_path']] ?? null;
                if ($slug === null) {
                    continue;
                }
                $parameters = ['slug' => $slug];
            }
            if ($row['route'] === 'archive.issue') {
                $issue = Setting::value('import.sjnr.issue.'.($row['source_issue_id'] ?? ''));
                if (! $issue) {
                    continue;
                }
                $parameters = ['issue' => $issue['id']];
            }
            $destination = route($row['route'], $parameters, false);
            if ($destination !== $row['source_path']) {
                $mapped[$row['source_path']] = $destination;
            }
        }

        return $mapped;
    }

    private function siteContacts(string $markdown): string
    {
        $mailbox = config('publication.contact_email');
        if (! is_string($mailbox) || ! filter_var($mailbox, FILTER_VALIDATE_EMAIL)) {
            return $markdown;
        }

        // Only old journal office addresses change. Author credits and PDFs remain original.
        return preg_replace('/\b(?:info|submit|support|editorial)@larixjournals\.com\b/i', $mailbox, $markdown) ?? $markdown;
    }
}
