<?php

namespace App\Http\Controllers\Database;

use App\Http\Controllers\Controller;
use App\Models\LiquidationHeatmap; // Using our new model
use App\Services\LiquidationHeatmapAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LiquidationHeatmapDbController extends Controller
{
    protected $analysisService;

    public function __construct(LiquidationHeatmapAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    public function index()
    {
        // Get unique symbols and intervals from metadata
        $symbols = LiquidationHeatmap::distinct('symbol')->pluck('symbol');
        // If DB has no metadata at all, provide defaults.
        if ($symbols->isEmpty()) {
            $symbols = collect(['BTC', 'ETH', 'DOGE']);
        }
        
        $intervals = LiquidationHeatmap::distinct('range')->pluck('range');
         if ($intervals->isEmpty()) {
            $intervals = collect(['12h', '24h', '3d', '7d']); // Fallback
        }

        return view('derivatives.liquidation-heatmap-advanced', [
            'symbols' => $symbols,
            'intervals' => $intervals
        ]);
    }

    public function getData(Request $request)
    {
        $symbol = $request->input('symbol', 'DOGE'); // Default to DOGE as it has data
        $interval = $request->input('interval', '12h');

        try {
            $result = $this->analysisService->analyze($symbol, $interval);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error("Liquidation Heatmap Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error processing heatmap data: ' . $e->getMessage()
            ], 500);
        }
    }
}
