<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketCandle extends Model
{
    protected $table = 'market_candles';

    protected $fillable = [
        'exchange',
        'type',
        'symbol',
        'timeframe',
        'timestamp',
        'open',
        'high',
        'low',
        'close',
        'volume',
    ];

    protected $casts = [
        'timestamp' => 'integer',
        'open'      => 'float',
        'high'      => 'float',
        'low'       => 'float',
        'close'     => 'float',
        'volume'    => 'float',
    ];

    /**
     * Timeframe duration in milliseconds.
     */
    public static function timeframeDurationMs(string $timeframe): int
    {
        return match ($timeframe) {
            '1m'  => 60_000,
            '3m'  => 180_000,
            '5m'  => 300_000,
            '15m' => 900_000,
            '30m' => 1_800_000,
            '1h'  => 3_600_000,
            '4h'  => 14_400_000,
            '1d'  => 86_400_000,
            default => 60_000,
        };
    }
}
