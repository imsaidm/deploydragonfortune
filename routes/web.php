<?php

use App\Http\Controllers\SignalController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'workspace')->name('workspace');
Route::view('/login', 'auth.login')->name('login');

// Profile & Auth Routes
Route::view('/profile', 'profile.show')->name('profile.show');
Route::post('/logout', function () {
    // Logout logic akan ditambahkan nanti
    return redirect()->route('login');
})->name('logout');

// Derivatives Core Routes
Route::view('/derivatives/funding-rate', 'derivatives.funding-rate')->name('derivatives.funding-rate');
Route::view('/derivatives/open-interest', 'derivatives.open-interest')->name('derivatives.open-interest');
Route::view('/derivatives/open-interest-old', 'derivatives.open-interest-old')->name('derivatives.open-interest-old');
Route::view('/derivatives/long-short-ratio', 'derivatives.long-short-ratio-new')->name('derivatives.long-short-ratio');

// Options Metrics (Blueprint from Open Interest)
Route::view('/derivatives/options-metrics', 'derivatives.options-metrics')->name('derivatives.options-metrics');

Route::view('/derivatives/liquidations', 'derivatives.liquidations-new')->name('derivatives.liquidations');
Route::view('/derivatives/liquidations-stream', 'derivatives.liquidations-stream')->name('derivatives.liquidations-stream');
Route::view('/derivatives/liquidations-aggregated', 'derivatives.liquidations-aggregated')->name('derivatives.liquidations-aggregated');
Route::view('/derivatives/basis-term-structure', 'derivatives.basis-term-structure-new')->name('derivatives.basis-term-structure');
Route::view('/derivatives/exchange-inflow-cdd', 'derivatives.exchange-inflow-cdd')->name('derivatives.exchange-inflow-cdd');

// Spot Microstructure - Single Unified Page
Route::view('/spot-microstructure', 'spot-microstructure.unified')->name('spot-microstructure.unified');

// On-Chain Metrics Routes (CryptoQuant integrated into main dashboard)
Route::view('/onchain-metrics', 'onchain-metrics.dashboard')->name('onchain-metrics.index');
Route::view('/onchain-metrics/dashboard', 'onchain-metrics.dashboard')->name('onchain-metrics.dashboard');

// Advanced On-Chain Metrics Routes
Route::view('/onchain-ethereum', 'onchain-ethereum.dashboard')->name('onchain-ethereum.dashboard');
Route::view('/onchain-exchange', 'onchain-exchange.dashboard')->name('onchain-exchange.dashboard');
Route::view('/onchain-mining-price', 'onchain-mining-price.dashboard')->name('onchain-mining-price.dashboard');

// ETF Institutional Routes
Route::view('/etf-institutional/dashboard', 'etf-flows.dashboard')->name('etf-institutional.dashboard');
Route::view('/etf-flows', 'etf-flows.dashboard')->name('etf-flows.dashboard');

// Volatility Regime Routes
Route::view('/volatility-regime/dashboard', 'volatility-regime.dashboard')->name('volatility-regime.dashboard');

// Macro Overlay Routes
Route::view('/macro-overlay', 'macro-overlay.dashboard')->name('macro-overlay.index');
Route::view('/macro-overlay/dashboard', 'macro-overlay.dashboard')->name('macro-overlay.dashboard');
Route::view('/macro-overlay/raw-dashboard', 'macro-overlay.dashboard-legacy')->name('macro-overlay.raw-dashboard');
Route::view('/macro-overlay/dashboard-legacy', 'macro-overlay.dashboard-legacy')->name('macro-overlay.dashboard-legacy');

// Sentiment & Flow Routes
Route::view('/sentiment-flow/dashboard', 'sentiment-flow.dashboard')->name('sentiment-flow.dashboard');

// Signal & Analytics Routes
Route::view('/signal-analytics', 'signal-analytics.dashboard')->name('signal-analytics.index');

// QuantConnect Results
Route::view('/quantconnect-results', 'quantconnect.results')->name('quantconnect-results');

