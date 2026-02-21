<?php

namespace App\Services;

use App\Models\TradingAccount;
use Illuminate\Support\Facades\App;

class ExchangeServiceFactory
{
    /**
     * Resolve the correct exchange service based on the account type.
     * 
     * Now uses CcxtExchangeService for both Binance and Bybit.
     * The CCXT service internally resolves the correct exchange instance.
     * 
     * @param TradingAccount $account
     * @return ExchangeInterface
     */
    public static function make(TradingAccount $account): ExchangeInterface
    {
        // === NEW: Unified CCXT-based service for all exchanges ===
        return App::make(CcxtExchangeService::class);

        // === FALLBACK: Uncomment below and comment above to revert ===
        // $exchange = strtolower($account->exchange ?: 'binance');
        // if ($exchange === 'bybit') {
        //     return App::make(BybitService::class);
        // }
        // return App::make(BinanceService::class);
    }
}
