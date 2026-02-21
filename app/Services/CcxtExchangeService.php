<?php

namespace App\Services;

use App\Models\TradingAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Unified Exchange Service built on top of CCXT Library.
 * 
 * Supports Binance and Bybit simultaneously without conflict.
 * Uses CCXT for all standard operations + direct HTTP for Binance Algo Orders.
 * 
 * Key features:
 * - Multi-exchange instance array (no global variables)
 * - Unified precision via amount_to_precision() / price_to_precision()
 * - CCXT exception hierarchy for graceful error handling
 * - Binance Algo Orders (SL/TP) via direct signed HTTP (hybrid)
 * - Bybit SL/TP via CCXT create_order() with reduceOnly
 */
class CcxtExchangeService implements ExchangeInterface
{
    /**
     * Array of CCXT exchange instances keyed by "{exchange}_{accountId}"
     * No global variables - each account gets its own instance.
     * @var array<string, \ccxt\Exchange>
     */
    protected array $instances = [];

    /**
     * Binance Futures base URL for Algo Orders (hybrid approach).
     */
    protected string $binanceFuturesBaseUrl;

    /**
     * Binance Spot base URL.
     */
    protected string $binanceSpotBaseUrl;

    /**
     * The base capital used by the master account to calculate signal quantity.
     * All follower accounts will scale their quantity proportional to this value.
     */
    const SIGNAL_BASE_CAPITAL = 105;

    public function __construct()
    {
        $this->binanceFuturesBaseUrl = config('services.binance.futures.base_url', 'https://fapi.binance.com');
        $this->binanceSpotBaseUrl = config('services.binance.spot.base_url', 'https://api.binance.com');
    }

    /**
     * Calculate quantity proportional to account balance.
     * Scale: Signal_Qty * (Current_Balance / 105)
     */
    public function calculateProportionalQuantity(
        float $signalQuantity,
        TradingAccount $account,
        string $symbol,
        bool $isFutures = true,
        $signalId = null
    ): array {
        try {
            // 1. Fetch current balance
            $balance = $this->getBalance($account, $signalId);
            $currentBalance = $isFutures ? ($balance['available_balance'] ?? 0) : ($balance['spot'] ?? 0);

            // 2. Calculate multiplier
            $multiplier = 1.0;
            if (self::SIGNAL_BASE_CAPITAL > 0) {
                $multiplier = $currentBalance / self::SIGNAL_BASE_CAPITAL;
            }

            // 3. Scale quantity
            $finalQuantity = $signalQuantity * $multiplier;

            // 4. Trace the calculation for logging
            $this->logTrade($account, 'info/calculateProportionalQuantity', [
                'symbol' => $symbol,
                'isFutures' => $isFutures,
                'signalQty' => $signalQuantity,
                'currentBalance' => $currentBalance,
                'baseCapital' => self::SIGNAL_BASE_CAPITAL,
                'multiplier' => round($multiplier, 4),
                'proportionalQty' => $finalQuantity,
            ], ['success' => true], $signalId);

            return [
                'quantity' => (float) $finalQuantity,
                'multiplier' => $multiplier,
                'balance' => $currentBalance,
            ];

        } catch (\Exception $e) {
            Log::error("Proportional sizing failed for account {$account->id}: " . $e->getMessage());
            return [
                'quantity' => $signalQuantity,
                'multiplier' => 1.0,
                'balance' => 0,
            ];
        }
    }

    // =========================================================================
    //  PUBLIC WRAPPER: executeOrder()
    // =========================================================================

    /**
     * Unified order execution wrapper.
     * 
     * @param string $exchangeName  'binance' or 'bybit'
     * @param string $symbol        e.g. 'BTC/USDT' (CCXT unified format)
     * @param string $side          'buy' or 'sell'
     * @param string $type          'market' or 'limit'
     * @param float  $amount        Quantity (will be auto-precision adjusted)
     * @param array  $options       Extra params: ['price' => ..., 'reduceOnly' => true, ...]
     * @return array                Normalized order result
     */
    public function executeOrder(
        string $exchangeName,
        string $symbol,
        string $side,
        string $type,
        float $amount,
        array $options = []
    ): array {
        try {
            // Build a temporary TradingAccount-like object for instance resolution
            $account = new TradingAccount();
            $account->exchange = $exchangeName;
            $account->api_key = $options['apiKey'] ?? '';
            $account->secret_key = $options['secret'] ?? '';
            $account->id = $options['accountId'] ?? 0;

            $exchange = $this->resolveExchange($account);

            // Apply precision
            $amount = (float) $exchange->amount_to_precision($symbol, $amount);
            if (isset($options['price'])) {
                $options['price'] = (float) $exchange->price_to_precision($symbol, $options['price']);
            }

            // Build params
            $params = [];
            if (!empty($options['reduceOnly'])) {
                $params['reduceOnly'] = true;
            }

            $order = $exchange->create_order($symbol, $type, $side, $amount, $options['price'] ?? null, $params);

            return [
                'success' => true,
                'order' => $order,
                'exchange' => $exchangeName,
            ];
        } catch (\ccxt\InsufficientFunds $e) {
            Log::error("CCXT InsufficientFunds [{$exchangeName}]: " . $e->getMessage());
            return ['success' => false, 'error' => 'insufficient_funds', 'message' => $e->getMessage()];
        } catch (\ccxt\InvalidOrder $e) {
            Log::error("CCXT InvalidOrder [{$exchangeName}]: " . $e->getMessage());
            return ['success' => false, 'error' => 'invalid_order', 'message' => $e->getMessage()];
        } catch (\ccxt\NetworkError $e) {
            Log::error("CCXT NetworkError [{$exchangeName}]: " . $e->getMessage());
            return ['success' => false, 'error' => 'network_error', 'message' => $e->getMessage()];
        } catch (\ccxt\ExchangeError $e) {
            Log::error("CCXT ExchangeError [{$exchangeName}]: " . $e->getMessage());
            return ['success' => false, 'error' => 'exchange_error', 'message' => $e->getMessage()];
        }
    }

