<?php

namespace App\Http\Controllers\Coinglass;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\CoinglassClient;

class LiquidationsController extends Controller
{
    private CoinglassClient $client;
    private int $cacheTtlSeconds;

    public function __construct(CoinglassClient $client)
    {
        $this->client = $client;
        $this->cacheTtlSeconds = (int) config('services.coinglass.cache_ttl.liquidations', 10);
    }

    /**
     * GET /api/coinglass/liquidation/aggregated-heatmap/model3
     * 
     * Fetch liquidation heatmap data (Model 3)
     * 
     * Query params:
     * - symbol: string (required) - Trading symbol (e.g., BTC)
     * - range: string (required) - Time range (12h, 24h, 3d, 7d, 30d, 90d, 180d, 1y)
     */
    public function heatmapModel3(Request $request)
    {
        $symbol = $this->toCoinglassSymbol($request->query('symbol', 'BTC'));
        $range = $request->query('range', '3d');

        // Validate range
        $validRanges = ['12h', '24h', '3d', '7d', '30d', '90d', '180d', '1y'];
        if (!in_array($range, $validRanges)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'Invalid range. Supported: ' . implode(', ', $validRanges)
                ]
            ], 400);
        }

        $cacheKey = sprintf('coinglass:liquidations:heatmap:model3:%s:%s', $symbol, $range);

        $raw = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () use ($symbol, $range) {
            $query = [
                'symbol' => $symbol,
                'range' => $range,
            ];

            return $this->client->get('/futures/liquidation/aggregated-heatmap/model3', $query);
        });

        $normalized = $this->normalizeHeatmapData($raw);
        return response()->json($normalized);
    }

    /**
     * Normalize heatmap data from Coinglass API
     */
    private function normalizeHeatmapData($raw): array
    {
        // Check for API error
        if (!is_array($raw) || (isset($raw['success']) && $raw['success'] === false)) {
            return [
                'success' => false,
                'error' => $raw['error'] ?? ['code' => 500, 'message' => 'Unknown error']
            ];
        }

        // Check for Coinglass error code
        if (isset($raw['code']) && $raw['code'] !== '0' && $raw['code'] !== 0) {
            return [
                'success' => false,
                'error' => [
                    'code' => $raw['code'],
                    'message' => $raw['msg'] ?? $raw['message'] ?? 'API error'
                ]
            ];
        }

        // Extract data
        $data = $raw['data'] ?? [];
        
        if (empty($data)) {
            return [
                'success' => false,
                'error' => ['code' => 404, 'message' => 'No data available']
            ];
        }

        // Return normalized structure
        return [
            'success' => true,
            'data' => [
                'y_axis' => $data['y_axis'] ?? [],
                'liquidation_leverage_data' => $data['liquidation_leverage_data'] ?? [],
                'price_candlesticks' => $data['price_candlesticks'] ?? [],
                'update_time' => $data['update_time'] ?? time(),
                'timestamp' => time()
            ]
        ];
    }

    /**
     * GET /api/coinglass/liquidation/aggregated-history
     * 
     * Fetch aggregated liquidation history
     * 
     * Query params:
     * - exchange_list: string (required) - Comma-separated exchanges (e.g., "Binance,OKX")
     * - symbol: string (required) - Trading symbol (e.g., BTC)
     * - interval: string (required) - Time interval (1m, 3m, 5m, 15m, 30m, 1h, 4h, 6h, 8h, 12h, 1d, 1w)
     * - start_time: int64 (optional) - Start timestamp in milliseconds
     * - end_time: int64 (optional) - End timestamp in milliseconds
     */
    public function aggregatedHistory(Request $request)
    {
        $exchangeList = $request->query('exchange_list', 'Binance');
        $symbol = $this->toCoinglassSymbol($request->query('symbol', 'BTC'));
        $interval = $request->query('interval', '1d');
        $startTime = $request->query('start_time');
        $endTime = $request->query('end_time');

        // Validate interval
        $validIntervals = ['1m', '3m', '5m', '15m', '30m', '1h', '4h', '6h', '8h', '12h', '1d', '1w'];
        if (!in_array($interval, $validIntervals)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'Invalid interval. Supported: ' . implode(', ', $validIntervals)
                ]
            ], 400);
        }

        $cacheKey = sprintf(
            'coinglass:liquidations:history:%s:%s:%s:%s:%s',
            md5($exchangeList),
            $symbol,
            $interval,
            $startTime ?? 'null',
            $endTime ?? 'null'
        );

        $raw = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () use ($exchangeList, $symbol, $interval, $startTime, $endTime) {
            $query = array_filter([
                'exchange_list' => $exchangeList,
                'symbol' => $symbol,
                'interval' => $interval,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ], fn($v) => $v !== null && $v !== '');

            return $this->client->get('/futures/liquidation/aggregated-history', $query);
        });

        $normalized = $this->normalizeAggregatedHistory($raw);
        return response()->json($normalized);
    }

    /**
     * Normalize aggregated history data
     */
    private function normalizeAggregatedHistory($raw): array
    {
        // Check for API error
        if (!is_array($raw) || (isset($raw['success']) && $raw['success'] === false)) {
            return [
                'success' => false,
                'error' => $raw['error'] ?? ['code' => 500, 'message' => 'Unknown error']
            ];
        }

        // Check for Coinglass error code
        if (isset($raw['code']) && $raw['code'] !== '0' && $raw['code'] !== 0) {
            return [
                'success' => false,
                'error' => [
                    'code' => $raw['code'],
                    'message' => $raw['msg'] ?? $raw['message'] ?? 'API error'
                ]
            ];
        }

        // Extract data
        $rows = $raw['data'] ?? [];
        
        if (empty($rows)) {
            return [
                'success' => false,
                'error' => ['code' => 404, 'message' => 'No data available']
            ];
        }

        // Normalize each row
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'time' => $row['time'] ?? null,
                'long_liquidation_usd' => $row['aggregated_long_liquidation_usd'] ?? 0,
                'short_liquidation_usd' => $row['aggregated_short_liquidation_usd'] ?? 0,
                'total_liquidation_usd' => ($row['aggregated_long_liquidation_usd'] ?? 0) + ($row['aggregated_short_liquidation_usd'] ?? 0),
            ];
        }

        return [
            'success' => true,
            'data' => $data,
            'timestamp' => time()
        ];
    }

    /**
     * Convert symbol to Coinglass format (remove quote currency)
     */
    private function toCoinglassSymbol(?string $symbol): ?string
    {
        if (!$symbol) return $symbol;
        
        $s = strtoupper($symbol);
        
        // Remove common quote currencies
        foreach (['USDT', 'USDC', 'BUSD', 'USD'] as $quote) {
            if (str_ends_with($s, $quote)) {
                return substr($s, 0, -strlen($quote));
            }
        }
        
        // Handle underscore format (BTC_USDT -> BTC)
        if (str_contains($s, '_')) {
            return explode('_', $s)[0];
        }
        
        return $s;
    }
}
