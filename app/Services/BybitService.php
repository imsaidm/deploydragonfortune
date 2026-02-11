<?php

namespace App\Services;

use App\Models\TradingAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BybitService implements ExchangeInterface
{
    protected $baseUrl;
    protected $timeout;
    protected $recvWindow;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('BYBIT_BASE_URL', 'https://api.bybit.com'), '/');
        $this->timeout = (int) env('BYBIT_TIMEOUT', 10);
        $this->recvWindow = (int) env('BYBIT_RECV_WINDOW', 5000);
    }

    /**
     * Get Bybit balance (Unified Trading Account & Funding)
     */
    public function getBalance(TradingAccount $account)
    {
        $apiKey = $account->api_key;
        $apiSecret = $account->secret_key;

        // 1. Fetch Unified / Trading Balance
        $tradingParams = ['accountType' => 'UNIFIED'];
        $tradingData = $this->fetchBybitBalance($tradingParams, $account);
        
        $unifiedTotal = 0;
        $unifiedAvailable = 0;
        $availableBalance = 0;

        if (isset($tradingData['result']['list'][0]) && ((float)($tradingData['result']['list'][0]['totalEquity'] ?? 0) > 0 || !empty($tradingData['result']['list'][0]['coin']))) {
            \Log::debug('Bybit Balance (UNIFIED): ' . json_encode($tradingData['result']['list'][0]));
            $wallet = $tradingData['result']['list'][0];
            $unifiedTotal = (float) ($wallet['totalEquity'] ?? 0);
            $unifiedAvailable = (float) ($wallet['totalAvailableBalance'] ?? 0);
            // SYSTEM OVERRIDE: Use totalEquity if availableBalance is reported as 0 (e.g., non-collateral IDR)
            $availableBalance = ($unifiedAvailable > 0) ? $unifiedAvailable : $unifiedTotal;
        } else {
            \Log::debug('Bybit: No UNIFIED balance found or ghost UTA. Trying fallback...');
            // Fallback for non-unified accounts
            $spotParams = ['accountType' => 'SPOT'];
            $spotData = $this->fetchBybitBalance($spotParams, $account);
            $spotTotal = 0;
            if (isset($spotData['result']['list'][0])) {
                foreach ($spotData['result']['list'][0]['coin'] ?? [] as $c) {
                    $spotTotal += (float) ($c['usdValue'] ?? 0);
                }
            }

            $contractParams = ['accountType' => 'CONTRACT'];
            $contractData = $this->fetchBybitBalance($contractParams, $account);
            $contractTotal = 0;
            $contractAvailable = 0;
            if (isset($contractData['result']['list'][0])) {
                $contractTotal = (float) ($contractData['result']['list'][0]['totalEquity'] ?? 0);
                // In Bybit V5 for non-unified, available margin is often in the coin list
                foreach ($contractData['result']['list'][0]['coin'] ?? [] as $c) {
                    if ($c['coin'] === 'USDT') {
                        $contractAvailable = (float) ($c['availableToWithdraw'] ?? 0);
                        break;
                    }
                }
                // If not found in USDT, use totalEquity as fallback for available
                if ($contractAvailable <= 0) $contractAvailable = $contractTotal;
            }
            
            $unifiedTotal = $contractTotal;
            $unifiedAvailable = $spotTotal;
            $availableBalance = $contractAvailable; // For Futures execution, use contract availability
            \Log::debug('Bybit Balance (Standard): ', [
                'spot_raw' => json_encode($spotData),
                'contract_raw' => json_encode($contractData),
                'available' => $availableBalance
            ]);
        }

        // 2. Fetch Funding Balance
        $fundingParams = ['accountType' => 'FUND'];
        $fundingData = $this->fetchBybitBalance($fundingParams, $account);
        
        $fundingTotal = 0;
        
        // Handle result.list (Standard wallet-balance endpoint)
        if (isset($fundingData['result']['list'][0])) {
            foreach ($fundingData['result']['list'][0]['coin'] ?? [] as $c) {
                $fundingTotal += $this->convertToUsd($c['coin'], $c['walletBalance'] ?? 0, $c['usdValue'] ?? null);
            }
        } 
        // Handle result.balance (Asset endpoint fallback)
        elseif (isset($fundingData['result']['balance'])) {
            foreach ($fundingData['result']['balance'] as $c) {
                $fundingTotal += $this->convertToUsd($c['coin'], $c['walletBalance'] ?? 0, $c['usdValue'] ?? null);
            }
        }

        return [
            'spot' => $unifiedAvailable, 
            'futures' => $unifiedTotal, 
            'funding' => $fundingTotal,
            'total' => $unifiedTotal + $fundingTotal,
            'available_balance' => $availableBalance,
        ];
    }

    /**
     * Helper to convert asset balance to USD
     */
    protected function convertToUsd($coin, $amount, $usdValue = null)
    {
        // If Bybit already provides the USD valuation, use it (most accurate)
        if ($usdValue !== null && (float)$usdValue > 0) {
            return (float) $usdValue;
        }

        $coin = strtoupper($coin);
        
        // Stablecoins are 1:1
        if (in_array($coin, ['USDT', 'USDC', 'DAI', 'BUSD', 'USD'])) {
            return (float) $amount;
        }

        // Fiat conversion fallback (if Bybit API doesn't value it)
        // Rate 16,800 is chosen based on user's current account valuation (~99.9k IDR = $5.95)
        if ($coin === 'IDR') {
            return (float) $amount / 16800;
        }

        // For other assets (BTC/ETH/etc), if Bybit doesn't provide usdValue, 
        // we skip to avoid summing non-USD amounts into the USD total.
        return 0;
    }

    /**
     * Helper to fetch wallet-balance from Bybit
     */
    protected function fetchBybitBalance(array $params, TradingAccount $account, $signalId = null)
    {
        $timestamp = $this->getTimestampMs();
        $apiKey = $account->api_key;
        $apiSecret = $account->secret_key;
        
        $queryString = http_build_query($params);
        $signature = $this->generateSignature($timestamp, $apiKey, $this->recvWindow, $queryString, $apiSecret);
        
        // Default endpoint for balance
        $url = $this->baseUrl . '/v5/account/wallet-balance?' . $queryString;
        
        // For FUND account type, if the standard wallet-balance endpoint fails or is not preferred,
        // some UTA accounts require the Asset endpoint.
        if ($params['accountType'] === 'FUND') {
            // We'll try the Asset endpoint if the standard one fails
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-BAPI-API-KEY' => $apiKey,
                    'X-BAPI-TIMESTAMP' => $timestamp,
                    'X-BAPI-RECV-WINDOW' => $this->recvWindow,
                    'X-BAPI-SIGN' => $signature,
                ])
                ->get($url);

            $this->logTransaction($account, '/v5/account/wallet-balance (FUND)', $params, $response, $signalId);

            if (!$response->successful() || empty($response->json()['result']['list'])) {
                // Fallback to Asset endpoint for Funding
                $assetUrl = $this->baseUrl . '/v5/asset/transfer/query-account-coins-balance?' . $queryString;
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'X-BAPI-API-KEY' => $apiKey,
                        'X-BAPI-TIMESTAMP' => $timestamp,
                        'X-BAPI-RECV-WINDOW' => $this->recvWindow,
                        'X-BAPI-SIGN' => $signature,
                    ])
                    ->get($assetUrl);

                $this->logTransaction($account, '/v5/asset/transfer/query-account-coins-balance', $params, $response, $signalId);
            }
        } else {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-BAPI-API-KEY' => $apiKey,
                    'X-BAPI-TIMESTAMP' => $timestamp,
                    'X-BAPI-RECV-WINDOW' => $this->recvWindow,
                    'X-BAPI-SIGN' => $signature,
                ])
                ->get($url);

            $this->logTransaction($account, '/v5/account/wallet-balance', $params, $response, $signalId);
        }

        if (!$response->successful()) {
            $err = $response->json();
            $retCode = $err['retCode'] ?? 'unknown';
            $retMsg = $err['retMsg'] ?? 'Unknown Error';
            
            Log::warning("Bybit Balance Fetch Warning ({$params['accountType']}): [{$retCode}] {$retMsg}");
            
            // Helpful hint for the specific permission error
            if ($retCode == 10005) {
                Log::info("Hint: Bybit Funding balance requires 'Account Transfer' or 'Sub-account Transfer' API permissions.");
            }
            
            return [];
        }

        return $response->json();
    }

    /**
     * Set Leverage for a symbol
     */
    public function setLeverage(string $symbol, int $leverage, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        if (!$isFutures) return null;

        $params = [
            'category' => 'linear',
            'symbol' => $symbol,
            'buyLeverage' => (string) $leverage,
            'sellLeverage' => (string) $leverage,
        ];

        $response = $this->signedRequest('POST', '/v5/position/set-leverage', $params, $account);
        $this->logTransaction($account, '/v5/position/set-leverage', $params, $response, $signalId);

        return $response;
    }

    /**
     * Place a MARKET order
     */
    public function placeMarketOrder(string $symbol, string $side, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        $category = $isFutures ? 'linear' : 'spot';
        $params = [
            'category' => $category,
            'symbol' => $symbol,
            'side' => ucfirst(strtolower($side)),
            'orderType' => 'Market',
            'qty' => (string) $quantity,
            'orderLinkId' => 'df_' . (int) (microtime(true) * 1000),
        ];

        $response = $this->signedRequest('POST', '/v5/order/create', $params, $account);
        $this->logTransaction($account, '/v5/order/create', $params, $response, $signalId);

        return $response;
    }

    /**
     * Place a STOP_MARKET order (Conditional Order in Bybit)
     */
    public function placeStopMarketOrder(string $symbol, string $side, float $stopPrice, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        $category = $isFutures ? 'linear' : 'spot';
        $params = [
            'category' => $category,
            'symbol' => $symbol,
            'side' => ucfirst(strtolower($side)),
            'orderType' => 'Market',
            'qty' => (string) $quantity,
            'triggerPrice' => (string) $stopPrice,
            'triggerBy' => 'MarkPrice', // Use Mark Price for more stable SL/TP execution
            'orderLinkId' => 'df_sl_' . (int) (microtime(true) * 1000),
        ];

        if ($isFutures) {
            $params['reduceOnly'] = true;
        }

        $response = $this->signedRequest('POST', '/v5/order/create', $params, $account);
        $this->logTransaction($account, '/v5/order/create/sl', $params, $response, $signalId);

        return $response;
    }

    /**
     * Place a TAKE_PROFIT_MARKET order
     */
    public function placeTakeProfitMarketOrder(string $symbol, string $side, float $stopPrice, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        // For Bybit Futures, we can also use /v5/position/trading-stop for TP/SL 
        // but for consistency with the Job flow, we use conditional orders.
        return $this->placeStopMarketOrder($symbol, $side, $stopPrice, $quantity, $account, $signalId, $isFutures);
    }

    /**
     * Cancel all open orders for a symbol
     */
    public function cancelAllSymbolOrders(string $symbol, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        $category = $isFutures ? 'linear' : 'spot';
        $params = [
            'category' => $category,
            'symbol' => $symbol,
        ];

        $response = $this->signedRequest('POST', '/v5/order/cancel-all', $params, $account);
        $this->logTransaction($account, '/v5/order/cancel-all', $params, $response, $signalId);

        return $response;
    }

    /**
     * Close position with MARKET reduceOnly
     */
    public function closePosition(string $symbol, string $side, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        return $this->placeMarketOrder($symbol, $side, $quantity, $account, $signalId, $isFutures);
    }

    /**
     * Get Mark Price or Ticker Price
     */
    public function getMarkPrice(string $symbol, $isFutures = true)
    {
        $category = $isFutures ? 'linear' : 'spot';
        $response = Http::get($this->baseUrl . '/v5/market/tickers', [
            'category' => $category,
            'symbol' => $symbol,
        ]);

        if ($response->successful()) {
            $list = $response->json()['result']['list'] ?? [];
            if (!empty($list)) {
                return (float) ($isFutures ? $list[0]['markPrice'] : $list[0]['lastPrice']);
            }
        }

        return null;
    }

    /**
     * Get specific asset balance
     */
    public function getSpecificAssetBalance(TradingAccount $account, string $asset)
    {
        $timestamp = $this->getTimestampMs();
        $params = [
            'accountType' => 'UNIFIED',
            'coin' => strtoupper($asset),
        ];
        
        $queryString = http_build_query($params);
        $signature = $this->generateSignature($timestamp, $account->api_key, $this->recvWindow, $queryString, $account->secret_key);
        
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'X-BAPI-API-KEY' => $account->api_key,
                'X-BAPI-TIMESTAMP' => $timestamp,
                'X-BAPI-RECV-WINDOW' => $this->recvWindow,
                'X-BAPI-SIGN' => $signature,
            ])
            ->get($this->baseUrl . '/v5/account/wallet-balance?' . $queryString);

        if ($response->successful()) {
            $coins = $response->json()['result']['list'][0]['coin'] ?? [];
            foreach ($coins as $coin) {
                if (strtoupper($coin['coin']) === strtoupper($asset)) {
                    return (float) ($coin['walletBalance'] ?? 0);
                }
            }
        }

        return 0;
    }

    /**
     * Helper for signed requests
     */
    protected function signedRequest(string $method, string $path, array $params, TradingAccount $account)
    {
        $timestamp = $this->getTimestampMs();
        $apiKey = $account->api_key;
        $apiSecret = $account->secret_key;
        
        if (strtoupper($method) === 'GET') {
            $payload = http_build_query($params);
            $url = $this->baseUrl . $path . '?' . $payload;
        } else {
            $payload = json_encode($params);
            $url = $this->baseUrl . $path;
        }

        $signature = $this->generateSignature($timestamp, $apiKey, $this->recvWindow, $payload, $apiSecret);

        $request = Http::timeout($this->timeout)
            ->withHeaders([
                'X-BAPI-API-KEY' => $apiKey,
                'X-BAPI-TIMESTAMP' => $timestamp,
                'X-BAPI-RECV-WINDOW' => $this->recvWindow,
                'X-BAPI-SIGN' => $signature,
                'Content-Type' => 'application/json',
            ]);

        return strtoupper($method) === 'GET' ? $request->get($url) : $request->post($url, $params);
    }

    protected function logTransaction($account, $endpoint, $payload, $response, $signalId = null)
    {
        try {
            \Illuminate\Support\Facades\DB::table('trade_logs')->insert([
                'signal_id' => $signalId,
                'account_id' => $account->id,
                'exchange' => 'bybit',
                'symbol' => $payload['symbol'] ?? null,
                'endpoint' => $endpoint,
                'payload' => json_encode($payload),
                'response' => $response->body(),
                'status_code' => $response->status(),
                'client_order_id' => $payload['orderLinkId'] ?? null,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error("Bybit Trade Logging Failed: " . $e->getMessage());
        }
    }

    /**
     * Generate Bybit V5 Signature
     */
    protected function generateSignature(int $timestamp, string $apiKey, int $recvWindow, string $payload, string $apiSecret): string
    {
        $signaturePayload = $timestamp . $apiKey . $recvWindow . $payload;
        return hash_hmac('sha256', $signaturePayload, $apiSecret);
    }

    protected function getTimestampMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
