<?php

namespace App\Services;

use App\Models\TradingAccount;
use Illuminate\Support\Facades\App;

class ExchangeServiceFactory
{
    /**
     * Resolve the correct exchange service based on the account type.
     * 
     * @param TradingAccount $account
     * @return ExchangeInterface
     */
    public static function make(TradingAccount $account): ExchangeInterface
    {
        $exchange = strtolower($account->exchange ?: 'binance');

        if ($exchange === 'bybit') {
            return App::make(BybitService::class);
        }

        // Default to Binance
        return App::make(BinanceService::class);
    }
}
