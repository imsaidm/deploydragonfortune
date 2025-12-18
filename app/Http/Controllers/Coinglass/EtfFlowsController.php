<?php

namespace App\Http\Controllers\Coinglass;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\CoinglassClient;

class EtfFlowsController extends Controller
{
    private CoinglassClient $client;
    private int $cacheTtlSeconds;

    public function __construct(CoinglassClient $client)
    {
        $this->client = $client;
        $this->cacheTtlSeconds = (int) env('COINGLASS_ETF_CACHE_TTL', 30);
    }

    // ========================================================================
    // ETF FLOWS HISTORY
    // ========================================================================
    
    // GET /api/coinglass/etf-flows/history
    public function flowHistory(Request $request)
    {
        return $this->fetchAndNormalize(
            endpoint: '/etf/bitcoin/flow-history',
            cacheKey: 'coinglass:etf:flows:history',
            normalizer: 'normalizeFlowHistory'
        );
    }

    // ========================================================================
    // ETF LIST (Real-time data for all Bitcoin ETFs)
    // ========================================================================
    
    // GET /api/coinglass/etf-flows/list
    public function etfList(Request $request)
    {
        return $this->fetchAndNormalize(
            endpoint: '/etf/bitcoin/list',
            cacheKey: 'coinglass:etf:list',
            normalizer: 'normalizeEtfList',
            cacheTtl: 5 // 5 minutes for real-time data
        );
    }

    // ========================================================================
    // PREMIUM/DISCOUNT HISTORY
    // ========================================================================
    
    // GET /api/coinglass/etf-flows/premium-discount?ticker=GBTC
    public function premiumDiscountHistory(Request $request)
    {
        $ticker = $request->input('ticker', 'GBTC');
        
        return $this->fetchAndNormalize(
            endpoint: '/etf/bitcoin/premium-discount/history',
            cacheKey: "coinglass:etf:premium-discount:{$ticker}",
            normalizer: 'normalizePremiumDiscount',
            queryParams: ['ticker' => $ticker],
            cacheTtl: 15 // 15 minutes
        );
    }

    // ========================================================================
    // CME FUTURES OPEN INTEREST
    // ========================================================================
    
    // GET /api/coinglass/etf-flows/cme-oi
    public function cmeOpenInterest(Request $request)
    {
        $symbol = $request->input('symbol', 'BTC');
        $interval = $request->input('interval', '1d');
        $limit = $request->input('limit', 30);
        
        return $this->fetchAndNormalize(
            endpoint: '/futures/open-interest/aggregated-stablecoin-history',
            cacheKey: "coinglass:cme:oi:{$symbol}:{$interval}:{$limit}",
            normalizer: 'normalizeCMEOpenInterest',
            queryParams: [
                'exchange_list' => 'CME',
                'symbol' => $symbol,
                'interval' => $interval,
                'limit' => $limit
            ],
            cacheTtl: 60 // 60 seconds cache for CME data
        );
    }

    // ========================================================================
    // PER-ETF FLOW BREAKDOWN (from flow-history data)
    // ========================================================================
    
