<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketCryptoPanic extends Model
{
    protected $table = 'market_crypto_panics';

    protected $fillable = [
        'panic_id', 
        'title', 
        'domain', 
        'url', 
        'panic_score',
        'votes_positive', 
        'votes_negative', 
        'votes_important',
        'votes_liked', 
        'votes_disliked', 
        'votes_lol', 
        'votes_toxic', 
        'votes_save',
        'currencies', 
        'published_at'
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'panic_score' => 'float',
    ];
}
