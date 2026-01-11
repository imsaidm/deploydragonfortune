<?php

namespace App\Http\Controllers;

use App\Models\MasterExchange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class MasterExchangeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $exchanges = MasterExchange::withCount('tradingMethods')
                ->orderBy('created_at', 'desc')
                ->get();
            return response()->json(['data' => $exchanges]);
        }
        return view('master-exchanges.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:master_exchanges,name',
            'exchange_type' => 'required|in:BINANCE,BYBIT,OKX',
            'market_type' => 'required|in:SPOT,FUTURES',
            'api_key' => 'required|string',
            'secret_key' => 'required|string',
            'testnet' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $exchange = MasterExchange::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Exchange account created successfully',
                'data' => $exchange
            ], 201);
        } catch (\Exception $e) {
            Log::error('Master Exchange Creation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create exchange account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $exchange = MasterExchange::withCount('tradingMethods')->findOrFail($id);
            
            // Mask credentials for security
            $exchange->api_key = $exchange->api_key ? '***ENCRYPTED***' : null;
            $exchange->secret_key = $exchange->secret_key ? '***ENCRYPTED***' : null;
            
            return response()->json([
                'success' => true,
                'data' => $exchange
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exchange account not found'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $exchange = MasterExchange::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:master_exchanges,name,' . $id,
            'exchange_type' => 'required|in:BINANCE,BYBIT,OKX',
            'market_type' => 'required|in:SPOT,FUTURES',
            'api_key' => 'nullable|string',
            'secret_key' => 'nullable|string',
            'testnet' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Don't update keys if they're masked placeholders
            $data = $request->except(['api_key', 'secret_key']);
            
            if ($request->api_key && $request->api_key !== '***ENCRYPTED***') {
                $data['api_key'] = $request->api_key;
            }
            
            if ($request->secret_key && $request->secret_key !== '***ENCRYPTED***') {
                $data['secret_key'] = $request->secret_key;
            }
            
            $exchange->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Exchange account updated successfully',
                'data' => $exchange
            ]);
        } catch (\Exception $e) {
            Log::error('Master Exchange Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update exchange account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $exchange = MasterExchange::findOrFail($id);
            
            // Check if any methods are using this exchange
            $methodsCount = $exchange->tradingMethods()->count();
            if ($methodsCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete: {$methodsCount} trading method(s) are using this exchange"
                ], 400);
            }
            
            $exchange->delete();

            return response()->json([
                'success' => true,
                'message' => 'Exchange account deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Master Exchange Delete Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete exchange account'
            ], 500);
        }
    }

    /**
     * Test API connection
     */
    public function testConnection($id)
    {
        try {
            $exchange = MasterExchange::findOrFail($id);
            
            if (!$exchange->isBinance()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Binance is supported currently'
                ], 400);
            }
            
            $client = new Client([
                'timeout' => 10,
                'verify' => !app()->isLocal(), // Disable SSL verification in local environment
            ]);
            $timestamp = round(microtime(true) * 1000);
            
            $queryString = "timestamp={$timestamp}";
            $signature = hash_hmac('sha256', $queryString, $exchange->secret_key);
            
            $baseUrl = $exchange->getApiBaseUrl();
            
            // Use different endpoints for SPOT vs FUTURES
            $endpoint = $exchange->market_type === 'SPOT' 
                ? '/api/v3/account' 
                : '/fapi/v2/account';
            
            $response = $client->get("{$baseUrl}{$endpoint}", [
                'query' => [
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                ],
                'headers' => [
                    'X-MBX-APIKEY' => $exchange->api_key,
                ],
            ]);
            
            $rawBody = $response->getBody()->getContents();
            $data = json_decode($rawBody, true);
            
            // Log response for debugging
            Log::info('Binance API Response', [
                'exchange_id' => $exchange->id,
                'market_type' => $exchange->market_type,
                'endpoint' => $endpoint,
                'status' => $response->getStatusCode(),
                'raw_body' => substr($rawBody, 0, 500), // First 500 chars
                'data' => $data,
                'json_error' => json_last_error_msg(),
            ]);
            
            // Check for API errors in response
            if (isset($data['code']) && isset($data['msg'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Binance API Error: ' . $data['msg'],
                    'code' => $data['code'],
                ], 400);
            }
            
            // Update last validated timestamp
            $exchange->last_validated_at = now();
            $exchange->save();
            
            // Different response structure for SPOT vs FUTURES
            if ($exchange->market_type === 'SPOT') {
                // Calculate total balance in USDT for SPOT
                $balances = $data['balances'] ?? [];
                $totalUSDT = 0;
                
                foreach ($balances as $balance) {
                    $asset = $balance['asset'] ?? '';
                    $free = floatval($balance['free'] ?? 0);
                    $locked = floatval($balance['locked'] ?? 0);
                    $total = $free + $locked;
                    
                    if ($total > 0) {
                        if ($asset === 'USDT') {
                            $totalUSDT += $total;
                        }
                        // For other assets, we'd need price conversion
                        // For now, just count USDT
                    }
                }
                
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful! API credentials are valid.',
                    'data' => [
                        'canTrade' => $data['canTrade'] ?? false,
                        'canDeposit' => $data['canDeposit'] ?? false,
                        'canWithdraw' => $data['canWithdraw'] ?? false,
                        'totalWalletBalance' => $totalUSDT,
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful! API credentials are valid.',
                    'data' => [
                        'canTrade' => $data['canTrade'] ?? false,
                        'canDeposit' => $data['canDeposit'] ?? false,
                        'canWithdraw' => $data['canWithdraw'] ?? false,
                        'totalWalletBalance' => $data['totalWalletBalance'] ?? 0,
                    ]
                ]);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = json_decode($e->getResponse()->getBody(), true);
            
            return response()->json([
                'success' => false,
                'message' => 'API Error: ' . ($body['msg'] ?? 'Invalid credentials'),
                'code' => $statusCode
            ], 400);
        } catch (\Exception $e) {
            Log::error('Test Connection Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get wallet balance
     */
    public function getBalance($id)
    {
        try {
            $exchange = MasterExchange::findOrFail($id);
            
            if (!$exchange->isBinance()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Binance is supported currently'
                ], 400);
            }
            
            $client = new Client([
                'timeout' => 10,
                'verify' => !app()->isLocal(), // Disable SSL verification in local environment
            ]);
            $timestamp = round(microtime(true) * 1000);
            
            $queryString = "timestamp={$timestamp}";
            $signature = hash_hmac('sha256', $queryString, $exchange->secret_key);
            
            $baseUrl = $exchange->getApiBaseUrl();
            
            // Use different endpoints for SPOT vs FUTURES
            $endpoint = $exchange->market_type === 'SPOT' 
                ? '/api/v3/account' 
                : '/fapi/v2/balance';
            
            $response = $client->get("{$baseUrl}{$endpoint}", [
                'query' => [
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                ],
                'headers' => [
                    'X-MBX-APIKEY' => $exchange->api_key,
                ],
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            // Validate response data
            if (!is_array($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid response from Binance API'
                ], 500);
            }
            
            // Different response structure for SPOT vs FUTURES
            if ($exchange->market_type === 'SPOT') {
                // SPOT returns account info with balances array
                $balances = $data['balances'] ?? [];
                
                if (!is_array($balances)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid balance data from Binance SPOT API'
                    ], 500);
                }
                
                $nonZeroBalances = array_filter($balances, function($balance) {
                    return isset($balance['free'], $balance['locked']) && 
                           (floatval($balance['free']) > 0 || floatval($balance['locked']) > 0);
                });
                
                // Format to match FUTURES structure
                $formatted = array_map(function($balance) {
                    return [
                        'asset' => $balance['asset'] ?? 'UNKNOWN',
                        'balance' => floatval($balance['free'] ?? 0) + floatval($balance['locked'] ?? 0),
                        'availableBalance' => floatval($balance['free'] ?? 0),
                    ];
                }, $nonZeroBalances);
                
                return response()->json([
                    'success' => true,
                    'data' => array_values($formatted)
                ]);
            } else {
                // FUTURES returns balance array directly
                if (!is_array($data)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid balance data from Binance FUTURES API'
                    ], 500);
                }
                
                $nonZeroBalances = array_filter($data, function($balance) {
                    return isset($balance['balance']) && floatval($balance['balance']) > 0;
                });
                
                return response()->json([
                    'success' => true,
                    'data' => array_values($nonZeroBalances)
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Get Balance Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch balance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle active status
     */
    public function toggleActive($id)
    {
        try {
            $exchange = MasterExchange::findOrFail($id);
            $exchange->is_active = !$exchange->is_active;
            $exchange->save();

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'is_active' => $exchange->is_active
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status'
            ], 500);
        }
    }
}
