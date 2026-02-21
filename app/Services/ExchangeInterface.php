<?php

namespace App\Services;

use App\Models\TradingAccount;

interface ExchangeInterface
{
    /**
     * Get account balances (Spot & Futures)
     */
    public function getBalance(TradingAccount $account);

    /**
     * Set leverage for a symbol (Futures only)
     */
    public function setLeverage(string $symbol, int $leverage, TradingAccount $account, $signalId = null, $isFutures = true);

    /**
     * Place a MARKET order
     */
    public function placeMarketOrder(string $symbol, string $side, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true);

    /**
     * Place a STOP_MARKET order
     */
    public function placeStopMarketOrder(string $symbol, string $side, float $stopPrice, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true);

    /**
     * Place a TAKE_PROFIT_MARKET order
     */
    public function placeTakeProfitMarketOrder(string $symbol, string $side, float $stopPrice, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true);

    /**
     * Cancel all open orders for a symbol
     */
    public function cancelAllSymbolOrders(string $symbol, TradingAccount $account, $signalId = null, $isFutures = true);

    /**
     * Close position with MARKET reduceOnly
     */
    public function closePosition(string $symbol, string $side, float $quantity, TradingAccount $account, $signalId = null, $isFutures = true);

    /**
     * Get current price (Mark Price for Futures, Ticker for Spot)
     */
    public function getMarkPrice(string $symbol, $isFutures = true);

    /**
     * Get specific asset balance (e.g. ETH in Spot)
     */
    public function getSpecificAssetBalance(TradingAccount $account, string $asset);
}