// CryptoQuant API Proxy Routes
Route::get('/api/cryptoquant/exchange-inflow-cdd', [App\Http\Controllers\CryptoQuantController::class, 'getExchangeInflowCDD'])->name('api.cryptoquant.exchange-inflow-cdd');
Route::get('/api/cryptoquant/btc-market-price', [App\Http\Controllers\CryptoQuantController::class, 'getBitcoinPrice'])->name('api.cryptoquant.btc-market-price');
Route::get('/api/cryptoquant/btc-price', [App\Http\Controllers\CryptoQuantController::class, 'getBitcoinPrice'])->name('api.cryptoquant.btc-price');
Route::get('/api/cryptoquant/funding-rate', [App\Http\Controllers\CryptoQuantController::class, 'getFundingRates'])->name('api.cryptoquant.funding-rate');
Route::get('/api/cryptoquant/funding-rates', [App\Http\Controllers\CryptoQuantController::class, 'getFundingRates'])->name('api.cryptoquant.funding-rates');
Route::get('/api/cryptoquant/open-interest', [App\Http\Controllers\CryptoQuantController::class, 'getOpenInterest'])->name('api.cryptoquant.open-interest');
Route::get('/api/cryptoquant/funding-rates-comparison', [App\Http\Controllers\CryptoQuantController::class, 'getFundingRatesComparison'])->name('api.cryptoquant.funding-rates-comparison');

// Internal Signal API
Route::get('/api/signal/analytics', [SignalController::class, 'show'])->name('api.signal.analytics');
Route::get('/api/signal/backtest', [SignalController::class, 'backtest'])->name('api.signal.backtest');
Route::get('/api/signal/history', [SignalController::class, 'history'])->name('api.signal.history');

// QuantConnect Backtest API
Route::prefix('api/quantconnect')->group(function () {
    Route::get('/backtests', [App\Http\Controllers\QuantConnectController::class, 'index'])->name('api.quantconnect.index');
    Route::get('/backtests/{id}', [App\Http\Controllers\QuantConnectController::class, 'show'])->name('api.quantconnect.show');
    Route::post('/backtests/import', [App\Http\Controllers\QuantConnectController::class, 'import'])->name('api.quantconnect.import');
    Route::post('/backtests/import-api', [App\Http\Controllers\QuantConnectController::class, 'importFromApi'])->name('api.quantconnect.import-api');
    Route::delete('/backtests/{id}', [App\Http\Controllers\QuantConnectController::class, 'destroy'])->name('api.quantconnect.destroy');
    Route::post('/webhook', [App\Http\Controllers\QuantConnectController::class, 'webhook'])->name('api.quantconnect.webhook');
});

// Coinglass API Proxy Routes

// On-Chain Metrics API Proxy
Route::prefix('api/onchain')->group(function () {
    Route::get('/metrics', [App\Http\Controllers\OnChainMetricsController::class, 'metrics'])->name('api.onchain.metrics');
    Route::get('/metrics/available', [App\Http\Controllers\OnChainMetricsController::class, 'availableMetrics'])->name('api.onchain.metrics.available');
    Route::get('/exchange-flows', [App\Http\Controllers\OnChainMetricsController::class, 'exchangeFlows'])->name('api.onchain.exchange-flows');
    Route::get('/network-activity', [App\Http\Controllers\OnChainMetricsController::class, 'networkActivity'])->name('api.onchain.network-activity');
    Route::get('/market-data', [App\Http\Controllers\OnChainMetricsController::class, 'marketData'])->name('api.onchain.market-data');
});
Route::get('/api/coinglass/global-account-ratio', [App\Http\Controllers\CoinglassController::class, 'getGlobalAccountRatio'])->name('api.coinglass.global-account-ratio');
Route::get('/api/coinglass/top-account-ratio', [App\Http\Controllers\CoinglassController::class, 'getTopAccountRatio'])->name('api.coinglass.top-account-ratio');
Route::get('/api/coinglass/top-position-ratio', [App\Http\Controllers\CoinglassController::class, 'getTopPositionRatio'])->name('api.coinglass.top-position-ratio');
Route::get('/api/coinglass/net-position', [App\Http\Controllers\CoinglassController::class, 'getNetPosition'])->name('api.coinglass.net-position');
Route::get('/api/coinglass/taker-buy-sell', [App\Http\Controllers\CoinglassController::class, 'getTakerBuySell'])->name('api.coinglass.taker-buy-sell');
Route::get('/api/coinglass/liquidation-coin-list', [App\Http\Controllers\CoinglassController::class, 'getLiquidationCoinList'])->name('api.coinglass.liquidation-coin-list');

