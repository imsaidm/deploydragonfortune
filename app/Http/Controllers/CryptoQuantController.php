<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CryptoQuantController extends Controller
{
    private $apiKey;
    private string $baseUrl = 'https://api.cryptoquant.com/v1';

    public function __construct()
    {
        // NOTE: Do not call env() here (breaks when config is cached). Use config() only.
        $this->apiKey = config('services.cryptoquant.key', '');
        $this->baseUrl = rtrim((string) config('services.cryptoquant.base_url', $this->baseUrl), '/');
    }

    /**
     * Get Bitcoin Market Price from CryptoQuant API
     */
    public function getBitcoinPrice(Request $request)
    {
        try {
            $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));
            
            // Add buffer to get more recent data (extend end date by 1 day)
            $endDateWithBuffer = \Carbon\Carbon::parse($endDate)->addDay()->format('Y-m-d');
            
            // Convert date format from YYYY-MM-DD to YYYYMMDD for CryptoQuant API
            $fromDate = str_replace('-', '', $startDate);
            $toDate = str_replace('-', '', $endDateWithBuffer);
            
            // Calculate limit based on date range with buffer
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDateWithBuffer);
            $daysDiff = $start->diffInDays($end) + 1;
            $limit = max($daysDiff + 5, 100); // Add 5 extra days buffer
            
            // Try multiple CryptoQuant price endpoints
            $endpoints = [
                // Market price USD
                [
                    'url' => "{$this->baseUrl}/btc/market-data/price-ohlcv",
                    'params' => [
                        'window' => 'day',
                        'market' => 'spot',
                        'exchange' => 'all_exchange',
                        'symbol' => 'btc_usd',
                        'from' => $fromDate,
                        'to' => $toDate,
                        'limit' => $limit
                    ]
                ],
                // Alternative: realized price
                [
                    'url' => "{$this->baseUrl}/btc/market-indicator/realized-price",
                    'params' => [
                        'window' => 'day',
                        'from' => $fromDate,
                        'to' => $toDate,
                        'limit' => $limit
                    ]
                ]
            ];
            
            foreach ($endpoints as $endpoint) {
                Log::info('Trying CryptoQuant Bitcoin Price', [
                    'url' => $endpoint['url'],
                    'params' => $endpoint['params']
                ]);
                
                $response = Http::timeout(30)->withOptions([
                    'verify' => false, // Disable SSL verification for local development
                ])->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Accept' => 'application/json',
                ])->get($endpoint['url'], $endpoint['params']);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    Log::info('CryptoQuant Bitcoin Price Success', [
                        'endpoint' => $endpoint['url'],
                        'data_count' => isset($data['result']['data']) ? count($data['result']['data']) : 0
                    ]);
                    
                    $transformedData = [];
                    if (isset($data['result']['data']) && is_array($data['result']['data'])) {
                        $startTimestamp = strtotime($startDate);
                        $endTimestamp = strtotime($endDate);
                        
                        foreach ($data['result']['data'] as $item) {
                            $itemDate = $item['date'] ?? null;
                            if ($itemDate) {
                                $itemTimestamp = strtotime($itemDate);
                                if ($itemTimestamp >= $startTimestamp && $itemTimestamp <= $endTimestamp) {
                                    // Handle different data structures
                                    $price = $item['close'] ?? $item['value'] ?? $item['price'] ?? 0;
                                    
                                    $transformedData[] = [
                                        'date' => $itemDate,
                                        'value' => $price,
                                        'close' => $item['close'] ?? $price,
                                        'open' => $item['open'] ?? $price,
                                        'high' => $item['high'] ?? $price,
                                        'low' => $item['low'] ?? $price,
                                        'volume' => $item['volume'] ?? 0
                                    ];
                                }
                            }
                        }
                        
                        // Sort by date ascending
                        usort($transformedData, function($a, $b) {
                            return strtotime($a['date']) - strtotime($b['date']);
                        });
                    }
                    
                    if (!empty($transformedData)) {
                        return response()->json([
                            'success' => true,
                            'data' => $transformedData,
                            'meta' => [
                                'start_date' => $startDate,
                                'end_date' => $endDate,
                                'count' => count($transformedData),
                                'source' => 'CryptoQuant Bitcoin Price',
                                'endpoint' => $endpoint['url']
                            ]
                        ]);
                    }
                }
                
                Log::warning('CryptoQuant Bitcoin Price endpoint failed', [
                    'url' => $endpoint['url'],
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
            
            // All endpoints failed
            return response()->json([
                'success' => false,
                'error' => 'All CryptoQuant Bitcoin price endpoints failed',
                'message' => 'Unable to fetch Bitcoin price data'
            ], 503);

        } catch (\Exception $e) {
            Log::error('CryptoQuant Bitcoin Price Exception: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Exchange Inflow CDD data from CryptoQuant API
     * Using CryptoQuant Professional Package - no historical data limitations
     */
    public function getExchangeInflowCDD(Request $request)
    {
        try {
            // Default to 30 days (1 month) to match frontend default and ensure MA30 calculation
            $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d')); // Default to today
            
            // No limitations - using CryptoQuant Professional Package
            
            // Convert date format from YYYY-MM-DD to YYYYMMDD for CryptoQuant API
            $fromDate = str_replace('-', '', $startDate);
            $toDate = str_replace('-', '', $endDate);
            
            // Calculate limit based on date range
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);
            $daysDiff = $start->diffInDays($end) + 1;
            $limit = max($daysDiff, 30); // Minimum 30 for better data coverage
            
            // Get exchange and interval parameters from request
            $requestedExchange = $request->input('exchange', 'binance');
            $interval = $request->input('interval', '1d');
            
            // Convert interval to CryptoQuant window format
            $window = $this->convertIntervalToWindow($interval);
            
            $url = "{$this->baseUrl}/btc/flow-indicator/exchange-inflow-cdd";
            
            // Use CryptoQuant native all_exchange endpoint or single exchange
            Log::info('CryptoQuant CDD Request', [
                'url' => $url,
                'exchange' => $requestedExchange,
                'interval' => $interval,
                'window' => $window,
                'from' => $fromDate,
                'to' => $toDate,
                'limit' => $limit,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'note' => $requestedExchange === 'all_exchange' 
                    ? 'Using CryptoQuant native all_exchange endpoint' 
                    : 'Single exchange data'
            ]);
            
            $transformedData = $this->fetchSingleExchangeCDD($url, $requestedExchange, $window, $fromDate, $toDate, $limit, $startDate, $endDate);
            $actualExchange = $requestedExchange;
            
            // Calculate advanced metrics (Z-Score, MA7, MA30, MA Cross Signal)
            $metrics = $this->calculateCDDMetrics($transformedData);
                
            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'metrics' => $metrics, // Pre-calculated metrics from backend
                'meta' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'exchange' => $actualExchange,
                    'requested_exchange' => $requestedExchange,
                    'count' => count($transformedData),
                    'source' => 'CryptoQuant Exchange Inflow CDD',
                    'latest_data_date' => !empty($transformedData) ? end($transformedData)['date'] : null,
                    'is_native_all_exchange' => $requestedExchange === 'all_exchange',
                    'note' => $requestedExchange === 'all_exchange' 
                        ? 'CryptoQuant native all_exchange endpoint (complete aggregation)' 
                        : 'Single exchange data'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('CryptoQuant API Exception: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Funding Rates data from CryptoQuant
     */
    public function getFundingRates(Request $request)
    {
        try {
            $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));
            $exchange = $request->input('exchange', 'binance');
            
            // Add buffer to get more recent data
            $endDateWithBuffer = \Carbon\Carbon::parse($endDate)->addDay()->format('Y-m-d');
            
            // Convert date format from YYYY-MM-DD to YYYYMMDD for CryptoQuant API
            $fromDate = str_replace('-', '', $startDate);
            $toDate = str_replace('-', '', $endDateWithBuffer);
            
            // Calculate limit based on date range with buffer
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDateWithBuffer);
            $daysDiff = $start->diffInDays($end) + 1;
            $limit = max($daysDiff + 5, 100);
            
            $url = "{$this->baseUrl}/btc/market-data/funding-rates";
            
            Log::info('CryptoQuant Funding Rates Request', [
                'url' => $url,
                'exchange' => $exchange,
                'from' => $fromDate,
                'to' => $toDate,
                'limit' => $limit
            ]);
            
            // Prepare API parameters
            $params = [
                'window' => 'day',
                'from' => $fromDate,
                'to' => $toDate,
                'limit' => $limit
            ];
            
            // Only add exchange parameter if it's not 'all_exchange'
            if ($exchange !== 'all_exchange') {
                $params['exchange'] = $exchange;
            }
            
            $response = Http::timeout(30)->withOptions([
                'verify' => false, // Disable SSL verification for local development
            ])->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
            ])->get($url, $params);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('CryptoQuant Funding Rates Success', [
                    'exchange' => $exchange,
                    'data_count' => isset($data['result']['data']) ? count($data['result']['data']) : 0
                ]);
                
                $transformedData = [];
                if (isset($data['result']['data']) && is_array($data['result']['data'])) {
                    $startTimestamp = strtotime($startDate);
                    $endTimestamp = strtotime($endDate);
                    
                    foreach ($data['result']['data'] as $item) {
                        $itemDate = $item['date'] ?? null;
                        if ($itemDate) {
                            $itemTimestamp = strtotime($itemDate);
                            if ($itemTimestamp >= $startTimestamp && $itemTimestamp <= $endTimestamp) {
                                $fundingRate = $item['funding_rates'] ?? null;
                                
                                // Handle null values (keep data in decimal format)
                                if ($fundingRate !== null) {
                                    $fundingRatePercent = $fundingRate; // Keep as decimal
                                } else {
                                    $fundingRatePercent = null;
                                }
                                
                                $transformedData[] = [
                                    'date' => $itemDate,
                                    'value' => $fundingRatePercent,
                                    'funding_rate' => $fundingRate,
                                    'exchange' => $exchange
                                ];
                            }
                        }
                    }
                    
                    // Sort by date ascending
                    usort($transformedData, function($a, $b) {
                        return strtotime($a['date']) - strtotime($b['date']);
                    });
                }
                
                return response()->json([
                    'success' => true,
                    'data' => $transformedData,
                    'meta' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'exchange' => $exchange,
                        'count' => count($transformedData),
                        'source' => 'CryptoQuant Funding Rates',
                        'latest_data_date' => !empty($transformedData) ? end($transformedData)['date'] : null
                    ]
                ]);
            }

            Log::error('CryptoQuant Funding Rates API Failed', [
                'exchange' => $exchange,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch funding rates from CryptoQuant API',
                'status' => $response->status(),
                'message' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('CryptoQuant Funding Rates Exception: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Open Interest data from CryptoQuant API
     */
    public function getOpenInterest(Request $request)
    {
        try {
            $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));
            $exchange = $request->input('exchange', 'binance');
            $symbol = $request->input('symbol', 'btc_usdt');
            $window = $request->input('window', 'day');
            
            // Add buffer to get more recent data
            $endDateWithBuffer = \Carbon\Carbon::parse($endDate)->addDay()->format('Y-m-d');
            
            // Convert date format from YYYY-MM-DD to YYYYMMDD for CryptoQuant API
            $fromDate = str_replace('-', '', $startDate);
            $toDate = str_replace('-', '', $endDateWithBuffer);
            
            // Calculate limit based on date range with buffer
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDateWithBuffer);
            $daysDiff = $start->diffInDays($end) + 1;
            $limit = max($daysDiff + 5, 100);
            
            $url = "{$this->baseUrl}/btc/market-data/open-interest";
            
            Log::info('CryptoQuant Open Interest Request', [
                'url' => $url,
                'exchange' => $exchange,
                'symbol' => $symbol,
                'window' => $window,
                'from' => $fromDate,
                'to' => $toDate,
                'limit' => $limit
            ]);
            
            // Prepare API parameters
            $params = [
                'window' => $window,
                'from' => $fromDate,
                'to' => $toDate,
                'limit' => $limit
            ];
            
            // Only add exchange parameter if it's not 'all_exchange'
            if ($exchange !== 'all_exchange') {
                $params['exchange'] = $exchange;
            }
            
            // Only add symbol parameter if it's not 'all_symbol' and exchange supports it
            if ($symbol !== 'all_symbol' && in_array($exchange, ['binance', 'bybit'])) {
                $params['symbol'] = $symbol;
            }
            
            $response = Http::timeout(30)->withOptions([
                'verify' => false, // Disable SSL verification for local development
            ])->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
            ])->get($url, $params);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('CryptoQuant Open Interest Success', [
                    'exchange' => $exchange,
                    'data_count' => isset($data['result']['data']) ? count($data['result']['data']) : 0
                ]);
                
                $transformedData = [];
                if (isset($data['result']['data']) && is_array($data['result']['data'])) {
                    $startTimestamp = strtotime($startDate);
                    $endTimestamp = strtotime($endDate);
                    
                    foreach ($data['result']['data'] as $item) {
                        $itemDate = $item['date'] ?? null;
                        if ($itemDate) {
                            $itemTimestamp = strtotime($itemDate);
                            if ($itemTimestamp >= $startTimestamp && $itemTimestamp <= $endTimestamp) {
                                $openInterest = $item['open_interest'] ?? null;
                                
                                $transformedData[] = [
                                    'date' => $itemDate,
                                    'value' => $openInterest,
                                    'open_interest' => $openInterest,
                                    'exchange' => $exchange
                                ];
                            }
                        }
                    }
                    
                    // Sort by date ascending
                    usort($transformedData, function($a, $b) {
                        return strtotime($a['date']) - strtotime($b['date']);
                    });
                }
                
                return response()->json([
                    'success' => true,
                    'data' => $transformedData,
                    'meta' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'exchange' => $exchange,
                        'symbol' => $symbol,
                        'count' => count($transformedData),
                        'source' => 'CryptoQuant Open Interest',
                        'latest_data_date' => !empty($transformedData) ? end($transformedData)['date'] : null
                    ]
                ]);
            }

            Log::error('CryptoQuant Open Interest API Failed', [
                'exchange' => $exchange,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch open interest from CryptoQuant API',
                'status' => $response->status(),
                'message' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('CryptoQuant Open Interest Exception: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get multiple exchanges funding rates for comparison
     */
    public function getFundingRatesComparison(Request $request)
    {
        try {
            $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));
            $exchanges = $request->input('exchanges', ['binance', 'bybit', 'bitmex', 'okx']);
            
            if (is_string($exchanges)) {
                $exchanges = explode(',', $exchanges);
            }
            
            // Add buffer to get more recent data
            $endDateWithBuffer = \Carbon\Carbon::parse($endDate)->addDay()->format('Y-m-d');
            
            // Convert date format
            $fromDate = str_replace('-', '', $startDate);
            $toDate = str_replace('-', '', $endDateWithBuffer);
            
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDateWithBuffer);
            $daysDiff = $start->diffInDays($end) + 1;
            $limit = max($daysDiff + 5, 100);
            
            $allData = [];
            $errors = [];
            
            // Fetch data for each exchange
            foreach ($exchanges as $exchange) {
                try {
                    $url = "{$this->baseUrl}/btc/market-data/funding-rates";
                    
                    $response = Http::timeout(15)->withOptions([
                        'verify' => false, // Disable SSL verification for local development
                    ])->withHeaders([
                        'Authorization' => "Bearer {$this->apiKey}",
                        'Accept' => 'application/json',
                    ])->get($url, [
                        'exchange' => $exchange,
                        'window' => 'day',
                        'from' => $fromDate,
                        'to' => $toDate,
                        'limit' => $limit
                    ]);
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        if (isset($data['result']['data'])) {
                            $transformedData = [];
                            $startTimestamp = strtotime($startDate);
                            $endTimestamp = strtotime($endDate);
                            
                            foreach ($data['result']['data'] as $item) {
                                $itemDate = $item['date'] ?? null;
                                if ($itemDate) {
                                    $itemTimestamp = strtotime($itemDate);
                                    if ($itemTimestamp >= $startTimestamp && $itemTimestamp <= $endTimestamp) {
                                        $fundingRate = $item['funding_rates'] ?? null;
                                        
                                        if ($fundingRate !== null) {
                                            $fundingRatePercent = $fundingRate * 100;
                                        } else {
                                            $fundingRatePercent = null;
                                        }
                                        
                                        $transformedData[] = [
                                            'date' => $itemDate,
                                            'value' => $fundingRatePercent,
                                            'funding_rate' => $fundingRate,
                                            'exchange' => $exchange
                                        ];
                                    }
                                }
                            }
                            
                            // Sort by date
                            usort($transformedData, function($a, $b) {
                                return strtotime($a['date']) - strtotime($b['date']);
                            });
                            
                            $allData[$exchange] = $transformedData;
                        }
                    } else {
                        $errors[$exchange] = 'API request failed: ' . $response->status();
                    }
                } catch (\Exception $e) {
                    $errors[$exchange] = $e->getMessage();
                }
            }
            
            if (empty($allData)) {
                throw new \Exception('No data available from any exchange');
            }
            
            Log::info('CryptoQuant Funding Rates Comparison Success', [
                'exchanges_success' => array_keys($allData),
                'exchanges_failed' => array_keys($errors)
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $allData,
                'meta' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'exchanges_success' => array_keys($allData),
                    'exchanges_failed' => array_keys($errors),
                    'source' => 'CryptoQuant Funding Rates Comparison',
                    'errors' => $errors
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('CryptoQuant Funding Rates Comparison Exception: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch funding rates comparison data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert interval format to CryptoQuant window format
     * 
     * NOTE: Current CryptoQuant plan only supports DAILY data
     * - window=day → ✅ Available
     * - window=hour → ❌ 403 Forbidden
     * - window=week → ❌ 403 Forbidden
     * 
     * All intervals fallback to 'day' to prevent 403 errors
     */
    private function convertIntervalToWindow($interval)
    {
        // Force 'day' for all intervals due to API plan limitations
        return 'day';
        
        /* Original mapping (disabled - requires higher tier plan):
        $mapping = [
            '1h' => 'hour',
            '4h' => 'hour',
            '1d' => 'day',
            '1w' => 'week'
        ];
        return $mapping[$interval] ?? 'day';
        */
    }

    /**
     * Calculate advanced metrics for CDD data
     * Includes: Z-Score, MA7, MA30, MA Cross Signal
     * 
     * @param array $data Array of CDD data with 'value' field
     * @return array Calculated metrics
     */
    private function calculateCDDMetrics($data)
    {
        if (empty($data)) {
            return [
                'zScore' => null,
                'ma7' => null,
                'ma30' => null,
                'maCrossSignal' => 'neutral'
            ];
        }

        // Extract values
        $values = array_map(function($item) {
            return floatval($item['value'] ?? 0);
        }, $data);

        $count = count($values);
        if ($count === 0) {
            return [
                'zScore' => null,
                'ma7' => null,
                'ma30' => null,
                'maCrossSignal' => 'neutral'
            ];
        }

        // Basic statistics
        $currentCDD = $values[$count - 1];
        $avgCDD = array_sum($values) / $count;

        // Calculate Standard Deviation
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $avgCDD, 2);
        }
        $variance = $variance / $count;
        $stdDev = sqrt($variance);

        // Z-Score calculation (anomaly detection)
        $zScore = null;
        if ($stdDev > 0) {
            $zScore = ($currentCDD - $avgCDD) / $stdDev;
            $zScore = round($zScore, 2);
        }

        // Moving Averages
        $ma7 = null;
        if ($count >= 7) {
            $last7 = array_slice($values, -7);
            $ma7 = array_sum($last7) / 7;
        }

        $ma30 = null;
        if ($count >= 30) {
            $last30 = array_slice($values, -30);
            $ma30 = array_sum($last30) / 30;
        }

        // MA Cross Signal
        // For CDD: MA7 > MA30 = pressure increasing (WARNING/bearish for price)
        // For CDD: MA7 < MA30 = pressure decreasing (SAFE/bullish for price)
        $maCrossSignal = 'neutral';
        if ($ma7 !== null && $ma30 !== null && $ma30 > 0) {
            $crossPct = (($ma7 - $ma30) / $ma30) * 100;
            if ($crossPct > 5) {
                $maCrossSignal = 'warning'; // MA7 significantly above MA30 = distribution increasing
            } elseif ($crossPct < -5) {
                $maCrossSignal = 'safe'; // MA7 significantly below MA30 = distribution decreasing
            }
        }

        return [
            'zScore' => $zScore,
            'ma7' => $ma7,
            'ma30' => $ma30,
            'maCrossSignal' => $maCrossSignal
        ];
    }

    /**
     * Fetch CDD data from single exchange
     */
    private function fetchSingleExchangeCDD($url, $exchange, $window, $fromDate, $toDate, $limit, $startDate, $endDate)
    {
        $params = [
            'exchange' => $exchange,
            'window' => $window,
            'from' => $fromDate,
            'to' => $toDate,
            'limit' => $limit
        ];
        
        $response = Http::timeout(30)->withOptions([
            'verify' => false, // Disable SSL verification for local development
        ])->withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Accept' => 'application/json',
        ])->get($url, $params);

        if (!$response->successful()) {
            Log::error('CryptoQuant Single Exchange CDD API Failed', [
                'exchange' => $exchange,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return [];
        }

        $data = $response->json();
        $transformedData = [];
        
        if (isset($data['result']['data']) && is_array($data['result']['data'])) {
            $startTimestamp = strtotime($startDate);
            $endTimestamp = strtotime($endDate);
            
            foreach ($data['result']['data'] as $item) {
                $itemDate = $item['date'] ?? null;
                if ($itemDate) {
                    $itemTimestamp = strtotime($itemDate);
                    if ($itemTimestamp >= $startTimestamp && $itemTimestamp <= $endTimestamp) {
                        $transformedData[] = [
                            'date' => $itemDate,
                            'value' => $item['inflow_cdd'] ?? 0
                        ];
                    }
                }
            }
            
            // Sort by date ascending
            usort($transformedData, function($a, $b) {
                return strtotime($a['date']) - strtotime($b['date']);
            });
        }
        
        return $transformedData;
    }

    /**
     * Fetch and aggregate CDD data from multiple exchanges
     */
    private function fetchAndAggregateAllExchangesCDD($url, $fromDate, $toDate, $limit, $startDate, $endDate)
    {
        // Major exchanges for CDD aggregation
        $exchanges = ['binance', 'coinbase', 'kraken', 'bitfinex', 'huobi'];
        $allExchangeData = [];
        $successfulExchanges = [];
        
        foreach ($exchanges as $exchange) {
            try {
                Log::info("Fetching CDD data from {$exchange}");
                
                $exchangeData = $this->fetchSingleExchangeCDD($url, $exchange, $fromDate, $toDate, $limit, $startDate, $endDate);
                
                if (!empty($exchangeData)) {
                    $allExchangeData[$exchange] = $exchangeData;
                    $successfulExchanges[] = $exchange;
                    Log::info("Successfully fetched {$exchange} CDD data", ['count' => count($exchangeData)]);
                } else {
                    Log::warning("No data from {$exchange}");
                }
                
            } catch (\Exception $e) {
                Log::error("Failed to fetch CDD data from {$exchange}", ['error' => $e->getMessage()]);
            }
        }
        
        if (empty($allExchangeData)) {
            Log::error('No CDD data available from any exchange');
            return [];
        }
        
        // Aggregate data by date
        $aggregatedData = $this->aggregateCDDByDate($allExchangeData, $successfulExchanges);
        
        Log::info('CDD All Exchanges Aggregation Complete', [
            'successful_exchanges' => $successfulExchanges,
            'total_data_points' => count($aggregatedData),
            'exchanges_count' => count($successfulExchanges)
        ]);
        
        return $aggregatedData;
    }

    /**
     * Aggregate CDD data by date across multiple exchanges
     */
    private function aggregateCDDByDate($allExchangeData, $successfulExchanges)
    {
        $dateMap = [];
        
        // Collect all data points by date
        foreach ($allExchangeData as $exchange => $data) {
            foreach ($data as $item) {
                $date = $item['date'];
                if (!isset($dateMap[$date])) {
                    $dateMap[$date] = [];
                }
                $dateMap[$date][$exchange] = $item['value'];
            }
        }
        
        // Calculate aggregated values for each date
        $aggregatedData = [];
        foreach ($dateMap as $date => $exchangeValues) {
            // Sum all exchange values for this date
            $totalCDD = array_sum($exchangeValues);
            $exchangeCount = count($exchangeValues);
            
            $aggregatedData[] = [
                'date' => $date,
                'value' => $totalCDD, // Total CDD across all exchanges
                'exchange_count' => $exchangeCount,
                'exchanges' => array_keys($exchangeValues)
            ];
        }
        
        // Sort by date ascending
        usort($aggregatedData, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        return $aggregatedData;
    }
}
