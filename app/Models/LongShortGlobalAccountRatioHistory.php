<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LongShortGlobalAccountRatioHistory extends Model
{
    use HasFactory;

    protected $table = 'cg_long_short_global_account_ratio_history';

    protected $fillable = [
        'exchange',
        'pair',
        'interval',
        'time',
        'global_account_long_percent',
        'global_account_short_percent',
        'global_account_long_short_ratio',
    ];

    protected $casts = [
        'time' => 'integer',
        'global_account_long_percent' => 'float',
        'global_account_short_percent' => 'float',
        'global_account_long_short_ratio' => 'float',
    ];
}
