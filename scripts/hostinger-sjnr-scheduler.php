<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$base = dirname(__DIR__);
$tickLock = fopen($base.'/storage/framework/sjnr-scheduler.lock', 'c');
if (! $tickLock || ! flock($tickLock, LOCK_EX | LOCK_NB)) {
    echo "The SJNR scheduler is already running.\n";
    exit(0);
}

$directory = $base.'/storage/app/private/deployment';
if (! is_file($directory.'/initialized.json')) {
    require $base.'/scripts/hostinger-initialize-sjnr.php';

    return;
}
if (! is_file($directory.'/public-imported.json')) {
    require $base.'/scripts/hostinger-import-sjnr-public.php';

    return;
}

require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if (config('journal.profile') !== 'sjnr'
    || $connection->getDriverName() !== 'mysql'
    || $connection->getDatabaseName() !== 'u709299470_sjnr_preview'
    || ! in_array($connection->getConfig('host'), ['localhost', '127.0.0.1'], true)) {
    echo "The scheduler target is not the intended SJNR database.\n";
    exit(1);
}

exit(Artisan::call('schedule:run'));
