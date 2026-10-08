<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Call commands in-process because shared hosting can disable proc_open.
Schedule::call(static fn () => Artisan::call('articles:publish-scheduled'))
    ->name('articles:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(static fn () => Artisan::call('queue:prune-failed', ['--hours' => 168]))
    ->name('queue:prune-failed')->daily();
Schedule::call(static fn () => Artisan::call('sanctum:prune-expired', ['--hours' => 24]))
    ->name('sanctum:prune-expired')->daily();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(static fn () => Artisan::call('workflow:remind'))
    ->name('workflow:remind')->hourly()->withoutOverlapping()->onOneServer();
