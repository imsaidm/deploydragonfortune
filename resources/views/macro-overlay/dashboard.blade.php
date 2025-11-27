@extends('layouts.app')

@section('title', 'Macro Overlay | DragonFortune')

@push('head')
    <!-- Resource Hints for Faster API Loading -->
    <link rel="dns-prefetch" href="{{ config('app.api_urls.internal') }}">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="{{ config('app.api_urls.internal') }}" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="{{ asset('js/macro-overlay/controller.js') }}" as="script" crossorigin="anonymous">
@endpush

@section('content')
    {{--
        Macro Overlay Dashboard
        Monitor key macro indicators and their impact on Bitcoin
        
        Data Sources:
        - FRED API: DXY, Treasury Yields, Fed Funds, CPI, M2, RRP, TGA
        - Coinglass: Bitcoin vs Global M2 ratio
    --}}

    <div class="d-flex flex-column h-100 gap-3" x-data="macroController()">
        <!-- Page Header -->
        <div class="derivatives-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h1 class="mb-0">Macro Overlay</h1>
                        <span class="spinner-border spinner-border-sm text-primary" style="width: 16px; height: 16px;" x-show="Object.keys(latestData).length === 0" x-cloak></span>
                    </div>
                    <p class="mb-0 text-secondary">
                        Monitor key macro economic indicators and their relationship with Bitcoin.
                    </p>
                </div>

                <!-- Global Controls -->
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <!-- Data Range Selector -->
                    <select class="form-select" style="width: 140px;" :value="dataLimit" @change="changeDataLimit(parseInt($event.target.value))">
                        <template x-for="opt in limitOptions" :key="opt.value">
                            <option :value="opt.value" x-text="opt.label"></option>
                        </template>
                    </select>

                    <!-- Manual Refresh -->
                    <button class="btn btn-outline-primary d-flex align-items-center gap-2" type="button"
                        @click="loadAllData()">
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

        <!-- Latest Macro Indicators Cards -->
        <div class="row g-3">
            <!-- DXY -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">DXY (Dollar Index)</span>
                        <span class="badge text-bg-primary" x-show="latestData.DTWEXBGS">Latest</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="latestData.DTWEXBGS" x-text="formatNumber(latestData.DTWEXBGS?.value, 2)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="!latestData.DTWEXBGS">...</div>
                        <small class="text-muted" x-show="latestData.DTWEXBGS" x-text="formatDate(latestData.DTWEXBGS?.date)"></small>
                    </div>
                </div>
            </div>

            <!-- 10Y Yield -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">10Y Treasury Yield</span>
                        <span class="badge text-bg-success" x-show="latestData.DGS10">Yield</span>
                    </div>
                    <div>
                        <div class="h3 mb-1 text-success" x-show="latestData.DGS10" x-text="formatPercent(latestData.DGS10?.value, 2)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="!latestData.DGS10">...</div>
                        <small class="text-muted" x-show="latestData.DGS10" x-text="formatDate(latestData.DGS10?.date)"></small>
                    </div>
                </div>
            </div>

            <!-- 2Y Yield -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">2Y Treasury Yield</span>
                        <span class="badge text-bg-info" x-show="latestData.DGS2">Yield</span>
                    </div>
                    <div>
                        <div class="h3 mb-1 text-info" x-show="latestData.DGS2" x-text="formatPercent(latestData.DGS2?.value, 2)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="!latestData.DGS2">...</div>
                        <small class="text-muted" x-show="latestData.DGS2" x-text="formatDate(latestData.DGS2?.date)"></small>
                    </div>
                </div>
            </div>

            <!-- Fed Funds Rate -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Fed Funds Rate</span>
                        <span class="badge text-bg-danger" x-show="latestData.DFF">Rate</span>
                    </div>
                    <div>
                        <div class="h3 mb-1 text-danger" x-show="latestData.DFF" x-text="formatPercent(latestData.DFF?.value, 2)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="!latestData.DFF">...</div>
                        <small class="text-muted" x-show="latestData.DFF" x-text="formatDate(latestData.DFF?.date)"></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Row: Liquidity Metrics -->
        <div class="row g-3">
            <!-- CPI -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">CPI (Inflation)</span>
                        <span class="badge text-bg-warning" x-show="latestData.CPIAUCSL">Index</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="latestData.CPIAUCSL" x-text="formatNumber(latestData.CPIAUCSL?.value, 2)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="!latestData.CPIAUCSL">...</div>
                        <small class="text-muted" x-show="latestData.CPIAUCSL" x-text="formatDate(latestData.CPIAUCSL?.date)"></small>
                    </div>
                </div>
            </div>

            <!-- M2 Money Supply -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">M2 Money Supply</span>
                        <span class="badge text-bg-primary" x-show="latestData.M2SL">Billions</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="latestData.M2SL" x-text="formatLargeNumber(latestData.M2SL?.value * 1e9)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="!latestData.M2SL">...</div>
                        <small class="text-muted" x-show="latestData.M2SL" x-text="formatDate(latestData.M2SL?.date)"></small>
                    </div>
                </div>
            </div>

            <!-- Reverse Repo -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Reverse Repo</span>
                        <span class="badge text-bg-info" x-show="latestData.RRPONTSYD">Billions</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="latestData.RRPONTSYD" x-text="formatLargeNumber(latestData.RRPONTSYD?.value * 1e9)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="!latestData.RRPONTSYD">...</div>
                        <small class="text-muted" x-show="latestData.RRPONTSYD" x-text="formatDate(latestData.RRPONTSYD?.date)"></small>
                    </div>
                </div>
            </div>

            <!-- TGA -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Treasury General Account</span>
                        <span class="badge text-bg-success" x-show="latestData.WTREGEN">Billions</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="latestData.WTREGEN" x-text="formatLargeNumber(latestData.WTREGEN?.value * 1e9)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="!latestData.WTREGEN">...</div>
                        <small class="text-muted" x-show="latestData.WTREGEN" x-text="formatDate(latestData.WTREGEN?.date)"></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row g-3">
            <!-- DXY Chart -->
            <div class="col-md-6">
                <div class="tradingview-chart-container">
                    <div class="chart-header">
                        <h5 class="mb-0">DXY (Dollar Index)</h5>
                    </div>
                    <div class="chart-body" style="height: 300px;">
                        <canvas id="dxyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- 10Y Yield Chart -->
            <div class="col-md-6">
                <div class="tradingview-chart-container">
                    <div class="chart-header">
                        <h5 class="mb-0">10Y Treasury Yield</h5>
                    </div>
                    <div class="chart-body" style="height: 300px;">
                        <canvas id="yield10yChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Row of Charts -->
        <div class="row g-3">
            <!-- 2Y Yield Chart -->
            <div class="col-md-6">
                <div class="tradingview-chart-container">
                    <div class="chart-header">
                        <h5 class="mb-0">2Y Treasury Yield</h5>
                    </div>
                    <div class="chart-body" style="height: 300px;">
                        <canvas id="yield2yChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Fed Funds Rate Chart -->
            <div class="col-md-6">
                <div class="tradingview-chart-container">
                    <div class="chart-header">
                        <h5 class="mb-0">Fed Funds Rate</h5>
                    </div>
                    <div class="chart-body" style="height: 300px;">
                        <canvas id="fedFundsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bitcoin vs M2 Chart -->
        <div class="row g-3">
            <div class="col-12">
                <div class="tradingview-chart-container">
                    <div class="chart-header">
                        <div class="d-flex align-items-center gap-3">
                            <h5 class="mb-0">Bitcoin vs Global M2 Growth</h5>
                            <small class="text-muted">Bitcoin price vs global money supply correlation</small>
                        </div>
                    </div>
                    <div class="chart-body" style="height: 400px;">
                        <canvas id="bitcoinM2Chart"></canvas>
                    </div>
                    <div class="chart-footer">
                        <small class="chart-footer-text">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-right: 4px;">
                                <circle cx="6" cy="6" r="5" fill="none" stroke="currentColor" stroke-width="1"/>
                                <path d="M6 3v3l2 2" stroke="currentColor" stroke-width="1" fill="none"/>
                            </svg>
                            Higher BTC price relative to M2 growth indicates Bitcoin is outperforming money supply expansion
                        </small>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
    <!-- Chart.js with Date Adapter and Plugins -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js" defer></script>

    <!-- Initialize Chart.js ready promise -->
    <script>
        window.chartJsReady = new Promise((resolve) => {
            if (typeof Chart !== 'undefined') {
                console.log('✅ Chart.js already loaded');
                resolve();
                return;
            }
            
            let checkCount = 0;
            const checkInterval = setInterval(() => {
                checkCount++;
                if (typeof Chart !== 'undefined') {
                    console.log('✅ Chart.js loaded (after', checkCount * 50, 'ms)');
                    clearInterval(checkInterval);
                    resolve();
                } else if (checkCount > 40) {
                    console.warn('⚠️ Chart.js load timeout, resolving anyway');
                    clearInterval(checkInterval);
                    resolve();
                }
            }, 50);
        });
    </script>

    <!-- Macro Overlay Controller -->
    <script type="module" src="{{ asset('js/macro-overlay/controller.js') }}" defer></script>

    <style>
        [x-cloak] { display: none !important; }
        
        /* Light Theme Chart Container */
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

        .chart-body {
            padding: 20px;
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

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .derivatives-header h1 {
                font-size: 1.5rem;
            }
            
            .chart-body {
                height: 250px !important;
                padding: 12px;
            }
        }
    </style>
@endsection
