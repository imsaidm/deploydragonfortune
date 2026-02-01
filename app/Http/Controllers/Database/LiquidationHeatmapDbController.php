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

        // Get all heatmaps for this symbol
        $heatmaps = LiquidationHeatmap::where('symbol', $symbol)->get();
        
        $validRanges = [];
        
        foreach ($heatmaps as $heatmap) {
            // Check if this range has BOTH candlesticks AND leverage data
            $candleCount = $heatmap->candlesticks()->count();
            $leverageCount = $heatmap->leverageData()->count();
            $yAxisCount = $heatmap->yAxis()->count();
            
            // Only include ranges with complete data (at least 100 candles and some leverage points and y-axis)
            // Also verify the data doesn't cause crashes by checking if we can fetch at least one row
            if ($candleCount >= 100 && $leverageCount > 0 && $yAxisCount > 0) {
                try {
                    // Quick sanity check: can we fetch the first candle?
                    $testCandle = $heatmap->candlesticks()->first();
                    if ($testCandle && !in_array($heatmap->range, $validRanges)) {
                        $validRanges[] = $heatmap->range;
                    }
                } catch (\Exception $e) {
                    Log::warning("Range {$heatmap->range} for {$symbol} excluded due to data error: " . $e->getMessage());
                }
            }
        }

        // Sort roughly by time duration for better UI
        $order = ['12h', '24h', '3d', '7d', '30d', '90d', '180d', '1y'];
        usort($validRanges, function($a, $b) use ($order) {
            $posA = array_search($a, $order);
            $posB = array_search($b, $order);
            return ($posA === false ? 99 : $posA) <=> ($posB === false ? 99 : $posB);
        });

        return response()->json([
            'success' => true,
            'ranges' => $validRanges
        ]);
    }

    public function getSummary(Request $request)
    {
        $symbol = $request->input('symbol');
        if (!$symbol) {
             return response()->json(['success' => false, 'message' => 'Missing symbol.'], 400);
        }

        try {
            $result = $this->analysisService->getSummaryForSymbol($symbol);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error("Heatmap Summary EXCEPTION: " . $e->getMessage(), ['symbol' => $symbol]);
            return response()->json(['success' => false, 'message' => 'Error fetching summary.'], 500);
        }
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
            
            // If data is requested for a specific range, we also want the summary 
            // to update the range cards if needed.
            if ($request->has('with_summary')) {
                $result['symbol_summary'] = $this->analysisService->getSummaryForSymbol($symbol);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error("Liquidation Heatmap EXCEPTION: " . $e->getMessage(), [
                'symbol' => $symbol,
                'interval' => $interval,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Service error. Please try again later.'
            ], 500);
        }
    }
}
