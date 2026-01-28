<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LongShortTopAccountRatioHistory extends Model
{
    use HasFactory;

    protected $table = 'cg_long_short_top_account_ratio_history';

    protected $fillable = [
        'exchange',
        'pair',
        'interval',
        'time',
        'top_account_long_percent',
        'top_account_short_percent',
        'top_account_long_short_ratio',
    ];

    protected $casts = [
        'time' => 'integer',
        'top_account_long_percent' => 'float',
        'top_account_short_percent' => 'float',
        'top_account_long_short_ratio' => 'float',
    ];
}
