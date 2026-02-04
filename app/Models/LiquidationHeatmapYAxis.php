<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidationHeatmapYAxis extends Model
{
    protected $table = 'cg_liquidation_heatmap_y_axis';
    protected $guarded = [];
    public $timestamps = false; // missing updated_at in DB

    public function heatmap()
    {
        return $this->belongsTo(LiquidationHeatmap::class, 'liquidation_heatmap_id');
    }
}
