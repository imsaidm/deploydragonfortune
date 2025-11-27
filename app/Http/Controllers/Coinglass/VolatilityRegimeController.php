<?php

namespace App\Http\Controllers\Coinglass;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\CoinglassClient;

class VolatilityRegimeController extends Controller
{
    private CoinglassClient $client;
    private int $cacheTtlSeconds;

    public function __construct(CoinglassClient $client)
    {
        $this->client = $client;
        $this->cacheTtlSeconds = (int) env('COINGLASS_VOLATILITY_CACHE_TTL', 30);
    }

    // ========================================================================
    // SPOT PRICE HISTORY (OHLC)
    // ========================================================================
    
    /**
     * GET /api/coinglass/volatility/price-history
     * 
     * Query params:
     *   - exchange: string (default: "Binance")
     *   - symbol: string (default: "BTCUSDT")
     *   - interval: string (default: "1h") - 1m, 3m, 5m, 15m, 30m, 1h, 4h, 6h, 8h, 12h, 1d, 1w
     *   - start_time: int (optional) - timestamp in milliseconds
     *   - end_time: int (optional) - timestamp in milliseconds
     */
    public function priceHistory(Request $request)
    {
        $exchange = $request->input('exchange', 'Binance');
        $symbol = $request->input('symbol', 'BTCUSDT');
        $interval = $request->input('interval', '1h');
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');

        // Build cache key including all params
        $cacheKey = sprintf(
            'coinglass:volatility:price:%s:%s:%s:%s:%s',
            $exchange,
            $symbol,
            $interval,
            $startTime ?? 'auto',
            $endTime ?? 'auto'
        );

        // Build query params
        $queryParams = [
            'exchange' => $exchange,
            'symbol' => $symbol,
            'interval' => $interval,
        ];

        if ($startTime) {
            $queryParams['start_time'] = $startTime;
        }
        if ($endTime) {
            $queryParams['end_time'] = $endTime;
        }

        return $this->fetchAndNormalize(
            endpoint: '/spot/price/history',
            cacheKey: $cacheKey,
            normalizer: 'normalizePriceHistory',
            queryParams: $queryParams,
            cacheTtl: $this->getIntervalCacheTtl($interval)
        );
    }

    /**
     * GET /api/coinglass/volatility/eod
     * 
     * End-of-Day data (ATR, HV, RV calculations will be done in Analytics Layer)
     * For now, returns daily OHLC data
     */
    public function eod(Request $request)
    {
        $exchange = $request->input('exchange', 'Binance');
        $symbol = $request->input('symbol', 'BTCUSDT');
        $days = $request->input('days', 30); // Default: last 30 days

        // Calculate start/end time for EOD
        $endTime = time() * 1000; // Current time in ms
        $startTime = $endTime - ($days * 24 * 60 * 60 * 1000);

        $cacheKey = sprintf(
            'coinglass:volatility:eod:%s:%s:%s',
            $exchange,
            $symbol,
            $days
        );

        $queryParams = [
            'exchange' => $exchange,
            'symbol' => $symbol,
            'interval' => '1d', // Daily candles
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];

        return $this->fetchAndNormalize(
            endpoint: '/spot/price/history',
            cacheKey: $cacheKey,
            normalizer: 'normalizeEodData',
            queryParams: $queryParams,
            cacheTtl: 60 // 60 minutes for EOD data
        );
    }

    // ========================================================================
    // GENERIC API CALLER (Reusable for all endpoints)
    // ========================================================================
    
