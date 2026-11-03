<?php

declare(strict_types=1);

// Private CLI helper. Temporary public staging also requires an HTTP-denied
// directory; HTTP execution is rejected before any application code is loaded.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

final class SjnrDomainRepair
{
    private const BASE = '/home/u709299470/domains/sjnrjournal.com/journal_app';

    private const PUBLIC_PATH = '/home/u709299470/domains/sjnrjournal.com/public_html';

    private const URL = 'https://sjnrjournal.com';

    public static string $stage = 'target_guard';

    /** @param list<string> $arguments */
    public static function run(array $arguments): array
    {
        $privateLocation = realpath(__DIR__) === self::BASE.'/scripts'
            && realpath(dirname(__DIR__)) === self::BASE;
        $stagedLocation = realpath(__DIR__) === self::PUBLIC_PATH.'/sjnr-domain-repair'
            && realpath(__DIR__.'/../../journal_app') === self::BASE
            && is_file(__DIR__.'/.htaccess')
            && trim(file_get_contents(__DIR__.'/.htaccess')) === 'Require all denied';
        if (! $privateLocation && ! $stagedLocation) {
            throw new RuntimeException('The private application path is not the intended SJNR installation.');
        }
        if (array_diff($arguments, ['--enable-indexing']) !== []) {
            throw new RuntimeException('Unknown repair option.');
        }
        $enableIndexing = in_array('--enable-indexing', $arguments, true);
        $environment = self::BASE.'/.env';
        if (is_link($environment) || ! is_file($environment) || ! is_readable($environment) || filesize($environment) > 200_000) {
            throw new RuntimeException('The private environment file is unavailable.');
        }
        $originalEnvironment = file_get_contents($environment);
        require self::BASE.'/vendor/autoload.php';

        self::$stage = 'environment_guard';
        $values = \Dotenv\Dotenv::parse($originalEnvironment);
        if (($values['APP_ENV'] ?? '') !== 'production' || ($values['JOURNAL_PROFILE'] ?? '') !== 'sjnr'
            || ($values['DB_CONNECTION'] ?? '') !== 'mysql'
            || ($values['DB_DATABASE'] ?? '') !== 'u709299470_sjnr_preview'
            || ! in_array($values['DB_HOST'] ?? '', ['localhost', '127.0.0.1'], true)
            // The reviewed installation uses explicit DB_* fields. A DB_URL
            // hidden by an old config cache must not retarget future requests.
            || ($values['DB_URL'] ?? '') !== ''
            || ! in_array(rtrim($values['APP_URL'] ?? '', '/'), [self::URL, 'https://lightgreen-leopard-945544.hostingersite.com'], true)) {
            throw new RuntimeException('The environment does not identify the intended production SJNR database.');
        }

        self::$stage = 'resolved_configuration_guard';
        $app = require self::BASE.'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $connection = \Illuminate\Support\Facades\DB::connection();
        if (! $app->isProduction() || config('journal.profile') !== 'sjnr'
            || config('database.default') !== 'mysql'
            || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'u709299470_sjnr_preview'
            || ! in_array($connection->getConfig('host'), ['localhost', '127.0.0.1'], true)
            || ! in_array(rtrim((string) config('app.url'), '/'), [self::URL, 'https://lightgreen-leopard-945544.hostingersite.com'], true)) {
            throw new RuntimeException('The resolved configuration targets a different installation.');
        }
        $cachedConfiguration = $app->getCachedConfigPath();
        if ($cachedConfiguration !== self::BASE.'/bootstrap/cache/config.php'
            || is_link($cachedConfiguration)
            || realpath(self::PUBLIC_PATH) !== self::PUBLIC_PATH
            || realpath(self::PUBLIC_PATH.'/storage') !== self::PUBLIC_PATH.'/storage'
            || realpath(self::BASE.'/storage/app/private') !== self::BASE.'/storage/app/private') {
            throw new RuntimeException('The application or public storage paths require manual review.');
        }

        self::$stage = 'public_files_guard';
        $manifestCount = self::verifyManifest();
        $resources = \App\Models\Setting::value('journal.resources', []);
        self::verifyResources($resources);
        $countsBefore = self::recordCounts($connection);

        $updates = [
            'APP_URL' => self::URL,
            'SITEMAP_BASE_URL' => self::URL,
            'APP_PUBLIC_PATH' => self::PUBLIC_PATH,
            'PUBLIC_DISK_ROOT' => self::PUBLIC_PATH.'/storage',
        ];
        if ($enableIndexing) {
            $updates['SEARCH_INDEXING_ENABLED'] = 'true';
        }
        $newEnvironment = self::replaceEnvironmentValues($originalEnvironment, $updates);
        $htaccess = self::PUBLIC_PATH.'/.htaccess';
        if (is_link($htaccess) || ! is_file($htaccess) || ! is_readable($htaccess)) {
            throw new RuntimeException('The public rewrite configuration is unavailable.');
        }
        $originalHtaccess = file_get_contents($htaccess);
        $originalHtaccessMode = fileperms($htaccess) & 0777;
        $newHtaccess = $enableIndexing ? self::removePreviewNoindex($originalHtaccess) : $originalHtaccess;

        self::$stage = 'private_backup';
        $deployment = self::BASE.'/storage/app/private/deployment';
        if (! is_dir($deployment) && ! mkdir($deployment, 0700, true)) {
            throw new RuntimeException('The private deployment directory could not be created.');
        }
        if (realpath($deployment) !== $deployment) {
            throw new RuntimeException('The deployment directory is not private local storage.');
        }
        $lock = fopen($deployment.'/sjnr-domain-repair.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another domain repair is already running.');
        }
        chmod($deployment.'/sjnr-domain-repair.lock', 0600);
        // Recheck the input after taking the lock; never overwrite a concurrent edit.
        if (file_get_contents($environment) !== $originalEnvironment || file_get_contents($htaccess) !== $originalHtaccess) {
            throw new RuntimeException('Deployment configuration changed during preflight.');
        }
        $backupName = 'before-sjnr-domain-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
        $backup = $deployment.'/'.$backupName;
        if (! mkdir($backup, 0700)) {
            throw new RuntimeException('The private rollback directory could not be created.');
        }
        self::writePrivate($backup.'/.env', $originalEnvironment);
        $hadCachedConfiguration = is_file($cachedConfiguration);
        if ($hadCachedConfiguration) {
            self::writePrivate($backup.'/config.php', file_get_contents($cachedConfiguration));
        }
        self::writePrivate($backup.'/public.htaccess', $originalHtaccess);