// Coinglass Open Interest (new proxy endpoints)
Route::prefix('api/coinglass/open-interest')->group(function () {
    Route::get('/exchanges', [App\Http\Controllers\Coinglass\OpenInterestController::class, 'exchanges']);
    Route::get('/history', [App\Http\Controllers\Coinglass\OpenInterestController::class, 'aggregatedHistory']);
    Route::get('/exchange-history', [App\Http\Controllers\Coinglass\OpenInterestController::class, 'exchangeHistory']);
});

// Coinglass Funding Rate (new proxy endpoints)
Route::prefix('api/coinglass/funding-rate')->group(function () {
    Route::get('/exchanges', [App\Http\Controllers\Coinglass\FundingRateController::class, 'exchanges']);
    Route::get('/history', [App\Http\Controllers\Coinglass\FundingRateController::class, 'history']);
    Route::get('/current', [App\Http\Controllers\Coinglass\FundingRateController::class, 'current']);
});

// Coinglass Long-Short Ratio (new proxy endpoints)
Route::prefix('api/coinglass/long-short-ratio')->group(function () {
    Route::get('/global-account/history', [App\Http\Controllers\Coinglass\LongShortRatioController::class, 'globalAccountHistory']);
    Route::get('/top-account/history', [App\Http\Controllers\Coinglass\LongShortRatioController::class, 'topAccountHistory']);
});

// Coinglass Basis & Term Structure (new proxy endpoints)
Route::prefix('api/coinglass/basis')->group(function () {
    Route::get('/history', [App\Http\Controllers\Coinglass\BasisController::class, 'basisHistory']);
});

// Coinglass Liquidations (new proxy endpoints)
Route::prefix('api/coinglass/liquidation')->group(function () {
    Route::get('/aggregated-heatmap/model3', [App\Http\Controllers\Coinglass\LiquidationsController::class, 'heatmapModel3']);
    Route::get('/aggregated-history', [App\Http\Controllers\Coinglass\LiquidationsController::class, 'aggregatedHistory']);
});

// On-Chain Metrics (Coinglass API)
Route::prefix('api/onchain')->group(function () {
    Route::get('/exchange/assets', [App\Http\Controllers\OnChainMetricsController::class, 'getExchangeAssets']);
    Route::get('/exchange/balance/list', [App\Http\Controllers\OnChainMetricsController::class, 'getExchangeBalanceList']);
    Route::get('/exchange/balance/chart', [App\Http\Controllers\OnChainMetricsController::class, 'getExchangeBalanceChart']);
    Route::get('/chain/transactions', [App\Http\Controllers\OnChainMetricsController::class, 'getChainTransactionList']);
    Route::get('/whale-transfers', [App\Http\Controllers\OnChainMetricsController::class, 'getWhaleTransfers']);
});

// Spot Microstructure (Coinglass API - All Endpoints)
Route::prefix('api/spot-microstructure')->group(function () {
    // Basic endpoints
    Route::get('/supported-coins', [App\Http\Controllers\SpotMicrostructureController::class, 'getSupportedCoins']);
    Route::get('/supported-exchange-pairs', [App\Http\Controllers\SpotMicrostructureController::class, 'getSupportedExchangePairs']);
    Route::get('/coins-markets', [App\Http\Controllers\SpotMicrostructureController::class, 'getCoinsMarkets']);
    Route::get('/pairs-markets', [App\Http\Controllers\SpotMicrostructureController::class, 'getPairsMarkets']);
    Route::get('/price-history', [App\Http\Controllers\SpotMicrostructureController::class, 'getPriceHistory']);
    
    // Orderbook endpoints
    Route::get('/orderbook/ask-bids-history', [App\Http\Controllers\SpotMicrostructureController::class, 'getOrderbookAskBidsHistory']);
    Route::get('/orderbook/aggregated-history', [App\Http\Controllers\SpotMicrostructureController::class, 'getAggregatedOrderbookHistory']);
    Route::get('/orderbook/history', [App\Http\Controllers\SpotMicrostructureController::class, 'getOrderbookHistory']);
    Route::get('/orderbook/large-limit-order', [App\Http\Controllers\SpotMicrostructureController::class, 'getLargeLimitOrder']);
    Route::get('/orderbook/large-limit-order-history', [App\Http\Controllers\SpotMicrostructureController::class, 'getLargeLimitOrderHistory']);
    
    // Taker volume endpoints
    Route::get('/taker-volume/history', [App\Http\Controllers\SpotMicrostructureController::class, 'getTakerBuySellVolumeHistory']);
    Route::get('/taker-volume/aggregated-history', [App\Http\Controllers\SpotMicrostructureController::class, 'getAggregatedTakerVolumeHistory']);
    Route::get('/volume-footprint/history', [App\Http\Controllers\SpotMicrostructureController::class, 'getVolumeFootprintHistory']);
});