    private function fetchAndNormalize(
        string $endpoint,
        string $cacheKey,
        string $normalizer,
        array $queryParams = [],
        ?int $cacheTtl = null
    ) {
        try {
            $ttl = $cacheTtl ?? $this->cacheTtlSeconds;

            $data = Cache::remember($cacheKey, $ttl, function () use ($endpoint, $queryParams, $normalizer) {
                $rawData = $this->callCoinglassApi($endpoint, $queryParams);
                return $this->$normalizer($rawData);
            });

            return response()->json($data);

        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    private function callCoinglassApi(string $endpoint, array $queryParams = []): array
    {
        Log::info("[Volatility] API call", ['endpoint' => $endpoint, 'params' => $queryParams]);

        $url = 'https://open-api-v4.coinglass.com/api' . $endpoint;
        $apiKey = env('COINGLASS_API_KEY');

        if (empty($apiKey)) {
            throw new \Exception('COINGLASS_API_KEY not configured');
        }

        $http = Http::timeout(15)
            ->withHeaders([
                'CG-API-KEY' => $apiKey,
                'accept' => 'application/json',
            ]);

        // In non-production environments, allow self-signed/mitm certs to avoid local SSL trust issues.
        if (! app()->environment('production')) {
            $http = $http->withOptions(['verify' => false]);
        }

        $response = $http->get($url, $queryParams);

        if (!$response->successful()) {
            throw new \Exception(
                sprintf(
                    'Coinglass API error: %s (HTTP %d)',
                    $response->body(),
                    $response->status()
                )
            );
        }

        $json = $response->json();

        if (!isset($json['code']) || $json['code'] !== '0') {
            throw new \Exception(
                sprintf(
                    'Coinglass API returned error code: %s',
                    $json['msg'] ?? 'Unknown error'
                )
            );
        }

        return $json;
    }

    private function errorResponse(\Exception $e)
    {
        Log::error('[Volatility] API Error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'message' => 'Failed to fetch volatility data'
        ], 500);
    }

    // ========================================================================
    // DATA NORMALIZERS
    // ========================================================================

    /**
     * Normalize OHLC price history
     */
    private function normalizePriceHistory($raw): array
    {
        if (!isset($raw['data']) || !is_array($raw['data'])) {
            throw new \Exception('Invalid price history response format');
        }

        $ohlc = [];
        foreach ($raw['data'] as $candle) {
            $ohlc[] = [
                'time' => (int) ($candle['time'] ?? 0),
                'date' => date('Y-m-d H:i:s', ($candle['time'] ?? 0) / 1000),
                'open' => (float) ($candle['open'] ?? 0),
                'high' => (float) ($candle['high'] ?? 0),
                'low' => (float) ($candle['low'] ?? 0),
                'close' => (float) ($candle['close'] ?? 0),
                'volume_usd' => (float) ($candle['volume_usd'] ?? 0),
            ];
        }

        return [
            'success' => true,
            'data' => $ohlc,
            'count' => count($ohlc),
            'cached_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Normalize EOD data with additional volatility metrics
     * (ATR, HV, RV will be calculated client-side in Analytics Layer)
     */
    private function normalizeEodData($raw): array
    {
        if (!isset($raw['data']) || !is_array($raw['data'])) {
            throw new \Exception('Invalid EOD response format');
        }

        $eodData = [];
        foreach ($raw['data'] as $candle) {
            $high = (float) ($candle['high'] ?? 0);
            $low = (float) ($candle['low'] ?? 0);
            $close = (float) ($candle['close'] ?? 0);
            $open = (float) ($candle['open'] ?? 0);

            // Calculate basic range metrics (for client-side ATR calculation)
            $trueRange = max(
                $high - $low,
                abs($high - $close),
                abs($low - $close)
            );

            $eodData[] = [
                'time' => (int) ($candle['time'] ?? 0),
                'date' => date('Y-m-d', ($candle['time'] ?? 0) / 1000),
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume_usd' => (float) ($candle['volume_usd'] ?? 0),
                'true_range' => $trueRange, // For ATR calculation
                'daily_return' => $open > 0 ? (($close - $open) / $open) * 100 : 0, // For HV/RV
            ];
        }

        return [
            'success' => true,
            'data' => $eodData,
            'count' => count($eodData),
            'cached_at' => now()->toIso8601String(),
        ];
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Get optimal cache TTL based on interval
     */
    private function getIntervalCacheTtl(string $interval): int
    {
        return match($interval) {
            '1m', '3m', '5m' => 5,      // 5 seconds for fast intervals
            '15m', '30m' => 10,          // 10 seconds
            '1h', '4h' => 30,            // 30 seconds
            '6h', '8h', '12h' => 60,     // 1 minute
            '1d' => 300,                 // 5 minutes
            '1w' => 900,                 // 15 minutes
            default => 30,               // 30 seconds default
        };
    }
}
