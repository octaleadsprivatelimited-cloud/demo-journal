<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// One-time private cron entry point. Never place this file under public_html.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$host = parse_url((string) config('app.url'), PHP_URL_HOST);
if (! config('hosting.initialize_sjnr') || config('journal.profile') !== 'sjnr'
    || config('database.default') !== 'mysql' || ! str_ends_with((string) $host, '.hostingersite.com')
    || config('publication.seeding.demo_content')
    || filled(getenv('SEED_ADMIN_EMAIL') ?: env('SEED_ADMIN_EMAIL'))
    || filled(getenv('SEED_ADMIN_PASSWORD') ?: env('SEED_ADMIN_PASSWORD'))) {
    echo "SJNR initialization is disabled or the target is not a temporary SJNR MySQL site.\n";
    exit(1);
}

$connection = DB::connection();
if ($connection->getDriverName() !== 'mysql'
    || $connection->getDatabaseName() !== 'u709299470_sjnr_preview'
    || ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost'], true)) {
    echo "The resolved database connection is not the intended SJNR preview database.\n";
    exit(1);
}

$directory = $base.'/storage/app/private/deployment';
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$lock = fopen($directory.'/initialize.lock', 'c');
if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
    echo "SJNR initialization is already running.\n";
    exit(0);
}
if (is_file($directory.'/initialized.json')) {
    echo "SJNR initialization was already completed.\n";
    exit(0);
}

// Refuse to migrate an existing journal even when its profile was changed.
foreach (['users', 'articles', 'workflow_files'] as $table) {
    if (Schema::hasTable($table) && DB::table($table)->exists()) {
        echo "The target database already contains journal records. Initialization refused.\n";
        exit(1);
    }
}

foreach ([
    ['migrate', ['--force' => true]],
    ['db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]],
    ['db:seed', ['--class' => SettingSeeder::class, '--force' => true]],
    ['journal:initialize-sjnr', ['--apply' => true, '--email' => config('publication.contact_email')]],
    ['view:cache', []],
] as [$command, $arguments]) {
    if (Artisan::call($command, $arguments) !== 0) {
        echo 'SJNR initialization failed during '.$command.". Review the private application log.\n";
        exit(1);
    }
    echo $command." completed.\n";
}

$summary = [
    'completed_at' => gmdate('c'),
    'host' => $host,
    'driver' => DB::connection()->getDriverName(),
    'journal' => config('publication.name'),
    'issn' => config('workflow.issn'),
    'articles' => DB::table('articles')->count(),
    'users' => DB::table('users')->count(),
    'categories' => DB::table('categories')->count(),
    'settings' => DB::table('settings')->count(),
];
file_put_contents($directory.'/initialized.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
chmod($directory.'/initialized.json', 0600);

$environment = $base.'/.env';
if (is_file($environment)) {
    $content = file_get_contents($environment);
    $content = preg_replace('/^SJNR_INITIALIZE_ALLOWED=true$/m', 'SJNR_INITIALIZE_ALLOWED=false', $content);
    file_put_contents($environment, $content, LOCK_EX);
    chmod($environment, 0600);
}
echo 'SJNR initialized: '.json_encode($summary, JSON_THROW_ON_ERROR)."\n";
