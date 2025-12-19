<?php

namespace App\Http\Controllers\Coinglass;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\CoinglassClient;

class SentimentFlowController extends Controller
{
    private CoinglassClient $client;
    private int $cacheTtlSeconds;

    public function __construct(CoinglassClient $client)
    {
        $this->client = $client;
        $this->cacheTtlSeconds = (int) config('services.coinglass.cache_ttl.sentiment', 10);
    }

    /**
     * GET /api/coinglass/sentiment/fear-greed
     * Crypto Fear & Greed Index History
     */
    public function fearGreedIndex()
    {
        $cacheKey = 'coinglass:sentiment:fear-greed';
        
        $raw = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () {
            return $this->client->get('/index/fear-greed-history', []);
        });

        $normalized = $this->normalizeFearGreed($raw);
        return response()->json($normalized);
    }

    /**
     * GET /api/coinglass/sentiment/funding-dominance
     * Funding Rate Exchange List for BTC
     */
    public function fundingDominance(Request $request)
    {
        $symbol = $request->query('symbol', 'BTC');
        $cacheKey = sprintf('coinglass:sentiment:funding:%s', $symbol);
        
        $raw = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () {
            return $this->client->get('/futures/funding-rate/exchange-list', []);
        });

        $normalized = $this->normalizeFundingDominance($raw, $symbol);
        return response()->json($normalized);
    }

    /**
     * GET /api/coinglass/sentiment/whale-alerts
     * Hyperliquid Whale Alert
     */
    public function whaleAlerts()
    {
        $cacheKey = 'coinglass:sentiment:whale-alerts';
        
        $raw = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () {
            return $this->client->get('/hyperliquid/whale-alert', []);
        });

        $normalized = $this->normalizeWhaleAlerts($raw);
        return response()->json($normalized);
    }

    /**
     * GET /api/coinglass/sentiment/whale-transfers
     * On-Chain Whale Transfers ($10M+ transfers)
     */
    public function whaleTransfers(Request $request)
    {
        $symbol = $request->query('symbol', null); // ALL if null
        $startTime = $request->query('start_time', null);
        $endTime = $request->query('end_time', null);
        
        // Build cache key with params
        $cacheKey = sprintf(
            'coinglass:sentiment:whale-transfers:%s:%s:%s',
            $symbol ?? 'all',
            $startTime ?? 'none',
            $endTime ?? 'none'
        );
        
        $raw = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () use ($symbol, $startTime, $endTime) {
            $params = [];
            if ($symbol) {
                $params['symbol'] = $symbol;
            }
            if ($startTime) {
                $params['start_time'] = $startTime;
            }
            if ($endTime) {
                $params['end_time'] = $endTime;
            }
            
            return $this->client->get('/chain/whale-transfer', $params);
        });

        $normalized = $this->normalizeWhaleTransfers($raw);
        return response()->json($normalized);
    }

    /**
     * Normalize Fear & Greed Index response
     */
    private function normalizeFearGreed($raw): array
    {
        if (!is_array($raw) || (isset($raw['success']) && $raw['success'] === false)) {
            return [
                'success' => false,
                'error' => $raw['error'] ?? ['code' => 500, 'message' => 'Unknown error']
            ];
        }

        // Extract data_list and time_list from response
        $dataList = $raw['data']['data_list'] ?? [];
        $timeList = $raw['data']['time_list'] ?? [];
        
        if (empty($dataList)) {
            return [
                'success' => false,
                'error' => ['code' => 404, 'message' => 'No fear & greed data available']
            ];
        }

        // Get latest value (last in array)
        $latest = end($dataList);
        
        // Calculate sentiment label
        $sentiment = $this->getSentimentLabel($latest);
        
        // Combine values and timestamps into objects (return ALL history)
        $history = [];
        $count = count($dataList);
        for ($i = 0; $i < $count; $i++) {
            $timestamp = $timeList[$i] ?? null;
            $history[] = [
                'value' => $dataList[$i],
                'timestamp' => $timestamp,
                'date' => $timestamp ? date('Y-m-d', $timestamp / 1000) : null
            ];
        }
        
        return [
            'success' => true,
            'data' => [
                'current_value' => $latest,
                'sentiment' => $sentiment,
                'history' => $history, // Return ALL history, let frontend filter
                'timestamp' => time()
            ]
        ];
    }

    /**
     * Normalize Funding Dominance response
     */
    private function normalizeFundingDominance($raw, $symbol): array
    {
        if (!is_array($raw) || (isset($raw['success']) && $raw['success'] === false)) {
            return [
                'success' => false,
                'error' => $raw['error'] ?? ['code' => 500, 'message' => 'Unknown error']
            ];
        }

        $data = $raw['data'] ?? [];
        
        // Find BTC data
        $btcData = collect($data)->firstWhere('symbol', $symbol);
        
        if (!$btcData) {
            return [
                'success' => false,
                'error' => ['code' => 404, 'message' => 'Symbol not found']
            ];
        }

        // Extract stablecoin margin exchanges (USDT/USD margin mode)
        $exchanges = [];
        foreach ($btcData['stablecoin_margin_list'] ?? [] as $item) {
            // Skip if required fields are missing
            if (!isset($item['exchange'], $item['funding_rate'], $item['funding_rate_interval'])) {
                continue;
            }

            $exchanges[] = [
                'exchange' => $item['exchange'],
                'funding_rate' => (float) $item['funding_rate'],
                'funding_rate_interval' => (int) $item['funding_rate_interval'],
                'next_funding_time' => $item['next_funding_time'] ?? null,
                'annualized_rate' => $this->calculateAnnualizedRate(
                    $item['funding_rate'],
                    $item['funding_rate_interval']
                )
            ];
        }

        // Sort by absolute funding rate (highest dominance first)
        usort($exchanges, function($a, $b) {
            return abs($b['funding_rate']) <=> abs($a['funding_rate']);
        });

        // Calculate aggregate metrics
        $avgRate = collect($exchanges)->avg('funding_rate');
        $maxRate = collect($exchanges)->max('funding_rate');
        $minRate = collect($exchanges)->min('funding_rate');

        return [
            'success' => true,
            'data' => [
                'symbol' => $symbol,
                'exchanges' => $exchanges,
                'aggregate' => [
                    'avg_funding_rate' => $avgRate,
                    'max_funding_rate' => $maxRate,
                    'min_funding_rate' => $minRate,
                    'sentiment' => $avgRate > 0.001 ? 'Bullish' : ($avgRate < -0.001 ? 'Bearish' : 'Neutral')
                ],
                'timestamp' => time()
            ]
        ];
    }

    /**
     * Normalize Whale Alerts response
     */
    private function normalizeWhaleAlerts($raw): array
    {
        if (!is_array($raw) || (isset($raw['success']) && $raw['success'] === false)) {
            return [
                'success' => false,
                'error' => $raw['error'] ?? ['code' => 500, 'message' => 'Unknown error']
            ];
        }

        $data = $raw['data'] ?? [];
        
        // Process whale alerts
        $alerts = [];
        foreach ($data as $item) {
            // Skip if required fields are missing
            if (!isset($item['user'], $item['symbol'], $item['position_size'], $item['entry_price'])) {
                continue;
            }

            $alerts[] = [
                'user' => $item['user'],
                'symbol' => $item['symbol'],
                'position_size' => (float) $item['position_size'],
                'position_type' => $item['position_size'] > 0 ? 'Long' : 'Short',
                'entry_price' => (float) $item['entry_price'],
                'liq_price' => (float) ($item['liq_price'] ?? 0),
                'position_value_usd' => (float) ($item['position_value_usd'] ?? 0),
                'position_action' => ($item['position_action'] ?? 1) == 1 ? 'Open' : 'Close',
                'create_time' => $item['create_time'] ?? time() * 1000,
                'formatted_time' => date('Y-m-d H:i:s', ($item['create_time'] ?? time() * 1000) / 1000)
            ];
        }

        // Calculate aggregate stats
        $btcAlerts = array_filter($alerts, fn($a) => $a['symbol'] === 'BTC');
        $totalValue = array_sum(array_column($btcAlerts, 'position_value_usd'));
        $longCount = count(array_filter($btcAlerts, fn($a) => $a['position_type'] === 'Long'));
        $shortCount = count($btcAlerts) - $longCount;

        return [
            'success' => true,
            'data' => [
                'alerts' => $alerts,
                'aggregate' => [
                    'total_alerts' => count($alerts),
                    'btc_alerts' => count($btcAlerts),
                    'total_value_usd' => $totalValue,
                    'long_count' => $longCount,
                    'short_count' => $shortCount,
                    'long_short_ratio' => $shortCount > 0 ? $longCount / $shortCount : $longCount
                ],
                'timestamp' => time()
            ]
        ];
    }

    /**
     * Normalize Whale Transfers response
     */
    private function normalizeWhaleTransfers($raw): array
    {
        if (!is_array($raw) || (isset($raw['success']) && $raw['success'] === false)) {
            return [
                'success' => false,
                'error' => $raw['error'] ?? ['code' => 500, 'message' => 'Unknown error']
            ];
        }

        $data = $raw['data'] ?? [];
        $transfers = [];

        foreach ($data as $item) {
            if (!isset($item['transaction_hash'])) {
                continue;
            }

            $transfers[] = [
                'transaction_hash' => $item['transaction_hash'],
                'amount_usd' => isset($item['amount_usd']) ? (float) $item['amount_usd'] : 0,
                'asset_quantity' => isset($item['asset_quantity']) ? (float) $item['asset_quantity'] : 0,
                'asset_symbol' => $item['asset_symbol'] ?? 'N/A',
                'from' => $item['from'] ?? 'unknown',
                'to' => $item['to'] ?? 'unknown',
                'blockchain_name' => $item['blockchain_name'] ?? 'N/A',
                'block_height' => $item['block_height'] ?? 0,
                'block_timestamp' => $item['block_timestamp'] ?? time(),
                'formatted_time' => date('Y-m-d H:i:s', $item['block_timestamp'] ?? time())
            ];
        }

        // Calculate aggregate stats
        $totalValueUsd = array_sum(array_column($transfers, 'amount_usd'));
        $symbols = array_unique(array_column($transfers, 'asset_symbol'));
        
        // Classify transfers by direction (to/from exchanges)
        $toExchange = count(array_filter($transfers, fn($t) => 
            !in_array($t['to'], ['unknown', 'unknown wallet']) && 
            preg_match('/(binance|coinbase|kraken|okx|bitfinex|huobi|bybit|gate|kucoin)/i', $t['to'])
        ));
        $fromExchange = count(array_filter($transfers, fn($t) => 
            !in_array($t['from'], ['unknown', 'unknown wallet']) && 
            preg_match('/(binance|coinbase|kraken|okx|bitfinex|huobi|bybit|gate|kucoin)/i', $t['from'])
        ));

        return [
            'success' => true,
            'data' => [
                'transfers' => $transfers,
                'aggregate' => [
                    'total_transfers' => count($transfers),
                    'total_value_usd' => $totalValueUsd,
                    'unique_symbols' => count($symbols),
                    'symbols' => $symbols,
                    'to_exchange' => $toExchange,
                    'from_exchange' => $fromExchange,
                    'net_flow' => $toExchange - $fromExchange // Positive = more to exchanges (bearish?)
                ],
                'timestamp' => time()
            ]
        ];
    }

    /**
     * Get sentiment label from fear & greed value
     */
    private function getSentimentLabel($value): string
    {
        if ($value >= 80) return 'Extreme Greed';
        if ($value >= 60) return 'Greed';
        if ($value >= 40) return 'Neutral';
        if ($value >= 20) return 'Fear';
        return 'Extreme Fear';
    }

    /**
     * Calculate annualized funding rate
     */
    private function calculateAnnualizedRate($rate, $intervalHours): float
    {
        // Funding rate per interval * (number of intervals per year)
        $intervalsPerYear = (365 * 24) / $intervalHours;
        return $rate * $intervalsPerYear * 100; // Convert to percentage
    }
}
