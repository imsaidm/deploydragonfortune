<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Process pending Telegram notifications every 10 seconds
Schedule::command('telegram:process-pending --limit=10')
    ->everyTenSeconds()
    ->withoutOverlapping()
    ->runInBackground();

// Process pending signals (PDO) every 10 seconds
Schedule::command('orders:process-pending --limit=10')
    ->everyTenSeconds()
    ->withoutOverlapping()
    ->runInBackground();

// Keep chart candles warm for all active strategies. Run a queue worker for the actual fetch jobs.
Schedule::command('market-candles:sync --active --timeframe=auto --days=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
