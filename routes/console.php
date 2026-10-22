<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run --scheduled')
    ->dailyAt(config('backups.daily_at'))
    ->timezone(config('backups.timezone'));

// Catch-up for a machine that was off or asleep at the daily time: only backs up when the
// last success is older than 24h. At :30 so it never shares a minute with the daily run.
Schedule::command('backup:run --scheduled --if-stale')
    ->hourlyAt(30);
