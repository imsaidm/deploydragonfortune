<?php

namespace App\Services;

use App\Models\TradingAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class BinanceService implements ExchangeInterface
{
    protected $futuresBaseUrl;
    protected $spotBaseUrl;
    protected $recvWindow = 5000;

    public function __construct()
    {
        $this->futuresBaseUrl = config('services.binance.futures.base_url', 'https://fapi.binance.com');
        $this->spotBaseUrl = config('services.binance.spot.base_url', 'https://api.binance.com');
    }

    /**
     * Get Server Time Offset (Cached)
     */
    protected function getTimeOffset($isFutures = true)
    {
        $cacheKey = $isFutures ? 'binance_futures_time_offset' : 'binance_spot_time_offset';
        return Cache::remember($cacheKey, 60, function() use ($isFutures) {
            try {
                $baseUrl = $isFutures ? $this->futuresBaseUrl : $this->spotBaseUrl;
                $path = $isFutures ? '/fapi/v1/time' : '/api/v3/time';
                $response = Http::withoutVerifying()->timeout(2)->get($baseUrl . $path);
                if ($response->successful()) {
                    $serverTime = $response->json()['serverTime'];
                    return $serverTime - (int) (microtime(true) * 1000);
                }
            } catch (\Exception $e) {}
            return 0;
        });
    }

    /**
     * Get Precise Timestamp
     */
    protected function getTimestamp($isFutures = true)
    {
        $offset = $this->getTimeOffset($isFutures);
        return (int) (microtime(true) * 1000) + $offset;
    }

    /**
     * Get Detailed Balances for a specific trading account in parallel.
     */
    public function getBalance(TradingAccount $account)
    {
        $results = Http::pool(fn ($pool) => [
            $pool->as('futures')->withoutVerifying()->timeout(5)
                ->withHeaders(['X-MBX-APIKEY' => $account->api_key])
                ->get($this->buildUrl('/fapi/v2/balance', [], $account, true)),

            $pool->as('spot')->withoutVerifying()->timeout(5)
                ->withHeaders(['X-MBX-APIKEY' => $account->api_key])
                ->get($this->buildUrl('/api/v3/account', [], $account, false)),

            $pool->as('funding')->withoutVerifying()->timeout(5)
                ->withHeaders(['X-MBX-APIKEY' => $account->api_key])
                ->post($this->buildUrl('/sapi/v1/asset/get-funding-asset', ['asset' => 'USDT'], $account, false)),
        ]);

        $spotBalance = 0;
        $futuresBalance = 0;
        $fundingBalance = 0;
        $availableBalance = 0;

        // 1. Process Futures
        if (isset($results['futures']) && $results['futures']->successful()) {
            $futuresData = collect($results['futures']->json())->firstWhere('asset', 'USDT');
            $futuresBalance = (float) ($futuresData['balance'] ?? 0);
            $availableBalance = (float) ($futuresData['availableBalance'] ?? 0);
        }

        // 2. Process Spot
        if (isset($results['spot']) && $results['spot']->successful()) {
            $spotData = collect($results['spot']->json()['balances'] ?? [])->firstWhere('asset', 'USDT');
            $spotBalance = (float) ($spotData['free'] ?? 0);
        }

        // 3. Process Funding
        if (isset($results['funding']) && $results['funding']->successful()) {
            $fundingData = collect($results['funding']->json() ?? [])->firstWhere('asset', 'USDT');
            $fundingBalance = (float) ($fundingData['free'] ?? 0);
        }

        return [
            'spot' => $spotBalance,
            'futures' => $futuresBalance,
            'funding' => $fundingBalance,
            'total' => $spotBalance + $futuresBalance + $fundingBalance,
            'available_balance' => $availableBalance,
            'raw_spot' => $results['spot']->successful() ? $results['spot']->json() : null,
        ];
    }

    /**
     * Get specific asset balance (e.g. ETH, BTC)
     */
    public function getSpecificAssetBalance(TradingAccount $account, string $asset)
    {
        $response = $this->signedRequest('GET', '/api/v3/account', [], $account, false);
        if ($response->successful()) {
            $balances = $response->json()['balances'] ?? [];
            $asset = strtoupper(trim($asset));
            $assetData = collect($balances)->firstWhere('asset', $asset);
            
            if (!$assetData) {
                \Log::warning("Asset $asset not found in Binance Spot. Available: " . collect($balances)->where('free', '>', 0)->pluck('asset')->implode(', '));
            }

            return (float) ($assetData['free'] ?? 0);
        }
        return 0;
    }

    /**
     * Set Leverage for a symbol
     */
    public function setLeverage(string $symbol, int $leverage, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        if (!$isFutures) return null; // Spot doesn't have leverage settings

        $response = $this->signedRequest('POST', '/fapi/v1/leverage', [
            'symbol' => $symbol,
            'leverage' => $leverage,
        ], $account, true);

        $this->logTransaction($account, '/fapi/v1/leverage', [
            'symbol' => $symbol,
            'leverage' => $leverage,
        ], $response, $signalId);

        return $response;
    }

    /**
     * Place a MARKET order
     */
    public function placeMarketOrder(string $symbol, string $side, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        $clientOrderId = 'df_' . (int) (microtime(true) * 1000);
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => 'MARKET',
            'quantity' => $quantity,
            'newClientOrderId' => $clientOrderId,
        ];

        $path = $isFutures ? '/fapi/v1/order' : '/api/v3/order';
        $response = $this->signedRequest('POST', $path, $params, $account, $isFutures);

        $this->logTransaction($account, $path, $params, $response, $signalId);

        // If it's an exit order for futures, also clear conditional orders
        if ($isFutures && str_contains(strtoupper($side), 'SELL') && $response->successful()) {
             $this->cancelAllAlgoOpenOrders($symbol, $account, $signalId);
        }

        return $response;
    }

    /**
     * Place a STOP_MARKET order (safety SL)
     */
    public function placeStopMarketOrder(string $symbol, string $side, float $stopPrice, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        if ($isFutures) {
            return $this->placeAlgoOrder('STOP_MARKET', $symbol, $side, $stopPrice, $quantity, $account, $signalId);
        }
        
        // Spot STOP_LOSS (Market)
        return $this->placeSpotConditionalOrder('STOP_LOSS', $symbol, $side, $stopPrice, $quantity, $account, $signalId);
    }

    /**
     * Place a TAKE_PROFIT_MARKET order
     */
    public function placeTakeProfitMarketOrder(string $symbol, string $side, float $stopPrice, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        if ($isFutures) {
            return $this->placeAlgoOrder('TAKE_PROFIT_MARKET', $symbol, $side, $stopPrice, $quantity, $account, $signalId);
        }

        // Spot TAKE_PROFIT (Market)
        return $this->placeSpotConditionalOrder('TAKE_PROFIT', $symbol, $side, $stopPrice, $quantity, $account, $signalId);
    }

    /**
     * Helper for Spot Conditional Orders (STOP_LOSS / TAKE_PROFIT)
     */
    public function placeSpotConditionalOrder(string $type, string $symbol, string $side, float $stopPrice, float $quantity, TradingAccount $account, $signalId = null)
    {
        $clientOrderId = 'df_spot_' . strtolower($type) . '_' . (int) (microtime(true) * 1000);
        
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => $type,
            'stopPrice' => number_format($stopPrice, 2, '.', ''),
            'quantity' => number_format($quantity, 4, '.', ''), // Spot uses 4 decimals usually
            'newClientOrderId' => $clientOrderId,
        ];

        $response = $this->signedRequest('POST', '/api/v3/order', $params, $account, false);

        $this->logTransaction($account, "/api/v3/order/{$type}", $params, $response, $signalId);

        return $response;
    }

    /**
     * Get the current Mark Price or Ticker Price for validation
     */
    public function getMarkPrice(string $symbol, $isFutures = true)
    {
        if ($isFutures) {
            $response = Http::withoutVerifying()->get($this->futuresBaseUrl . '/fapi/v1/premiumIndex', [
                'symbol' => $symbol
            ]);
            if ($response->successful()) {
                return (float) ($response->json()['markPrice'] ?? null);
            }
        } else {
            // Spot use ticker price for validation reference
            $response = Http::withoutVerifying()->get($this->spotBaseUrl . '/api/v3/ticker/price', [
                'symbol' => $symbol
            ]);
            if ($response->successful()) {
                return (float) ($response->json()['price'] ?? null);
            }
        }

        return null;
    }

    /**
     * Place an Algo Order (Mandatory for SL/TP Market in late 2025)
     */
    protected function placeAlgoOrder(string $type, string $symbol, string $side, float $stopPrice, float $quantity, TradingAccount $account, $signalId = null)
    {
        $clientOrderId = 'df_algo_' . (int) (microtime(true) * 1000);
        
        $params = [
            'algoType' => 'CONDITIONAL',
            'symbol' => $symbol,
            'side' => $side,
            'type' => $type,
            'triggerPrice' => number_format($stopPrice, 2, '.', ''),
            'quantity' => number_format($quantity, 3, '.', ''),
            'workingType' => 'MARK_PRICE',
            'reduceOnly' => 'true',
            'newClientOrderId' => $clientOrderId,
        ];

        $response = $this->signedRequest('POST', '/fapi/v1/algoOrder', $params, $account, true);

        $this->logTransaction($account, "/fapi/v1/algoOrder/{$type}", $params, $response, $signalId);

        return $response;
    }

    /**
     * Cancel all open orders for a specific symbol
     */
    public function cancelAllSymbolOrders(string $symbol, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        $params = ['symbol' => $symbol];
        
        if ($isFutures) {
            $response = $this->signedRequest('DELETE', '/fapi/v1/allOpenOrders', $params, $account, true);
            $this->logTransaction($account, '/fapi/v1/allOpenOrders', $params, $response, $signalId);
            // Also cancel Algo/Conditional orders for futures
            $this->cancelAllAlgoOpenOrders($symbol, $account, $signalId);
        } else {
            // Spot: DELETE /api/v3/openOrders (cancels all on symbol)
            $response = $this->signedRequest('DELETE', '/api/v3/openOrders', $params, $account, false);
            $this->logTransaction($account, '/api/v3/openOrders', $params, $response, $signalId);
        }

        return $response;
    }

    /**
     * Cancel all Algo/Conditional open orders for a symbol (Mandatory for late 2025)
     */
    public function cancelAllAlgoOpenOrders(string $symbol, TradingAccount $account, $signalId = null)
    {
        $path = '/fapi/v1/algoOpenOrders';
        $params = ['symbol' => $symbol];

        $response = $this->signedRequest('DELETE', $path, $params, $account, true);

        $this->logTransaction($account, $path, $params, $response, $signalId);

        return $response;
    }

    /**
     * Internal logging helper
     */
    protected function logTransaction($account, $endpoint, $payload, $response, $signalId = null)
    {
        try {
            \Illuminate\Support\Facades\DB::table('trade_logs')->insert([
                'signal_id' => $signalId,
                'account_id' => $account->id,
                'exchange' => 'binance',
                'symbol' => $payload['symbol'] ?? null,
                'endpoint' => $endpoint,
                'payload' => json_encode($payload),
                'response' => $response->body(),
                'status_code' => $response->status(),
                'client_order_id' => $payload['newClientOrderId'] ?? null,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Trade Logging Failed: " . $e->getMessage());
        }
    }

    /**
     * Close position with MARKET reduceOnly
     */
    public function closePosition(string $symbol, string $side, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        $clientOrderId = 'df_exit_' . (int) (microtime(true) * 1000);
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => 'MARKET',
            'quantity' => $quantity,
            'newClientOrderId' => $clientOrderId,
        ];

        if ($isFutures) {
            $params['reduceOnly'] = 'true';
        }

        $path = $isFutures ? '/fapi/v1/order' : '/api/v3/order';
        $response = $this->signedRequest('POST', $path, $params, $account, $isFutures);

        $this->logTransaction($account, $path . '/exit', $params, $response, $signalId);

        // If it's futures, clear conditional orders too
        if ($isFutures && $response->successful()) {
            $this->cancelAllAlgoOpenOrders($symbol, $account, $signalId);
        }

        return $response;
    }

    /**
     * Internal helper to build signed URL
     */
    protected function buildUrl(string $path, array $params, TradingAccount $account, $isFutures = true)
    {
        $params['timestamp'] = $this->getTimestamp($isFutures);
        $params['recvWindow'] = $this->recvWindow;

        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $account->secret_key);

        $baseUrl = $isFutures ? $this->futuresBaseUrl : $this->spotBaseUrl;
        return $baseUrl . $path . '?' . $queryString . '&signature=' . $signature;
    }

    /**
     * Place an order (using pool-compatible logic internally if needed, but here simple is fine)
     */
    protected function signedRequest(string $method, string $path, array $params, TradingAccount $account, $isFutures = true)
    {
        $url = $this->buildUrl($path, $params, $account, $isFutures);
        return Http::withoutVerifying()->timeout(10)->withHeaders([
            'X-MBX-APIKEY' => $account->api_key,
        ])->send($method, $url);
    }
}
