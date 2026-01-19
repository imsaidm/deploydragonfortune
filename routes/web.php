<?php

use App\Http\Controllers\BacktestResultController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\BinanceSpotController;
use App\Http\Controllers\BinanceFuturesController;
use App\Http\Controllers\QuantConnectController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'workspace')->name('workspace');
Route::view('/login', 'auth.login')->name('login');

// Profile & Auth Routes
Route::view('/profile', 'profile.show')->name('profile.show');
Route::post('/logout', LogoutController::class)->name('logout');

// Derivatives Core Routes
Route::view('/derivatives/funding-rate', 'derivatives.funding-rate')->name('derivatives.funding-rate');
Route::view('/derivatives/funding-rate-advanced', 'derivatives.funding-rate-advanced')->name('derivatives.funding-rate-advanced');
Route::view('/derivatives/open-interest', 'derivatives.open-interest')->name('derivatives.open-interest');
Route::view('/derivatives/open-interest-old', 'derivatives.open-interest-old')->name('derivatives.open-interest-old');
Route::view('/derivatives/long-short-ratio', 'derivatives.long-short-ratio-new')->name('derivatives.long-short-ratio');

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

// Backtest & Signal Placeholder Routes
Route::view('/signal-analytics', 'signal-analytics.dashboard')->name('signal-analytics.index');
Route::get('/backtest-result', [BacktestResultController::class, 'index'])->name('backtest-result.index');
Route::get('/backtest-result/{file}', [BacktestResultController::class, 'show'])
    ->where('file', '[A-Za-z0-9._-]+')
    ->name('backtest-result.show');

// CryptoQuant API Proxy Routes
Route::get('/api/cryptoquant/exchange-inflow-cdd', [App\Http\Controllers\CryptoQuantController::class, 'getExchangeInflowCDD'])->name('api.cryptoquant.exchange-inflow-cdd');
Route::get('/api/cryptoquant/btc-market-price', [App\Http\Controllers\CryptoQuantController::class, 'getBitcoinPrice'])->name('api.cryptoquant.btc-market-price');
Route::get('/api/cryptoquant/btc-price', [App\Http\Controllers\CryptoQuantController::class, 'getBitcoinPrice'])->name('api.cryptoquant.btc-price');
Route::get('/api/cryptoquant/funding-rate', [App\Http\Controllers\CryptoQuantController::class, 'getFundingRates'])->name('api.cryptoquant.funding-rate');
Route::get('/api/cryptoquant/funding-rates', [App\Http\Controllers\CryptoQuantController::class, 'getFundingRates'])->name('api.cryptoquant.funding-rates');
Route::get('/api/cryptoquant/open-interest', [App\Http\Controllers\CryptoQuantController::class, 'getOpenInterest'])->name('api.cryptoquant.open-interest');
Route::get('/api/cryptoquant/funding-rates-comparison', [App\Http\Controllers\CryptoQuantController::class, 'getFundingRatesComparison'])->name('api.cryptoquant.funding-rates-comparison');

// QuantConnect API Proxy Routes (Backtest management)
Route::prefix('api/quantconnect')->group(function () {
    Route::get('/authenticate', [QuantConnectController::class, 'authenticate'])
        ->middleware('throttle:10,1')
        ->name('api.quantconnect.authenticate');

    Route::get('/projects', [QuantConnectController::class, 'projects'])
        ->middleware('throttle:20,1')
        ->name('api.quantconnect.projects');

    Route::get('/backtests', [QuantConnectController::class, 'backtests'])
        ->middleware('throttle:20,1')
        ->name('api.quantconnect.backtests');

    // Compile
    Route::post('/compile/create', [QuantConnectController::class, 'compileCreate'])
        ->middleware('throttle:10,1')
        ->name('api.quantconnect.compile.create');
    Route::get('/compile/read', [QuantConnectController::class, 'compileRead'])
        ->middleware('throttle:30,1')
        ->name('api.quantconnect.compile.read');

    // Files
    Route::get('/files', [QuantConnectController::class, 'files'])
        ->middleware('throttle:30,1')
        ->name('api.quantconnect.files.read');
    Route::post('/files/create', [QuantConnectController::class, 'filesCreate'])
        ->middleware('throttle:20,1')
        ->name('api.quantconnect.files.create');
    Route::post('/files/update', [QuantConnectController::class, 'filesUpdate'])
        ->middleware('throttle:20,1')
        ->name('api.quantconnect.files.update');
    Route::post('/files/rename', [QuantConnectController::class, 'filesRename'])
        ->middleware('throttle:20,1')
        ->name('api.quantconnect.files.rename');
    Route::post('/files/delete', [QuantConnectController::class, 'filesDelete'])
        ->middleware('throttle:20,1')
        ->name('api.quantconnect.files.delete');

    // Backtests
    Route::post('/backtests/create', [QuantConnectController::class, 'backtestsCreate'])
        ->middleware('throttle:10,1')
        ->name('api.quantconnect.backtests.create');
    Route::get('/backtests/read', [QuantConnectController::class, 'backtestsRead'])
        ->middleware('throttle:30,1')
        ->name('api.quantconnect.backtests.read');
    Route::post('/backtests/update', [QuantConnectController::class, 'backtestsUpdate'])
        ->middleware('throttle:20,1')
        ->name('api.quantconnect.backtests.update');
    Route::post('/backtests/delete', [QuantConnectController::class, 'backtestsDelete'])
        ->middleware('throttle:20,1')
        ->name('api.quantconnect.backtests.delete');

    // Reports
    Route::get('/reports/backtest', [QuantConnectController::class, 'backtestReport'])
        ->middleware('throttle:10,1')
        ->name('api.quantconnect.reports.backtest');
});

