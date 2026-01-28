<?php

namespace App\Http\Controllers;

use App\Models\LongShortTopAccountRatioHistory;
use App\Models\LongShortGlobalAccountRatioHistory;
use App\Services\LongShortAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LongShortAnalysisController extends Controller
{
    protected $analysisService;

    public function __construct(LongShortAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    /**
     * Display the main analysis page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Fetch available filters
        $exchanges = LongShortTopAccountRatioHistory::distinct('exchange')->pluck('exchange');
        $pairs = LongShortTopAccountRatioHistory::distinct('pair')->pluck('pair');
        $intervals = LongShortTopAccountRatioHistory::distinct('interval')->pluck('interval');

        return view('derivatives.long-short-analysis', compact('exchanges', 'pairs', 'intervals'));
    }

    /**
     * Get chart data and insights via AJAX.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getData(Request $request)
    {
        try {
            $exchange = $request->input('exchange', 'Binance');
            $pair = $request->input('pair', 'BTCUSDT');
            $interval = $request->input('interval', '1h');
            $limit = $request->input('limit', 100);

            // Fetch Top Account History
            $topHistory = LongShortTopAccountRatioHistory::where('exchange', $exchange)
                ->where('pair', $pair)
                ->where('interval', $interval)
                ->orderBy('time', 'desc')
                ->limit($limit)
                ->get();

            // Fetch Global Account History
            $globalHistory = LongShortGlobalAccountRatioHistory::where('exchange', $exchange)
                ->where('pair', $pair)
                ->where('interval', $interval)
                ->orderBy('time', 'desc')
                ->limit($limit)
                ->get();

            if ($topHistory->isEmpty() || $globalHistory->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found for the selected parameters.',
                ]);
            }

            // Align data and Data Enrichment
            $latestTop = $topHistory->first();
            $latestGlobal = $globalHistory->where('time', $latestTop->time)->first() ?? $globalHistory->first();
            
            // Previous data point (for Delta calculation) - assume sorted desc, so index 1
            $prevTop = $topHistory->get(1) ?? $latestTop; 
            
            // Calculate Enrichment Metrics
            $netPositionTop = $latestTop->top_account_long_percent - $latestTop->top_account_short_percent;
            $netPositionGlobal = $latestGlobal->global_account_long_percent - $latestGlobal->global_account_short_percent;
            
            $deltaRatio = $latestTop->top_account_long_short_ratio - $prevTop->top_account_long_short_ratio;
            $deltaLongPct = $latestTop->top_account_long_percent - $prevTop->top_account_long_percent;

            $sentiment = $this->analysisService->analyzeSentiment(
                $latestTop->top_account_long_short_ratio,
                $latestGlobal->global_account_long_short_ratio
            );

            $insightText = $this->analysisService->generateInsightText(
                $latestTop->top_account_long_short_ratio,
                $latestGlobal->global_account_long_short_ratio,
                $prevTop->top_account_long_short_ratio,
                $pair
            );

            // Prepare Table Data (Merged)
            $tableData = $topHistory->map(function ($item) use ($globalHistory) {
                $globalItem = $globalHistory->where('time', $item->time)->first();
                return [
                    'time' => $item->time,
                    'date' => date('Y-m-d H:i:s', $item->time / 1000),
                    'top_ratio' => $item->top_account_long_short_ratio,
                    'top_long' => $item->top_account_long_percent,
                    'top_short' => $item->top_account_short_percent,
                    'global_ratio' => $globalItem ? $globalItem->global_account_long_short_ratio : null,
                    'global_long' => $globalItem ? $globalItem->global_account_long_percent : null,
                    'global_short' => $globalItem ? $globalItem->global_account_short_percent : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'chart_data' => [
                        'labels' => $topHistory->sortBy('time')->values()->map(fn($i) => date('H:i', $i->time/1000)),
                        'top_series' => $topHistory->sortBy('time')->values()->pluck('top_account_long_short_ratio'),
                        'global_series' => $globalHistory->sortBy('time')->values()->pluck('global_account_long_short_ratio'),
                        // V5 Addition: Historical Long % Composition
                        'top_long_series' => $topHistory->sortBy('time')->values()->pluck('top_account_long_percent'),
                        'global_long_series' => $globalHistory->sortBy('time')->values()->pluck('global_account_long_percent'),
                    ],
                    'table_data' => $tableData->values(),
                    'latest_stats' => [
                        'top_ratio' => $latestTop->top_account_long_short_ratio,
                        'top_long_pct' => $latestTop->top_account_long_percent,
                        'top_short_pct' => $latestTop->top_account_short_percent,
                        'top_net_position' => $netPositionTop,
                        'top_delta_ratio' => $deltaRatio,
                        'top_delta_long' => $deltaLongPct,
                        
                        'global_ratio' => $latestGlobal->global_account_long_short_ratio,
                        'global_long_pct' => $latestGlobal->global_account_long_percent,
                        'global_short_pct' => $latestGlobal->global_account_short_percent,
                        'global_net_position' => $netPositionGlobal,
                        
                        'updated_at' => date('H:i:s', $latestTop->time / 1000),
                    ],
                    'sentiment' => $sentiment,
                    'insight' => $insightText,
                ]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("LongShortAnalysis Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
