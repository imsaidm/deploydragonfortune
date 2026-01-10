<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Client\ConnectionException;

class BinanceFuturesController extends Controller
{
    public function summary(Request $request)
    {
        $futures = $this->getFuturesConfig($request);
        if ($this->shouldForceStub($request) || $this->isStubMode($futures)) {
            return $this->stubSummary($futures);
        }
        if ($this->shouldProxy($request, $futures)) {
            return $this->proxyFutures($request, $futures, '/summary');
        }

        $baseUrl = $futures['base_url'];
        $apiKey = $futures['api_key'];
        $apiSecret = $futures['api_secret'];
        $timeout = $futures['timeout'];
        $recvWindow = $futures['recv_window'];
        $verify = $futures['verify_ssl'];

        if ($apiKey === '' || $apiSecret === '') {
            return $this->unconfiguredSummary($request, $futures);
        }

        $http = $this->buildHttpClient($timeout, $verify);
        $accountRes = null;

        try {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $timestamp = $this->getSignedTimestampMs($http, $baseUrl);
                $query = http_build_query([
                    'timestamp' => $timestamp,
                    'recvWindow' => $recvWindow,
                ]);
                $signature = hash_hmac('sha256', $query, $apiSecret);
                $accountUrl = $baseUrl . '/fapi/v2/account?' . $query . '&signature=' . $signature;

                $accountRes = $http
                    ->withHeaders(['X-MBX-APIKEY' => $apiKey])
                    ->get($accountUrl);

                $body = $accountRes->json();
                $code = is_array($body) && isset($body['code']) ? (int) $body['code'] : null;
                if ($attempt === 0 && $code === -1021) {
                    $this->clearTimeOffsetCache($baseUrl);
                    continue;
                }

                break;
            }
        } catch (ConnectionException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unable to connect to Binance Futures API from this network.',
                'hint' => 'Binance may be blocked by your ISP. Try VPN/another network or run this on the server.',
                'base_url' => $baseUrl,
                'mode' => $futures['mode'] ?? null,
                'account' => $this->publicAccountMeta($futures),
            ], 503);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unexpected error while calling Binance Futures API.',
                'base_url' => $baseUrl,
                'mode' => $futures['mode'] ?? null,
                'account' => $this->publicAccountMeta($futures),
            ], 500);
        }

        if ($this->isTelkomselBlocked($accountRes)) {
            return response()->json([
                'success' => false,
                'error' => 'Binance Futures API is blocked by your ISP (Telkomsel Internet Baik).',
                'hint' => 'Use VPN/WARP, or run this feature on a server that can reach Binance. Alternatively set BINANCE_FUTURES_PROXY_BASE_URL to proxy through your server.',
                'base_url' => $baseUrl,
                'mode' => $futures['mode'] ?? null,
                'account' => $this->publicAccountMeta($futures),
            ], 403);
        }

        if (! $accountRes->ok()) {
            $body = $accountRes->json();
            if (is_array($body) && isset($body['code']) && isset($body['msg'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Binance Futures API error: ' . $body['msg'],
                    'hint' => $this->hintForBinanceError($futures, (int) $body['code'], (string) $body['msg']),
                    'base_url' => $baseUrl,
                    'mode' => $futures['mode'] ?? null,
                    'account' => $this->publicAccountMeta($futures),
                ], $accountRes->status() ?: 400);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch Binance Futures account.',
                'hint' => 'Check API key permissions (FUTURES + read), IP whitelist, and BINANCE_FUTURES_BASE_URL.',
                'base_url' => $baseUrl,
                'mode' => $futures['mode'] ?? null,
                'account' => $this->publicAccountMeta($futures),
            ], $accountRes->status() ?: 500);
        }

        $account = $accountRes->json();
        if (! is_array($account)) {
            return response()->json([
                'success' => false,
                'error' => 'Unexpected response from Binance Futures API.',
                'hint' => 'This usually happens when the API is blocked by ISP or a proxy is intercepting the request.',
                'base_url' => $baseUrl,
                'mode' => $futures['mode'] ?? null,
                'account' => $this->publicAccountMeta($futures),
            ], 502);
        }

        if (isset($account['code']) && isset($account['msg'])) {
            return response()->json([
                'success' => false,
                'error' => 'Binance Futures API error: ' . $account['msg'],
                'hint' => $this->hintForBinanceError($futures, (int) $account['code'], (string) $account['msg']),
                'base_url' => $baseUrl,
                'mode' => $futures['mode'] ?? null,
                'account' => $this->publicAccountMeta($futures),
            ], 400);
        }

        // Futures response structure is different from Spot
        $assets = is_array($account['assets'] ?? null) ? $account['assets'] : [];
        $positions = is_array($account['positions'] ?? null) ? $account['positions'] : [];
        
        $totalWalletBalance = (float) ($account['totalWalletBalance'] ?? 0);
        $availableBalance = (float) ($account['availableBalance'] ?? 0);
        $totalUnrealizedProfit = (float) ($account['totalUnrealizedProfit'] ?? 0);
        $totalMarginBalance = (float) ($account['totalMarginBalance'] ?? 0);

        // Filter non-zero assets
        $nonZeroAssets = array_values(array_filter($assets, function ($row) {
            $walletBalance = (float) ($row['walletBalance'] ?? 0);
            return $walletBalance > 0;
        }));

        // Get BTC price for conversion
        $pricePayload = $this->getPriceMap($http, $baseUrl);
        $priceMap = $pricePayload['map'];
        $btcPrice = $priceMap['BTCUSDT'] ?? null;
        $btcValue = $btcPrice && $btcPrice > 0 ? $totalWalletBalance / $btcPrice : null;

        // Process assets with USDT values
        $processedAssets = [];
        foreach ($nonZeroAssets as $row) {
            $asset = strtoupper((string) ($row['asset'] ?? ''));
            $walletBalance = (float) ($row['walletBalance'] ?? 0);
            $availableBalance = (float) ($row['availableBalance'] ?? 0);
            $crossUnPnl = (float) ($row['crossUnPnl'] ?? 0);

            if ($asset === '') {
                continue;
            }

            $price = $this->resolveAssetPrice($asset, $priceMap);
            $valueUsdt = $price !== null ? $walletBalance * $price : null;

            $processedAssets[] = [
                'asset' => $asset,
                'wallet_balance' => $walletBalance,
                'available_balance' => $availableBalance,
                'cross_unpnl' => $crossUnPnl,
                'price_usdt' => $price,
                'value_usdt' => $valueUsdt,
            ];
        }

        // Process open positions
        $openPositions = array_values(array_filter($positions, function ($pos) {
            $positionAmt = (float) ($pos['positionAmt'] ?? 0);
            return $positionAmt != 0;
        }));

        $processedPositions = [];
        foreach ($openPositions as $pos) {
            $processedPositions[] = [
                'symbol' => $pos['symbol'] ?? '',
                'position_amt' => (float) ($pos['positionAmt'] ?? 0),
                'entry_price' => (float) ($pos['entryPrice'] ?? 0),
                'mark_price' => (float) ($pos['markPrice'] ?? 0),
                'unrealized_profit' => (float) ($pos['unRealizedProfit'] ?? 0),
                'leverage' => (int) ($pos['leverage'] ?? 1),
                'position_side' => $pos['positionSide'] ?? 'BOTH',
            ];
        }

        return response()->json([
            'success' => true,
            'mode' => $futures['mode'] ?? 'direct',
            'base_url' => $baseUrl,
            'account' => [
                'type' => 'FUTURES',
                'can_trade' => $account['canTrade'] ?? null,
                'label' => $futures['label'] ?? null,
            ],
            'summary' => [
                'total_usdt' => round($totalWalletBalance, 6),
                'available_usdt' => round($availableBalance, 6),
                'margin_balance' => round($totalMarginBalance, 6),
                'unrealized_pnl' => round($totalUnrealizedProfit, 6),
                'btc_value' => $btcValue !== null ? round($btcValue, 8) : null,
                'asset_count' => count($processedAssets),
                'position_count' => count($processedPositions),
                'updated_at' => now()->toDateTimeString(),
            ],
            'assets' => $processedAssets,
            'positions' => $processedPositions,
            'price_source' => [
                'error' => $pricePayload['error'],
            ],
        ]);
    }

    public function positions(Request $request)
    {
        $symbol = strtoupper(trim((string) $request->query('symbol', '')));
        $params = [];
        if ($symbol !== '') {
            $params['symbol'] = $symbol;
        }
        return $this->signedGet($request, '/fapi/v2/positionRisk', $params);
    }

    public function openOrders(Request $request)
    {
        $symbol = strtoupper(trim((string) $request->query('symbol', 'BTCUSDT')));
        return $this->signedGet($request, '/fapi/v1/openOrders', [
            'symbol' => $symbol !== '' ? $symbol : null,
        ]);
    }

    public function orders(Request $request)
    {
        $symbol = strtoupper(trim((string) $request->query('symbol', 'BTCUSDT')));
        $limit = (int) $request->query('limit', 50);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        return $this->signedGet($request, '/fapi/v1/allOrders', [
            'symbol' => $symbol !== '' ? $symbol : null,
            'limit' => $limit,
        ]);
    }

    public function trades(Request $request)
    {
        $symbol = strtoupper(trim((string) $request->query('symbol', 'BTCUSDT')));
        $limit = (int) $request->query('limit', 50);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 1000) {
            $limit = 1000;
        }

        return $this->signedGet($request, '/fapi/v1/userTrades', [
            'symbol' => $symbol !== '' ? $symbol : null,
            'limit' => $limit,
        ]);
    }

    private function getPriceMap($http, string $baseUrl): array
    {
        $cacheKey = 'binance:futures:prices:' . md5($baseUrl);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['map'])) {
            return $cached;
        }

        try {
            $res = $http->get($baseUrl . '/fapi/v1/ticker/price');
        } catch (\Throwable $e) {
            return ['map' => [], 'error' => 'Failed to fetch price list.'];
        }
        if (! $res->ok()) {
            return ['map' => [], 'error' => $res->body()];
        }

        $map = [];
        $rows = $res->json();
        if (! is_array($rows)) {
            return ['map' => [], 'error' => 'Unexpected price response.'];
        }

        foreach ($rows as $row) {
            $symbol = $row['symbol'] ?? null;
            $price = $row['price'] ?? null;
            if (! $symbol || $price === null) {
                continue;
            }
            $map[$symbol] = (float) $price;
        }

        $payload = ['map' => $map, 'error' => null];
        Cache::put($cacheKey, $payload, now()->addSeconds(30));

        return $payload;
    }

    private function resolveAssetPrice(string $asset, array $priceMap): ?float
    {
        if ($asset === 'USDT') {
            return 1.0;
        }

        $symbol = $asset . 'USDT';
        if (isset($priceMap[$symbol])) {
            return $priceMap[$symbol];
        }

        return null;
    }

    private function signedGet(Request $request, string $path, array $params)
    {
        $futures = $this->getFuturesConfig($request);
        if ($this->shouldForceStub($request) || $this->isStubMode($futures)) {
            return $this->stubSigned($request, $futures, $path, $params);
        }
        if ($this->shouldProxy($request, $futures)) {
            $proxyEndpoint = match ($path) {
                '/fapi/v2/positionRisk' => '/positions',
                '/fapi/v1/openOrders' => '/open-orders',
                '/fapi/v1/allOrders' => '/orders',
                '/fapi/v1/userTrades' => '/trades',
                default => null,
            };
            if ($proxyEndpoint !== null) {
                return $this->proxyFutures($request, $futures, $proxyEndpoint);
            }
        }

        $baseUrl = $futures['base_url'];
        $apiKey = $futures['api_key'];
        $apiSecret = $futures['api_secret'];
        $timeout = $futures['timeout'];
        $recvWindow = $futures['recv_window'];
        $verify = $futures['verify_ssl'];

        if ($apiKey === '' || $apiSecret === '') {
            return $this->unconfiguredSigned($request, $futures);
        }

        $http = $this->buildHttpClient($timeout, $verify);
        $res = null;

        try {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $timestamp = $this->getSignedTimestampMs($http, $baseUrl);

                $queryParams = array_merge(
                    array_filter($params, fn($v) => $v !== null && $v !== ''),
                    [
                        'timestamp' => $timestamp,
                        'recvWindow' => $recvWindow,
                    ],
                );

                $query = http_build_query($queryParams);
                $signature = hash_hmac('sha256', $query, $apiSecret);
                $url = $baseUrl . $path . '?' . $query . '&signature=' . $signature;

                $res = $http
                    ->withHeaders(['X-MBX-APIKEY' => $apiKey])
                    ->get($url);

                $body = $res->json();
                $code = is_array($body) && isset($body['code']) ? (int) $body['code'] : null;
                if ($attempt === 0 && $code === -1021) {
                    $this->clearTimeOffsetCache($baseUrl);
                    continue;
                }

                break;
            }
        } catch (ConnectionException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unable to connect to Binance Futures API from this network.',
                'hint' => 'Binance may be blocked by your ISP. Try VPN/another network or run this on the server.',
                'base_url' => $baseUrl,
                'mode' => $futures['mode'] ?? null,
                'account' => $this->publicAccountMeta($futures),
            ], 503);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unexpected error while calling Binance Futures API.',
                'base_url' => $baseUrl,
                'mode' => $futures['mode'] ?? null,
                'account' => $this->publicAccountMeta($futures),
            ], 500);
        }

        if ($this->isTelkomselBlocked($res)) {
            return response()->json([
                'success' => false,
                'error' => 'Binance Futures API is blocked by your ISP (Telkomsel Internet Baik).',
                'hint' => 'Use VPN/WARP, or run this feature on a server that can reach Binance. Alternatively set BINANCE_FUTURES_PROXY_BASE_URL to proxy through your server.',
                'base_url' => $baseUrl,
                'mode' => $futures['mode'] ?? null,
                'account' => $this->publicAccountMeta($futures),
            ], 403);
        }

        $body = $res->json();
        if (is_array($body) && isset($body['code']) && isset($body['msg'])) {
            return response()->json([
                'success' => false,
                'error' => 'Binance Futures API error: ' . $body['msg'],
                'hint' => $this->hintForBinanceError($futures, (int) $body['code'], (string) $body['msg']),
                'base_url' => $baseUrl,
                'mode' => $futures['mode'] ?? null,
                'account' => $this->publicAccountMeta($futures),
            ], $res->status() ?: 400);
        }

        if (! $res->ok()) {
            return response()->json([
                'success' => false,
                'error' => 'Binance Futures request failed.',
                'base_url' => $baseUrl,
                'mode' => $futures['mode'] ?? null,
                'account' => $this->publicAccountMeta($futures),
            ], $res->status() ?: 500);
        }

        return response()->json([
            'success' => true,
            'mode' => $futures['mode'] ?? 'direct',
            'base_url' => $baseUrl,
            'data' => $body,
            'account' => $this->publicAccountMeta($futures),
        ]);
    }

    private function timeOffsetCacheKey(string $baseUrl): string
    {
        return 'binance:futures:time_offset:' . md5($baseUrl);
    }

    private function clearTimeOffsetCache(string $baseUrl): void
    {
        Cache::forget($this->timeOffsetCacheKey($baseUrl));
    }

    private function getSignedTimestampMs($http, string $baseUrl): int
    {
        $localNow = (int) floor(microtime(true) * 1000);
        $cacheKey = $this->timeOffsetCacheKey($baseUrl);
        $cachedOffset = Cache::get($cacheKey);
        if (is_numeric($cachedOffset)) {
            return $localNow + (int) $cachedOffset;
        }

        try {
            $res = $http->get($baseUrl . '/fapi/v1/time');
            $data = $res->ok() ? $res->json() : null;
            if (is_array($data) && isset($data['serverTime'])) {
                $serverTime = (int) $data['serverTime'];
                $offset = $serverTime - $localNow;
                Cache::put($cacheKey, $offset, now()->addMinutes(10));
                return $localNow + $offset;
            }
        } catch (\Throwable $e) {
            // ignore and fall back to local time
        }

        return $localNow;
    }

    private function getFuturesConfig(?Request $request = null): array
    {
        $config = config('services.binance.futures', []);
        $clean = fn ($value) => trim((string) $value, " \t\n\r\0\x0B\"'");

        $mode = strtolower(trim((string) ($config['mode'] ?? 'auto')));
        if ($mode === '') {
            $mode = 'auto';
        }

        $verify = $config['verify_ssl'] ?? true;
        if (! is_bool($verify)) {
            $verify = filter_var($verify, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $verify = $verify === null ? true : $verify;
        }

        $proxyVerify = $config['proxy_verify_ssl'] ?? true;
        if (! is_bool($proxyVerify)) {
            $proxyVerify = filter_var($proxyVerify, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $proxyVerify = $proxyVerify === null ? true : $proxyVerify;
        }

        $stubData = $config['stub_data'] ?? false;
        if (! is_bool($stubData)) {
            $stubData = filter_var($stubData, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $stubData = $stubData === null ? false : $stubData;
        }

        $baseUrl = rtrim($clean($config['base_url'] ?? 'https://fapi.binance.com'), '/');
        if ($baseUrl === '') {
            $baseUrl = 'https://fapi.binance.com';
        }

        $label = $clean($config['label'] ?? 'Binance Futures');
        if ($label === '') {
            $label = 'Binance Futures';
        }

        $apiKey = $clean($config['api_key'] ?? '');
        $apiSecret = $clean($config['api_secret'] ?? '');
        $credentialSource = 'env';

        $methodId = $this->selectedMethodId($request);
        if ($methodId !== null) {
            $resolved = $this->resolveMethodBinanceCredentials($methodId);
            if (($resolved['found'] ?? false) === true) {
                $apiKey = $clean($resolved['api_key'] ?? '');
                $apiSecret = $clean($resolved['api_secret'] ?? '');
                $credentialSource = (string) ($resolved['source'] ?? 'method');
            } else {
                $apiKey = '';
                $apiSecret = '';
                $credentialSource = 'method_missing';
            }
        }

        return [
            'mode' => $mode,
            'base_url' => $baseUrl,
            'label' => $label,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'credential_source' => $credentialSource,
            'timeout' => (int) ($config['timeout'] ?? 10),
            'recv_window' => (int) ($config['recv_window'] ?? 5000),
            'verify_ssl' => $verify,
            'proxy_base_url' => rtrim($clean($config['proxy_base_url'] ?? ''), '/'),
            'proxy_token' => $clean($config['proxy_token'] ?? ''),
            'proxy_verify_ssl' => $proxyVerify,
            'stub_data' => $stubData,
        ];
    }

    private function selectedMethodId(?Request $request): ?int
    {
        if (! $request) {
            return null;
        }

        $raw = $request->query('method_id')
            ?? $request->query('id_method')
            ?? $request->query('methodId');

        $id = is_numeric($raw) ? (int) $raw : null;
        return $id !== null && $id > 0 ? $id : null;
    }

    private function resolveMethodBinanceCredentials(int $methodId): array
    {
        $fromDb = $this->resolveMethodBinanceCredentialsFromDatabase($methodId);
        if (($fromDb['found'] ?? false) === true) {
            return $fromDb;
        }

        $fromConfig = $this->resolveMethodBinanceCredentialsFromConfigMap($methodId);
        if (($fromConfig['found'] ?? false) === true) {
            return $fromConfig;
        }

        $fromApi = $this->resolveMethodBinanceCredentialsFromApi($methodId);
        if (($fromApi['found'] ?? false) === true) {
            return $fromApi;
        }

        return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'none'];
    }

    private function resolveMethodBinanceCredentialsFromApi(int $methodId): array
    {
        $apiBaseUrl = rtrim((string) config('services.api.base_url', ''), '/');
        if ($apiBaseUrl === '') {
            return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'api'];
        }

        $verify = config('services.api.verify_ssl', true);
        if (! is_bool($verify)) {
            $verify = filter_var($verify, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $verify = $verify === null ? true : $verify;
        }

        try {
            $http = Http::timeout(8)
                ->connectTimeout(4)
                ->acceptJson();
            if (! $verify) {
                $http = $http->withoutVerifying();
            }
            $res = $http->get($apiBaseUrl . '/methods/' . $methodId);
        } catch (\Throwable $e) {
            return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'api'];
        }

        if (! $res->ok()) {
            return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'api'];
        }

        $json = $res->json();
        if (is_array($json) && isset($json['data']) && is_array($json['data'])) {
            $json = $json['data'];
        }

        if (! is_array($json)) {
            return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'api'];
        }

        $apiKey = trim((string) ($json['api_key'] ?? $json['binance_api_key'] ?? ''));
        $apiSecret = trim((string) ($json['secret_key'] ?? $json['api_secret'] ?? $json['binance_secret_key'] ?? ''));
        $found = $apiKey !== '' && $apiSecret !== '';

        return [
            'found' => $found,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'source' => 'api',
        ];
    }

    private function resolveMethodBinanceCredentialsFromDatabase(int $methodId): array
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

                if (! $hasTable) {
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

                    if (! $hasIdColumn) {
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

                if (! $row) {
                    continue;
                }

                $apiKey = trim((string) ($row->api_key ?? $row->binance_api_key ?? $row->apiKey ?? ''));
                $apiSecret = trim((string) ($row->secret_key ?? $row->api_secret ?? $row->binance_secret_key ?? $row->secretKey ?? ''));
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

    private function resolveMethodBinanceCredentialsFromConfigMap(int $methodId): array
    {
        $raw = (string) config('services.binance.futures.method_credentials', '');
        $raw = trim($raw);
        if ($raw === '') {
            return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'config'];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'config'];
        }

        if (! is_array($decoded)) {
            return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'config'];
        }

        $entry = $decoded[(string) $methodId] ?? $decoded[$methodId] ?? null;
        if (! is_array($entry)) {
            return ['found' => false, 'api_key' => '', 'api_secret' => '', 'source' => 'config'];
        }

        $apiKey = trim((string) ($entry['api_key'] ?? $entry['apiKey'] ?? ''));
        $apiSecret = trim((string) ($entry['api_secret'] ?? $entry['apiSecret'] ?? $entry['secret_key'] ?? $entry['secretKey'] ?? ''));
        $found = $apiKey !== '' && $apiSecret !== '';

        return [
            'found' => $found,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'source' => 'config',
        ];
    }

    private function methodCredentialDbConnections(): array
    {
        $connections = [null];

        $methodsDb = (string) config('database.connections.methods.database', '');
        $methodsHost = (string) config('database.connections.methods.host', '');
        if (trim($methodsDb) !== '' && trim($methodsHost) !== '') {
            $connections[] = 'methods';
        }

        return $connections;
    }

    private function buildHttpClient(int $timeout, bool $verify)
    {
        $http = Http::timeout($timeout)
            ->connectTimeout(min(5, max(1, $timeout)))
            ->withoutRedirecting()
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Dragonfortune/1.0 (+laravel)',
            ]);
        if (! $verify) {
            $http = $http->withoutVerifying();
        }
        return $http;
    }

    private function shouldProxy(Request $request, array $futures): bool
    {
        $proxyBaseUrl = (string) ($futures['proxy_base_url'] ?? '');
        if ($proxyBaseUrl === '') {
            return false;
        }
        if ($request->headers->has('X-Dragonfortune-Proxy')) {
            return false;
        }

        $mode = (string) ($futures['mode'] ?? 'auto');
        return in_array($mode, ['auto', 'proxy'], true);
    }

    private function isStubMode(array $futures): bool
    {
        if (! empty($futures['stub_data'])) {
            return true;
        }
        return ($futures['mode'] ?? 'auto') === 'stub';
    }

    private function shouldForceStub(Request $request): bool
    {
        if (! app()->isLocal()) {
            return false;
        }

        if ($request->boolean('stub')) {
            return true;
        }

        $header = strtolower(trim((string) $request->header('X-Dragonfortune-Stub', '')));
        return in_array($header, ['1', 'true', 'yes', 'on'], true);
    }

    private function proxyFutures(Request $request, array $futures, string $endpoint)
    {
        $proxyBaseUrl = rtrim((string) ($futures['proxy_base_url'] ?? ''), '/');
        if ($proxyBaseUrl === '') {
            return response()->json([
                'success' => false,
                'error' => 'Binance Futures proxy is not configured.',
            ], 500);
        }

        $timeout = (int) ($futures['timeout'] ?? 10);
        $verify = (bool) ($futures['proxy_verify_ssl'] ?? true);
        $token = (string) ($futures['proxy_token'] ?? '');

        $http = $this->buildHttpClient($timeout, $verify)
            ->withHeaders(array_filter([
                'X-Dragonfortune-Proxy' => '1',
                'X-Dragonfortune-Proxy-Token' => $token !== '' ? $token : null,
            ]));

        $url = $proxyBaseUrl . $endpoint;
        try {
            $res = $http->get($url, $request->query());
        } catch (ConnectionException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unable to reach Binance Futures proxy server.',
                'hint' => 'Check BINANCE_FUTURES_PROXY_BASE_URL or your network connection.',
                'proxy_base_url' => $proxyBaseUrl,
            ], 503);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unexpected error while calling Binance Futures proxy server.',
                'proxy_base_url' => $proxyBaseUrl,
            ], 500);
        }

        return response($res->body(), $res->status())
            ->header('Content-Type', 'application/json');
    }

    private function isTelkomselBlocked($response): bool
    {
        if (! $response) {
            return false;
        }
        $body = $response->body();
        return str_contains($body, 'Internet Baik') || str_contains($body, 'Telkomsel');
    }

    private function publicAccountMeta(array $futures): array
    {
        return [
            'type' => 'FUTURES',
            'label' => $futures['label'] ?? 'Binance Futures',
        ];
    }

    private function hintForBinanceError(array $futures, int $code, string $msg): ?string
    {
        if ($code === -2015) {
            return 'Invalid API key, IP, or permissions. Check that "Enable Futures" is enabled for this API key.';
        }
        if ($code === -1021) {
            return 'Timestamp sync issue. This is usually auto-resolved on retry.';
        }
        if ($code === -2014) {
            return 'API key format is invalid. Generate a new API key from Binance.';
        }
        return null;
    }

    private function unconfiguredSummary(Request $request, array $futures)
    {
        return response()->json([
            'success' => false,
            'error' => 'Binance Futures API credentials are not configured.',
            'hint' => 'Set BINANCE_FUTURES_API_KEY and BINANCE_FUTURES_API_SECRET in .env, or add api_key/secret_key to qc_method table.',
            'configured' => false,
            'mode' => 'unconfigured',
            'base_url' => $futures['base_url'] ?? null,
            'account' => $this->publicAccountMeta($futures),
        ], 400);
    }

    private function unconfiguredSigned(Request $request, array $futures)
    {
        return response()->json([
            'success' => false,
            'error' => 'Binance Futures API credentials are not configured.',
            'hint' => 'Set BINANCE_FUTURES_API_KEY and BINANCE_FUTURES_API_SECRET in .env, or add api_key/secret_key to qc_method table.',
            'configured' => false,
            'mode' => 'unconfigured',
            'base_url' => $futures['base_url'] ?? null,
            'account' => $this->publicAccountMeta($futures),
        ], 400);
    }

    private function stubSummary(array $futures)
    {
        return response()->json([
            'success' => true,
            'mode' => 'stub',
            'base_url' => $futures['base_url'] ?? 'https://fapi.binance.com',
            'account' => [
                'type' => 'FUTURES',
                'can_trade' => true,
                'label' => $futures['label'] ?? 'Binance Futures (Stub)',
            ],
            'summary' => [
                'total_usdt' => 10000.0,
                'available_usdt' => 8500.0,
                'margin_balance' => 10250.0,
                'unrealized_pnl' => 250.0,
                'btc_value' => 0.105,
                'asset_count' => 2,
                'position_count' => 1,
                'updated_at' => now()->toDateTimeString(),
            ],
            'assets' => [
                [
                    'asset' => 'USDT',
                    'wallet_balance' => 10000.0,
                    'available_balance' => 8500.0,
                    'cross_unpnl' => 250.0,
                    'price_usdt' => 1.0,
                    'value_usdt' => 10000.0,
                ],
            ],
            'positions' => [
                [
                    'symbol' => 'BTCUSDT',
                    'position_amt' => 0.1,
                    'entry_price' => 95000.0,
                    'mark_price' => 97500.0,
                    'unrealized_profit' => 250.0,
                    'leverage' => 10,
                    'position_side' => 'LONG',
                ],
            ],
            'price_source' => ['error' => null],
        ]);
    }

    private function stubSigned(Request $request, array $futures, string $path, array $params)
    {
        return response()->json([
            'success' => true,
            'mode' => 'stub',
            'base_url' => $futures['base_url'] ?? 'https://fapi.binance.com',
            'data' => [],
            'account' => $this->publicAccountMeta($futures),
        ]);
    }
}
