<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

// Hostinger cron entry point. Keep queued messages until live delivery is configured.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$base = dirname(__DIR__);
$pause = $base.'/storage/framework/mail-paused';
if (is_file($pause)) {
    echo "Mail queue paused for delivery review.\n";
    exit(0);
}

// A slow SMTP connection must not let the next cron run claim the same jobs.
$lock = fopen($base.'/storage/framework/mail-worker.lock', 'c');
if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
    echo "The mail worker is already running.\n";
    exit(0);
}
if (is_file($pause)) {
    exit(0);
}

require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

if (! $app->isProduction() || config('mail.default') !== 'smtp'
    || blank(config('mail.mailers.smtp.username')) || blank(config('mail.mailers.smtp.password'))) {
    echo "Mail queue paused until production SMTP is configured.\n";
    exit(0);
}

Queue::looping(static function () use ($app, $pause): bool {
    if (is_file($pause)) {
        $app->make('queue.worker')->shouldQuit = true;

        return false;
    }

    return true;
});

exit(Artisan::call('queue:work', [
    // Held queues deliberately remain outside the worker's queue list.
    '--queue' => 'mail,default',
    '--stop-when-empty' => true,
    '--max-time' => 45,
    '--max-jobs' => 20,
    '--timeout' => 30,
    '--sleep' => 1,
    '--rest' => 1,
    '--backoff' => 60,
    '--tries' => 3,
]));
