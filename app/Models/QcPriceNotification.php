<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcPriceNotification extends Model
{
    protected $connection = 'methods';
    protected $table = 'qc_price_notifications';

    protected $fillable = [
        'qc_signal_id',
        'id_method',
        'direction',
        'step_percentage',
        'level_percentage',
        'entry_price',
        'market_price',
        'movement_percentage',
        'source',
        'event_uid',
        'telegram_sent_at',
        'telegram_response',
    ];

    protected $casts = [
        'step_percentage' => 'decimal:4',
        'level_percentage' => 'decimal:4',
        'entry_price' => 'decimal:8',
        'market_price' => 'decimal:8',
        'movement_percentage' => 'decimal:6',
        'telegram_sent_at' => 'datetime',
        'telegram_response' => 'array',
    ];

    public function signal(): BelongsTo
    {
        return $this->belongsTo(QcSignal::class, 'qc_signal_id');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(QcMethod::class, 'id_method');
    }
}