    // GET /api/coinglass/etf-flows/breakdown
    public function flowBreakdown(Request $request)
    {
        $cacheKey = 'coinglass:etf:flows:breakdown';

        try {
            $data = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () {
                $flowData = $this->callCoinglassApi('/etf/bitcoin/flow-history');
                return $this->normalizeFlowBreakdown($flowData);
            });

            return response()->json($data);

        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // ========================================================================
    // GENERIC API CALLER (Reusable for all endpoints)
    // ========================================================================
    
    private function fetchAndNormalize(
        string $endpoint,
        string $cacheKey,
        string $normalizer,
        array $queryParams = [],
        int $cacheTtl = null
    ) {
        $ttl = $cacheTtl ?? $this->cacheTtlSeconds;
        $backupKey = $cacheKey . ':backup';

        try {
            $raw = Cache::remember($cacheKey, $ttl, function () use ($endpoint, $queryParams, $backupKey, $ttl) {
                $response = $this->callCoinglassApi($endpoint, $queryParams);
                // Keep a longer-lived backup in case subsequent calls fail
                Cache::put($backupKey, $response, max($ttl * 12, 300));
                return $response;
            });

            $normalized = $this->$normalizer($raw);
            return response()->json($normalized);

        } catch (\Exception $e) {
            $fallbackRaw = Cache::get($backupKey);
            if ($fallbackRaw) {
                Log::warning('ETF API fallback using stale cache', [
                    'endpoint' => $endpoint,
                    'params' => $queryParams,
                    'error' => $e->getMessage()
                ]);

                $normalized = $this->$normalizer($fallbackRaw);
                $normalized['stale'] = true;

                return response()->json($normalized);
            }

            return $this->errorResponse($e);
        }
    }

    private function callCoinglassApi(string $endpoint, array $queryParams = []): array
    {
        Log::info('ETF API Call', [
            'endpoint' => $endpoint,
            'params' => $queryParams,
            'has_key' => !empty(env('COINGLASS_API_KEY')),
        ]);

        $data = $this->client->get($endpoint, $queryParams);

        if (is_array($data) && ($data['success'] ?? true) === false) {
            $message = $data['error']['message'] ?? 'Coinglass API request failed';
            throw new \Exception($message);
        }

        return $data;
    }

    private function errorResponse(\Exception $e)
    {
        Log::error('ETF API Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => 500
                ]
            ], 500);
        }

    // ========================================================================
    // NORMALIZERS (One for each endpoint)
    // ========================================================================

    private function normalizeFlowHistory($raw): array
    {
        if (!is_array($raw) || (isset($raw['success']) && $raw['success'] === false)) {
            return ['success' => false, 'error' => $raw['error'] ?? ['code' => 500, 'message' => 'Unknown error']];
        }

        $rows = $raw['data'] ?? ($raw['result'] ?? []);
        $data = [];
        
        foreach ($rows as $row) {
            $ts = $row['timestamp'] ?? $row['time'] ?? $row['ts'] ?? null;
            $flowUsd = $row['flow_usd'] ?? $row['net_flow'] ?? null;
            $etfFlows = $row['etf_flows'] ?? [];
            
            if ($ts === null || $flowUsd === null) {
                continue;
            }
            
            $data[] = [
                'ts' => (int) $ts,
                'flow_usd' => (float) $flowUsd,
                'etf_flows' => $etfFlows,
                'date' => date('Y-m-d', $ts / 1000),
            ];
        }

        return ['success' => true, 'data' => $data];
    }

    private function normalizeEtfList($raw): array
    {
        if (!is_array($raw) || !isset($raw['data'])) {
            return ['success' => false, 'error' => ['message' => 'Invalid response']];
        }

        $etfs = [];
        foreach ($raw['data'] as $etf) {
            $etfs[] = [
                'ticker' => $etf['ticker'] ?? '',
                'fund_name' => $etf['fund_name'] ?? '',
                'fund_type' => $etf['fund_type'] ?? '',
                'region' => $etf['region'] ?? '',
                'market_status' => $etf['market_status'] ?? '',
                
                // Financial metrics
                'aum_usd' => (float) ($etf['aum_usd'] ?? 0),
                'market_cap_usd' => (float) ($etf['market_cap_usd'] ?? 0),
                'management_fee_percent' => (float) ($etf['management_fee_percent'] ?? 0),
                
                // Holdings
                'holding_quantity' => (float) ($etf['asset_details']['holding_quantity'] ?? 0),
                'change_quantity_24h' => (float) ($etf['asset_details']['change_quantity_24h'] ?? 0),
                'change_percent_24h' => (float) ($etf['asset_details']['change_percent_24h'] ?? 0),
                'change_quantity_7d' => (float) ($etf['asset_details']['change_quantity_7d'] ?? 0),
                'change_percent_7d' => (float) ($etf['asset_details']['change_percent_7d'] ?? 0),
                
                // Premium/Discount
                'premium_discount_percent' => (float) ($etf['asset_details']['premium_discount_percent'] ?? 0),
                'premium_discount_bps' => (float) (($etf['asset_details']['premium_discount_percent'] ?? 0) * 100),
                
                // Trading
                'price_usd' => (float) ($etf['price_usd'] ?? 0),
                'price_change_percent' => (float) ($etf['price_change_percent'] ?? 0),
                'volume_usd' => (float) ($etf['volume_usd'] ?? 0),
                
                // Metadata
                'list_date' => $etf['list_date'] ?? null,
                'update_timestamp' => $etf['update_timestamp'] ?? null,
                'update_date' => isset($etf['update_timestamp']) 
                    ? date('Y-m-d', $etf['update_timestamp'] / 1000) 
                    : null,
            ];
        }

        // Sort by AUM descending
        usort($etfs, function($a, $b) {
            return $b['aum_usd'] <=> $a['aum_usd'];
        });

        return ['success' => true, 'data' => $etfs];
    }

    private function normalizePremiumDiscount($raw): array
    {
        if (!is_array($raw) || !isset($raw['data'])) {
            return ['success' => false, 'error' => ['message' => 'Invalid response']];
        }

        $data = [];
        foreach ($raw['data'] as $row) {
            $ts = $row['timestamp'] ?? null;
            if (!$ts) continue;

            $premiumDiscountPercent = (float) ($row['premium_discount_details'] ?? 0);
            
            $data[] = [
                'ts' => (int) $ts,
                'date' => date('Y-m-d', $ts / 1000),
                'nav_usd' => (float) ($row['nav_usd'] ?? 0),
                'market_price_usd' => (float) ($row['market_price_usd'] ?? 0),
                'premium_discount_percent' => $premiumDiscountPercent,
                'premium_discount_bps' => $premiumDiscountPercent * 100, // Convert % to bps
            ];
        }

        return ['success' => true, 'data' => $data];
    }

    private function normalizeFlowBreakdown($raw): array
    {
        if (!is_array($raw) || !isset($raw['data'])) {
            return ['success' => false, 'error' => ['message' => 'Invalid response']];
        }

        // Aggregate flows by ETF ticker
        $etfTotals = [];
        $latestDate = null;
        $latestFlows = [];

        foreach ($raw['data'] as $row) {
            $ts = $row['timestamp'] ?? null;
            $etfFlows = $row['etf_flows'] ?? [];
            
            if (!$ts || empty($etfFlows)) continue;

            $date = date('Y-m-d', $ts / 1000);
            
            // Track latest date
            if (!$latestDate || $ts > strtotime($latestDate) * 1000) {
                $latestDate = $date;
                $latestFlows = $etfFlows;
            }

            foreach ($etfFlows as $etf) {
                $ticker = $etf['etf_ticker'] ?? '';
                $flow = (float) ($etf['flow_usd'] ?? 0);
                
                if (!$ticker) continue;

                if (!isset($etfTotals[$ticker])) {
                    $etfTotals[$ticker] = [
                        'ticker' => $ticker,
                        'total_inflow' => 0,
                        'total_outflow' => 0,
                        'net_flow' => 0,
                        'days_count' => 0,
                        'latest_flow' => 0,
                        'latest_date' => null,
                    ];
                }

                $etfTotals[$ticker]['net_flow'] += $flow;
                $etfTotals[$ticker]['days_count']++;
                
                if ($flow > 0) {
                    $etfTotals[$ticker]['total_inflow'] += $flow;
                } else {
                    $etfTotals[$ticker]['total_outflow'] += abs($flow);
                }
            }
        }

        // Add latest flow data
        foreach ($latestFlows as $etf) {
            $ticker = $etf['etf_ticker'] ?? '';
            $flow = (float) ($etf['flow_usd'] ?? 0);
            
            if ($ticker && isset($etfTotals[$ticker])) {
                $etfTotals[$ticker]['latest_flow'] = $flow;
                $etfTotals[$ticker]['latest_date'] = $latestDate;
            }
        }

        // Convert to array and sort by net flow
        $breakdown = array_values($etfTotals);
        usort($breakdown, function($a, $b) {
            return $b['net_flow'] <=> $a['net_flow'];
        });

        return [
            'success' => true,
            'data' => $breakdown,
            'latest_date' => $latestDate
        ];
    }

    private function normalizeCMEOpenInterest($raw): array
    {
        if (!is_array($raw) || !isset($raw['data'])) {
            return ['success' => false, 'error' => ['message' => 'Invalid response']];
        }

        $data = [];
        foreach ($raw['data'] as $row) {
            $time = $row['time'] ?? null;
            if (!$time) continue;

            $data[] = [
                'ts' => (int) $time,
                'date' => date('Y-m-d', $time / 1000),
                'open' => (float) ($row['open'] ?? 0),
                'high' => (float) ($row['high'] ?? 0),
                'low' => (float) ($row['low'] ?? 0),
                'close' => (float) ($row['close'] ?? 0),
            ];
        }

        // Sort by timestamp ascending (oldest first)
        usort($data, function($a, $b) {
            return $a['ts'] <=> $b['ts'];
        });

        // Calculate change metrics
        $latest = end($data);
        $previous = count($data) > 1 ? $data[count($data) - 2] : null;
        
        $change = 0;
        $changePercent = 0;
        
        if ($previous && $previous['close'] > 0) {
            $change = $latest['close'] - $previous['close'];
            $changePercent = ($change / $previous['close']) * 100;
        }

        return [
            'success' => true,
            'data' => $data,
            'summary' => [
                'latest_oi' => $latest['close'] ?? 0,
                'change' => $change,
                'change_percent' => $changePercent,
                'latest_date' => $latest['date'] ?? null,
            ]
        ];
    }
}