// Binance Spot API Proxy
Route::get('/api/binance/spot/summary', [BinanceSpotController::class, 'summary'])
    ->middleware('throttle:20,1')
    ->name('api.binance.spot.summary');
Route::get('/api/binance/spot/open-orders', [BinanceSpotController::class, 'openOrders'])
    ->middleware('throttle:20,1')
    ->name('api.binance.spot.open-orders');
Route::get('/api/binance/spot/orders', [BinanceSpotController::class, 'orders'])
    ->middleware('throttle:20,1')
    ->name('api.binance.spot.orders');
Route::get('/api/binance/spot/trades', [BinanceSpotController::class, 'trades'])
    ->middleware('throttle:20,1')
    ->name('api.binance.spot.trades');

// Binance Futures API Proxy
Route::get('/api/binance/futures/summary', [BinanceFuturesController::class, 'summary'])
    ->middleware('throttle:20,1')
    ->name('api.binance.futures.summary');
Route::get('/api/binance/futures/positions', [BinanceFuturesController::class, 'positions'])
    ->middleware('throttle:20,1')
    ->name('api.binance.futures.positions');
Route::get('/api/binance/futures/open-orders', [BinanceFuturesController::class, 'openOrders'])
    ->middleware('throttle:20,1')
    ->name('api.binance.futures.open-orders');
Route::get('/api/binance/futures/orders', [BinanceFuturesController::class, 'orders'])
    ->middleware('throttle:20,1')
    ->name('api.binance.futures.orders');
Route::get('/api/binance/futures/trades', [BinanceFuturesController::class, 'trades'])
    ->middleware('throttle:20,1')
    ->name('api.binance.futures.trades');

// Coinglass API Proxy Routes

// On-Chain Metrics API Proxy
Route::prefix('api/onchain')->group(function () {
    Route::get('/metrics', [App\Http\Controllers\OnChainMetricsController::class, 'metrics'])->name('api.onchain.metrics');
    Route::get('/metrics/available', [App\Http\Controllers\OnChainMetricsController::class, 'availableMetrics'])->name('api.onchain.metrics.available');
    Route::get('/exchange-flows', [App\Http\Controllers\OnChainMetricsController::class, 'exchangeFlows'])->name('api.onchain.exchange-flows');
    Route::get('/network-activity', [App\Http\Controllers\OnChainMetricsController::class, 'networkActivity'])->name('api.onchain.network-activity');
    Route::get('/market-data', [App\Http\Controllers\OnChainMetricsController::class, 'marketData'])->name('api.onchain.market-data');
});

// Coinglass Open Interest (new proxy endpoints)
Route::prefix('api/coinglass/open-interest')->group(function () {
    Route::get('/exchanges', [App\Http\Controllers\Coinglass\OpenInterestController::class, 'exchanges']);
    Route::get('/history', [App\Http\Controllers\Coinglass\OpenInterestController::class, 'aggregatedHistory']);
    Route::get('/exchange-history', [App\Http\Controllers\Coinglass\OpenInterestController::class, 'exchangeHistory']);
});

// Coinglass Funding Rate (new proxy endpoints)
Route::prefix('api/coinglass/funding-rate')->group(function () {
    Route::get('/exchanges', [App\Http\Controllers\Coinglass\FundingRateController::class, 'exchanges']);
    Route::get('/exchange-list', [App\Http\Controllers\Coinglass\FundingRateController::class, 'exchangeList']);
    Route::get('/history', [App\Http\Controllers\Coinglass\FundingRateController::class, 'history']);
    Route::get('/current', [App\Http\Controllers\Coinglass\FundingRateController::class, 'current']);
});

// Database Funding Rate (reads from local cg_funding_rate tables)
Route::prefix('api/db/funding-rate')->group(function () {
    Route::get('/exchange-list', [App\Http\Controllers\Database\FundingRateDbController::class, 'exchangeList']);
    Route::get('/history', [App\Http\Controllers\Database\FundingRateDbController::class, 'history']);
    Route::get('/exchanges', [App\Http\Controllers\Database\FundingRateDbController::class, 'exchanges']);
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

// Chart Components Demo
Route::view('/examples/chart-components', 'examples.chart-components-demo')->name('examples.chart-components');

if (app()->isLocal()) {
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
}

// API consumption happens directly from frontend using meta api-base-url
