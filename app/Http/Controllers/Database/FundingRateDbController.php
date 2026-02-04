<?php

namespace App\Http\Controllers\Database;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\FundingRateAnalysisService;

/**
 * Controller for Funding Rate data from local database
 * Reads from cg_funding_rate_exchange_list and cg_funding_rate_history tables
 * Integrated with AI Risk Analysis
 */
class FundingRateDbController extends Controller
{
    private FundingRateAnalysisService $analysisService;

    public function __construct(FundingRateAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }
    /**
     * Get current funding rates for all exchanges
     * Reads from cg_funding_rate_exchange_list table
     */
    public function exchangeList(Request $request)
    {
        $symbol = strtoupper($request->query('symbol', 'BTC'));
        
        // Cache for 10 seconds (data is scraped periodically)
        $cacheKey = "db_funding_rate_exchange_list_{$symbol}";
        
        $data = Cache::remember($cacheKey, 10, function () use ($symbol) {
            // Get latest data for each exchange (deduplicated - one record per exchange)
            $rows = DB::table('cg_funding_rate_exchange_list as t1')
                ->select('t1.*')
                ->where('t1.symbol', $symbol)
                ->whereRaw('t1.updated_at = (SELECT MAX(t2.updated_at) FROM cg_funding_rate_exchange_list t2 WHERE t2.exchange = t1.exchange AND t2.symbol = t1.symbol)')
                ->orderBy('t1.exchange')
                ->get();
            
            return $rows;
        });
        
        // Normalize for frontend
        $normalized = $this->normalizeExchangeList($data, $symbol);
        
        return response()->json($normalized);
    }
    
