<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = [
        'strategy_id',
        'account_id',
        'symbol',
        'side',
        'quantity',
        'entry_price',
        'leverage',
        'margin_type',
        'status'
    ];

    public function account()
    {
        return $this->belongsTo(TradingAccount::class, 'account_id');
    }
}
