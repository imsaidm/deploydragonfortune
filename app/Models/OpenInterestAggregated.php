<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenInterestAggregated extends Model
{
    use HasFactory;

    protected $table = 'cg_open_interest_aggregated_history';
    public $timestamps = false; // Assuming raw data table doesn't have standard laravel timestamps if not specified
    // But usually these tables have `created_at` or `updated_at`. Let's check the SQL or assume standard.
    // Based on FundingRateDbController, the table uses `updated_at`.
    // Let's assume standard timestamps are NOT managed by Eloquent unless we know for sure.
    // However, if we are just reading, it doesn't matter much.
    
    protected $fillable = [
        'symbol',
        'open_interest',
        'open_interest_amount',
        'avg_funding_rate',
        'price',
        'time', // Timestamp from source
        'updated_at'
    ];
}
