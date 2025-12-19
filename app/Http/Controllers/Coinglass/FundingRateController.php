<?php

namespace App\Http\Controllers\Coinglass;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\CoinglassClient;

class FundingRateController extends Controller
{
    private CoinglassClient $client;
    private int $cacheTtlSeconds;

    public function __construct(CoinglassClient $client)
    {
        $this->client = $client;
        $this->cacheTtlSeconds = (int) config('services.coinglass.cache_ttl.funding_rate', 10);
    }

    // GET /api/coinglass/funding-rate/exchanges
    public function exchanges()
    {
        $cacheKey = 'coinglass:funding-rate:exchanges:list';
        $data = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () {
            return $this->client->get('/futures/funding-rate/supported-exchange-pair', [ 'symbol' => 'BTC' ]);
        });

        return response()->json($data);
    }

    // GET /api/coinglass/funding-rate/history
    public function history(Request $request)
    {
        $exchange = $request->query('exchange', 'Binance');
        $symbol = $this->toCoinglassSymbol($request->query('symbol', 'BTCUSDT'));
        $interval = $request->query('interval', '8h');
        $start = $request->query('start_time');
        $end = $request->query('end_time');

        $cacheKey = sprintf('coinglass:fr:history:%s:%s:%s:%s:%s', $exchange, $symbol, $interval, $start, $end);

        $raw = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () use ($exchange, $symbol, $interval, $start, $end) {
            $query = array_filter([
                'exchange' => $exchange,
                'symbol' => $symbol,
                'interval' => $interval,
                'start_time' => $start,
                'end_time' => $end,
            ], fn($v) => $v !== null && $v !== '');

            return $this->client->get('/futures/funding-rate/history', $query);
        });

        $normalized = $this->normalizeFundingRateHistory($raw);
        return response()->json($normalized);
    }

    // GET /api/coinglass/funding-rate/current
    public function current(Request $request)
    {
        $exchange = $request->query('exchange', 'Binance');
        $symbol = $this->toCoinglassSymbol($request->query('symbol', 'BTCUSDT'));

        $cacheKey = sprintf('coinglass:fr:current:%s:%s', $exchange, $symbol);

        $raw = Cache::remember($cacheKey, 60, function () use ($exchange, $symbol) {
            // Get latest funding rate (limit to 1 for current)
            return $this->client->get('/futures/funding-rate/history', [
                'exchange' => $exchange,
                'symbol' => $symbol,
                'interval' => '8h',
                'limit' => 1
            ]);
        });

        $normalized = $this->normalizeCurrentFundingRate($raw, $exchange, $symbol);
        return response()->json($normalized);
    }

    private function normalizeFundingRateHistory($raw): array
    {
        if (!is_array($raw) || (isset($raw['success']) && $raw['success'] === false)) {
            return [ 'success' => false, 'error' => $raw['error'] ?? [ 'code' => 500, 'message' => 'Unknown error' ] ];
        }

        // Handle Coinglass API response format
        if (isset($raw['code']) && $raw['code'] !== '0') {
            return [ 
                'success' => false, 
                'error' => [ 
                    'code' => (int) $raw['code'], 
                    'message' => $raw['msg'] ?? 'API error' 
                ] 
            ];
        }

        $rows = $raw['data'] ?? [];
        $data = [];
        
        foreach ($rows as $row) {
            $ts = $row['time'] ?? null;
            $fundingRate = $row['close'] ?? $row['funding_rate'] ?? null;
            
            if ($ts === null || $fundingRate === null) {
                continue;
            }
            
            $data[] = [
                'ts' => (int) $ts,
                'funding_rate' => (float) $fundingRate,
                'open' => (float) ($row['open'] ?? $fundingRate),
                'high' => (float) ($row['high'] ?? $fundingRate),
                'low' => (float) ($row['low'] ?? $fundingRate),
                'close' => (float) $fundingRate,
            ];
        }

        return [ 'success' => true, 'data' => $data ];
    }

    private function normalizeCurrentFundingRate($raw, $exchange, $symbol): array
    {
        if (!is_array($raw) || (isset($raw['success']) && $raw['success'] === false)) {
            return [ 'success' => false, 'error' => $raw['error'] ?? [ 'code' => 500, 'message' => 'Unknown error' ] ];
        }

        // Handle Coinglass API response format
        if (isset($raw['code']) && $raw['code'] !== '0') {
            return [ 
                'success' => false, 
                'error' => [ 
                    'code' => (int) $raw['code'], 
                    'message' => $raw['msg'] ?? 'API error' 
                ] 
            ];
        }

        $rows = $raw['data'] ?? [];
        $latestRow = $rows[0] ?? null;
        
        if (!$latestRow) {
            return [ 
                'success' => false, 
                'error' => [ 
                    'code' => 404, 
                    'message' => 'No current funding rate data available' 
                ] 
            ];
        }

        $data = [
            'ts' => (int) ($latestRow['time'] ?? 0),
            'funding_rate' => (float) ($latestRow['close'] ?? 0),
            'exchange' => $exchange,
            'symbol' => $symbol,
            'next_funding_time' => null, // Coinglass doesn't provide this in history endpoint
        ];

        return [ 'success' => true, 'data' => $data ];
    }

    private function toCoinglassSymbol(?string $symbol): ?string
    {
        if (!$symbol) return $symbol;
        
        $s = strtoupper($symbol);
        
        // Handle common symbol formats
        foreach (['USDT','USDC','BUSD','USD'] as $quote) {
            if (str_ends_with($s, $quote)) {
                return $s; // Keep full symbol for funding rate API
            }
        }
        
        if (str_contains($s, '_')) {
            return str_replace('_', '', $s);
        }
        
        // Default to BTCUSDT if just base currency provided
        if (strlen($s) <= 4 && !str_contains($s, 'USD')) {
            return $s . 'USDT';
        }
        
        return $s;
    }

    private function toCoinglassExchange(?string $exchange): ?string
    {
        if (!$exchange) return 'Binance'; // Default exchange
        
        // Coinglass expects proper case for exchange names
        $exchangeMap = [
            'binance' => 'Binance',
            'okx' => 'OKX',
            'bybit' => 'Bybit',
            'bitget' => 'Bitget',
            'bitmex' => 'BitMEX',
            'deribit' => 'Deribit',
            'huobi' => 'Huobi',
            'kucoin' => 'KuCoin',
        ];
        
        $lowerExchange = strtolower($exchange);
        return $exchangeMap[$lowerExchange] ?? ucfirst($exchange);
    }
}
