<?php

namespace App\Http\Controllers\Coinglass;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\CoinglassClient;

/**
 * Long-Short Ratio Controller (Coinglass API)
 * 
 * Handles two types of long-short ratio data:
 * 1. Global Account Ratio - All traders
 * 2. Top Account Ratio - Top traders only
 * 
 * Blueprint: OpenInterestController (proven stable)
 */
class LongShortRatioController extends Controller
{
    private CoinglassClient $client;
    private int $cacheTtlSeconds;

    public function __construct(CoinglassClient $client)
    {
        $this->client = $client;
        // Use same cache TTL as Open Interest for consistency
        $this->cacheTtlSeconds = (int) config('services.coinglass.cache_ttl.long_short_ratio', 10);
    }

    /**
     * GET /api/coinglass/long-short-ratio/global-account/history
     * 
     * Global Account Long-Short Ratio History
     * Shows sentiment across all traders
     */
    public function globalAccountHistory(Request $request)
    {
        $symbol = $this->toCoinglassSymbol($request->query('symbol', 'BTC'));
        $exchange = $request->query('exchange', 'Binance');
        $interval = $request->query('interval', '1h');
        $start = $request->query('start_time');
        $end = $request->query('end_time');

        $cacheKey = sprintf(
            'coinglass:lsr:global:%s:%s:%s:%s:%s',
            $symbol,
            $exchange,
            $interval,
            $start,
            $end
        );

        $raw = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () use ($symbol, $exchange, $interval, $start, $end) {
            $query = array_filter([
                'symbol' => $this->toCoinglassPair($symbol),
                'exchange' => $exchange,
                'interval' => $interval,
                'start_time' => $start,
                'end_time' => $end,
            ], fn($v) => $v !== null && $v !== '');

            return $this->client->get('/futures/global-long-short-account-ratio/history', $query);
        });

        $normalized = $this->normalizeGlobalAccountHistory($raw);
        return response()->json($normalized);
    }

    /**
     * GET /api/coinglass/long-short-ratio/top-account/history
     * 
     * Top Account Long-Short Ratio History
     * Shows sentiment among top traders (smart money)
     */
    public function topAccountHistory(Request $request)
    {
        $symbol = $this->toCoinglassSymbol($request->query('symbol', 'BTC'));
        $exchange = $request->query('exchange', 'Binance');
        $interval = $request->query('interval', '1h');
        $start = $request->query('start_time');
        $end = $request->query('end_time');

        $cacheKey = sprintf(
            'coinglass:lsr:top:%s:%s:%s:%s:%s',
            $symbol,
            $exchange,
            $interval,
            $start,
            $end
        );

        $raw = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () use ($symbol, $exchange, $interval, $start, $end) {
            $query = array_filter([
                'symbol' => $this->toCoinglassPair($symbol),
                'exchange' => $exchange,
                'interval' => $interval,
                'start_time' => $start,
                'end_time' => $end,
            ], fn($v) => $v !== null && $v !== '');

            return $this->client->get('/futures/top-long-short-account-ratio/history', $query);
        });

        $normalized = $this->normalizeTopAccountHistory($raw);
        return response()->json($normalized);
    }

    /**
     * Normalize Global Account History Response
     */
    private function normalizeGlobalAccountHistory($raw): array
    {
        if (!is_array($raw) || (isset($raw['success']) && $raw['success'] === false)) {
            return [
                'success' => false,
                'error' => $raw['error'] ?? ['code' => 500, 'message' => 'Unknown error']
            ];
        }

        $rows = $raw['data'] ?? ($raw['result'] ?? []);
        $data = [];

        foreach ($rows as $row) {
            $ts = $row['time'] ?? $row['ts'] ?? $row['timestamp'] ?? null;
            $ratio = $row['global_account_long_short_ratio'] ?? $row['ratio'] ?? null;
            $longPercent = $row['global_account_long_percent'] ?? $row['long_percent'] ?? null;
            $shortPercent = $row['global_account_short_percent'] ?? $row['short_percent'] ?? null;

            if ($ts === null || $ratio === null) {
                continue;
            }

            $data[] = [
                'ts' => (int) $ts,
                'ratio' => (float) $ratio,
                'long_percent' => $longPercent !== null ? (float) $longPercent : null,
                'short_percent' => $shortPercent !== null ? (float) $shortPercent : null,
            ];
        }

        return ['success' => true, 'data' => $data];
    }

    /**
     * Normalize Top Account History Response
     */
    private function normalizeTopAccountHistory($raw): array
    {
        if (!is_array($raw) || (isset($raw['success']) && $raw['success'] === false)) {
            return [
                'success' => false,
                'error' => $raw['error'] ?? ['code' => 500, 'message' => 'Unknown error']
            ];
        }

        $rows = $raw['data'] ?? ($raw['result'] ?? []);
        $data = [];

        foreach ($rows as $row) {
            $ts = $row['time'] ?? $row['ts'] ?? $row['timestamp'] ?? null;
            $ratio = $row['top_account_long_short_ratio'] ?? $row['ratio'] ?? null;
            $longPercent = $row['top_account_long_percent'] ?? $row['long_percent'] ?? null;
            $shortPercent = $row['top_account_short_percent'] ?? $row['short_percent'] ?? null;

            if ($ts === null || $ratio === null) {
                continue;
            }

            $data[] = [
                'ts' => (int) $ts,
                'ratio' => (float) $ratio,
                'long_percent' => $longPercent !== null ? (float) $longPercent : null,
                'short_percent' => $shortPercent !== null ? (float) $shortPercent : null,
            ];
        }

        return ['success' => true, 'data' => $data];
    }

    /**
     * Convert symbol to Coinglass format (BTC -> BTC, BTCUSDT -> BTC)
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

    /**
     * Convert symbol to Coinglass pair format (BTC -> BTCUSDT)
     */
    private function toCoinglassPair(?string $symbol): ?string
    {
        if (!$symbol) return $symbol;

        $base = $this->toCoinglassSymbol($symbol);

        // If already has USDT suffix, return as is
        if (str_ends_with(strtoupper($symbol), 'USDT')) {
            return strtoupper($symbol);
        }

        // Add USDT suffix
        return $base . 'USDT';
    }
}
