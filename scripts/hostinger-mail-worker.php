<?php

declare(strict_types=1);

// Hostinger cron entry point. Keep queued messages until live delivery is configured.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (! $app->isProduction() || config('mail.default') !== 'smtp'
    || blank(config('mail.mailers.smtp.username')) || blank(config('mail.mailers.smtp.password'))) {
    echo "Mail queue paused until production SMTP is configured.\n";
    exit(0);
}

exit(Illuminate\Support\Facades\Artisan::call('queue:work', [
    '--queue' => 'default,mail',
    '--stop-when-empty' => true,
    '--max-time' => 50,
    '--tries' => 3,
]));
