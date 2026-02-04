<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidationHeatmapCandle extends Model
{
    protected $table = 'cg_liquidation_heatmap_price_candlesticks';
    protected $guarded = [];
    public $timestamps = false;

    public function heatmap()
    {
        return $this->belongsTo(LiquidationHeatmap::class, 'liquidation_heatmap_id');
    }
}
