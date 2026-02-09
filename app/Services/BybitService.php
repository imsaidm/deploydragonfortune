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
     * Get Bybit balance (Unified Trading Account)
     */
    public function getBalance(TradingAccount $account)
    {
        $apiKey = $account->api_key;
        $apiSecret = $account->secret_key;

        $timestamp = $this->getTimestampMs();
        $params = [
            'accountType' => 'UNIFIED', 
        ];
        
        $queryString = http_build_query($params);
        $signature = $this->generateSignature($timestamp, $apiKey, $this->recvWindow, $queryString, $apiSecret);
        
        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'X-BAPI-API-KEY' => $apiKey,
                'X-BAPI-TIMESTAMP' => $timestamp,
                'X-BAPI-RECV-WINDOW' => $this->recvWindow,
                'X-BAPI-SIGN' => $signature,
            ])
            ->get($this->baseUrl . '/v5/account/wallet-balance?' . $queryString);

        if (!$response->successful()) {
            throw new \Exception("Bybit API Error: " . ($response->json()['retMsg'] ?? 'Unknown Error'));
        }

        $data = $response->json();
        $walletList = $data['result']['list'] ?? [];
        
        $totalUsdt = 0;
        $availableUsdt = 0;

        if (!empty($walletList)) {
            $wallet = $walletList[0];
            $totalUsdt = (float) ($wallet['totalEquity'] ?? 0);
            $availableUsdt = (float) ($wallet['totalAvailableBalance'] ?? 0);
        }

        return [
            'spot' => $availableUsdt, // In Unified, it's unified
            'futures' => $totalUsdt, 
            'total' => $totalUsdt,
            'available_balance' => $availableUsdt,
        ];
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
