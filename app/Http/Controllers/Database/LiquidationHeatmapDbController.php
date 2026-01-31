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
        // Get unique symbols from database
        $symbols = LiquidationHeatmap::distinct('symbol')->orderBy('symbol')->pluck('symbol');
        
        if ($symbols->isEmpty()) {
            $symbols = collect(['BTC', 'ETH', 'SOL']);
        }

        // We don't need to pass all intervals anymore, just the ones for the first symbol or defaults
        $firstSymbol = $symbols->first();
        $intervals = LiquidationHeatmap::where('symbol', $firstSymbol)
            ->distinct('range')
            ->pluck('range');

        if ($intervals->isEmpty()) {
            $intervals = collect(['24h', '3d', '7d']);
        }

        return view('derivatives.liquidation-heatmap-advanced', [
            'symbols' => $symbols,
            'intervals' => $intervals
        ]);
    }

    public function getAvailableRanges(Request $request)
    {
        $symbol = $request->input('symbol');
        if (!$symbol) {
             return response()->json(['ranges' => []]);
        }

        $ranges = LiquidationHeatmap::where('symbol', $symbol)
            ->distinct('range')
            ->pluck('range')
            ->toArray();

        // Sort roughly by time duration for better UI
        $order = ['12h', '24h', '3d', '7d', '30d', '90d', '180d', '1y'];
        usort($ranges, function($a, $b) use ($order) {
            $posA = array_search($a, $order);
            $posB = array_search($b, $order);
            return ($posA === false ? 99 : $posA) <=> ($posB === false ? 99 : $posB);
        });

        return response()->json([
            'success' => true,
            'ranges' => $ranges
        ]);
    }

    public function getData(Request $request)
    {
        $symbol = $request->input('symbol');
        $interval = $request->input('interval');

        if (!$symbol || !$interval) {
             return response()->json(['success' => false, 'message' => 'Missing symbol or interval.'], 400);
        }

        try {
            $result = $this->analysisService->analyze($symbol, $interval);
            
            if (!$result['success']) {
                return response()->json($result, 200); // Handled error (e.g. No data)
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error("Liquidation Heatmap EXCEPTION: " . $e->getMessage(), [
                'symbol' => $symbol,
                'interval' => $interval,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Server encountered an error processing your request. Please check if data exists for this pair.'
            ], 500);
        }
    }
}
