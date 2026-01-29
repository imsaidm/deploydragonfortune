<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidationHeatmap extends Model
{
    protected $table = 'cg_liquidation_heatmap';
    protected $guarded = [];

    public function yAxis()
    {
        return $this->hasMany(LiquidationHeatmapYAxis::class, 'liquidation_heatmap_id');
    }

    public function leverageData()
    {
        return $this->hasMany(LiquidationHeatmapLeverage::class, 'liquidation_heatmap_id');
    }

    public function candlesticks()
    {
        return $this->hasMany(LiquidationHeatmapCandle::class, 'liquidation_heatmap_id');
    }
}
