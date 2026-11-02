<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\EditorialMember;
use App\Models\Setting;
use App\Services\JournalPages;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$base = null;
foreach ([dirname(__DIR__), realpath(__DIR__.'/../../journal_app')] as $candidate) {
    if (is_string($candidate) && is_file($candidate.'/vendor/autoload.php')) {
        $base = $candidate;
        break;
    }
}
if (! $base) {
    throw new RuntimeException('The private Laravel application is unavailable.');
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
    throw new RuntimeException('This is not the intended SJNR preview.');
}
$deployment = $base.'/storage/app/private/deployment';
$lock = fopen($deployment.'/sjc-pages.lock', 'c');
if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
$marker = $deployment.'/sjc-pages-imported.json';
if (is_file($marker)) {
    echo file_get_contents($marker)."\n";
    exit(0);
}
$private = $deployment.'/sjc-pages';
$directory = realpath(is_file($private.'/manifest.json') ? $private : public_path('sjc-pages-import'));
if (! $directory || ! is_file($directory.'/manifest.json') || filesize($directory.'/manifest.json') > 8_000_000) {
    throw new RuntimeException('The reviewed public information snapshot is unavailable.');
}
$data = json_decode(file_get_contents($directory.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
if (($data['schema_version'] ?? null) !== 1 || ($data['source'] ?? null) !== 'https://sjcjournal.com'
    || ! is_array($data['pages'] ?? null) || ! is_array($data['editorial_members'] ?? null)
    || count($data['editorial_members']) !== 7 || ! is_array($data['assets'] ?? null)) {
    throw new RuntimeException('The source public information inventory is invalid.');
}
foreach (['authors', 'resources', 'public_contact_fields', 'categories', 'featured_article_paths', 'homepage_latest_paths'] as $key) {
    if (! is_array($data[$key] ?? null)) {
        throw new RuntimeException('An expected public collection is unavailable.');
    }
}
$sourceUrl = static function (string $url): bool {
    $parts = parse_url($url);

    return is_array($parts) && ($parts['scheme'] ?? null) === 'https' && ($parts['host'] ?? null) === 'sjcjournal.com'
        && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['port']);
};
$assets = [];
foreach ($data['assets'] as $row) {
    $file = realpath($directory.'/'.($row['file'] ?? ''));
    if (! $file || ! str_starts_with($file, $directory.DIRECTORY_SEPARATOR)
        || ! preg_match('/\Aimports\/sjc-pages\/[a-f0-9]{64}\.(?:pdf|jpg|jpeg|png|webp|svg)\z/', $row['path'] ?? '')
        || ! $sourceUrl($row['source_url'] ?? '') || filesize($file) !== ($row['bytes'] ?? null)
        || filesize($file) > 80_000_000 || ! hash_equals($row['sha256'] ?? '', hash_file('sha256', $file))) {
        throw new RuntimeException('A public source asset failed its path or hash validation.');
    }
    if (str_ends_with($row['path'], '.pdf') && file_get_contents($file, false, null, 0, 5) !== '%PDF-') {
        throw new RuntimeException('A source PDF is invalid.');
    }
    $assets[$row['path']] = ['file' => $file, 'row' => $row];
}
foreach ($data['pages'] as $row) {
    if (! isset(config('journal_pages')[$row['slug'] ?? '']) || ! is_string($row['content'] ?? null)
        || strlen($row['content']) > 1_000_000 || ! is_bool($row['published'] ?? null) || ! $sourceUrl($row['source_url'] ?? '')) {
        throw new RuntimeException('A source journal page is invalid.');
    }
}
foreach ($data['editorial_members'] as $row) {
    if (! is_string($row['name'] ?? null) || mb_strlen($row['name']) > 180 || ! is_string($row['role'] ?? null)
        || ! in_array($row['group'] ?? null, ['editorial_board', 'reviewers', 'advisors', 'publisher_staff'], true)
        || (isset($row['photo']) && ! isset($assets[$row['photo']['path']]))) {
        throw new RuntimeException('A source editorial profile is invalid.');
    }
}
$before = ['articles' => Article::query()->count(), 'users' => DB::table('users')->count()];
if ($before['articles'] !== 48) {
    throw new RuntimeException('The expected retained article collection is not present.');
}
// Back up every record that this intentional public-content replacement can alter.
$backup = [
    'created_at' => gmdate('c'), 'settings' => Setting::query()->where('key', 'like', 'journal_page.%')
        ->orWhereIn('key', ['journal.resources', 'contact.phone', 'contact.whatsapp', 'contact.address'])->get()->toArray(),
    'editorial_members' => EditorialMember::query()->get()->toArray(),
    'authors' => Author::query()->get()->toArray(), 'categories' => Category::query()->get()->toArray(),
    'article_flags' => Article::query()->get(['id', 'slug', 'is_featured', 'is_homepage_latest'])->toArray(),
];
$backupFile = $deployment.'/before-sjc-pages-'.gmdate('Ymd-His').'.json';
file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
chmod($backupFile, 0600);
foreach ($assets as $path => $asset) {
    $disk = Storage::disk('public');
    if ($disk->exists($path)) {
        if (! hash_equals($asset['row']['sha256'], hash('sha256', $disk->get($path)))) {
            throw new RuntimeException('A later public-file change was preserved.');
        }

        continue;
    }
    $stream = fopen($asset['file'], 'rb');
    try {
        if (! $disk->put($path, $stream)) {
            throw new RuntimeException('A public source file could not be saved.');
        }
    } finally {
        fclose($stream);
    }
}
$summary = ['source' => $data['source'], 'pages' => 0, 'published_pages' => 0, 'preserved_pages' => 0, 'editorial_members' => 0, 'authors_updated' => 0,
    'resources' => count($data['resources']), 'categories' => count($data['categories']), 'featured_articles' => 0, 'backup_file' => basename($backupFile)];
DB::transaction(function () use ($data, &$summary): void {
    foreach ($data['pages'] as $row) {
        $existing = JournalPages::state($row['slug']);
        if (! $row['published'] && $existing['published'] && filled($existing['content'])) {
            Setting::put('import.sjc.page.'.$row['slug'], $row, 'imports', false);
            $summary['pages']++;
            $summary['preserved_pages']++;

            continue;
        }
        $content = str_replace(['Singapore Journal of Cardiology', 'Singapore Journal of Chemistry'], config('publication.name'), $row['content']);
        // Do not transplant old contact addresses into the new site's mail workflow.
        $content = preg_replace('/mailto:[^\s)]+/', 'mailto:'.config('publication.contact_email'), $content);
        Setting::put('journal_page.'.$row['slug'], ['draft' => $content, 'content' => $content, 'published' => $row['published'], 'version' => 1, 'updated_at' => now()->toISOString()], 'journal_pages', false);
        Setting::put('import.sjc.page.'.$row['slug'], $row, 'imports', false);
        $summary['pages']++;
        $summary['published_pages'] += (int) $row['published'];
    }
    EditorialMember::query()->update(['is_active' => false]);
    foreach ($data['editorial_members'] as $position => $row) {
        $member = EditorialMember::query()->create(['name' => $row['name'], 'group' => $row['group'], 'role' => $row['role'],
            'institution' => $row['institution'], 'biography' => $row['biography'], 'photo_path' => $row['photo']['path'] ?? null,
            'sort_order' => $position, 'is_active' => true]);
        Setting::put('import.sjc.editorial.'.$member->getKey(), $row, 'imports', false);
        $summary['editorial_members']++;
    }
    foreach ($data['authors'] as $row) {
        $author = Author::query()->where('slug', $row['slug'])->first();
        if (! $author || $author->user_id !== null) {
            continue;
        }
        $fields = array_filter(['biography' => $row['biography'], 'designation' => $row['designation'], 'organization' => $row['organization'], 'avatar_path' => $row['photo']['path'] ?? null], fn ($value) => filled($value));
        if ($fields !== []) {
            $author->fill($fields)->save();
            $summary['authors_updated']++;
        }
        Setting::put('import.sjc.author.'.$row['slug'], $row, 'imports', false);
    }
    $resources = [];
    foreach ($data['resources'] as $row) {
        $resources[] = [$row['title'], $row['filename'], '/storage/'.$row['asset']['path'], $row['description'], $row['asset']['path']];
    }
    Setting::put('journal.resources', $resources, 'publication', false);
    foreach ($data['public_contact_fields'] as $key => $value) {
        if (in_array($key, ['contact.phone', 'contact.whatsapp', 'contact.address'], true)) {
            Setting::put($key, $value, 'contact', true);
        }
    }
    foreach ($data['categories'] as $row) {
        $category = Category::query()->where('slug', $row['slug'])->first();
        if (! $category) {
            $category = new Category(['name' => $row['name']]);
        }
        $category->fill(['name' => $row['name'], 'description' => $row['description'], 'is_active' => true])->save();
    }
    Article::query()->update(['is_featured' => false, 'is_homepage_latest' => false]);
    foreach ($data['featured_article_paths'] as $path) {
        $summary['featured_articles'] += Article::query()->where('slug', basename($path))->update(['is_featured' => true]);
    }
    foreach ($data['homepage_latest_paths'] as $path) {
        Article::query()->where('slug', basename($path))->update(['is_homepage_latest' => true]);
    }
    Setting::put('import.sjc.public_pages_summary', $summary, 'imports', false);
});
$summary['completed_at'] = gmdate('c');
$summary['articles_retained'] = Article::query()->count();
$summary['users_retained'] = DB::table('users')->count();
if ($summary['articles_retained'] !== $before['articles'] || $summary['users_retained'] !== $before['users']) {
    throw new RuntimeException('The retained record counts changed unexpectedly.');
}
file_put_contents($marker, json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
chmod($marker, 0600);
echo json_encode($summary, JSON_THROW_ON_ERROR)."\n";
