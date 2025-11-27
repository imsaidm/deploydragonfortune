@extends('layouts.app')

@section('title', 'Volatility & Regime Analysis | DragonFortune')

@push('head')
    <!-- Resource Hints for Faster API Loading (Critical for Hard Refresh) -->
    <link rel="dns-prefetch" href="{{ config('app.api_urls.internal') }}">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="{{ config('app.api_urls.internal') }}" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    
    <!-- Preload critical resources for faster initial load -->
    <link rel="preload" href="{{ asset('js/volatility/controller.js') }}" as="script" crossorigin="anonymous">
@endpush

@section('content')
    {{--
        Volatility & Regime Analysis Dashboard
        Monitor Bitcoin volatility and price action across multiple timeframes
        
        Data Source: Coinglass Spot Price API
        - Real-time OHLC data
        - Multiple intervals (1m to 1w)
        - Volatility metrics (ATR, HV, Regime)
    --}}

    <div class="d-flex flex-column h-100 gap-3" x-data="volatilityController()">
        <!-- Page Header -->
        <div class="derivatives-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h1 class="mb-0">Volatility & Regime Analysis</h1>
                        <span class="spinner-border spinner-border-sm text-primary" style="width: 16px; height: 16px;" x-show="priceData.length === 0" x-cloak></span>
                    </div>
                    <p class="mb-0 text-secondary">
                        Monitor Bitcoin volatility regimes and price action across multiple timeframes.
                    </p>
                </div>

                <!-- Global Controls -->
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <!-- Exchange Selector -->
                    <select class="form-select" style="width: 120px;" :value="selectedExchange" @change="changeExchange($event.target.value)">
                        <option value="Binance">Binance</option>
                        <option value="OKX">OKX</option>
                        <option value="Bybit">Bybit</option>
                    </select>

                    <!-- Symbol Selector -->
                    <select class="form-select" style="width: 120px;" :value="selectedSymbol" @change="changeSymbol($event.target.value)">
                        <option value="BTCUSDT">BTC/USDT</option>
                        <option value="ETHUSDT">ETH/USDT</option>
                    </select>

                    <!-- Interval Selector -->
                    <select class="form-select" style="width: 120px;" :value="selectedInterval" @change="changeInterval($event.target.value)">
                        <template x-for="int in intervals" :key="int.value">
                            <option :value="int.value" x-text="int.label"></option>
                        </template>
                    </select>

                    <!-- Manual Refresh -->
                    <button class="btn btn-outline-primary d-flex align-items-center gap-2" type="button"
                        @click="loadPriceData(); loadEodData();">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"/>
                            <polyline points="1 20 1 14 7 14"/>
                            <path d="M3.51 9a9 9 0 0 1 14.137-3.36L23 10"/>
                            <path d="M20.49 15a9 9 0 0 1-14.137 3.36L1 14"/>
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary Cards Row -->
        <div class="row g-3">
            <!-- Current Price -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Current Price</span>
                        <span class="badge text-bg-primary" x-show="currentPrice !== null">Latest</span>
                        <span class="badge text-bg-secondary" x-show="currentPrice === null">Loading...</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="currentPrice !== null" x-text="formatPrice(currentPrice)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="currentPrice === null">...</div>
                        <small class="text-muted" x-show="priceChange !== null">
                            <span :class="priceChange >= 0 ? 'text-success' : 'text-danger'" x-text="formatChange(priceChange)"></span>
                        </small>
                    </div>
                </div>
            </div>

            <!-- 24h High -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">24h High</span>
                        <span class="badge text-bg-success" x-show="high24h !== null">Peak</span>
                    </div>
                    <div>
                        <div class="h3 mb-1 text-success" x-show="high24h !== null" x-text="formatPrice(high24h)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="high24h === null">...</div>
                        <small class="text-muted">Highest price</small>
                    </div>
                </div>
            </div>

            <!-- 24h Low -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">24h Low</span>
                        <span class="badge text-bg-danger" x-show="low24h !== null">Bottom</span>
                    </div>
                    <div>
                        <div class="h3 mb-1 text-danger" x-show="low24h !== null" x-text="formatPrice(low24h)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="low24h === null">...</div>
                        <small class="text-muted">Lowest price</small>
                    </div>
                </div>
            </div>

            <!-- Volume -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">24h Volume</span>
                        <span class="badge text-bg-info">USD</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="volume24h !== null" x-text="formatVolume(volume24h)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="volume24h === null">...</div>
                        <small class="text-muted">Trading volume</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Volatility Metrics -->
        <div class="row g-3">
            <div class="col-12">
                <div class="df-panel p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">Volatility Metrics Analysis - End of Day</h5>
                        <span class="badge text-bg-info" x-show="eodLoading">
                            <i class="fas fa-sync fa-spin"></i> Calculating...
                        </span>
                    </div>

                    <div class="row g-3">
                        <!-- ATR -->
                        <div class="col-md-4">
                            <div class="p-3 border rounded">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="small text-secondary">ATR (14)</span>
                                    <span class="badge text-bg-info">Technical</span>
                                </div>
                                <div class="h4 mb-1" x-show="atr !== null" x-text="formatPrice(atr)"></div>
                                <div class="h4 mb-1 text-secondary" x-show="atr === null">—</div>
                                <small class="text-muted">Average True Range</small>
                            </div>
                        </div>

                        <!-- HV -->
                        <div class="col-md-4">
                            <div class="p-3 border rounded">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="small text-secondary">HV (20)</span>
                                    <span class="badge text-bg-warning">Volatility</span>
                                </div>
                                <div class="h4 mb-1" x-show="hv !== null" x-text="formatPercent(hv)"></div>
                                <div class="h4 mb-1 text-secondary" x-show="hv === null">—</div>
                                <small class="text-muted">Historical Volatility</small>
                            </div>
                        </div>

                        <!-- Regime -->
                        <div class="col-md-4">
                            <div class="p-3 border rounded">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="small text-secondary">Regime</span>
                                    <span class="badge" 
                                          :class="regime === 'low' ? 'text-bg-success' : regime === 'medium' ? 'text-bg-warning' : regime === 'high' ? 'text-bg-danger' : 'text-bg-secondary'">
                                        <span x-text="regime ? regime.toUpperCase() : 'N/A'"></span>
                                    </span>
                                </div>
                                <div class="h4 mb-1 text-capitalize" x-text="regime || '—'"></div>
                                <small class="text-muted">Current regime</small>
                            </div>
                        </div>
                    </div>

                    <!-- Regime Guide -->
                    <div class="alert alert-info mt-3 mb-0">
                        <strong>💡 Regime Guide:</strong> Low (&lt;30%) = Stable | Medium (30-60%) = Normal | High (&gt;60%) = Turbulent
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Chart (TradingView Style) -->
        <div class="row g-3">
            <div class="col-12">
                <div class="tradingview-chart-container">
                    <div class="chart-header">
                        <div class="d-flex align-items-center gap-3">
                            <h5 class="mb-0">Spot Price Chart</h5>
                            <small class="text-muted" x-text="`${selectedExchange} - ${selectedSymbol} - ${getIntervalLabel(selectedInterval)}`"></small>
                        </div>
                        <div class="chart-controls">
                            <!-- Time Range Buttons -->
                            <div class="time-range-selector">
                                <template x-for="range in timeRanges" :key="range.value">
                                    <button type="button" 
                                            class="btn btn-sm time-range-btn"
                                            :class="selectedTimeRange === range.value ? 'btn-primary' : 'btn-outline-secondary'"
                                            @click="updateTimeRange(range.value)"
                                            x-text="range.label">
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="chart-body" style="position: relative;">
                        <canvas id="volatilityMainChart"></canvas>
                    </div>
                    <div class="chart-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="chart-footer-text">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-right: 4px;">
                                    <circle cx="6" cy="6" r="5" fill="none" stroke="currentColor" stroke-width="1"/>
                                    <path d="M6 3v3l2 2" stroke="currentColor" stroke-width="1" fill="none"/>
                                </svg>
                                Price line chart shows close prices - hover for OHLC details
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
    <!-- Chart.js with Date Adapter and Plugins - Load async for faster initial render -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js" defer></script>

    <!-- Initialize Chart.js ready promise immediately (non-blocking) -->
    <script>
        // Create promise immediately (non-blocking)
        window.chartJsReady = new Promise((resolve) => {
            // Check if Chart.js already loaded (from cache or previous load)
            if (typeof Chart !== 'undefined') {
                console.log('✅ Chart.js already loaded');
                resolve();
                return;
            }
            
            // Wait for Chart.js to load (with fallback timeout)
            let checkCount = 0;
            const checkInterval = setInterval(() => {
                checkCount++;
                if (typeof Chart !== 'undefined') {
                    console.log('✅ Chart.js loaded (after', checkCount * 50, 'ms)');
                    clearInterval(checkInterval);
                    resolve();
                } else if (checkCount > 40) {
                    // Timeout after 2 seconds - resolve anyway
                    console.warn('⚠️ Chart.js load timeout, resolving anyway');
                    clearInterval(checkInterval);
                    resolve();
                }
            }, 50);
        });
    </script>

    <!-- Volatility Controller - Load with defer for non-blocking -->
    <script type="module" src="{{ asset('js/volatility/controller.js') }}" defer></script>

    <style>
        [x-cloak] { display: none !important; }
        
        /* Light Theme Chart Container (copied from Open Interest) */
        .tradingview-chart-container {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: none;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            background: rgba(59, 130, 246, 0.03);
        }

        .chart-header h5 {
            color: #1e293b;
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }

        .chart-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-body {
            padding: 20px;
            height: 500px;
            position: relative;
            background: #ffffff;
        }

        .chart-footer {
            padding: 12px 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
            background: rgba(59, 130, 246, 0.02);
        }

        .chart-footer small {
            color: #64748b;
            display: flex;
            align-items: center;
        }

        /* Pulse animation for live indicator */
        .pulse-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }

        .pulse-success {
            background-color: #22c55e;
            box-shadow: 0 0 0 rgba(34, 197, 94, 0.7);
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
            }
            50% {
                box-shadow: 0 0 0 8px rgba(34, 197, 94, 0);
            }
        }

        /* Enhanced Summary Cards */
        .df-panel {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
            border: 1px solid rgba(59, 130, 246, 0.1);
            transition: all 0.3s ease;
        }

        .df-panel:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.3);
        }

        /* Time Range Selector Buttons */
        .time-range-selector {
            display: flex;
            gap: 4px;
            background: rgba(0, 0, 0, 0.03);
            padding: 4px;
            border-radius: 8px;
        }

        .time-range-btn {
            min-width: 40px;
            padding: 4px 12px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .time-range-btn.btn-outline-secondary {
            background: transparent;
            border-color: transparent;
            color: #64748b;
        }

        .time-range-btn.btn-outline-secondary:hover {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border-color: transparent;
        }

        .time-range-btn.btn-primary {
            background: #3b82f6;
            border-color: #3b82f6;
            color: white;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
        }

        .time-range-btn.btn-primary:hover {
            background: #2563eb;
            border-color: #2563eb;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .derivatives-header h1 {
                font-size: 1.5rem;
            }
            
            .chart-body {
                height: 350px;
                padding: 12px;
            }
            
            .chart-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
        }
    </style>
@endsection
