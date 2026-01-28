<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenInterestStablecoin extends Model
{
    use HasFactory;

    protected $table = 'cg_open_interest_aggregated_stablecoin_history';
    public $timestamps = false;
    
    protected $fillable = [
        'symbol',
        'open_interest',
        'open_interest_amount',
        'avg_funding_rate',
        'price',
        'time',
        'updated_at'
    ];
}
