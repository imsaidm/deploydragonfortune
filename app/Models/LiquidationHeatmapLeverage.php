<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidationHeatmapLeverage extends Model
{
    protected $table = 'cg_liquidation_heatmap_leverage_data';
    protected $guarded = [];
    public $timestamps = false; // Missing updated_at in DB
    protected $casts = [
        'data_map' => 'array', // Assuming data might be stored as JSON, if not this is harmless
    ];

    public function heatmap()
    {
        return $this->belongsTo(LiquidationHeatmap::class, 'liquidation_heatmap_id');
    }
}
