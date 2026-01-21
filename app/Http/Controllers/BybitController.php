<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Client\ConnectionException;

class BybitController extends Controller
{
    /**
     * Get Bybit account summary with balances
     */
    public function summary(Request $request)
    {
        $config = $this->getBybitConfig($request);
        
        if ($config['api_key'] === '' || $config['api_secret'] === '') {
            return response()->json([
                'success' => false,
                'error' => 'Bybit API credentials not configured.',
                'hint' => 'Please set api_key and secret_key in qc_method table for this method.',
                'credential_source' => $config['credential_source'],
            ], 400);
        }

        $baseUrl = $config['base_url'];
        $apiKey = $config['api_key'];
        $apiSecret = $config['api_secret'];
        $timeout = $config['timeout'];
        $recvWindow = $config['recv_window'];

        $http = $this->buildHttpClient($timeout);

        // Get wallet balance (Unified Trading Account)
        try {
            $timestamp = $this->getTimestampMs();
            $params = [
                'accountType' => 'UNIFIED', // or 'SPOT' for spot-only
            ];
            
            $queryString = http_build_query($params);
            $signature = $this->generateSignature($timestamp, $apiKey, $recvWindow, $queryString, $apiSecret);
            
            $accountRes = $http
                ->withHeaders([
                    'X-BAPI-API-KEY' => $apiKey,
                    'X-BAPI-TIMESTAMP' => $timestamp,
                    'X-BAPI-RECV-WINDOW' => $recvWindow,
                    'X-BAPI-SIGN' => $signature,
                ])
                ->get($baseUrl . '/v5/account/wallet-balance?' . $queryString);

        } catch (ConnectionException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unable to connect to Bybit API from this network.',
                'hint' => 'Check your network connection or try using a VPN.',
                'base_url' => $baseUrl,
            ], 503);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unexpected error while calling Bybit API.',
                'message' => $e->getMessage(),
                'base_url' => $baseUrl,
            ], 500);
        }

        if (!$accountRes->ok()) {
            $body = $accountRes->json();
            $rawBody = $accountRes->body(); // Get raw response for debugging
            return response()->json([
                'success' => false,
                'error' => 'Bybit API error: ' . ($body['retMsg'] ?? 'Unknown error'),
                'code' => $body['retCode'] ?? null,
                'base_url' => $baseUrl,
                'debug' => [
                    'http_status' => $accountRes->status(),
                    'raw_response' => $rawBody,
                    'parsed_body' => $body,
                ],
            ], $accountRes->status() ?: 400);
        }

        $account = $accountRes->json();
        
        if (!is_array($account) || ($account['retCode'] ?? 0) !== 0) {
            return response()->json([
                'success' => false,
                'error' => 'Bybit API error: ' . ($account['retMsg'] ?? 'Invalid response'),
                'code' => $account['retCode'] ?? null,
                'base_url' => $baseUrl,
                'debug' => [
                    'is_array' => is_array($account),
                    'retCode' => $account['retCode'] ?? 'missing',
                    'full_response' => $account,
                ],
            ], 400);
        }

        // Parse wallet data
        $walletList = $account['result']['list'] ?? [];
        if (empty($walletList)) {
            return response()->json([
                'success' => true,
                'base_url' => $baseUrl,
                'account' => [
                    'type' => 'UNIFIED',
                    'label' => $config['label'],
                ],
                'summary' => [
                    'total_usdt' => 0,
                    'available_usdt' => 0,
                    'locked_usdt' => 0,
                    'btc_value' => null,
                    'asset_count' => 0,
                    'updated_at' => now()->toDateTimeString(),
                ],
                'assets' => [],
            ]);
        }

        $wallet = $walletList[0]; // First wallet (UNIFIED account)
        $coins = $wallet['coin'] ?? [];
        
        $totalUsdt = (float) ($wallet['totalEquity'] ?? 0);
        $availableUsdt = (float) ($wallet['totalAvailableBalance'] ?? 0);
        $lockedUsdt = $totalUsdt - $availableUsdt;

        // Get BTC price for conversion
        $btcPrice = $this->getBtcPrice($http, $baseUrl);
        $btcValue = $btcPrice && $btcPrice > 0 ? $totalUsdt / $btcPrice : null;

        $assets = [];
        foreach ($coins as $coin) {
            $asset = strtoupper((string) ($coin['coin'] ?? ''));
            $walletBalance = (float) ($coin['walletBalance'] ?? 0);
            $availableToWithdraw = (float) ($coin['availableToWithdraw'] ?? 0);
            $locked = $walletBalance - $availableToWithdraw;
            $usdValue = (float) ($coin['usdValue'] ?? 0);

            if ($walletBalance <= 0) {
                continue;
            }

            $assets[] = [
                'asset' => $asset,
                'free' => $availableToWithdraw,
                'locked' => $locked,
                'total' => $walletBalance,
                'value_usdt' => $usdValue,
            ];
        }

        return response()->json([
            'success' => true,
            'base_url' => $baseUrl,
            'account' => [
                'type' => 'UNIFIED',
                'label' => $config['label'],
            ],
            'summary' => [
                'total_usdt' => round($totalUsdt, 6),
                'available_usdt' => round($availableUsdt, 6),
                'locked_usdt' => round($lockedUsdt, 6),
                'btc_value' => $btcValue !== null ? round($btcValue, 8) : null,
                'asset_count' => count($assets),
                'updated_at' => now()->toDateTimeString(),
            ],
            'assets' => $assets,
        ]);
    }

    /**
     * Get open orders for a symbol
     */
    public function openOrders(Request $request)
    {
        $symbol = strtoupper(trim((string) $request->query('symbol', 'BTCUSDT')));
        $category = $request->query('category', 'spot'); // spot, linear, inverse
        
        return $this->signedGet($request, '/v5/order/realtime', [
            'category' => $category,
            'symbol' => $symbol !== '' ? $symbol : null,
        ]);
    }

    /**
     * Get order history
     */
    public function orders(Request $request)
    {
        $symbol = strtoupper(trim((string) $request->query('symbol', 'BTCUSDT')));
        $category = $request->query('category', 'spot');
        $limit = (int) $request->query('limit', 50);
        
        if ($limit < 1) $limit = 1;
        if ($limit > 50) $limit = 50;

        return $this->signedGet($request, '/v5/order/history', [
            'category' => $category,
            'symbol' => $symbol !== '' ? $symbol : null,
            'limit' => $limit,
        ]);
    }

    /**
     * Get trade history
     */
    public function trades(Request $request)
    {
        $symbol = strtoupper(trim((string) $request->query('symbol', 'BTCUSDT')));
        $category = $request->query('category', 'spot');
        $limit = (int) $request->query('limit', 50);
        
        if ($limit < 1) $limit = 1;
        if ($limit > 50) $limit = 50;

        return $this->signedGet($request, '/v5/execution/list', [
            'category' => $category,
            'symbol' => $symbol !== '' ? $symbol : null,
            'limit' => $limit,
        ]);
    }

    /**
     * Get positions (for futures/derivatives)
     */
    public function positions(Request $request)
    {
        $symbol = strtoupper(trim((string) $request->query('symbol', '')));
        $category = $request->query('category', 'linear'); // linear, inverse
        
        return $this->signedGet($request, '/v5/position/list', [
            'category' => $category,
            'symbol' => $symbol !== '' ? $symbol : null,
        ]);
    }

    /**
     * Generic signed GET request
     */
    private function signedGet(Request $request, string $path, array $params)
    {
        $config = $this->getBybitConfig($request);
        
        if ($config['api_key'] === '' || $config['api_secret'] === '') {
            return response()->json([
                'success' => false,
                'error' => 'Bybit API credentials not configured.',
                'hint' => 'Please set api_key and secret_key in qc_method table for this method.',
            ], 400);
        }

        $baseUrl = $config['base_url'];
        $apiKey = $config['api_key'];
        $apiSecret = $config['api_secret'];
        $timeout = $config['timeout'];
        $recvWindow = $config['recv_window'];

        $http = $this->buildHttpClient($timeout);

        try {
            $timestamp = $this->getTimestampMs();
            
            // Filter out null/empty params
            $queryParams = array_filter($params, fn($v) => $v !== null && $v !== '');
            $queryString = http_build_query($queryParams);
            
            $signature = $this->generateSignature($timestamp, $apiKey, $recvWindow, $queryString, $apiSecret);
            
            $url = $baseUrl . $path . ($queryString ? '?' . $queryString : '');
            
            $res = $http
                ->withHeaders([
                    'X-BAPI-API-KEY' => $apiKey,
                    'X-BAPI-TIMESTAMP' => $timestamp,
                    'X-BAPI-RECV-WINDOW' => $recvWindow,
                    'X-BAPI-SIGN' => $signature,
                ])
                ->get($url);

        } catch (ConnectionException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unable to connect to Bybit API.',
                'base_url' => $baseUrl,
            ], 503);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unexpected error while calling Bybit API.',
                'message' => $e->getMessage(),
            ], 500);
        }

        $body = $res->json();
        
        if (!$res->ok() || ($body['retCode'] ?? 0) !== 0) {
            return response()->json([
                'success' => false,
                'error' => 'Bybit API error: ' . ($body['retMsg'] ?? 'Unknown error'),
                'code' => $body['retCode'] ?? null,
                'base_url' => $baseUrl,
            ], $res->status() ?: 400);
        }

        return response()->json([
            'success' => true,
            'base_url' => $baseUrl,
            'data' => $body['result'] ?? $body,
        ]);
    }

    /**
     * Generate Bybit API signature
     * Signature = HMAC_SHA256(timestamp + apiKey + recvWindow + queryString, apiSecret)
     */
    private function generateSignature(int $timestamp, string $apiKey, int $recvWindow, string $queryString, string $apiSecret): string
    {
        $signaturePayload = $timestamp . $apiKey . $recvWindow . $queryString;
        return hash_hmac('sha256', $signaturePayload, $apiSecret);
    }

    /**
     * Get current timestamp in milliseconds
     */
    private function getTimestampMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    /**
     * Get BTC price from Bybit
     */
    private function getBtcPrice($http, string $baseUrl): ?float
    {
        $cacheKey = 'bybit:btc:price:' . md5($baseUrl);
        $cached = Cache::get($cacheKey);
        if (is_numeric($cached)) {
            return (float) $cached;
        }

        try {
            $res = $http->get($baseUrl . '/v5/market/tickers?category=spot&symbol=BTCUSDT');
            if (!$res->ok()) {
                return null;
            }
            
            $data = $res->json();
            if (($data['retCode'] ?? 0) !== 0) {
                return null;
            }
            
            $list = $data['result']['list'] ?? [];
            if (empty($list)) {
                return null;
            }
            
            $price = (float) ($list[0]['lastPrice'] ?? 0);
            if ($price > 0) {
                Cache::put($cacheKey, $price, now()->addSeconds(30));
                return $price;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * Get Bybit configuration from database or env
     */
    private function getBybitConfig(?Request $request = null): array
    {
        $clean = fn ($value) => trim((string) $value, " \t\n\r\0\x0B\"'");

        // Default config from env (fallback)
        $baseUrl = rtrim($clean(env('BYBIT_BASE_URL', 'https://api.bybit.com')), '/');
        $label = $clean(env('BYBIT_LABEL', 'Bybit'));
        $apiKey = '';
        $apiSecret = '';
        $credentialSource = 'env';

        // Try to get credentials from database based on method_id
        $methodId = $this->selectedMethodId($request);
        if ($methodId !== null) {
            $resolved = $this->resolveMethodBybitCredentials($methodId);
            if (($resolved['found'] ?? false) === true) {
                $apiKey = $clean($resolved['api_key'] ?? '');
                $apiSecret = $clean($resolved['api_secret'] ?? '');
                $credentialSource = (string) ($resolved['source'] ?? 'method');
            } else {
                $credentialSource = 'method_missing';
            }
        }

        return [
            'base_url' => $baseUrl,
            'label' => $label,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'credential_source' => $credentialSource,
            'timeout' => (int) env('BYBIT_TIMEOUT', 10),
            'recv_window' => (int) env('BYBIT_RECV_WINDOW', 5000),
        ];
    }

    /**
     * Extract method_id from request
     */
    private function selectedMethodId(?Request $request): ?int
    {
        if (!$request) {
            return null;
        }

        $raw = $request->query('method_id')
            ?? $request->query('id_method')
            ?? $request->query('methodId');

        $id = is_numeric($raw) ? (int) $raw : null;
        return $id !== null && $id > 0 ? $id : null;
    }

    /**
     * Resolve Bybit credentials from test.dragonfortune.ai API
     */
    private function resolveMethodBybitCredentials(int $methodId): array
    {
        // Try API first (production pattern)
        $fromApi = $this->resolveMethodBybitCredentialsFromApi($methodId);
        if (($fromApi['found'] ?? false) === true) {
            return $fromApi;
        }

        // Fallback to database if API fails
        $fromDb = $this->resolveMethodBybitCredentialsFromDatabase($methodId);
        if (($fromDb['found'] ?? false) === true) {
            return $fromDb;
        }

        return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'none'];
    }

    /**
     * Resolve Bybit credentials from test.dragonfortune.ai API
     */
    private function resolveMethodBybitCredentialsFromApi(int $methodId): array
    {
        $apiBaseUrl = (string) config('services.qc_api.base_url', '');
        if (trim($apiBaseUrl) === '') {
            return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'api'];
        }

        try {
            $url = rtrim($apiBaseUrl, '/') . '/api/methods/' . $methodId;
            $res = Http::timeout(5)->get($url);
            
            if (!$res->ok()) {
                return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'api'];
            }

            $data = $res->json();
            if (!is_array($data)) {
                return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'api'];
            }

            // Check if this method is for Bybit exchange
            $exchange = strtolower(trim((string) ($data['exchange'] ?? '')));
            if ($exchange !== 'bybit') {
                return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'wrong_exchange'];
            }

            $apiKey = trim((string) ($data['api_key'] ?? ''));
            $apiSecret = trim((string) ($data['secret_key'] ?? $data['api_secret'] ?? ''));
            $found = $apiKey !== '' && $apiSecret !== '';

            return [
                'found' => $found,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'source' => 'api',
            ];
        } catch (\Throwable $e) {
            return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'api'];
        }
    }

    /**
     * Resolve Bybit credentials from qc_method table (fallback)
     */
    private function resolveMethodBybitCredentialsFromDatabase(int $methodId): array
    {
        $tables = ['qc_method', 'qc_methods'];
        $idColumns = ['id', 'id_method', 'method_id'];

        foreach ($this->methodCredentialDbConnections() as $connection) {
            foreach ($tables as $table) {
                try {
                    $hasTable = $connection !== null
                        ? Schema::connection($connection)->hasTable($table)
                        : Schema::hasTable($table);
                } catch (\Throwable $e) {
                    continue;
                }

                if (!$hasTable) {
                    continue;
                }

                $row = null;
                foreach ($idColumns as $column) {
                    try {
                        $hasIdColumn = $connection !== null
                            ? Schema::connection($connection)->hasColumn($table, $column)
                            : Schema::hasColumn($table, $column);
                    } catch (\Throwable $e) {
                        continue;
                    }

                    if (!$hasIdColumn) {
                        continue;
                    }

                    try {
                        $row = ($connection !== null ? DB::connection($connection) : DB::connection())
                            ->table($table)
                            ->where($column, $methodId)
                            ->first();
                    } catch (\Throwable $e) {
                        $row = null;
                    }

                    if ($row) {
                        break;
                    }
                }

                if (!$row) {
                    continue;
                }

                // Check if this method is for Bybit exchange
                $exchange = strtolower(trim((string) ($row->exchange ?? '')));
                if ($exchange !== 'bybit') {
                    return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'wrong_exchange'];
                }

                $apiKey = trim((string) ($row->api_key ?? ''));
                $apiSecret = trim((string) ($row->secret_key ?? $row->api_secret ?? ''));
                $found = $apiKey !== '' && $apiSecret !== '';

                return [
                    'found' => $found,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                    'source' => $connection !== null ? 'db:' . $connection : 'db',
                ];
            }
        }

        return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'db'];
    }

    /**
     * Get database connections to check for method credentials
     */
    private function methodCredentialDbConnections(): array
    {
        $connections = [null]; // Default connection

        // Check if methods database is configured
        $methodsDb = (string) config('database.connections.methods.database', '');
        $methodsHost = (string) config('database.connections.methods.host', '');
        if (trim($methodsDb) !== '' && trim($methodsHost) !== '') {
            $connections[] = 'methods';
        }

        return $connections;
    }

    /**
     * Build HTTP client
     */
    private function buildHttpClient(int $timeout)
    {
        $http = Http::timeout($timeout)
            ->connectTimeout(min(5, max(1, $timeout)))
            ->withoutRedirecting()
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'Dragonfortune/1.0 (+laravel)',
            ]);
        
        // Disable SSL verification if needed (for VPN/WARP compatibility)
        if (env('BYBIT_VERIFY_SSL', true) === false) {
            $http = $http->withoutVerifying();
        }
        
        return $http;
    }
}
