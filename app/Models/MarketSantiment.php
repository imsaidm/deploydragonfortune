<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketSantiment extends Model
{
    protected $fillable = [
        'metric', 
        'slug', 
        'value', 
        'api_timestamp' 
    ];

    // Mengubah kolom api_timestamp menjadi instance Carbon secara otomatis
    protected $casts = [
        'api_timestamp' => 'datetime',
    ];    

}