    // =========================================================================
    //  ExchangeInterface IMPLEMENTATIONS (used by AccountExecutionJob)
    // =========================================================================

    /**
     * Get account balances (Spot & Futures).
     */
    public function getBalance(TradingAccount $account, $signalId = null)
    {
        try {
            $exchange = $this->resolveExchange($account);
            $exchangeName = strtolower($account->exchange ?: 'binance');

            $spotBalance = 0;
            $futuresBalance = 0;
            $fundingBalance = 0;
            $availableBalance = 0;

            // Fetch Spot balance
            try {
                $exchange->options['defaultType'] = 'spot';
                $spotData = $exchange->fetch_balance();
                $spotBalance = (float) ($spotData['USDT']['free'] ?? 0);
            } catch (\Exception $e) {
                Log::warning("CCXT Spot balance fetch failed [{$exchangeName}]: " . $e->getMessage());
            }

            // Fetch Futures balance
            try {
                $type = ($exchangeName === 'bybit') ? 'swap' : 'future';
                $exchange->options['defaultType'] = $type;
                $futuresData = $exchange->fetch_balance();
                $futuresBalance = (float) ($futuresData['USDT']['total'] ?? 0);
                $availableBalance = (float) ($futuresData['USDT']['free'] ?? 0);
            } catch (\Exception $e) {
                Log::warning("CCXT Futures balance fetch failed [{$exchangeName}]: " . $e->getMessage());
            }

            // Fetch Funding balance (Binance-specific via SAPI, Bybit via FUND)
            try {
                if ($exchangeName === 'binance') {
                    $url = $this->buildBinanceSignedUrl('/sapi/v1/asset/get-funding-asset', ['asset' => 'USDT'], $account, false);
                    $response = Http::withoutVerifying()->timeout(5)
                        ->withHeaders(['X-MBX-APIKEY' => $account->api_key])
                        ->post($url);
                    if ($response->successful()) {
                        $fundingData = collect($response->json() ?? [])->firstWhere('asset', 'USDT');
                        $fundingBalance = (float) ($fundingData['free'] ?? 0);
                    }
                } elseif ($exchangeName === 'bybit') {
                    // Bybit FUND wallet via direct API
                    $fundingBalance = $this->fetchBybitFundingBalance($account, $signalId);
                }
            } catch (\Exception $e) {
                Log::warning("Funding balance fetch failed [{$exchangeName}]: " . $e->getMessage());
            }

            $result = [
                'spot' => $spotBalance,
                'futures' => $futuresBalance,
                'funding' => $fundingBalance,
                'total' => $spotBalance + $futuresBalance + $fundingBalance,
                'available_balance' => $availableBalance,
            ];

            $this->logTrade($account, 'ccxt/getBalance', [], $result, $signalId);
            return $result;

        } catch (\ccxt\NetworkError $e) {
            Log::error("CCXT NetworkError getBalance [{$account->exchange}]: " . $e->getMessage());
            return ['spot' => 0, 'futures' => 0, 'funding' => 0, 'total' => 0, 'available_balance' => 0];
        } catch (\ccxt\ExchangeError $e) {
            Log::error("CCXT ExchangeError getBalance [{$account->exchange}]: " . $e->getMessage());
            return ['spot' => 0, 'futures' => 0, 'funding' => 0, 'total' => 0, 'available_balance' => 0];
        }
    }

