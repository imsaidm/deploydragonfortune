<?php

namespace App\Http\Controllers\Coinglass;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\CoinglassClient;

class MacroOverlayController extends Controller
{
    private CoinglassClient $client;
    private string $fredApiKey;
    private string $fredBaseUrl;
    private int $cacheTtlMinutes;

    public function __construct(CoinglassClient $client)
    {
        $this->client = $client;
        $this->fredApiKey = (string) config('services.fred.key', '');
        $this->fredBaseUrl = (string) config('services.fred.base_url', 'https://api.stlouisfed.org/fred/series/observations');
        $this->cacheTtlMinutes = (int) config('services.coinglass.cache_ttl_minutes', 15);
    }

    // ========================================================================
    // FRED MACRO INDICATORS
    // ========================================================================
    
    /**
     * Get multiple FRED series data in one call
     * GET /api/coinglass/macro-overlay/fred
     * Query params: series_ids (comma-separated), limit, sort_order
     */
    public function fredMultiSeries(Request $request)
    {
        $seriesIds = $request->input('series_ids', 'DTWEXBGS,DGS10,DGS2,DFF');
        $limit = $request->input('limit', 100);
        $sortOrder = $request->input('sort_order', 'desc');
        
        $seriesArray = explode(',', $seriesIds);
        $cacheKey = "fred:multi:" . md5($seriesIds . $limit . $sortOrder);
        
        try {
            $data = Cache::remember($cacheKey, $this->cacheTtlMinutes * 60, function () use ($seriesArray, $limit, $sortOrder) {
                $results = [];
                
                foreach ($seriesArray as $seriesId) {
                    $seriesId = trim($seriesId);
                    try {
                        $response = $this->callFredApi($seriesId, [
                            'limit' => $limit,
                            'sort_order' => $sortOrder,
                            'file_type' => 'json'
                        ]);
                        
                        $results[$seriesId] = $this->normalizeFredSeries($response, $seriesId);
                    } catch (\Exception $e) {
                        Log::warning("FRED API error for series {$seriesId}: " . $e->getMessage());
                        $results[$seriesId] = [
                            'success' => false,
                            'error' => $e->getMessage(),
                            'series_id' => $seriesId
                        ];
                    }
                }
                
                return $results;
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'cached_at' => now()->toIso8601String()
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Get single FRED series data
     * GET /api/coinglass/macro-overlay/fred/{seriesId}
     */
    public function fredSingleSeries(Request $request, string $seriesId)
    {
        $limit = $request->input('limit', 100);
        $sortOrder = $request->input('sort_order', 'desc');
        $observationStart = $request->input('observation_start');
        $observationEnd = $request->input('observation_end');
        
        $cacheKey = "fred:single:{$seriesId}:" . md5($limit . $sortOrder . $observationStart . $observationEnd);
        
        try {
            $data = Cache::remember($cacheKey, $this->cacheTtlMinutes * 60, function () use ($seriesId, $limit, $sortOrder, $observationStart, $observationEnd) {
                $params = [
                    'limit' => $limit,
                    'sort_order' => $sortOrder,
                    'file_type' => 'json'
                ];
                
                if ($observationStart) {
                    $params['observation_start'] = $observationStart;
                }
                
                if ($observationEnd) {
                    $params['observation_end'] = $observationEnd;
                }
                
                $response = $this->callFredApi($seriesId, $params);
                return $this->normalizeFredSeries($response, $seriesId);
            });

            return response()->json($data);

        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * Get latest values for all major macro indicators
     * GET /api/coinglass/macro-overlay/fred/latest
     */
    public function fredLatest(Request $request)
    {
        $cacheKey = "fred:latest:all";
        
        try {
            $data = Cache::remember($cacheKey, 5 * 60, function () { // 5 minute cache for latest data
                $indicators = [
                    'DTWEXBGS' => 'DXY (Dollar Index)',
                    'DGS10' => '10Y Treasury Yield',
                    'DGS2' => '2Y Treasury Yield',
                    'DFF' => 'Fed Funds Rate',
                    'CPIAUCSL' => 'CPI (Inflation)',
                    'M2SL' => 'M2 Money Supply',
                    'RRPONTSYD' => 'Reverse Repo',
                    'WTREGEN' => 'Treasury General Account'
                ];
                
                $results = [];
                
                foreach ($indicators as $seriesId => $label) {
                    try {
                        $response = $this->callFredApi($seriesId, [
                            'limit' => 1,
                            'sort_order' => 'desc',
                            'file_type' => 'json'
                        ]);
                        
                        if (isset($response['observations'][0])) {
                            $obs = $response['observations'][0];
                            $results[$seriesId] = [
                                'label' => $label,
                                'series_id' => $seriesId,
                                'date' => $obs['date'] ?? null,
                                'value' => $this->parseValue($obs['value'] ?? null),
                                'value_raw' => $obs['value'] ?? null,
                                'units' => $response['units'] ?? 'lin'
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::warning("FRED latest error for {$seriesId}: " . $e->getMessage());
                        $results[$seriesId] = [
                            'label' => $label,
                            'series_id' => $seriesId,
                            'error' => $e->getMessage()
                        ];
                    }
                }
                
                return $results;
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'cached_at' => now()->toIso8601String()
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // ========================================================================
    // COINGLASS BITCOIN VS M2
    // ========================================================================
    
    /**
     * Get Bitcoin vs Global M2 data
     * GET /api/coinglass/macro-overlay/bitcoin-m2
     * 
     * Note: Endpoint does not require any query parameters
     */
    public function bitcoinVsM2(Request $request)
    {
        $cacheKey = 'coinglass:bitcoin-m2';

        try {
            $data = Cache::remember($cacheKey, 60 * 60, function () { // 1 hour cache
                // Fetch global M2 supply & growth data (no parameters needed)
                $raw = $this->callCoinglassApi('/index/bitcoin-vs-global-m2-growth', []);
                return $this->normalizeBitcoinM2($raw);
            });

            return response()->json($data);

        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // ========================================================================
    // API CALLERS
    // ========================================================================

    /**
     * Call FRED API
     */
    private function callFredApi(string $seriesId, array $params = []): array
    {
        $params['series_id'] = $seriesId;
        $params['api_key'] = $this->fredApiKey ?: 'demo';
        
        Log::info('FRED API Call', [
            'series_id' => $seriesId,
            'params' => $params
        ]);

        $http = Http::timeout(30);

        // Allow local/staging without strict SSL
        if (! app()->environment('production')) {
            $http = $http->withOptions(['verify' => false]);
        }

        // If no API key, skip call and return empty
        if (empty($this->fredApiKey) && app()->environment('production')) {
            throw new \Exception('FRED_API_KEY not configured');
        }

        $response = $http->get($this->fredBaseUrl, $params);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('FRED API call failed: ' . $response->status() . ' - ' . $response->body());
    }

    /**
     * Call Coinglass API
     */
    private function callCoinglassApi(string $endpoint, array $queryParams = []): array
    {
        $apiKey = config('services.coinglass.key');

        Log::info('Coinglass Macro API Call', [
            'endpoint' => $endpoint,
            'params' => $queryParams,
            'has_key' => !empty($apiKey)
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
        Log::error('Macro Overlay API Error', [
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
    // NORMALIZERS
    // ========================================================================

    /**
     * Normalize FRED series data
     */
    private function normalizeFredSeries($raw, string $seriesId): array
    {
        if (!is_array($raw) || !isset($raw['observations'])) {
            return [
                'success' => false,
                'error' => 'Invalid FRED API response',
                'series_id' => $seriesId
            ];
        }

        $data = [];
        
        foreach ($raw['observations'] as $obs) {
            $value = $this->parseValue($obs['value'] ?? null);
            
            if ($value === null) {
                continue; // Skip missing data points
            }
            
            $data[] = [
                'date' => $obs['date'] ?? null,
                'value' => $value,
                'value_raw' => $obs['value'] ?? null,
                'realtime_start' => $obs['realtime_start'] ?? null,
                'realtime_end' => $obs['realtime_end'] ?? null
            ];
        }

        return [
            'success' => true,
            'series_id' => $seriesId,
            'data' => $data,
            'count' => count($data),
            'units' => $raw['units'] ?? 'lin',
            'frequency' => $raw['frequency'] ?? null,
            'observation_start' => $raw['observation_start'] ?? null,
            'observation_end' => $raw['observation_end'] ?? null
        ];
    }

    /**
     * Normalize Bitcoin vs M2 data
     * Response format: { code: "0", data: [{ timestamp, price, global_m2_yoy_growth, global_m2_supply }] }
     */
    private function normalizeBitcoinM2($raw): array
    {
        if (!is_array($raw)) {
            return ['success' => false, 'error' => 'Invalid API response'];
        }

        // Handle Coinglass response format
        $code = $raw['code'] ?? null;
        $data = $raw['data'] ?? [];

        if ($code !== '0' && $code !== 0) {
            return [
                'success' => false,
                'error' => 'API returned error code: ' . $code
            ];
        }

        $normalized = [];
        
        foreach ($data as $item) {
            // API returns: timestamp, price, global_m2_yoy_growth, global_m2_supply
            $timestamp = $item['timestamp'] ?? null;
            
            if (!$timestamp) continue;
            
            $supply = (float) ($item['global_m2_supply'] ?? 0);
            $supplyTrillions = $supply > 0 ? $supply / 1_000_000_000_000 : null;
            $price = (float) ($item['price'] ?? 0);
            $ratio = $supplyTrillions ? ($price / $supplyTrillions) : null;
            
            $normalized[] = [
                'time' => (int) $timestamp,
                'date' => date('Y-m-d', $timestamp / 1000),
                'price' => $price,
                'global_m2_yoy_growth' => (float) ($item['global_m2_yoy_growth'] ?? 0),
                'global_m2_supply' => $supply,
                'global_m2_supply_trillions' => $supplyTrillions,
                'ratio' => $ratio
            ];
        }

        return [
            'success' => true,
            'data' => $normalized,
            'count' => count($normalized)
        ];
    }

    /**
     * Parse FRED value (handle ".", "", and numeric strings)
     */
    private function parseValue($value)
    {
        if ($value === null || $value === '' || $value === '.') {
            return null;
        }
        
        return (float) $value;
    }
}
