<?php

namespace App\Http\Controllers\Coinglass;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\CoinglassClient;

class OpenInterestController extends Controller
{
    private CoinglassClient $client;
    private int $cacheTtlSeconds;

    public function __construct(CoinglassClient $client)
    {
        $this->client = $client;
        $this->cacheTtlSeconds = (int) config('services.coinglass.cache_ttl.open_interest', 10);
    }

    // GET /api/coinglass/open-interest/exchanges
    public function exchanges()
    {
        $cacheKey = 'coinglass:exchanges:list';
        $data = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () {
            return $this->client->get('/futures/open-interest/exchange-list', [ 'symbol' => 'BTC' ]);
        });

        return response()->json($data);
    }

    // GET /api/coinglass/open-interest/history
    public function aggregatedHistory(Request $request)
    {
        $symbol = $this->toCoinglassSymbol($request->query('symbol'));
        $interval = $request->query('interval', '1h');
        $start = $request->query('start_time');
        $end = $request->query('end_time');

        $cacheKey = sprintf('coinglass:oi:agg:%s:%s:%s:%s', $symbol, $interval, $start, $end);

        $raw = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () use ($symbol, $interval, $start, $end) {
            $query = array_filter([
                'symbol' => $symbol,
                'interval' => $interval,
                'start_time' => $start,
                'end_time' => $end,
            ], fn($v) => $v !== null && $v !== '');

            return $this->client->get('/futures/open-interest/aggregated-history', $query + ['unit' => 'usd']);
        });

        $normalized = $this->normalizeAggregatedHistory($raw);
        return response()->json($normalized);
    }

    // GET /api/coinglass/open-interest/exchange-history
    public function exchangeHistory(Request $request)
    {
        $symbol = $this->toCoinglassSymbol($request->query('symbol'));
        $exchange = $this->toCoinglassExchange($request->query('exchange'));
        $interval = $request->query('interval', '1h');
        $start = $request->query('start_time');
        $end = $request->query('end_time');

        $cacheKey = sprintf('coinglass:oi:ex:%s:%s:%s:%s:%s', $symbol, $exchange, $interval, $start, $end);

        $raw = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () use ($symbol, $exchange, $interval, $start, $end) {
            $query = array_filter([
                'symbol' => $symbol,
                'exchange' => $exchange,
                'interval' => $interval,
                'start_time' => $start,
                'end_time' => $end,
            ], fn($v) => $v !== null && $v !== '');

            // No per-exchange history in provided docs; fallback to aggregated
            return $this->client->get('/futures/open-interest/aggregated-history', $query + ['unit' => 'usd']);
        });

        $normalized = $this->normalizeExchangeHistory($raw);
        return response()->json($normalized);
    }

    private function normalizeAggregatedHistory($raw): array
    {
        if (!is_array($raw) || (isset($raw['success']) && $raw['success'] === false)) {
            return [ 'success' => false, 'error' => $raw['error'] ?? [ 'code' => 500, 'message' => 'Unknown error' ] ];
        }

        $rows = $raw['data'] ?? ($raw['result'] ?? []);
        $data = [];
        foreach ($rows as $row) {
            $ts = $row['time'] ?? $row['ts'] ?? $row['timestamp'] ?? null;
            $oiTotal = $row['open_interest_total'] ?? $row['oi_total'] ?? $row['open_interest'] ?? $row['open_interest_usd'] ?? $row['oi_usd'] ?? ($row['close'] ?? null);
            if ($ts === null || $oiTotal === null) {
                continue;
            }
            $data[] = [
                'ts' => (int) $ts,
                'oi_total' => (float) $oiTotal,
                'currency' => $row['currency'] ?? 'USD',
            ];
        }

        return [ 'success' => true, 'data' => $data ];
    }

    private function normalizeExchangeHistory($raw): array
    {
        if (!is_array($raw) || (isset($raw['success']) && $raw['success'] === false)) {
            return [ 'success' => false, 'error' => $raw['error'] ?? [ 'code' => 500, 'message' => 'Unknown error' ] ];
        }

        $rows = $raw['data'] ?? ($raw['result'] ?? []);
        $data = [];
        foreach ($rows as $row) {
            $ts = $row['time'] ?? $row['ts'] ?? $row['timestamp'] ?? null;
            $oi = $row['open_interest'] ?? $row['oi_value'] ?? $row['open_interest_usd'] ?? $row['oi_usd'] ?? null;
            if ($ts === null || $oi === null) {
                continue;
            }
            $data[] = [
                'ts' => (int) $ts,
                'oi_value' => (float) $oi,
                'exchange' => $row['exchange'] ?? ($row['ex'] ?? null),
                'symbol' => $row['symbol'] ?? ($row['pair'] ?? null),
                'currency' => $row['currency'] ?? 'USD',
            ];
        }

        return [ 'success' => true, 'data' => $data ];
    }

    private function toCoinglassSymbol(?string $symbol): ?string
    {
        if (!$symbol) return $symbol;
        $s = strtoupper($symbol);
        foreach (['USDT','USDC','BUSD','USD'] as $quote) {
            if (str_ends_with($s, $quote)) {
                return substr($s, 0, -strlen($quote));
            }
        }
        if (str_contains($s, '_')) {
            return explode('_', $s)[0];
        }
        return $s;
    }

    private function toCoinglassExchange(?string $exchange): ?string
    {
        return $exchange ? strtolower($exchange) : $exchange;
    }
}