    /**
     * Get specific asset balance (e.g. ETH in Spot) for exit checks.
     */
    public function getSpecificAssetBalance(TradingAccount $account, string $asset, $signalId = null)
    {
        try {
            $exchange = $this->resolveExchange($account);
            $exchange->options['defaultType'] = 'spot';
            $balance = $exchange->fetch_balance();
            
            $asset = strtoupper(trim($asset));
            $free = (float) ($balance[$asset]['free'] ?? 0);

            if ($free <= 0) {
                $available = array_filter($balance['free'] ?? [], fn($v) => $v > 0);
                Log::warning("Asset {$asset} not found or zero. Available: " . implode(', ', array_keys($available)));
            }

            $this->logTrade($account, 'ccxt/getSpecificAssetBalance', ['asset' => $asset], ['free' => $free], $signalId);
            return $free;

        } catch (\ccxt\NetworkError $e) {
            Log::error("CCXT NetworkError getSpecificAssetBalance: " . $e->getMessage());
            return 0;
        } catch (\ccxt\ExchangeError $e) {
            Log::error("CCXT ExchangeError getSpecificAssetBalance: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Set leverage for a symbol (Futures only).
     */
    public function setLeverage(string $symbol, int $leverage, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        if (!$isFutures) return null;

        try {
            $exchange = $this->resolveExchange($account);
            $exchangeName = strtolower($account->exchange ?: 'binance');

            // Set correct market type
            $exchange->options['defaultType'] = ($exchangeName === 'bybit') ? 'swap' : 'future';

            // Convert symbol to CCXT format
            $ccxtSymbol = $this->toCcxtSymbol($symbol, $exchangeName, true);

            $result = $exchange->set_leverage($leverage, $ccxtSymbol);

            $this->logTrade($account, 'ccxt/setLeverage', [
                'symbol' => $symbol, 'leverage' => $leverage,
            ], $result ?? ['status' => 'ok'], $signalId);

            return $result;

        } catch (\ccxt\ExchangeError $e) {
            // "leverage not modified" is common and harmless
            if (str_contains($e->getMessage(), 'leverage not modified') || str_contains($e->getMessage(), 'No need to change')) {
                Log::info("Leverage already set to {$leverage} for {$symbol}");
                return ['status' => 'already_set'];
            }
            Log::error("CCXT setLeverage error: " . $e->getMessage());
            return null;
        } catch (\ccxt\NetworkError $e) {
            Log::error("CCXT NetworkError setLeverage: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Place a MARKET order via CCXT.
     * Returns an Illuminate HTTP-like response for backward compatibility with AccountExecutionJob.
     */
    public function placeMarketOrder(string $symbol, string $side, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        try {
            $exchange = $this->resolveExchange($account);
            $exchangeName = strtolower($account->exchange ?: 'binance');
            $ccxtSymbol = $this->toCcxtSymbol($symbol, $exchangeName, $isFutures);

            // Set correct market type
            $exchange->options['defaultType'] = $this->getDefaultType($exchangeName, $isFutures);

            // Build params
            $params = [];
            $ccxtSide = strtolower($side);

            // For Spot BUY with USDT amount (quoteOrderQty equivalent)
            if (!$isFutures && strtoupper($side) === 'BUY' && $quantity >= 5) {
                if ($exchangeName === 'binance') {
                    // Binance Spot: use quoteOrderQty — quantity is USDT amount, NOT base asset
                    $params['quoteOrderQty'] = number_format($quantity, 2, '.', '');
                    // Ensure clientOrderId has df_ prefix
            if (!isset($params['clientOrderId'])) {
                $params['clientOrderId'] = 'df_' . uniqid();
            }

            $order = $exchange->create_order($ccxtSymbol, 'market', $ccxtSide, 0, null, $params);
                } elseif ($exchangeName === 'bybit') {
                    // Bybit Spot Market BUY: convert USDT amount → base qty
                    $ticker = $exchange->fetch_ticker($ccxtSymbol);
                    $lastPrice = $ticker['last'] ?? $ticker['close'] ?? 0;
                    if ($lastPrice > 0) {
                        $baseQty = $quantity / $lastPrice;
                        $baseQty = (float) $exchange->amount_to_precision($ccxtSymbol, $baseQty);
                        // Ensure clientOrderId has df_ prefix
            if (!isset($params['clientOrderId'])) {
                $params['clientOrderId'] = 'df_' . uniqid();
            }

            $order = $exchange->create_order($ccxtSymbol, 'market', $ccxtSide, $baseQty, null, $params);
                    } else {
                        throw new \ccxt\ExchangeError('Could not fetch Bybit ticker price for ' . $ccxtSymbol);
                    }
                } else {
                    // Ensure clientOrderId has df_ prefix
            if (!isset($params['clientOrderId'])) {
                $params['clientOrderId'] = 'df_' . uniqid();
            }

            $order = $exchange->create_order($ccxtSymbol, 'market', $ccxtSide, $quantity, null, $params);
                }
            } else {
                // For SELL or Futures: quantity is base asset amount — apply precision
                $quantity = (float) $exchange->amount_to_precision($ccxtSymbol, $quantity);
                // Ensure clientOrderId has df_ prefix
            if (!isset($params['clientOrderId'])) {
                $params['clientOrderId'] = 'df_' . uniqid();
            }

            $order = $exchange->create_order($ccxtSymbol, 'market', $ccxtSide, $quantity, null, $params);
            }

            $this->logTrade($account, 'ccxt/placeMarketOrder', [
                'symbol' => $symbol, 'side' => $side, 'quantity' => $quantity, 'isFutures' => $isFutures,
            ], $order, $signalId, 200);

            // If futures exit (SELL on long), also clear conditional orders
            if ($isFutures && str_contains(strtoupper($side), 'SELL')) {
                $this->cancelAlgoOrConditionalOrders($symbol, $account, $signalId, $exchangeName);
            }

            return $this->wrapResponse(true, $order, 200);

        } catch (\ccxt\InsufficientFunds $e) {
            Log::error("CCXT InsufficientFunds placeMarketOrder [{$account->exchange}]: " . $e->getMessage());
            $this->logTrade($account, 'ccxt/placeMarketOrder/ERROR', [
                'symbol' => $symbol, 'side' => $side, 'quantity' => $quantity ?? 0, 'isFutures' => $isFutures
            ], ['error' => $e->getMessage()], $signalId, 400);
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 400);
        } catch (\ccxt\InvalidOrder $e) {
            Log::error("CCXT InvalidOrder placeMarketOrder [{$account->exchange}]: " . $e->getMessage());
            $this->logTrade($account, 'ccxt/placeMarketOrder/ERROR', [
                'symbol' => $symbol, 'side' => $side, 'quantity' => $quantity ?? 0, 'isFutures' => $isFutures
            ], ['error' => $e->getMessage()], $signalId, 400);
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 400);
        } catch (\ccxt\NetworkError $e) {
            Log::error("CCXT NetworkError placeMarketOrder [{$account->exchange}]: " . $e->getMessage());
            $this->logTrade($account, 'ccxt/placeMarketOrder/ERROR', [
                'symbol' => $symbol, 'side' => $side, 'quantity' => $quantity ?? 0, 'isFutures' => $isFutures
            ], ['error' => $e->getMessage()], $signalId, 408);
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 408);
        } catch (\ccxt\ExchangeError $e) {
            Log::error("CCXT ExchangeError placeMarketOrder [{$account->exchange}]: " . $e->getMessage());
            $this->logTrade($account, 'ccxt/placeMarketOrder/ERROR', [
                'symbol' => $symbol, 'side' => $side, 'quantity' => $quantity ?? 0, 'isFutures' => $isFutures
            ], ['error' => $e->getMessage()], $signalId, 400);
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Place a STOP_MARKET (SL) order.
     * - Binance Futures: uses Algo Order via direct HTTP (hybrid)
     * - Bybit Futures: uses CCXT conditional order with reduceOnly
     * - Spot: uses CCXT conditional order
     */
    public function placeStopMarketOrder(string $symbol, string $side, float $stopPrice, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        $exchangeName = strtolower($account->exchange ?: 'binance');

        try {
            if ($isFutures && $exchangeName === 'binance') {
                // === BINANCE FUTURES: Algo Order (direct HTTP) ===
                return $this->placeBinanceAlgoOrder('STOP_MARKET', $symbol, $side, $stopPrice, $quantity, $account, $signalId);
            }

            // === CCXT path for Bybit Futures & all Spot ===
            $exchange = $this->resolveExchange($account);
            $ccxtSymbol = $this->toCcxtSymbol($symbol, $exchangeName, $isFutures);
            $exchange->options['defaultType'] = $this->getDefaultType($exchangeName, $isFutures);

            $quantity = (float) $exchange->amount_to_precision($ccxtSymbol, $quantity);
            $stopPrice = (float) $exchange->price_to_precision($ccxtSymbol, $stopPrice);

            $params = [
                'triggerPrice' => $stopPrice,
            ];

            if ($isFutures) {
                $params['reduceOnly'] = true;
                if ($exchangeName === 'bybit') {
                    $params['triggerBy'] = 'MarkPrice';
                }
            }

            // Ensure clientOrderId has df_ prefix
            if (!isset($params['clientOrderId'])) {
                $params['clientOrderId'] = 'df_' . uniqid();
            }

            $order = $exchange->create_order($ccxtSymbol, 'market', strtolower($side), $quantity, null, $params);

            $this->logTrade($account, 'ccxt/placeStopMarketOrder', [
                'symbol' => $symbol, 'side' => $side, 'stopPrice' => $stopPrice, 'quantity' => $quantity,
            ], $order, $signalId, 200);

            return $this->wrapResponse(true, $order, 200);

        } catch (\ccxt\InsufficientFunds $e) {
            Log::error("CCXT InsufficientFunds placeStopMarketOrder: " . $e->getMessage());
            $this->logTrade($account, 'ccxt/placeStopMarketOrder/ERROR', [
                'symbol' => $symbol, 'side' => $side, 'stopPrice' => $stopPrice, 'quantity' => $quantity
            ], ['error' => $e->getMessage()], $signalId, 400);
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 400);
        } catch (\ccxt\InvalidOrder $e) {
            Log::error("CCXT InvalidOrder placeStopMarketOrder: " . $e->getMessage());
            $this->logTrade($account, 'ccxt/placeStopMarketOrder/ERROR', [
                'symbol' => $symbol, 'side' => $side, 'stopPrice' => $stopPrice, 'quantity' => $quantity
            ], ['error' => $e->getMessage()], $signalId, 400);
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 400);
        } catch (\ccxt\NetworkError $e) {
            Log::error("CCXT NetworkError placeStopMarketOrder: " . $e->getMessage());
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 408);
        } catch (\ccxt\ExchangeError $e) {
            Log::error("CCXT ExchangeError placeStopMarketOrder: " . $e->getMessage());
            $this->logTrade($account, 'ccxt/placeStopMarketOrder/ERROR', [
                'symbol' => $symbol, 'side' => $side, 'stopPrice' => $stopPrice, 'quantity' => $quantity
            ], ['error' => $e->getMessage()], $signalId, 400);
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Place a TAKE_PROFIT_MARKET (TP) order.
     * - Binance Futures: uses Algo Order via direct HTTP (hybrid)
     * - Bybit Futures: uses CCXT conditional order with reduceOnly
     * - Spot: uses CCXT conditional order
     */
    public function placeTakeProfitMarketOrder(string $symbol, string $side, float $stopPrice, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        $exchangeName = strtolower($account->exchange ?: 'binance');

        try {
            if ($isFutures && $exchangeName === 'binance') {
                // === BINANCE FUTURES: Algo Order (direct HTTP) ===
                return $this->placeBinanceAlgoOrder('TAKE_PROFIT_MARKET', $symbol, $side, $stopPrice, $quantity, $account, $signalId);
            }

            // === CCXT path for Bybit Futures & all Spot ===
            $exchange = $this->resolveExchange($account);
            $ccxtSymbol = $this->toCcxtSymbol($symbol, $exchangeName, $isFutures);
            $exchange->options['defaultType'] = $this->getDefaultType($exchangeName, $isFutures);

            $quantity = (float) $exchange->amount_to_precision($ccxtSymbol, $quantity);
            $stopPrice = (float) $exchange->price_to_precision($ccxtSymbol, $stopPrice);

            $params = [
                'triggerPrice' => $stopPrice,
            ];

            if ($isFutures) {
                $params['reduceOnly'] = true;
                if ($exchangeName === 'bybit') {
                    $params['triggerBy'] = 'MarkPrice';
                }
            }

            // Ensure clientOrderId has df_ prefix
            if (!isset($params['clientOrderId'])) {
                $params['clientOrderId'] = 'df_' . uniqid();
            }

            $order = $exchange->create_order($ccxtSymbol, 'market', strtolower($side), $quantity, null, $params);

            $this->logTrade($account, 'ccxt/placeTakeProfitMarketOrder', [
                'symbol' => $symbol, 'side' => $side, 'stopPrice' => $stopPrice, 'quantity' => $quantity,
            ], $order, $signalId, 200);

            return $this->wrapResponse(true, $order, 200);

        } catch (\ccxt\InsufficientFunds $e) {
            Log::error("CCXT InsufficientFunds placeTakeProfitMarketOrder: " . $e->getMessage());
            $this->logTrade($account, 'ccxt/placeTakeProfitMarketOrder/ERROR', [
                'symbol' => $symbol, 'side' => $side, 'stopPrice' => $stopPrice, 'quantity' => $quantity
            ], ['error' => $e->getMessage()], $signalId, 400);
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 400);
        } catch (\ccxt\InvalidOrder $e) {
            Log::error("CCXT InvalidOrder placeTakeProfitMarketOrder: " . $e->getMessage());
            $this->logTrade($account, 'ccxt/placeTakeProfitMarketOrder/ERROR', [
                'symbol' => $symbol, 'side' => $side, 'stopPrice' => $stopPrice, 'quantity' => $quantity
            ], ['error' => $e->getMessage()], $signalId, 400);
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 400);
        } catch (\ccxt\NetworkError $e) {
            Log::error("CCXT NetworkError placeTakeProfitMarketOrder: " . $e->getMessage());
            $this->logTrade($account, 'ccxt/placeTakeProfitMarketOrder/ERROR', [
                'symbol' => $symbol, 'side' => $side, 'stopPrice' => $stopPrice, 'quantity' => $quantity
            ], ['error' => $e->getMessage()], $signalId, 408);
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 408);
        } catch (\ccxt\ExchangeError $e) {
            Log::error("CCXT ExchangeError placeTakeProfitMarketOrder: " . $e->getMessage());
            $this->logTrade($account, 'ccxt/placeTakeProfitMarketOrder/ERROR', [
                'symbol' => $symbol, 'side' => $side, 'stopPrice' => $stopPrice, 'quantity' => $quantity
            ], ['error' => $e->getMessage()], $signalId, 400);
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Cancel all open orders for a symbol.
     */
    public function cancelAllSymbolOrders(string $symbol, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        try {
            $exchange = $this->resolveExchange($account);
            $exchangeName = strtolower($account->exchange ?: 'binance');
            $ccxtSymbol = $this->toCcxtSymbol($symbol, $exchangeName, $isFutures);
            $exchange->options['defaultType'] = $this->getDefaultType($exchangeName, $isFutures);

            $result = $exchange->cancel_all_orders($ccxtSymbol);

            $this->logTrade($account, 'ccxt/cancelAllSymbolOrders', [
                'symbol' => $symbol, 'isFutures' => $isFutures,
            ], $result ?? ['status' => 'cancelled'], $signalId);

            // For Binance Futures, also cancel Algo orders
            if ($isFutures && $exchangeName === 'binance') {
                $this->cancelBinanceAlgoOrders($symbol, $account, $signalId);
            }

            return $this->wrapResponse(true, $result ?? ['status' => 'cancelled'], 200);

        } catch (\ccxt\ExchangeError $e) {
            Log::warning("CCXT cancelAllSymbolOrders warning: " . $e->getMessage());
            return $this->wrapResponse(true, ['status' => 'no_orders'], 200); // Not critical
        } catch (\ccxt\NetworkError $e) {
            Log::error("CCXT NetworkError cancelAllSymbolOrders: " . $e->getMessage());
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 408);
        }
    }

    /**
     * Close position with MARKET reduceOnly (Futures) or plain SELL (Spot).
     */
    public function closePosition(string $symbol, string $side, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true)
    {
        try {
            $exchange = $this->resolveExchange($account);
            $exchangeName = strtolower($account->exchange ?: 'binance');
            $ccxtSymbol = $this->toCcxtSymbol($symbol, $exchangeName, $isFutures);
            $exchange->options['defaultType'] = $this->getDefaultType($exchangeName, $isFutures);

            $quantity = (float) $exchange->amount_to_precision($ccxtSymbol, $quantity);

            $params = [];
            if ($isFutures) {
                $params['reduceOnly'] = true;
            }

            // Ensure clientOrderId has df_ prefix
            if (!isset($params['clientOrderId'])) {
                $params['clientOrderId'] = 'df_' . uniqid();
            }

            $order = $exchange->create_order($ccxtSymbol, 'market', strtolower($side), $quantity, null, $params);

            $this->logTrade($account, 'ccxt/closePosition', [
                'symbol' => $symbol, 'side' => $side, 'quantity' => $quantity, 'isFutures' => $isFutures,
            ], $order, $signalId, 200);

            // If futures, also clear conditional orders
            if ($isFutures) {
                $this->cancelAlgoOrConditionalOrders($symbol, $account, $signalId, $exchangeName);
            }

            return $this->wrapResponse(true, $order, 200);

        } catch (\ccxt\InsufficientFunds $e) {
            Log::error("CCXT InsufficientFunds closePosition: " . $e->getMessage());
            $this->logTrade($account, 'ccxt/closePosition/ERROR', [
                'symbol' => $symbol, 'side' => $side, 'quantity' => $quantity, 'isFutures' => $isFutures
            ], ['error' => $e->getMessage()], $signalId, 400);
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 400);
        } catch (\ccxt\InvalidOrder $e) {
            Log::error("CCXT InvalidOrder closePosition: " . $e->getMessage());
            $this->logTrade($account, 'ccxt/closePosition/ERROR', [
                'symbol' => $symbol, 'side' => $side, 'quantity' => $quantity, 'isFutures' => $isFutures
            ], ['error' => $e->getMessage()], $signalId, 400);
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 400);
        } catch (\ccxt\NetworkError $e) {
            Log::error("CCXT NetworkError closePosition: " . $e->getMessage());
            $this->logTrade($account, 'ccxt/closePosition/ERROR', [
                'symbol' => $symbol, 'side' => $side, 'quantity' => $quantity, 'isFutures' => $isFutures
            ], ['error' => $e->getMessage()], $signalId, 408);
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 408);
        } catch (\ccxt\ExchangeError $e) {
            Log::error("CCXT ExchangeError closePosition: " . $e->getMessage());
            $this->logTrade($account, 'ccxt/closePosition/ERROR', [
                'symbol' => $symbol, 'side' => $side, 'quantity' => $quantity, 'isFutures' => $isFutures
            ], ['error' => $e->getMessage()], $signalId, 400);
            return $this->wrapResponse(false, ['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get current price (Mark Price for Futures, Ticker for Spot) via CCXT.
     */
    public function getMarkPrice(string $symbol, $isFutures = true)
    {
        try {
            // Use a shared Binance or Bybit instance - this method doesn't need auth
            $exchange = null;
            $exchangeName = 'binance'; // Default

            foreach ($this->instances as $inst) {
                $exchange = $inst;
                $exchangeName = strtolower($exchange->id);
                break;
            }

            if (!$exchange) {
                // Create a minimal public instance 
                $exchange = new \ccxt\binance(['enableRateLimit' => true]);
                $exchangeName = 'binance';
            }

            $ccxtSymbol = $this->toCcxtSymbol($symbol, $exchangeName, $isFutures);
            $ticker = $exchange->fetch_ticker($ccxtSymbol);

            if ($isFutures) {
                // Mark price for futures (CCXT provides 'info' with markPrice for futures)
                return (float) ($ticker['info']['markPrice'] ?? $ticker['last'] ?? null);
            }

            return (float) ($ticker['last'] ?? null);

        } catch (\Exception $e) {
            Log::error("CCXT getMarkPrice error for {$symbol}: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    //  BINANCE ALGO ORDER METHODS (Direct HTTP - Hybrid Approach)
    // =========================================================================

    /**
     * Place Binance Futures Algo Order (SL/TP) via direct signed HTTP.
     * Preserves the proven /fapi/v1/algoOrder endpoint.
     */
    protected function placeBinanceAlgoOrder(string $type, string $symbol, string $side, float $stopPrice, float $quantity, TradingAccount $account, $signalId = null)
    {
        $clientOrderId = 'df_algo_' . (int) (microtime(true) * 1000);

        // Apply precision via CCXT instance
        $exchange = $this->resolveExchange($account);
        $ccxtSymbol = $this->toCcxtSymbol($symbol, 'binance', true);
        $quantity = (float) $exchange->amount_to_precision($ccxtSymbol, $quantity);
        $stopPrice = (float) $exchange->price_to_precision($ccxtSymbol, $stopPrice);

        $params = [
            'algoType' => 'CONDITIONAL',
            'symbol' => $symbol, // Binance uses non-CCXT format (e.g. BTCUSDT)
            'side' => strtoupper($side),
            'type' => $type,
            'triggerPrice' => (string) $stopPrice,
            'quantity' => (string) $quantity,
            'workingType' => 'MARK_PRICE',
            'reduceOnly' => 'true',
            'newClientOrderId' => $clientOrderId,
        ];

        $url = $this->buildBinanceSignedUrl('/fapi/v1/algoOrder', $params, $account, true);
        $response = Http::withoutVerifying()->timeout(10)
            ->withHeaders(['X-MBX-APIKEY' => $account->api_key])
            ->post($url);

        $this->logTrade($account, "/fapi/v1/algoOrder/{$type}", $params, $response->json() ?? [], $signalId);

        return $this->wrapHttpResponse($response);
    }

    /**
     * Cancel all Binance Futures Algo/Conditional open orders for a symbol.
     */
    protected function cancelBinanceAlgoOrders(string $symbol, TradingAccount $account, $signalId = null)
    {
        $params = ['symbol' => $symbol];
        $url = $this->buildBinanceSignedUrl('/fapi/v1/algoOpenOrders', $params, $account, true);

        $response = Http::withoutVerifying()->timeout(10)
            ->withHeaders(['X-MBX-APIKEY' => $account->api_key])
            ->delete($url);

        $this->logTrade($account, '/fapi/v1/algoOpenOrders', $params, $response->json() ?? [], $signalId);

        return $response;
    }

    /**
     * Cancel Algo (Binance) or conditional orders (Bybit) based on exchange.
     */
    protected function cancelAlgoOrConditionalOrders(string $symbol, TradingAccount $account, $signalId, string $exchangeName)
    {
        if ($exchangeName === 'binance') {
            $this->cancelBinanceAlgoOrders($symbol, $account, $signalId);
        }
        // For Bybit, cancel_all_orders already covers conditional orders
    }

    // =========================================================================
    //  BINANCE SIGNED URL BUILDER (for Algo Orders)
    // =========================================================================

    /**
     * Build signed URL for Binance API calls (used by algo order hybrid approach).
     */
    protected function buildBinanceSignedUrl(string $path, array $params, TradingAccount $account, $isFutures = true): string
    {
        $params['timestamp'] = $this->getBinanceTimestamp($isFutures);
        $params['recvWindow'] = 5000;

        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $account->secret_key);

        $baseUrl = $isFutures ? $this->binanceFuturesBaseUrl : $this->binanceSpotBaseUrl;
        return $baseUrl . $path . '?' . $queryString . '&signature=' . $signature;
    }

    /**
     * Get Binance timestamp with server offset.
     */
    protected function getBinanceTimestamp($isFutures = true): int
    {
        $cacheKey = $isFutures ? 'binance_futures_time_offset' : 'binance_spot_time_offset';
        $offset = Cache::remember($cacheKey, 60, function () use ($isFutures) {
            try {
                $baseUrl = $isFutures ? $this->binanceFuturesBaseUrl : $this->binanceSpotBaseUrl;
                $path = $isFutures ? '/fapi/v1/time' : '/api/v3/time';
                $response = Http::withoutVerifying()->timeout(2)->get($baseUrl . $path);
                if ($response->successful()) {
                    return $response->json()['serverTime'] - (int) (microtime(true) * 1000);
                }
            } catch (\Exception $e) {}
            return 0;
        });

        return (int) (microtime(true) * 1000) + $offset;
    }

    // =========================================================================
    //  BYBIT FUNDING BALANCE HELPER
    // =========================================================================

    /**
     * Fetch Bybit Funding (FUND) wallet balance.
     */
    protected function fetchBybitFundingBalance(TradingAccount $account, $signalId = null): float
    {
        $baseUrl = rtrim(env('BYBIT_BASE_URL', 'https://api.bybit.com'), '/');
        $recvWindow = (int) env('BYBIT_RECV_WINDOW', 5000);
        $timestamp = (int) floor(microtime(true) * 1000);
        $params = ['accountType' => 'FUND'];
        $queryString = http_build_query($params);

        $signaturePayload = $timestamp . $account->api_key . $recvWindow . $queryString;
        $signature = hash_hmac('sha256', $signaturePayload, $account->secret_key);

        $response = Http::timeout(10)->withHeaders([
            'X-BAPI-API-KEY' => $account->api_key,
            'X-BAPI-TIMESTAMP' => $timestamp,
            'X-BAPI-RECV-WINDOW' => $recvWindow,
            'X-BAPI-SIGN' => $signature,
        ])->get($baseUrl . '/v5/account/wallet-balance?' . $queryString);

        $fundingTotal = 0;
        if ($response->successful()) {
            $list = $response->json()['result']['list'][0]['coin'] ?? [];
            foreach ($list as $coin) {
                if (in_array(strtoupper($coin['coin']), ['USDT', 'USDC', 'USD'])) {
                    $fundingTotal += (float) ($coin['walletBalance'] ?? 0);
                } elseif (!empty($coin['usdValue']) && (float) $coin['usdValue'] > 0) {
                    $fundingTotal += (float) $coin['usdValue'];
                }
            }
        }

        return $fundingTotal;
    }

    // =========================================================================
    //  CCXT INSTANCE MANAGEMENT
    // =========================================================================

    /**
     * Resolve or create a CCXT exchange instance for the given account.
     * Instances are cached in $this->instances array (no globals).
     */
    protected function resolveExchange(TradingAccount $account): \ccxt\Exchange
    {
        $exchangeName = strtolower($account->exchange ?: 'binance');
        $key = $exchangeName . '_' . $account->id;

        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        $config = [
            'apiKey' => $account->api_key,
            'secret' => $account->secret_key,
            'enableRateLimit' => true,
            'timeout' => 10000,
            'options' => [],
        ];

        switch ($exchangeName) {
            case 'bybit':
                $config['options'] = [
                    'defaultType' => 'swap', // USDT-Margined Futures default (swap)
                    'recvWindow' => (int) env('BYBIT_RECV_WINDOW', 5000),
                ];
                $exchange = new \ccxt\bybit($config);
                break;

            case 'binance':
            default:
                $config['options'] = [
                    'defaultType' => 'future', // Futures default
                    'recvWindow' => 5000,
                    'adjustForTimeDifference' => true,
                ];
                $exchange = new \ccxt\binance($config);
                break;
        }

        // Load markets (cached by CCXT internally)
        $exchange->load_markets();

        $this->instances[$key] = $exchange;
        return $exchange;
    }

    // =========================================================================
    //  UTILITY METHODS
    // =========================================================================

    /**
     * Convert exchange-native symbol (e.g. 'BTCUSDT') to CCXT format ('BTC/USDT').
     */
    protected function toCcxtSymbol(string $symbol, string $exchangeName = '', bool $isFutures = false): string
    {
        // Already has settle coin (e.g. ETH/USDT:USDT)
        if (str_contains($symbol, ':')) {
            return $symbol;
        }

        $base = $symbol;

        // Already has slash
        if (!str_contains($symbol, '/')) {
            // Convert native → CCXT
            if (str_ends_with($symbol, 'USDT')) {
                $base = str_replace('USDT', '/USDT', $symbol);
            } elseif (str_ends_with($symbol, 'USDC')) {
                $base = str_replace('USDC', '/USDC', $symbol);
            } elseif (str_ends_with($symbol, 'BUSD')) {
                $base = str_replace('BUSD', '/BUSD', $symbol);
            } else {
                $base = $symbol . '/USDT';
            }
        }

        // Bybit Futures linear: append settle coin (ETH/USDT → ETH/USDT:USDT)
        if ($isFutures && $exchangeName === 'bybit') {
            $quote = str_contains($base, '/USDC') ? 'USDC' : 'USDT';
            $base .= ':' . $quote;
        }

        return $base;
    }

    /**
     * Get the CCXT defaultType string for the exchange.
     */
    protected function getDefaultType(string $exchangeName, bool $isFutures): string
    {
        if (!$isFutures) return 'spot';
        return ($exchangeName === 'bybit') ? 'swap' : 'future';
    }

    /**
     * Wrap CCXT order result into a response-like object that is backward-compatible
     * with AccountExecutionJob (which calls ->successful(), ->json(), ->body()).
     */
    protected function wrapResponse(bool $success, $data, int $status = 200): CcxtResponse
    {
        return new CcxtResponse($success, $data, $status);
    }

    /**
     * Wrap a Laravel HTTP response into the same CcxtResponse format.
     */
    protected function wrapHttpResponse($httpResponse): CcxtResponse
    {
        return new CcxtResponse($httpResponse->successful(), $httpResponse->json() ?? [], $httpResponse->status());
    }

    /**
     * Log trade to trade_logs table (PDO via Laravel DB facade).
     */
    protected function logTrade(TradingAccount $account, string $endpoint, array $payload, $response, $signalId = null, $statusCode = null): void
    {
        try {
            $responseBody = is_array($response) ? json_encode($response) : (string) $response;

            // Priority: Explicit statusCode > payload['status_code'] > Auto-detect 200/400
            $finalStatus = $statusCode;
            if ($finalStatus === null) {
                if (str_contains($endpoint, 'ERROR')) {
                    $finalStatus = 400;
                } else {
                    $finalStatus = is_array($response) ? 200 : null;
                }
            }

            DB::table('trade_logs')->insert([
                'signal_id' => $signalId,
                'account_id' => $account->id,
                'exchange' => strtolower($account->exchange ?: 'binance'),
                'symbol' => $payload['symbol'] ?? null,
                'endpoint' => $endpoint,
                'payload' => json_encode($payload),
                'response' => $responseBody,
                'status_code' => $finalStatus,
                'client_order_id' => $payload['newClientOrderId'] ?? ($payload['orderLinkId'] ?? ($response['id'] ?? ($response['orderId'] ?? null))),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("CCXT Trade Logging Failed: " . $e->getMessage());
        }
    }
}

/**
 * Compatibility wrapper for CCXT responses.
 * Mimics Illuminate\Http\Client\Response for AccountExecutionJob.
 */
class CcxtResponse
{
    protected bool $success;
    protected $data;
    protected int $status;

    public function __construct(bool $success, $data, int $status = 200)
    {
        $this->success = $success;
        $this->data = $data;
        $this->status = $status;
    }

    public function successful(): bool
    {
        return $this->success;
    }

    public function failed(): bool
    {
        return !$this->success;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function json($key = null)
    {
        if ($key) {
            return $this->data[$key] ?? null;
        }
        return $this->data;
    }

    public function body(): string
    {
        return json_encode($this->data);
    }
}