        try {
            self::$stage = 'environment_update';
            if ($newEnvironment !== $originalEnvironment) {
                self::replaceAtomically($environment, $newEnvironment, 0600);
            }
            if ($newHtaccess !== $originalHtaccess) {
                self::replaceAtomically($htaccess, $newHtaccess, $originalHtaccessMode);
            }

            self::$stage = 'configuration_cache_clear';
            if (\Illuminate\Support\Facades\Artisan::call('config:clear') !== 0 || is_file($cachedConfiguration)) {
                throw new RuntimeException('The configuration cache could not be cleared.');
            }
            // This process was booted with the old configuration. Future requests
            // load the repaired .env; these overrides verify the same public paths now.
            config([
                'app.url' => self::URL,
                'hosting.public_path' => self::PUBLIC_PATH,
                'filesystems.disks.public.root' => self::PUBLIC_PATH.'/storage',
                'filesystems.disks.public.url' => self::URL.'/storage',
                'view.compiled' => self::BASE.'/storage/framework/views',
            ]);
            if ($enableIndexing) {
                config(['publication.indexing_enabled' => true]);
            }
            $app->usePublicPath(self::PUBLIC_PATH);
            \Illuminate\Support\Facades\Storage::forgetDisk('public');
            self::$stage = 'compiled_views_clear';
            if (\Illuminate\Support\Facades\Artisan::call('view:clear') !== 0) {
                throw new RuntimeException('The compiled views could not be cleared.');
            }

            self::$stage = 'verification';
            $savedValues = \Dotenv\Dotenv::parse(file_get_contents($environment));
            foreach ($updates as $key => $value) {
                if (($savedValues[$key] ?? null) !== $value) {
                    throw new RuntimeException('A repaired environment value did not persist.');
                }
            }
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            if (! is_readable(public_path('build/manifest.json'))
                || $disk->path('') !== self::PUBLIC_PATH.'/storage/'
                || $disk->url('health-check') !== self::URL.'/storage/health-check') {
                throw new RuntimeException('The repaired public paths did not resolve.');
            }
            foreach ($resources as $resource) {
                if (! $disk->exists($resource[4])) {
                    throw new RuntimeException('A resource is unavailable through the repaired public disk.');
                }
            }
            $countsAfter = self::recordCounts($connection);
            if ($countsAfter['articles'] < $countsBefore['articles'] || $countsAfter['users'] < $countsBefore['users']) {
                throw new RuntimeException('Journal records changed unexpectedly during verification.');
            }
            $summary = [
                'ok' => true,
                'completed_at' => gmdate('c'),
                'domain' => self::URL,
                'public_path' => self::PUBLIC_PATH,
                'environment_changed' => $newEnvironment !== $originalEnvironment,
                'indexing_enabled' => (bool) config('publication.indexing_enabled'),
                'noindex_header_removed' => $newHtaccess !== $originalHtaccess,
                'manifest_entries_verified' => $manifestCount,
                'resources_verified' => count($resources),
                'articles_retained' => $countsAfter['articles'],
                'users_retained' => $countsAfter['users'],
                'rollback_directory' => $backup,
            ];
            self::writePrivate($deployment.'/sjnr-domain-repaired.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");

            return $summary;
        } catch (Throwable $exception) {
            // The helper never changes journal rows. Restore file configuration
            // from the private snapshot if a subsequent check fails.
            self::replaceAtomically($environment, $originalEnvironment, 0600);
            self::replaceAtomically($htaccess, $originalHtaccess, $originalHtaccessMode);
            if ($hadCachedConfiguration) {
                self::replaceAtomically($cachedConfiguration, file_get_contents($backup.'/config.php'), 0600);
            }
            throw $exception;
        }
    }

