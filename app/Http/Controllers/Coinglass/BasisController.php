<?php

namespace App\Http\Controllers\Coinglass;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\CoinglassClient;

/**
 * Basis & Term Structure Controller (Coinglass API)
 * 
 * Handles futures basis data - the difference between futures and spot prices
 * 
 * Blueprint: OpenInterestController (proven stable)
 * 
 * Trading Context:
 * - Positive basis (Contango): Futures > Spot (normal market)
 * - Negative basis (Backwardation): Futures < Spot (bullish signal)
 */
class BasisController extends Controller
{
    private CoinglassClient $client;
    private int $cacheTtlSeconds;

    public function __construct(CoinglassClient $client)
    {
        $this->client = $client;
        // Use same cache TTL as Open Interest for consistency
        $this->cacheTtlSeconds = (int) config('services.coinglass.cache_ttl.basis', 10);
    }

    /**
     * GET /api/coinglass/basis/history
     * 
     * Futures Basis History
     * Shows the spread between futures and spot prices over time
     */
    public function basisHistory(Request $request)
    {
        $symbol = $this->toCoinglassSymbol($request->query('symbol', 'BTC'));
        $exchange = $request->query('exchange', 'Binance');
        $interval = $request->query('interval', '1h');
        $start = $request->query('start_time');
        $end = $request->query('end_time');

        $cacheKey = sprintf(
            'coinglass:basis:%s:%s:%s:%s:%s',
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

            return $this->client->get('/futures/basis/history', $query);
        });

        $normalized = $this->normalizeBasisHistory($raw);
        return response()->json($normalized);
    }

    /**
     * Normalize Basis History Response
     */
    private function normalizeBasisHistory($raw): array
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
            $openBasis = $row['open_basis'] ?? null;
            $closeBasis = $row['close_basis'] ?? null;
            $openChange = $row['open_change'] ?? null;
            $closeChange = $row['close_change'] ?? null;

            if ($ts === null || $closeBasis === null) {
                continue;
            }

            $data[] = [
                'ts' => (int) $ts,
                'open_basis' => $openBasis !== null ? (float) $openBasis : null,
                'close_basis' => (float) $closeBasis,
                'open_change' => $openChange !== null ? (float) $openChange : null,
                'close_change' => $closeChange !== null ? (float) $closeChange : null,
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
