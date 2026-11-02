<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = DB::connection();
$host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
$directory = $base.'/storage/app/private/deployment';
if (! config('hosting.import_sjnr') || config('journal.profile') !== 'sjnr'
    || ! str_ends_with($host, '.hostingersite.com')
    || $connection->getDriverName() !== 'mysql'
    || $connection->getDatabaseName() !== 'u709299470_sjnr_preview'
    || ! in_array($connection->getConfig('host'), ['localhost', '127.0.0.1'], true)
    || ! is_file($directory.'/initialized.json')) {
    echo "Import disabled or initialization is not complete for the intended SJNR preview.\n";
    exit(1);
}

$lock = fopen($directory.'/public-import.lock', 'c');
if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
    echo "SJNR public import is already running.\n";
    exit(0);
}
if (is_file($directory.'/public-imported.json')) {
    echo "SJNR public content was already imported.\n";
    exit(0);
}

$email = 'archive-migration@sjnrjournal.com';
$creator = User::query()->where('email', $email)->first();
if ($creator && ($creator->name !== 'SJNR Archive Migration' || $creator->is_active || $creator->roles()->exists())) {
    echo "The migration provenance identity is already in use. No account was changed.\n";
    exit(1);
}
if (! $creator) {
    $creator = User::query()->create([
        'name' => 'SJNR Archive Migration',
        'email' => $email,
        'password' => bin2hex(random_bytes(32)),
        'status' => 'suspended',
        'is_active' => false,
        'email_verified_at' => null,
    ]);
}

$arguments = ['directory' => $directory.'/public-import', '--creator' => $creator->getKey(), '--apply' => true];
if (Artisan::call('journal:import-sjnr-public', $arguments) !== 0) {
    echo "SJNR public import failed; maintenance remains enabled.\n";
    echo Artisan::output();
    exit(1);
}
echo Artisan::output();

// Provision the owner's first website login from the private release handover.
// This does not create a Hostinger mailbox or send a setup message.
$adminFile = $directory.'/initial-admin.json';
if (is_file($adminFile)) {
    $admin = json_decode(file_get_contents($adminFile), true, 512, JSON_THROW_ON_ERROR);
    if (User::query()->where('email', $admin['email'])->exists()) {
        echo "The initial administrator identifier is already in use. No credential was changed.\n";
        exit(1);
    }
    if (Artisan::call('user:create-admin', [
        '--email' => $admin['email'],
        '--name' => $admin['name'],
        '--password' => $admin['password'],
    ]) !== 0) {
        echo "Initial administrator provisioning failed; maintenance remains enabled.\n";
        exit(1);
    }
    unlink($adminFile);
    echo "The initial website administrator was provisioned.\n";
}

$summary = [
    'completed_at' => gmdate('c'),
    'host' => $host,
    'articles' => DB::table('articles')->count(),
    'editorial_members' => DB::table('editorial_members')->count(),
    'users' => DB::table('users')->count(),
    'mail_paused' => is_file($base.'/storage/framework/mail-paused'),
];
file_put_contents($directory.'/public-imported.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
chmod($directory.'/public-imported.json', 0600);

$environment = $base.'/.env';
$content = file_get_contents($environment);
$content = preg_replace('/^SJNR_IMPORT_ALLOWED=true$/m', 'SJNR_IMPORT_ALLOWED=false', $content);
file_put_contents($environment, $content, LOCK_EX);
chmod($environment, 0600);

Artisan::call('up');
echo 'SJNR public preview ready: '.json_encode($summary, JSON_THROW_ON_ERROR)."\n";
