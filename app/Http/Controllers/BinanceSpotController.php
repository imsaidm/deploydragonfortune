<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Client\ConnectionException;

class BinanceSpotController extends Controller
{
    public function summary(Request $request)
    {
        $spot = $this->getSpotConfig($request);
        if ($this->shouldForceStub($request) || $this->isStubMode($spot)) {
            return $this->stubSummary($spot);
        }
        if ($this->shouldProxy($request, $spot)) {
            return $this->proxySpot($request, $spot, '/summary');
        }

        $baseUrl = $spot['base_url'];
        $apiKey = $spot['api_key'];
        $apiSecret = $spot['api_secret'];
        $timeout = $spot['timeout'];
        $recvWindow = $spot['recv_window'];
        $verify = $spot['verify_ssl'];

        if ($apiKey === '' || $apiSecret === '') {
            $methodId = $this->selectedMethodId($request);
            $hint = $methodId !== null
                ? 'Binance API credentials are missing for the selected method. Fill api_key and secret_key for this method.'
                : 'Set BINANCE_SPOT_API_KEY and BINANCE_SPOT_API_SECRET in your .env.';
            return response()->json([
                'success' => false,
                'error' => 'Binance API credentials are not configured for the selected account.',
                'hint' => $hint,
                'base_url' => $baseUrl,
                'mode' => $spot['mode'] ?? null,
                'account' => $this->publicAccountMeta($spot),
            ], 500);
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
                $accountUrl = $baseUrl . '/api/v3/account?' . $query . '&signature=' . $signature;

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
                'error' => 'Unable to connect to Binance API from this network.',
                'hint' => 'Binance may be blocked by your ISP. Try VPN/another network or run this on the server.',
                'base_url' => $baseUrl,
                'mode' => $spot['mode'] ?? null,
                'account' => $this->publicAccountMeta($spot),
            ], 503);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unexpected error while calling Binance API.',
                'base_url' => $baseUrl,
                'mode' => $spot['mode'] ?? null,
                'account' => $this->publicAccountMeta($spot),
            ], 500);
        }

        if ($this->isTelkomselBlocked($accountRes)) {
            return response()->json([
                'success' => false,
                'error' => 'Binance API is blocked by your ISP (Telkomsel Internet Baik).',
                'hint' => 'Use VPN/WARP, or run this feature on a server that can reach Binance. Alternatively set BINANCE_SPOT_PROXY_BASE_URL to proxy through your server.',
                'base_url' => $baseUrl,
                'mode' => $spot['mode'] ?? null,
                'account' => $this->publicAccountMeta($spot),
            ], 403);
        }

        if (! $accountRes->ok()) {
            $body = $accountRes->json();
            if (is_array($body) && isset($body['code']) && isset($body['msg'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Binance API error: ' . $body['msg'],
                    'hint' => $this->hintForBinanceError($spot, (int) $body['code'], (string) $body['msg']),
                    'base_url' => $baseUrl,
                    'mode' => $spot['mode'] ?? null,
                    'account' => $this->publicAccountMeta($spot),
                ], $accountRes->status() ?: 400);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch Binance account.',
                'hint' => 'Check API key permissions (SPOT + read), IP whitelist, and BINANCE_SPOT_BASE_URL.',
                'base_url' => $baseUrl,
                'mode' => $spot['mode'] ?? null,
                'account' => $this->publicAccountMeta($spot),
            ], $accountRes->status() ?: 500);
        }

        $account = $accountRes->json();
        if (! is_array($account)) {
            return response()->json([
                'success' => false,
                'error' => 'Unexpected response from Binance API.',
                'hint' => 'This usually happens when the API is blocked by ISP or a proxy is intercepting the request.',
                'base_url' => $baseUrl,
                'mode' => $spot['mode'] ?? null,
                'account' => $this->publicAccountMeta($spot),
            ], 502);
        }

        if (isset($account['code']) && isset($account['msg'])) {
            return response()->json([
                'success' => false,
                'error' => 'Binance API error: ' . $account['msg'],
                'hint' => $this->hintForBinanceError($spot, (int) $account['code'], (string) $account['msg']),
                'base_url' => $baseUrl,
                'mode' => $spot['mode'] ?? null,
                'account' => $this->publicAccountMeta($spot),
            ], 400);
        }

        $balances = is_array($account['balances'] ?? null) ? $account['balances'] : [];
        $nonZero = array_values(array_filter($balances, function ($row) {
            $free = (float) ($row['free'] ?? 0);
            $locked = (float) ($row['locked'] ?? 0);
            return $free > 0 || $locked > 0;
        }));

        $pricePayload = $this->getPriceMap($http, $baseUrl);
        $priceMap = $pricePayload['map'];

        $assets = [];
        $totalUsdt = 0.0;
        $availableUsdt = 0.0;
        $lockedUsdt = 0.0;

        foreach ($nonZero as $row) {
            $asset = strtoupper((string) ($row['asset'] ?? ''));
            $free = (float) ($row['free'] ?? 0);
            $locked = (float) ($row['locked'] ?? 0);
            $total = $free + $locked;

            if ($asset === '') {
                continue;
            }

            $price = $this->resolveAssetPrice($asset, $priceMap);
            $valueUsdt = $price !== null ? $total * $price : null;

            if ($valueUsdt !== null) {
                $totalUsdt += $valueUsdt;
                $availableUsdt += $free * $price;
                $lockedUsdt += $locked * $price;
            }

            $assets[] = [
                'asset' => $asset,
                'free' => $free,
                'locked' => $locked,
                'price_usdt' => $price,
                'value_usdt' => $valueUsdt,
            ];
        }

        $btcPrice = $priceMap['BTCUSDT'] ?? null;
        $btcValue = $btcPrice && $btcPrice > 0 ? $totalUsdt / $btcPrice : null;

        return response()->json([
            'success' => true,
            'mode' => $spot['mode'] ?? 'direct',
            'base_url' => $baseUrl,
            'account' => [
                'type' => $account['accountType'] ?? 'SPOT',
                'can_trade' => $account['canTrade'] ?? null,
                'label' => $spot['label'] ?? null,
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
            'price_source' => [
                'error' => $pricePayload['error'],
            ],
        ]);
    }

    public function openOrders(Request $request)
    {
        $symbol = strtoupper(trim((string) $request->query('symbol', 'BTCUSDT')));
        return $this->signedGet($request, '/api/v3/openOrders', [
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

        return $this->signedGet($request, '/api/v3/allOrders', [
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

        return $this->signedGet($request, '/api/v3/myTrades', [
            'symbol' => $symbol !== '' ? $symbol : null,
            'limit' => $limit,
        ]);
    }

    private function getPriceMap($http, string $baseUrl): array
    {
        $cacheKey = 'binance:spot:prices:' . md5($baseUrl);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['map'])) {
            return $cached;
        }

        try {
            $res = $http->get($baseUrl . '/api/v3/ticker/price');
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

    private function signedGet(Request $request, string $path, array $params)
    {
        $spot = $this->getSpotConfig($request);
        if ($this->shouldForceStub($request) || $this->isStubMode($spot)) {
            return $this->stubSigned($request, $spot, $path, $params);
        }
        if ($this->shouldProxy($request, $spot)) {
            $proxyEndpoint = match ($path) {
                '/api/v3/openOrders' => '/open-orders',
                '/api/v3/allOrders' => '/orders',
                '/api/v3/myTrades' => '/trades',
                default => null,
            };
            if ($proxyEndpoint !== null) {
                return $this->proxySpot($request, $spot, $proxyEndpoint);
            }
        }

        $baseUrl = $spot['base_url'];
        $apiKey = $spot['api_key'];
        $apiSecret = $spot['api_secret'];
        $timeout = $spot['timeout'];
        $recvWindow = $spot['recv_window'];
        $verify = $spot['verify_ssl'];

        if ($apiKey === '' || $apiSecret === '') {
            $methodId = $this->selectedMethodId($request);
            $hint = $methodId !== null
                ? 'Binance API credentials are missing for the selected method. Fill api_key and secret_key for this method.'
                : 'Set BINANCE_SPOT_API_KEY and BINANCE_SPOT_API_SECRET in your .env.';
            return response()->json([
                'success' => false,
                'error' => 'Binance API credentials are not configured for the selected account.',
                'hint' => $hint,
                'base_url' => $baseUrl,
                'mode' => $spot['mode'] ?? null,
                'account' => $this->publicAccountMeta($spot),
            ], 500);
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
                'error' => 'Unable to connect to Binance API from this network.',
                'hint' => 'Binance may be blocked by your ISP. Try VPN/another network or run this on the server.',
                'base_url' => $baseUrl,
                'mode' => $spot['mode'] ?? null,
                'account' => $this->publicAccountMeta($spot),
            ], 503);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unexpected error while calling Binance API.',
                'base_url' => $baseUrl,
                'mode' => $spot['mode'] ?? null,
                'account' => $this->publicAccountMeta($spot),
            ], 500);
        }

        if ($this->isTelkomselBlocked($res)) {
            return response()->json([
                'success' => false,
                'error' => 'Binance API is blocked by your ISP (Telkomsel Internet Baik).',
                'hint' => 'Use VPN/WARP, or run this feature on a server that can reach Binance. Alternatively set BINANCE_SPOT_PROXY_BASE_URL to proxy through your server.',
                'base_url' => $baseUrl,
                'mode' => $spot['mode'] ?? null,
                'account' => $this->publicAccountMeta($spot),
            ], 403);
        }

        $body = $res->json();
        if (is_array($body) && isset($body['code']) && isset($body['msg'])) {
            return response()->json([
                'success' => false,
                'error' => 'Binance API error: ' . $body['msg'],
                'hint' => $this->hintForBinanceError($spot, (int) $body['code'], (string) $body['msg']),
                'base_url' => $baseUrl,
                'mode' => $spot['mode'] ?? null,
                'account' => $this->publicAccountMeta($spot),
            ], $res->status() ?: 400);
        }

        if (! $res->ok()) {
            return response()->json([
                'success' => false,
                'error' => 'Binance request failed.',
                'base_url' => $baseUrl,
                'mode' => $spot['mode'] ?? null,
                'account' => $this->publicAccountMeta($spot),
            ], $res->status() ?: 500);
        }

        return response()->json([
            'success' => true,
            'mode' => $spot['mode'] ?? 'direct',
            'base_url' => $baseUrl,
            'data' => $body,
            'account' => $this->publicAccountMeta($spot),
        ]);
    }

    private function timeOffsetCacheKey(string $baseUrl): string
    {
        return 'binance:spot:time_offset:' . md5($baseUrl);
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
            $res = $http->get($baseUrl . '/api/v3/time');
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

    private function getSpotConfig(?Request $request = null): array
    {
        $config = config('services.binance.spot', []);
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

        $baseUrl = rtrim($clean($config['base_url'] ?? 'https://api.binance.com'), '/');
        if ($baseUrl === '') {
            $baseUrl = 'https://api.binance.com';
        }

        $label = $clean($config['label'] ?? 'Binance Spot');
        if ($label === '') {
            $label = 'Binance Spot';
        }

        $apiKey = $clean($config['api_key'] ?? '');
        $apiSecret = $clean($config['api_secret'] ?? '');

        $methodId = $this->selectedMethodId($request);
        if ($methodId !== null) {
            $resolved = $this->resolveMethodBinanceCredentials($methodId);
            $apiKey = $clean($resolved['api_key'] ?? '');
            $apiSecret = $clean($resolved['api_secret'] ?? '');
        }

        return [
            'mode' => $mode,
            'base_url' => $baseUrl,
            'label' => $label,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
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
        if (($fromDb['api_key'] ?? '') !== '' && ($fromDb['api_secret'] ?? '') !== '') {
            return $fromDb;
        }

        $apiBaseUrl = rtrim((string) config('services.api.base_url', ''), '/');
        if ($apiBaseUrl === '') {
            return ['api_key' => '', 'api_secret' => ''];
        }

        $cacheKey = 'df:method:binance:' . $methodId;

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($apiBaseUrl, $methodId) {
            try {
                $res = Http::timeout(8)
                    ->connectTimeout(4)
                    ->acceptJson()
                    ->get($apiBaseUrl . '/methods/' . $methodId);
            } catch (\Throwable $e) {
                return ['api_key' => '', 'api_secret' => ''];
            }

            if (! $res->ok()) {
                return ['api_key' => '', 'api_secret' => ''];
            }

            $json = $res->json();
            if (is_array($json) && isset($json['data']) && is_array($json['data'])) {
                $json = $json['data'];
            }

            if (! is_array($json)) {
                return ['api_key' => '', 'api_secret' => ''];
            }

            $apiKey = (string) ($json['api_key'] ?? $json['binance_api_key'] ?? '');
            $apiSecret = (string) ($json['secret_key'] ?? $json['api_secret'] ?? $json['binance_secret_key'] ?? '');

            return [
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ];
        });
    }

    private function resolveMethodBinanceCredentialsFromDatabase(int $methodId): array
    {
        $cacheKey = 'df:method:binance:db:' . $methodId;

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($methodId) {
            $tables = ['qc_method', 'qc_methods'];
            $idColumns = ['id', 'id_method', 'method_id'];

            foreach ($tables as $table) {
                try {
                    if (! Schema::hasTable($table)) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    continue;
                }

                try {
                    $row = null;
                    foreach ($idColumns as $column) {
                        try {
                            if (! Schema::hasColumn($table, $column)) {
                                continue;
                            }
                        } catch (\Throwable $e) {
                            continue;
                        }

                        $row = DB::table($table)->where($column, $methodId)->first();
                        if ($row) {
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    continue;
                }

                if (! $row) {
                    continue;
                }

                $apiKey = trim((string) ($row->api_key ?? $row->binance_api_key ?? $row->apiKey ?? ''));
                $apiSecret = trim((string) ($row->secret_key ?? $row->api_secret ?? $row->binance_secret_key ?? $row->secretKey ?? ''));

                return [
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ];
            }

            return ['api_key' => '', 'api_secret' => ''];
        });
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

    private function shouldProxy(Request $request, array $spot): bool
    {
        $proxyBaseUrl = (string) ($spot['proxy_base_url'] ?? '');
        if ($proxyBaseUrl === '') {
            return false;
        }
        if ($request->headers->has('X-Dragonfortune-Proxy')) {
            return false;
        }

        $mode = (string) ($spot['mode'] ?? 'auto');
        return in_array($mode, ['auto', 'proxy'], true);
    }

    private function isStubMode(array $spot): bool
    {
        if (! empty($spot['stub_data'])) {
            return true;
        }
        return ($spot['mode'] ?? 'auto') === 'stub';
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

    private function proxySpot(Request $request, array $spot, string $endpoint)
    {
        $proxyBaseUrl = rtrim((string) ($spot['proxy_base_url'] ?? ''), '/');
        if ($proxyBaseUrl === '') {
            return response()->json([
                'success' => false,
                'error' => 'Binance proxy is not configured.',
            ], 500);
        }

        $timeout = (int) ($spot['timeout'] ?? 10);
        $verify = (bool) ($spot['proxy_verify_ssl'] ?? true);
        $token = (string) ($spot['proxy_token'] ?? '');

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
                'error' => 'Unable to reach Binance proxy server.',
                'hint' => 'Check BINANCE_SPOT_PROXY_BASE_URL or your network connection.',
                'proxy_base_url' => $proxyBaseUrl,
            ], 503);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unexpected error while calling Binance proxy server.',
                'proxy_base_url' => $proxyBaseUrl,
            ], 500);
        }

        $body = $res->json();
        if (! is_array($body)) {
            return response()->json([
                'success' => false,
                'error' => 'Binance proxy returned a non-JSON response.',
                'proxy_base_url' => $proxyBaseUrl,
                'status' => $res->status(),
            ], 502);
        }

        return response()->json($body, $res->status());
    }

    private function isTelkomselBlocked($response): bool
    {
        $status = method_exists($response, 'status') ? (int) $response->status() : 0;
        if ($status < 300 || $status >= 400) {
            return false;
        }
        $location = '';
        if (method_exists($response, 'header')) {
            $location = (string) $response->header('Location');
        }
        return $location !== '' && str_contains($location, 'internetbaik.telkomsel.com/block');
    }

    private function hintForBinanceError(array $spot, int $code, string $msg): string
    {
        $baseUrl = (string) ($spot['base_url'] ?? '');
        if ($code === -2015 || str_contains(strtolower($msg), 'invalid api-key')) {
            if (str_contains($baseUrl, 'testnet.binance.vision')) {
                return 'You are calling Binance Spot TESTNET. Use TESTNET API keys (generated on testnet.binance.vision), or switch BINANCE_SPOT_BASE_URL to mainnet.';
            }
            return 'Check Binance API key permissions (Enable Reading + Spot), IP restriction/whitelist, and BINANCE_SPOT_BASE_URL.';
        }

        if ($code === -1021 || str_contains(strtolower($msg), 'timestamp')) {
            return 'Timestamp is out of sync. The app auto-syncs using /api/v3/time; ensure that endpoint is reachable.';
        }

        return 'Check Binance API key permissions (Enable Reading + Spot), IP restriction/whitelist, and BINANCE_SPOT_BASE_URL.';
    }

    private function publicAccountMeta(array $spot): array
    {
        return array_filter([
            'label' => $spot['label'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function stubSummary(array $spot)
    {
        $assets = [
            ['asset' => 'USDT', 'free' => 7350.12, 'locked' => 0.0, 'price_usdt' => 1.0, 'value_usdt' => 7350.12],
            ['asset' => 'BTC', 'free' => 0.085, 'locked' => 0.01, 'price_usdt' => 98000.0, 'value_usdt' => 9310.0],
            ['asset' => 'ETH', 'free' => 1.75, 'locked' => 0.0, 'price_usdt' => 3600.0, 'value_usdt' => 6300.0],
            ['asset' => 'BNB', 'free' => 6.25, 'locked' => 0.0, 'price_usdt' => 650.0, 'value_usdt' => 4062.5],
        ];

        $totalUsdt = array_reduce($assets, fn ($sum, $a) => $sum + (float) ($a['value_usdt'] ?? 0), 0.0);
        $availableUsdt = array_reduce($assets, fn ($sum, $a) => $sum + (float) ($a['free'] ?? 0) * (float) ($a['price_usdt'] ?? 0), 0.0);
        $lockedUsdt = $totalUsdt - $availableUsdt;

        $btcPrice = 98000.0;
        $btcValue = $btcPrice > 0 ? $totalUsdt / $btcPrice : null;

        return response()->json([
            'success' => true,
            'mode' => 'stub',
            'base_url' => (string) ($spot['base_url'] ?? ''),
            'account' => [
                'type' => 'SPOT',
                'can_trade' => false,
                'label' => $spot['label'] ?? 'SIMULATED',
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
            'price_source' => [
                'error' => 'stub',
            ],
        ]);
    }

    private function stubSigned(Request $request, array $spot, string $path, array $params)
    {
        $symbol = strtoupper(trim((string) $request->query('symbol', $params['symbol'] ?? 'BTCUSDT')));
        if ($symbol === '') {
            $symbol = 'BTCUSDT';
        }

        $now = (int) floor(microtime(true) * 1000);
        $orders = [
            [
                'symbol' => $symbol,
                'orderId' => 9001001,
                'price' => '98500.00',
                'origQty' => '0.01000000',
                'executedQty' => '0.00000000',
                'status' => 'NEW',
                'type' => 'LIMIT',
                'side' => 'BUY',
                'time' => $now - 2 * 60 * 60 * 1000,
            ],
            [
                'symbol' => $symbol,
                'orderId' => 9001002,
                'price' => '101200.00',
                'origQty' => '0.00500000',
                'executedQty' => '0.00000000',
                'status' => 'NEW',
                'type' => 'LIMIT',
                'side' => 'SELL',
                'time' => $now - 60 * 60 * 1000,
            ],
        ];

        $history = [
            [
                'symbol' => $symbol,
                'orderId' => 9000901,
                'price' => '97250.00',
                'origQty' => '0.01000000',
                'executedQty' => '0.01000000',
                'status' => 'FILLED',
                'type' => 'MARKET',
                'side' => 'BUY',
                'time' => $now - 3 * 24 * 60 * 60 * 1000,
            ],
            [
                'symbol' => $symbol,
                'orderId' => 9000902,
                'price' => '99500.00',
                'origQty' => '0.01000000',
                'executedQty' => '0.01000000',
                'status' => 'FILLED',
                'type' => 'MARKET',
                'side' => 'SELL',
                'time' => $now - 2 * 24 * 60 * 60 * 1000,
            ],
        ];

        $trades = [
            [
                'symbol' => $symbol,
                'id' => 7001001,
                'orderId' => 9000901,
                'price' => '97250.00',
                'qty' => '0.01000000',
                'quoteQty' => '972.50',
                'time' => $now - 3 * 24 * 60 * 60 * 1000,
                'isBuyer' => true,
            ],
            [
                'symbol' => $symbol,
                'id' => 7001002,
                'orderId' => 9000902,
                'price' => '99500.00',
                'qty' => '0.01000000',
                'quoteQty' => '995.00',
                'time' => $now - 2 * 24 * 60 * 60 * 1000,
                'isBuyer' => false,
            ],
        ];

        $data = match ($path) {
            '/api/v3/openOrders' => $orders,
            '/api/v3/allOrders' => $history,
            '/api/v3/myTrades' => $trades,
            default => [],
        };

        return response()->json([
            'success' => true,
            'mode' => 'stub',
            'base_url' => (string) ($spot['base_url'] ?? ''),
            'data' => $data,
            'account' => $this->publicAccountMeta($spot),
        ]);
    }

    private function resolveAssetPrice(string $asset, array $priceMap): ?float
    {
        if (in_array($asset, ['USDT', 'USDC', 'BUSD', 'FDUSD', 'TUSD'], true)) {
            return 1.0;
        }

        $pairs = [
            $asset . 'USDT',
            $asset . 'USDC',
            $asset . 'BUSD',
            $asset . 'FDUSD',
            $asset . 'TUSD',
            $asset . 'BTC',
        ];

        foreach ($pairs as $pair) {
            if (! array_key_exists($pair, $priceMap)) {
                continue;
            }
            $price = (float) $priceMap[$pair];
            if ($price <= 0) {
                continue;
            }
            if (str_ends_with($pair, 'BTC')) {
                $btcUsdt = $priceMap['BTCUSDT'] ?? null;
                if ($btcUsdt && $btcUsdt > 0) {
                    return $price * (float) $btcUsdt;
                }
            }
            return $price;
        }

        return null;
    }
}