    /** @param array<string,string> $updates */
    public static function replaceEnvironmentValues(string $content, array $updates): string
    {
        $lines = explode("\n", $content);
        foreach ($updates as $key => $value) {
            if (! preg_match('/\A[A-Z][A-Z0-9_]*\z/', $key) || preg_match('/[\s\x00"\'#$\\\\]/', $value)) {
                throw new RuntimeException('An environment replacement is invalid.');
            }
            $matches = [];
            foreach ($lines as $index => $line) {
                if (preg_match('/\A[ \t]*(?:export[ \t]+)?'.preg_quote($key, '/').'[ \t]*=/', $line)) {
                    $matches[] = $index;
                }
            }
            if (count($matches) > 1) {
                throw new RuntimeException('Duplicate deployment environment entries require review.');
            }
            if ($matches !== []) {
                $index = $matches[0];
                $lines[$index] = $key.'='.$value.(str_ends_with($lines[$index], "\r") ? "\r" : '');
            } else {
                $eol = str_contains($content, "\r\n") ? "\r" : '';
                if (end($lines) === '') {
                    array_pop($lines);
                }
                $lines[] = $key.'='.$value.$eol;
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    public static function removePreviewNoindex(string $content): string
    {
        $block = "<IfModule mod_headers.c>\n    Header always set X-Robots-Tag \"noindex, nofollow\"\n</IfModule>";
        $updated = str_replace([$block, str_replace("\n", "\r\n", $block)], '', $content);
        if (preg_match('/^[ \t]*Header\b[^\r\n]*X-Robots-Tag[^\r\n]*noindex/im', $updated)) {
            throw new RuntimeException('An unrecognized noindex directive requires review.');
        }

        return $updated;
    }

    private static function verifyManifest(): int
    {
        $build = self::PUBLIC_PATH.'/build';
        $manifest = json_decode(file_get_contents($build.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['resources/css/app.css', 'resources/js/app.js'] as $entry) {
            if (! isset($manifest[$entry]['file'])) {
                throw new RuntimeException('The public build is incomplete.');
            }
        }
        foreach ($manifest as $entry) {
            $path = realpath($build.'/'.($entry['file'] ?? ''));
            if (! $path || ! str_starts_with($path, $build.'/') || ! is_file($path) || ! is_readable($path) || filesize($path) === 0) {
                throw new RuntimeException('A public build asset is unavailable.');
            }
        }

        return count($manifest);
    }

    private static function verifyResources(mixed $resources): void
    {
        if (! is_array($resources) || count($resources) < 3) {
            throw new RuntimeException('The copied journal resource inventory is incomplete.');
        }
        foreach ($resources as $resource) {
            $relative = $resource[4] ?? null;
            if (! is_string($relative) || str_starts_with($relative, '/') || str_contains($relative, '..') || str_contains($relative, "\0")) {
                throw new RuntimeException('A resource storage path is invalid.');
            }
            $file = realpath(self::PUBLIC_PATH.'/storage/'.$relative);
            if (! $file || ! str_starts_with($file, self::PUBLIC_PATH.'/storage/') || ! is_file($file) || ! is_readable($file)) {
                throw new RuntimeException('A copied journal resource is missing.');
            }
        }
    }

    private static function recordCounts(object $connection): array
    {
        $counts = ['articles' => $connection->table('articles')->count(), 'users' => $connection->table('users')->count()];
        if ($counts['articles'] < 48 || $counts['users'] < 2) {
            throw new RuntimeException('The expected copied journal records are unavailable.');
        }

        return $counts;
    }

    private static function writePrivate(string $path, string $content): void
    {
        if (file_put_contents($path, $content, LOCK_EX) !== strlen($content) || ! chmod($path, 0600)) {
            throw new RuntimeException('A private rollback file could not be saved.');
        }
    }

    private static function replaceAtomically(string $path, string $content, int $mode): void
    {
        $temporary = tempnam(dirname($path), '.sjnr-repair-');
        if (! $temporary) {
            throw new RuntimeException('A deployment file could not be prepared.');
        }
        try {
            self::writePrivate($temporary, $content);
            if (! chmod($temporary, $mode) || ! rename($temporary, $path)) {
                throw new RuntimeException('A deployment file could not be replaced.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        echo json_encode(SjnrDomainRepair::run(array_slice($argv, 1)), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    } catch (Throwable) {
        // Exceptions from PDO or dotenv can contain credentials. Log only the
        // safe phase identifier; inspect rollback files privately if necessary.
        echo json_encode(['ok' => false, 'stage' => SjnrDomainRepair::$stage, 'error' => 'The guarded SJNR domain repair did not complete.'], JSON_THROW_ON_ERROR)."\n";
        exit(1);
    }
}