// Coinglass ETF Flows (new proxy endpoints)
Route::prefix('api/coinglass/etf-flows')->group(function () {
    // Daily Flows (Aggregated)
    Route::get('/history', [App\Http\Controllers\Coinglass\EtfFlowsController::class, 'flowHistory']);
    
    // ETF List (Real-time comparison data)
    Route::get('/list', [App\Http\Controllers\Coinglass\EtfFlowsController::class, 'etfList']);
    
    // Premium/Discount History (Per ETF)
    Route::get('/premium-discount', [App\Http\Controllers\Coinglass\EtfFlowsController::class, 'premiumDiscountHistory']);
    
    // Flow Breakdown (Per ETF from aggregated data)
    Route::get('/breakdown', [App\Http\Controllers\Coinglass\EtfFlowsController::class, 'flowBreakdown']);
    
    // CME Futures Open Interest
    Route::get('/cme-oi', [App\Http\Controllers\Coinglass\EtfFlowsController::class, 'cmeOpenInterest']);
});

// Coinglass Volatility & Regime Analysis
Route::prefix('api/coinglass/volatility')->group(function () {
    // Spot Price History (OHLC)
    Route::get('/price-history', [App\Http\Controllers\Coinglass\VolatilityRegimeController::class, 'priceHistory']);
    
    // End-of-Day data (for ATR/HV/RV calculations)
    Route::get('/eod', [App\Http\Controllers\Coinglass\VolatilityRegimeController::class, 'eod']);
});

// Coinglass Sentiment & Flow Analysis
Route::prefix('api/coinglass/sentiment')->group(function () {
    // Fear & Greed Index History
    Route::get('/fear-greed', [App\Http\Controllers\Coinglass\SentimentFlowController::class, 'fearGreedIndex']);
    
    // Funding Rate Dominance (Exchange List)
    Route::get('/funding-dominance', [App\Http\Controllers\Coinglass\SentimentFlowController::class, 'fundingDominance']);
    
    // Whale Alerts (Hyperliquid)
    Route::get('/whale-alerts', [App\Http\Controllers\Coinglass\SentimentFlowController::class, 'whaleAlerts']);
    
    // Whale Transfers (On-Chain)
    Route::get('/whale-transfers', [App\Http\Controllers\Coinglass\SentimentFlowController::class, 'whaleTransfers']);
});

// Coinglass Macro Overlay (FRED + Bitcoin vs M2)
Route::prefix('api/coinglass/macro-overlay')->group(function () {
    // FRED Multiple Series
    Route::get('/fred', [App\Http\Controllers\Coinglass\MacroOverlayController::class, 'fredMultiSeries']);
    
    // FRED Latest Values (must be before {seriesId} route to avoid conflict)
    Route::get('/fred-latest', [App\Http\Controllers\Coinglass\MacroOverlayController::class, 'fredLatest']);
    
    // FRED Single Series
    Route::get('/fred/{seriesId}', [App\Http\Controllers\Coinglass\MacroOverlayController::class, 'fredSingleSeries']);
    
    // Bitcoin vs Global M2
    Route::get('/bitcoin-m2', [App\Http\Controllers\Coinglass\MacroOverlayController::class, 'bitcoinVsM2']);
});

