<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuantConnectSignal extends Model
{
    protected $table = 'quantconnect_signals';
    
    protected $fillable = [
        'qc_id',
        'type',
        'market_type',
        'symbol',
        'side',
        'price',
        'tp',
        'sl',
        'leverage',
        'margin_usd',
        'quantity',
        'message',
        'telegram_sent',
        'telegram_sent_at',
        'telegram_response',
    ];

    protected $casts = [
        'price' => 'decimal:8',
        'tp' => 'decimal:8',
        'sl' => 'decimal:8',
        'margin_usd' => 'decimal:8',
        'quantity' => 'decimal:8',
        'telegram_sent' => 'boolean',
        'telegram_sent_at' => 'datetime',
    ];

    /**
     * Check if this is a reminder notification
     */
    public function isReminder(): bool
    {
        return $this->type === 'REMINDER';
    }

    /**
     * Check if this is a signal notification
     */
    public function isSignal(): bool
    {
        return $this->type === 'SIGNAL';
    }

    /**
     * Check if this is a futures trade
     */
    public function isFutures(): bool
    {
        return $this->market_type === 'FUTURES';
    }

    /**
     * Check if this is a spot trade
     */
    public function isSpot(): bool
    {
        return $this->market_type === 'SPOT';
    }
}
