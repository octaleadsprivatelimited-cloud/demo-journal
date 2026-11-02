<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\JournalIssue;
use App\Models\JournalVolume;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// This helper is never a web endpoint, even if staged under a denied public directory.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$candidates = [dirname(__DIR__), realpath(__DIR__.'/../../journal_app')];
$base = null;
foreach ($candidates as $candidate) {
    if (is_string($candidate) && is_file($candidate.'/vendor/autoload.php')) {
        $base = $candidate;
        break;
    }
}
if ($base === null) {
    throw new RuntimeException('The private Laravel application was not found.');
}
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if (config('journal.profile') !== 'sjnr'
    || parse_url((string) config('app.url'), PHP_URL_HOST) !== 'lightgreen-leopard-945544.hostingersite.com'
    || $connection->getDriverName() !== 'mysql'
    || $connection->getDatabaseName() !== 'u709299470_sjnr_preview'
    || ! in_array($connection->getConfig('host'), ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('This is not the intended SJNR preview database.');
}
$deployment = $base.'/storage/app/private/deployment';
if (! is_file($deployment.'/public-imported.json')) {
    throw new RuntimeException('The original SJNR import must be complete first.');
}
$lock = fopen($deployment.'/sjc-public-import.lock', 'c');
if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
$marker = $deployment.'/sjc-public-imported.json';
if (is_file($marker)) {
    echo "The SJC public collection was already copied; existing records were preserved.\n";
    echo file_get_contents($marker)."\n";
    exit(0);
}
$private = $deployment.'/sjc-public';
$directory = is_file($private.'/manifest.json') ? $private : public_path('sjc-public-import');
$directory = realpath($directory);
if (! $directory || ! is_file($directory.'/manifest.json') || filesize($directory.'/manifest.json') > 5_000_000) {
    throw new RuntimeException('The reviewed SJC public snapshot is unavailable.');
}
$data = json_decode(file_get_contents($directory.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
if (($data['schema_version'] ?? null) !== 1 || ($data['source'] ?? null) !== 'https://sjcjournal.com'
    || ($data['source_count'] ?? null) !== 39 || ! is_array($data['articles'] ?? null) || count($data['articles']) !== 39) {
    throw new RuntimeException('The public source inventory is incomplete.');
}
$files = [];
$identities = [];
$slugs = [];
foreach ($data['articles'] as $row) {
    if (! preg_match('/\A[a-f0-9]{20}\z/', $row['id'] ?? '') || isset($identities[$row['id']])
        || ! preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $row['slug'] ?? '') || strlen($row['slug']) > 255
        || isset($slugs[$row['slug']]) || ! is_string($row['title'] ?? null) || $row['title'] === '' || mb_strlen($row['title']) > 255
        || ! is_array($row['authors'] ?? null) || count($row['authors']) > 100
        || ! preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $row['published_date'] ?? '')
        || ($row['source_url'] ?? null) !== 'https://sjcjournal.com/article/'.$row['slug']) {
        throw new RuntimeException('Invalid public article identity or metadata.');
    }
    foreach ($row['authors'] as $name) {
        if (! is_string($name) || $name === '' || mb_strlen($name) > 255) {
            throw new RuntimeException('Invalid public author name.');
        }
    }
    $identities[$row['id']] = true;
    $slugs[$row['slug']] = true;
    if (($row['pdf']['source_url'] ?? null) !== $row['source_url'].'/pdf') {
        throw new RuntimeException('Unexpected public PDF endpoint.');
    }
    foreach (['pdf', 'image'] as $kind) {
        if (! isset($row[$kind])) {
            if ($kind === 'pdf') {
                throw new RuntimeException('Every article must have its original PDF.');
            }

            continue;
        }
        $asset = $row[$kind];
        $file = realpath($directory.'/'.($asset['file'] ?? ''));
        $url = parse_url((string) ($asset['source_url'] ?? ''));
        if (! $file || ! str_starts_with($file, $directory.DIRECTORY_SEPARATOR) || ! is_file($file)
            || ! preg_match('/\A[a-f0-9]{64}\z/', $asset['sha256'] ?? '')
            || ! hash_equals($asset['sha256'], hash_file('sha256', $file))
            || filesize($file) !== ($asset['bytes'] ?? null) || filesize($file) > 80_000_000
            || ($url['scheme'] ?? null) !== 'https' || ($url['host'] ?? null) !== 'sjcjournal.com'
            || isset($url['user']) || isset($url['pass']) || isset($url['port'])) {
            throw new RuntimeException('A source asset failed its integrity or path check.');
        }
        if ($kind === 'pdf' && file_get_contents($file, false, null, 0, 5) !== '%PDF-') {
            throw new RuntimeException('An original PDF is invalid.');
        }
        if ($kind === 'image' && ! in_array((new finfo(FILEINFO_MIME_TYPE))->file($file), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('An original cover image is invalid.');
        }
        $files[$asset['file']] = $file;
    }
}

$creator = User::query()->where('email', 'archive-migration@sjnrjournal.com')->firstOrFail();
if ($creator->is_active || $creator->roles()->exists()) {
    throw new RuntimeException('The inactive provenance identity is unavailable.');
}
// Preflight all collisions before the first article or file is written.
foreach ($data['articles'] as $row) {
    $number = 'SJNR-LEGACY-SJC-'.$row['id'];
    $existing = Article::withTrashed()->where('article_number', $number)->first();
    $provenance = Setting::value('import.sjc.article.'.$row['id']);
    if ($existing) {
        if (! is_array($provenance) || ($provenance['source_url'] ?? null) !== $row['source_url']) {
            throw new RuntimeException('An article identity collision requires review.');
        }

        continue;
    }
    if (Article::withTrashed()->where('slug', $row['slug'])->exists()
        || (filled($row['doi']) && Article::withTrashed()->where('doi', $row['doi'])->exists())) {
        throw new RuntimeException('An unrelated canonical URL or DOI already exists; no article was overwritten.');
    }
}
$store = static function (string $disk, string $path, array $asset) use ($files): void {
    $storage = Storage::disk($disk);
    if ($storage->exists($path)) {
        if (! hash_equals($asset['sha256'], hash('sha256', $storage->get($path)))) {
            throw new RuntimeException('A later file change was preserved.');
        }

        return;
    }
    $stream = fopen($files[$asset['file']], 'rb');
    try {
        if (! $storage->put($path, $stream)) {
            throw new RuntimeException('A source file could not be copied.');
        }
    } finally {
        fclose($stream);
    }
};
$summary = ['source' => $data['source'], 'source_count' => 39, 'created' => 0, 'existing' => 0, 'authors_created' => 0, 'images' => 0, 'total_before' => Article::query()->count(), 'canonical_articles' => []];
DB::transaction(function () use ($data, $creator, $store, &$summary): void {
    foreach ($data['articles'] as $row) {
        $number = 'SJNR-LEGACY-SJC-'.$row['id'];
        $existing = Article::withTrashed()->where('article_number', $number)->first();
        if ($existing) {
            $summary['existing']++;
            $summary['canonical_articles'][] = ['source_url' => $row['source_url'], 'slug' => $existing->slug, 'pdf_sha256' => $row['pdf']['sha256']];

            continue;
        }
        $category = Category::query()->firstOrCreate(['slug' => Str::slug($row['category'])], ['name' => $row['category'], 'is_active' => true]);
        $issue = null;
        if (filled($row['volume']) && filled($row['issue'])) {
            $volume = JournalVolume::query()->firstOrCreate(['number' => $row['volume'], 'year' => (int) substr($row['published_date'], 0, 4)]);
            $issue = JournalIssue::query()->firstOrCreate(['journal_volume_id' => $volume->getKey(), 'number' => $row['issue']], ['is_current' => false]);
        }
        $pdf = 'articles/sjc-copy/'.$row['id'].'-'.$row['pdf']['sha256'].'.pdf';
        $store('local', $pdf, $row['pdf']);
        $image = null;
        if (isset($row['image'])) {
            $image = 'imports/sjc-copy/images/'.$row['image']['sha256'].'.'.pathinfo($row['image']['file'], PATHINFO_EXTENSION);
            $store('public', $image, $row['image']);
            $summary['images']++;
        }
        $body = '<p>Originally published in the Singapore Journal of Cardiology. <a href="'.e($row['source_url']).'" rel="external noopener">View the original publication</a>. The original PDF, including its journal identity, authorship and copyright notices, is preserved unchanged.</p>';
        $article = Article::query()->create([
            'created_by_id' => $creator->getKey(), 'category_id' => $category->getKey(), 'journal_issue_id' => $issue?->getKey(),
            'article_number' => $number, 'title' => $row['title'], 'abstract' => $row['abstract'], 'keywords' => $row['keywords'],
            'content' => $body, 'doi' => $row['doi'], 'volume' => $row['volume'], 'issue' => $row['issue'],
            'status' => 'published', 'published_at' => $row['published_date'].' 00:00:00', 'pdf_path' => $pdf,
            'featured_image_path' => $image, 'pdf_download_enabled' => true, 'is_featured' => false,
        ]);
        // The source slug may differ from today's title; preserve its public URL.
        Article::query()->whereKey($article->getKey())->update(['slug' => $row['slug']]);
        $article->slug = $row['slug'];
        $attached = [];
        foreach ($row['authors'] as $name) {
            $slug = Str::slug($name);
            if ($slug === '' || isset($attached[$slug])) {
                continue;
            }
            $attached[$slug] = true;
            $author = Author::withTrashed()->where('slug', $slug)->first();
            if ($author?->trashed()) {
                throw new RuntimeException('A later author deletion requires review.');
            }
            if (! $author) {
                $author = Author::query()->create(['name' => $name, 'is_active' => true, 'is_verified' => false]);
                $summary['authors_created']++;
            }
            $article->authors()->attach($author->getKey(), ['sort_order' => count($attached) - 1, 'is_corresponding' => false]);
        }
        Setting::put('import.sjc.article.'.$row['id'], $row, 'imports', false);
        $summary['created']++;
        $summary['canonical_articles'][] = ['source_url' => $row['source_url'], 'slug' => $article->slug, 'pdf_sha256' => $row['pdf']['sha256']];
    }
    Setting::put('import.sjc.summary', $summary, 'imports', false);
});
$summary['completed_at'] = gmdate('c');
$summary['total_after'] = Article::query()->count();
$summary['imported'] = $summary['created'];
file_put_contents($marker, json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
chmod($marker, 0600);
echo 'SJC public article copy completed: '.json_encode($summary, JSON_THROW_ON_ERROR)."\n";