Route::get('/api/coinglass/liquidation-aggregated-history', [App\Http\Controllers\CoinglassController::class, 'getLiquidationAggregatedHistory'])->name('api.coinglass.liquidation-aggregated-history');
Route::get('/api/coinglass/liquidation-exchange-list', [App\Http\Controllers\CoinglassController::class, 'getLiquidationExchangeList'])->name('api.coinglass.liquidation-exchange-list');
Route::get('/api/coinglass/liquidation-history', [App\Http\Controllers\CoinglassController::class, 'getLiquidationHistory'])->name('api.coinglass.liquidation-history');
Route::get('/api/coinglass/liquidation-summary', [App\Http\Controllers\CoinglassController::class, 'getLiquidationSummary'])->name('api.coinglass.liquidation-summary');

// Chart Components Demo
Route::view('/examples/chart-components', 'examples.chart-components-demo')->name('examples.chart-components');

// Test Funding Rates API
Route::get('/test/funding-rates-debug', function() {
    try {
        $controller = new App\Http\Controllers\CryptoQuantController();
        $request = new Illuminate\Http\Request([
            'start_date' => now()->subDays(7)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'exchange' => 'binance'
        ]);
        
        return $controller->getFundingRates($request);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Test failed',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
})->name('test.funding-rates-debug');

// Test Open Interest API
Route::get('/test/open-interest-debug', function() {
    try {
        $controller = new App\Http\Controllers\CryptoQuantController();
        $request = new Illuminate\Http\Request([
            'start_date' => now()->subDays(7)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'exchange' => 'binance'
        ]);
        
        return $controller->getOpenInterest($request);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Test failed',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
})->name('test.open-interest-debug');

// Test CDD API
Route::get('/test/cdd-debug', function() {
    try {
        $controller = new App\Http\Controllers\CryptoQuantController();
        $request = new Illuminate\Http\Request([
            'start_date' => now()->subDays(7)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'exchange' => 'binance'
        ]);
        
        return $controller->getExchangeInflowCDD($request);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Test failed',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
})->name('test.cdd-debug');

// Test CoinGlass API Integration
Route::get('/test/coinglass-integration', function() {
    try {
        $controller = new App\Http\Controllers\SpotMicrostructureController();
        $results = [];
        
        // Test large trades
        $request = new Illuminate\Http\Request(['symbol' => 'BTCUSDT', 'limit' => 5]);
        $largeTrades = $controller->getCoinglassLargeTrades($request);
        $results['large_trades'] = $largeTrades->getData(true);
        
        // Test spot flow
        $request = new Illuminate\Http\Request(['symbol' => 'BTCUSDT', 'limit' => 5]);
        $spotFlow = $controller->getCoinglassSpotFlow($request);
        $results['spot_flow'] = $spotFlow->getData(true);
        
        // Test hybrid large orders
        $request = new Illuminate\Http\Request(['symbol' => 'BTCUSDT', 'limit' => 5, 'min_notional' => 100000]);
        $hybridOrders = $controller->getLargeOrders($request);
        $results['hybrid_orders'] = $hybridOrders->getData(true);
        
        return response()->json([
            'success' => true,
            'test_results' => $results,
            'summary' => [
                'coinglass_large_trades_count' => count($results['large_trades']['data'] ?? []),
                'coinglass_spot_flow_count' => count($results['spot_flow']['data'] ?? []),
                'hybrid_orders_count' => count($results['hybrid_orders']['data'] ?? []),
                'coinglass_enabled' => env('SPOT_USE_COINGLASS', true),
                'stub_data_enabled' => env('SPOT_STUB_DATA', true),
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'CoinGlass integration test failed',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
})->name('test.coinglass-integration');

// Test CDD API with different exchanges
Route::get('/test/cdd-all-exchanges', function() {
    try {
        $controller = new App\Http\Controllers\CryptoQuantController();
        $exchanges = ['binance', 'coinbase', 'kraken', 'bitfinex', 'huobi', 'okex', 'bybit', 'bitstamp', 'gemini'];
        $results = [];
        
        foreach ($exchanges as $exchange) {
            $request = new Illuminate\Http\Request([
                'start_date' => '2025-10-22',
                'end_date' => '2025-10-23',
                'exchange' => $exchange
            ]);
            
            try {
                $response = $controller->getExchangeInflowCDD($request);
                $data = $response->getData(true);
                
                if ($data['success'] && !empty($data['data'])) {
                    $oct22Data = collect($data['data'])->firstWhere('date', '2025-10-22');
                    $results[$exchange] = [
                        'success' => true,
                        'oct_22_value' => $oct22Data['value'] ?? 'No data',
                        'total_points' => count($data['data'])
                    ];
                } else {
                    $results[$exchange] = [
                        'success' => false,
                        'error' => 'No data returned'
                    ];
                }
            } catch (\Exception $e) {
                $results[$exchange] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'comparison_date' => '2025-10-22',
            'cryptoquant_web_value' => '193.2K',
            'our_values' => $results,
            'analysis' => [
                'note' => 'Comparing Oct 22 values across exchanges',
                'web_vs_api_difference' => 'CryptoQuant web shows 193.2K, our API shows much lower values'
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Test failed',
            'message' => $e->getMessage()
        ], 500);
    }
})->name('test.cdd-all-exchanges');



// Test Liquidation Summary
Route::get('/test/liquidation-summary', function() {
    try {
        $controller = new App\Http\Controllers\CoinglassController();
        $request = new Illuminate\Http\Request([
            'symbol' => 'BTC'
        ]);
        
        return $controller->getLiquidationSummary($request);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Test failed',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
})->name('test.liquidation-summary');

// Test Coinglass API endpoints
Route::get('/test/coinglass-debug', function() {
    try {
        $controller = new App\Http\Controllers\CoinglassController();
        
        $results = [];
        
        // Test Global Account Ratio
        $request1 = new Illuminate\Http\Request([
            'exchange' => 'Binance',
            'symbol' => 'BTCUSDT',
            'interval' => '1h',
            'limit' => 50
        ]);
        $results['global_account'] = $controller->getGlobalAccountRatio($request1)->getData(true);
        
        // Test Top Account Ratio
        $request2 = new Illuminate\Http\Request([
            'exchange' => 'Binance',
            'symbol' => 'BTCUSDT',
            'interval' => '1h',
            'limit' => 5
        ]);
        $results['top_account'] = $controller->getTopAccountRatio($request2)->getData(true);
        
        // Test Top Position Ratio
        $request3 = new Illuminate\Http\Request([
            'exchange' => 'Binance',
            'symbol' => 'BTCUSDT',
            'interval' => '1h',
            'limit' => 5
        ]);
        $results['top_position'] = $controller->getTopPositionRatio($request3)->getData(true);
        
        // Test Net Position
        $request4 = new Illuminate\Http\Request([
            'exchange' => 'Binance',
            'symbol' => 'BTCUSDT',
            'interval' => '1h',
            'limit' => 5
        ]);
        $results['net_position'] = $controller->getNetPosition($request4)->getData(true);
        
        // Test Taker Buy/Sell
        $request5 = new Illuminate\Http\Request([
            'symbol' => 'BTC',
            'range' => '1h'
        ]);
        $results['taker_buysell'] = $controller->getTakerBuySell($request5)->getData(true);
        
        // Test Liquidation Coin List
        $request6 = new Illuminate\Http\Request([
            'exchange' => 'Binance'
        ]);
        $results['liquidation_coinlist'] = $controller->getLiquidationCoinList($request6)->getData(true);
        
        // Test Liquidation Aggregated History
        $request7 = new Illuminate\Http\Request([
            'exchange_list' => 'Binance',
            'symbol' => 'BTC',
            'interval' => '1h',
            'limit' => 10
        ]);
        $results['liquidation_aggregated'] = $controller->getLiquidationAggregatedHistory($request7)->getData(true);
        
        // Test Liquidation Exchange List
        $request8 = new Illuminate\Http\Request([
            'symbol' => 'BTC',
            'range' => '1h'
        ]);
        $results['liquidation_exchange'] = $controller->getLiquidationExchangeList($request8)->getData(true);
        
        // Test Liquidation History
        $request9 = new Illuminate\Http\Request([
            'exchange' => 'Binance',
            'symbol' => 'BTCUSDT',
            'interval' => '1h',
            'limit' => 10
        ]);
        $results['liquidation_history'] = $controller->getLiquidationHistory($request9)->getData(true);
        
        return response()->json([
            'success' => true,
            'timestamp' => now()->toISOString(),
            'results' => $results
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Test failed',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
})->name('test.coinglass-debug');

// API consumption happens directly from frontend using meta api-base-url
