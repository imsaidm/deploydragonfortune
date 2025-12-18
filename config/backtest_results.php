<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Backtest Result Browser
    |--------------------------------------------------------------------------
    |
    | Configure where the Backtest Result page reads files from.
    |
    | - disk: Laravel filesystem disk (default: local -> storage/app)
    | - directory: relative path within the disk root
    |
    */
    'disk' => env('BACKTEST_RESULTS_DISK', 'local'),
    'directory' => env('BACKTEST_RESULTS_DIR', 'backtest-results'),
];

