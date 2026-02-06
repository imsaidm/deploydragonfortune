<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Execution extends Model
{
    protected $fillable = [
        'qc_signal_id',
        'strategy_id',
        'account_id',
        'symbol',
        'side',
        'type',
        'master_quantity',
        'follower_quantity',
        'leverage',
        'executed_price',
        'exit_price',
        'pnl',
        'status',
        'error_message',
        'executed_at'
    ];

    public function account()
    {
        return $this->belongsTo(TradingAccount::class, 'account_id');
    }
}
