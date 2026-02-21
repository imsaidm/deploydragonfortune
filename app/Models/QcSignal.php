<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Observers\QcSignalObserver;

#[ObservedBy([QcSignalObserver::class])]
class QcSignal extends Model
{
    /**
     * Get the method that this signal belongs to.
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(QcMethod::class, 'id_method');
    }

    // Use 'methods' connection (simulates remote database)
    protected $connection = 'methods';
    protected $table = 'qc_signal';
    
    protected $fillable = [
        'id_method',
        'datetime',
        'type',
        'jenis',
        'leverage',
        'price_entry',
        'price_exit',
        'target_tp',
        'target_sl',
        'real_tp',
        'real_sl',
        'message',
        'telegram_sent',
        'telegram_sent_at',
        'telegram_response',
        'quantity',
        'ratio',
        'market_type',
        'telegram_processing',
    ];
    
    protected $casts = [
        'datetime' => 'datetime',
        'telegram_sent' => 'boolean',
        'telegram_sent_at' => 'datetime',
        'leverage' => 'integer',
        'price_entry' => 'decimal:8',
        'price_exit' => 'decimal:8',
        'target_tp' => 'decimal:8',
        'target_sl' => 'decimal:8',
        'real_tp' => 'decimal:8',
        'real_sl' => 'decimal:8',
        'quantity' => 'decimal:8',
        'ratio' => 'decimal:3',
        'market_type' => 'string',
    ];
}