    /**
     * Get funding rate history for charts
     * Reads from cg_funding_rate_history table
     */
    public function history(Request $request)
    {
        $symbol = strtoupper($request->query('symbol', 'BTC'));
        $interval = $request->query('interval', '1h');
        $exchange = $request->query('exchange', 'Binance');
        $limit = min((int) $request->query('limit', 100), 500);
        
        // Cache for 30 seconds
        $cacheKey = "db_funding_rate_history_{$symbol}_{$exchange}_{$interval}_{$limit}";
        
        $data = Cache::remember($cacheKey, 30, function () use ($symbol, $exchange, $interval, $limit) {
            $pair = $symbol . 'USDT';
            
            $rows = DB::table('cg_funding_rate_history')
                ->where('exchange', $exchange)
                ->where('pair', $pair)
                ->where('interval', $interval)
                ->orderBy('time', 'desc')
                ->limit($limit)
                ->get();
            
            return $rows->reverse()->values();
        });
        
        // Format for frontend
        $formatted = $data->map(function ($row) {
            return [
                'ts' => (int) $row->time,
                'funding_rate' => (float) $row->close, // Use close as funding rate
                'open' => (float) $row->open,
                'high' => (float) $row->high,
                'low' => (float) $row->low,
                'close' => (float) $row->close,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $formatted,
            'count' => count($formatted),
        ]);
    }
    
    /**
     * Get available exchanges for a symbol
     */
    public function exchanges(Request $request)
    {
        $symbol = strtoupper($request->query('symbol', 'BTC'));
        
        $exchanges = DB::table('cg_funding_rate_exchange_list')
            ->where('symbol', $symbol)
            ->select('exchange', 'margin_type')
            ->distinct()
            ->orderBy('exchange')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $exchanges,
        ]);
    }
    
    /**
     * Get AI risk analysis in formatted text
     * Returns analysis in the structured format requested
     */
    public function aiAnalysis(Request $request)
    {
        $symbol = strtoupper($request->query('symbol', 'BTC'));
        $format = $request->query('format', 'json'); // 'json' or 'text'
        
        // Cache for 10 seconds (same as exchange list)
        $cacheKey = "db_funding_rate_ai_analysis_{$symbol}_{$format}";
        
        $result = Cache::remember($cacheKey, 10, function () use ($symbol, $format) {
            // Get latest data for analysis
            $rows = DB::table('cg_funding_rate_exchange_list')
                ->where('symbol', $symbol)
                ->orderBy('updated_at', 'desc')
                ->get();
            
            if ($rows->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "No data found for {$symbol}",
                ];
            }
            
            // Convert to array format for analysis
            $data = $rows->map(function ($row) {
                return [
                    'exchange' => $row->exchange,
                    'symbol' => $row->symbol,
                    'margin_type' => $row->margin_type ?? 'stablecoin',
                    'funding_rate' => (float) $row->funding_rate,
                ];
            })->toArray();
            
            // Perform AI analysis
            $analysis = $this->analysisService->analyzeMarketCondition($data);
            
            if ($format === 'text') {
                return [
                    'success' => true,
                    'analysis' => $this->analysisService->formatForDisplay($analysis),
                    'raw' => $analysis,
                ];
            }
            
            return [
                'success' => true,
                'analysis' => $analysis,
            ];
        });
        
        if ($format === 'text') {
            return response($result['analysis'] ?? 'No analysis available', 200)
                ->header('Content-Type', 'text/plain');
        }
        
        return response()->json($result);
    }
    
    /**
     * Normalize exchange list data for frontend
     */
    private function normalizeExchangeList($rows, $symbol): array
    {
        if ($rows->isEmpty()) {
            return [
                'success' => true,
                'data' => [],
                'insights' => [],
                'ai_analysis' => $this->analysisService->analyzeMarketCondition([]),
                'message' => "No data found for {$symbol}",
            ];
        }
        
        $data = [];
        
        foreach ($rows as $row) {
            // Map margin_type from database to frontend display
            $marginType = 'USDT'; // default
            if ($row->margin_type === 'coin') {
                $marginType = 'COIN';
            } elseif ($row->margin_type === 'stablecoin') {
                $marginType = 'USDT';
            }
            
            $data[] = [
                'exchange' => $row->exchange,
                'symbol' => $row->symbol,
                'margin_type' => $marginType,
                'funding_rate' => (float) $row->funding_rate,
                'predicted_rate' => (float) ($row->funding_rate * 0.95), // Estimate
                'funding_interval_hours' => (int) ($row->funding_rate_interval ?? 8),
                'next_funding_time' => $row->next_funding_time ? (int) $row->next_funding_time : null,
                'updated_at' => $row->updated_at,
            ];
        }
        
        // Sort by exchange name
        usort($data, fn($a, $b) => strcmp($a['exchange'], $b['exchange']));
        
        // Calculate insights from real data
        $insights = $this->calculateInsights($data);
        
        // Perform AI risk analysis
        $aiAnalysis = $this->analysisService->analyzeMarketCondition($data);
        
        return [
            'success' => true,
            'data' => $data,
            'insights' => $insights,
            'ai_analysis' => $aiAnalysis,
            'source' => 'database',
            'updated_at' => $rows->first()->updated_at ?? now(),
        ];
    }
    
    /**
     * Calculate market insights from funding data
     */
    private function calculateInsights(array $data): array
    {
        if (empty($data)) return [];
        
        $insights = [];
        $rates = array_column($data, 'funding_rate');
        $avgRate = array_sum($rates) / count($rates);
        $maxRate = max($rates);
        $minRate = min($rates);
        $spreadBps = ($maxRate - $minRate) * 10000;
        
        // Find exchanges with max/min
        $maxEx = null;
        $minEx = null;
        foreach ($data as $d) {
            if ($d['funding_rate'] == $maxRate) $maxEx = $d['exchange'];
            if ($d['funding_rate'] == $minRate) $minEx = $d['exchange'];
        }
        
        // Market sentiment
        $positiveCount = count(array_filter($rates, fn($r) => $r > 0));
        $negativeCount = count(array_filter($rates, fn($r) => $r < 0));
        
        if ($positiveCount > $negativeCount * 2) {
            $insights[] = [
                'type' => 'warning',
                'message' => "Market bullish: {$positiveCount}/" . count($data) . " exchanges positive - potential long squeeze",
            ];
        } elseif ($negativeCount > $positiveCount * 2) {
            $insights[] = [
                'type' => 'info', 
                'message' => "Market bearish: {$negativeCount}/" . count($data) . " exchanges negative - shorts paying longs",
            ];
        }
        
        // Extreme rates
        if ($maxRate > 0.001) {
            $insights[] = [
                'type' => 'warning',
                'message' => "High funding on {$maxEx}: " . number_format($maxRate * 100, 4) . "% (annualized " . number_format($maxRate * 100 * 3 * 365, 1) . "%)",
            ];
        }
        
        // Arbitrage opportunity  
        if ($spreadBps > 100) {
            $insights[] = [
                'type' => 'success',
                'message' => "Arbitrage: Short {$maxEx} / Long {$minEx} = " . number_format($spreadBps, 1) . " bps spread",
            ];
        }
        
        // Average rate info
        $annualized = $avgRate * 100 * 3 * 365;
        $insights[] = [
            'type' => 'info',
            'message' => "Average funding: " . number_format($avgRate * 100, 4) . "% (" . number_format($annualized, 1) . "% APY)",
        ];
        
        // Data freshness
        $insights[] = [
            'type' => 'success',
            'message' => "Tracking " . count($data) . " exchanges from database",
        ];
        
        return $insights;
    }
    
    /**
     * Get OHLC funding rate data for candlestick chart
     * Uses open, high, low, close from cg_funding_rate_history
     */
    public function ohlc(Request $request)
    {
        $symbol = strtoupper($request->query('symbol', 'BTC'));
        $interval = $request->query('interval', '1h');
        $exchange = $request->query('exchange', 'Binance');
        $limit = min((int) $request->query('limit', 100), 500);
        
        $cacheKey = "db_funding_rate_ohlc_{$symbol}_{$exchange}_{$interval}_{$limit}";
        
        $data = Cache::remember($cacheKey, 30, function () use ($symbol, $exchange, $interval, $limit) {
            $pair = $symbol . 'USDT';
            
            $rows = DB::table('cg_funding_rate_history')
                ->where('exchange', $exchange)
                ->where('pair', $pair)
                ->where('interval', $interval)
                ->orderBy('time', 'desc')
                ->limit($limit)
                ->get();
            
            return $rows->reverse()->values();
        });
        
        $formatted = $data->map(function ($row) {
            return [
                'time' => (int) $row->time,
                'open' => (float) $row->open * 100,
                'high' => (float) $row->high * 100,
                'low' => (float) $row->low * 100,
                'close' => (float) $row->close * 100,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $formatted,
            'count' => count($formatted),
            'exchange' => $exchange,
            'interval' => $interval,
        ]);
    }
    
    /**
     * Get cross-exchange comparison for same symbol
     * Returns latest funding rate from all exchanges for comparison
     */
    public function comparison(Request $request)
    {
        $symbol = strtoupper($request->query('symbol', 'BTC'));
        $interval = $request->query('interval', '1h');
        $limit = min((int) $request->query('limit', 24), 100);
        
        $cacheKey = "db_funding_rate_comparison_{$symbol}_{$interval}_{$limit}";
        
        $data = Cache::remember($cacheKey, 30, function () use ($symbol, $interval, $limit) {
            $pair = $symbol . 'USDT';
            
            // Get unique exchanges
            $exchanges = DB::table('cg_funding_rate_history')
                ->where('pair', $pair)
                ->where('interval', $interval)
                ->select('exchange')
                ->distinct()
                ->pluck('exchange');
            
            $result = [];
            foreach ($exchanges as $exchange) {
                $rows = DB::table('cg_funding_rate_history')
                    ->where('exchange', $exchange)
                    ->where('pair', $pair)
                    ->where('interval', $interval)
                    ->orderBy('time', 'desc')
                    ->limit($limit)
                    ->get(['time', 'close']);
                
                $result[$exchange] = $rows->reverse()->values()->map(function ($row) {
                    return [
                        'time' => (int) $row->time,
                        'rate' => (float) $row->close * 100,
                    ];
                });
            }
            
            return $result;
        });
        
        return response()->json([
            'success' => true,
            'data' => $data,
            'exchanges' => array_keys($data),
            'interval' => $interval,
        ]);
    }
    
    /**
     * Get volatility analysis (high-low range)
     * Measures funding rate volatility over time
     */
    public function volatility(Request $request)
    {
        $symbol = strtoupper($request->query('symbol', 'BTC'));
        $interval = $request->query('interval', '1h');
        $exchange = $request->query('exchange', 'Binance');
        $limit = min((int) $request->query('limit', 100), 500);
        
        $cacheKey = "db_funding_rate_volatility_{$symbol}_{$exchange}_{$interval}_{$limit}";
        
        $data = Cache::remember($cacheKey, 30, function () use ($symbol, $exchange, $interval, $limit) {
            $pair = $symbol . 'USDT';
            
            $rows = DB::table('cg_funding_rate_history')
                ->where('exchange', $exchange)
                ->where('pair', $pair)
                ->where('interval', $interval)
                ->orderBy('time', 'desc')
                ->limit($limit)
                ->get();
            
            return $rows->reverse()->values();
        });
        
        $formatted = $data->map(function ($row) {
            $range = ((float) $row->high - (float) $row->low) * 100;
            return [
                'time' => (int) $row->time,
                'range' => $range,
                'high' => (float) $row->high * 100,
                'low' => (float) $row->low * 100,
            ];
        });
        
        // Calculate volatility statistics
        $ranges = $formatted->pluck('range')->toArray();
        $avgVolatility = count($ranges) > 0 ? array_sum($ranges) / count($ranges) : 0;
        $maxVolatility = count($ranges) > 0 ? max($ranges) : 0;
        
        return response()->json([
            'success' => true,
            'data' => $formatted,
            'count' => count($formatted),
            'stats' => [
                'avg_volatility' => round($avgVolatility, 6),
                'max_volatility' => round($maxVolatility, 6),
            ],
        ]);
    }
    
    /**
     * Get enhanced statistics for funding rates
     * Returns median, percentiles, trend, std dev
     */
    public function statistics(Request $request)
    {
        $symbol = strtoupper($request->query('symbol', 'BTC'));
        
        $cacheKey = "db_funding_rate_statistics_{$symbol}";
        
        $data = Cache::remember($cacheKey, 30, function () use ($symbol) {
            // Get latest from exchange list
            $exchangeData = DB::table('cg_funding_rate_exchange_list')
                ->where('symbol', $symbol)
                ->orderBy('updated_at', 'desc')
                ->get();
            
            if ($exchangeData->isEmpty()) {
                return null;
            }
            
            $rates = $exchangeData->pluck('funding_rate')->map(fn($r) => (float) $r)->toArray();
            sort($rates);
            
            $count = count($rates);
            $sum = array_sum($rates);
            $mean = $sum / $count;
            
            // Median
            $median = $count % 2 === 0
                ? ($rates[$count/2 - 1] + $rates[$count/2]) / 2
                : $rates[floor($count/2)];
            
            // Percentiles
            $p25Index = (int) floor($count * 0.25);
            $p75Index = (int) floor($count * 0.75);
            $p25 = $rates[$p25Index] ?? $rates[0];
            $p75 = $rates[$p75Index] ?? end($rates);
            
            // Standard deviation
            $variance = array_sum(array_map(fn($r) => pow($r - $mean, 2), $rates)) / $count;
            $stdDev = sqrt($variance);
            
            // Count by sentiment
            $positiveCount = count(array_filter($rates, fn($r) => $r > 0));
            $negativeCount = count(array_filter($rates, fn($r) => $r < 0));
            $neutralCount = count(array_filter($rates, fn($r) => $r == 0));
            
            // Determine market sentiment
            $sentimentRatio = $count > 0 ? $positiveCount / $count : 0.5;
            if ($sentimentRatio > 0.7) {
                $sentiment = 'Bullish';
                $sentimentScore = 80 + ($sentimentRatio - 0.7) * 66.67;
            } elseif ($sentimentRatio > 0.5) {
                $sentiment = 'Slightly Bullish';
                $sentimentScore = 50 + ($sentimentRatio - 0.5) * 150;
            } elseif ($sentimentRatio > 0.3) {
                $sentiment = 'Slightly Bearish';
                $sentimentScore = 20 + ($sentimentRatio - 0.3) * 150;
            } else {
                $sentiment = 'Bearish';
                $sentimentScore = $sentimentRatio * 66.67;
            }
            
            return [
                'count' => $count,
                'mean' => round($mean * 100, 4),
                'median' => round($median * 100, 4),
                'min' => round(min($rates) * 100, 4),
                'max' => round(max($rates) * 100, 4),
                'std_dev' => round($stdDev * 100, 4),
                'p25' => round($p25 * 100, 4),
                'p75' => round($p75 * 100, 4),
                'spread_bps' => round((max($rates) - min($rates)) * 10000, 1),
                'positive_count' => $positiveCount,
                'negative_count' => $negativeCount,
                'neutral_count' => $neutralCount,
                'sentiment' => $sentiment,
                'sentiment_score' => round($sentimentScore),
                'annualized_apy' => round($mean * 100 * 3 * 365, 2),
            ];
        });
        
        if ($data === null) {
            return response()->json([
                'success' => false,
                'message' => "No data found for {$symbol}",
            ]);
        }
        
        return response()->json([
            'success' => true,
            'data' => $data,
            'symbol' => $symbol,
        ]);
    }
}
