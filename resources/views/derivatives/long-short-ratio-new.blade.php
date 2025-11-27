@extends('layouts.app')

@section('title', 'Long-Short Ratio | DragonFortune')

@push('head')
    <!-- Resource Hints for Faster API Loading -->
    <link rel="dns-prefetch" href="{{ config('app.api_urls.internal') }}">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="{{ config('app.api_urls.internal') }}" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    
    <!-- Preload critical resources for faster initial load -->
    <link rel="preload" href="{{ asset('js/long-short-ratio-controller.js') }}" as="script" crossorigin="anonymous">
@endpush

@section('content')
    {{--
        Bitcoin: Long-Short Ratio Dashboard (Coinglass)
        Think like a trader • Build like an engineer • Visualize like a designer

        Interpretasi Trading:
        - Long-Short Ratio mengukur sentimen pasar
        - Ratio > 1 = More longs (bullish sentiment)
        - Ratio < 1 = More shorts (bearish sentiment)
        - Global Account = All traders sentiment
        - Top Account = Smart money sentiment
    --}}

    <div class="d-flex flex-column h-100 gap-3" x-data="longShortRatioController()">
        <!-- Page Header -->
        <div class="derivatives-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h1 class="mb-0">Long-Short Ratio</h1>
                        <span class="spinner-border spinner-border-sm text-primary" style="width: 16px; height: 16px;" x-show="globalRawData.length === 0" x-cloak></span>
                    </div>
                    <p class="mb-0 text-secondary">
                        Pantau sentimen pasar melalui Long-Short Ratio. Global Account menunjukkan sentimen retail, Top Account menunjukkan smart money.
                    </p>
                </div>

                <!-- Global Controls -->
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <!-- Symbol Selector (Verified Data Available) -->
                    <select class="form-select" style="width: 120px;" :value="selectedSymbol" @change="updateSymbol($event.target.value)">
                        <option value="BTC">BTC</option>
                        <option value="ETH">ETH</option>
                        <option value="SOL">SOL</option>
                        <option value="BNB">BNB</option>
                        <option value="XRP">XRP</option>
                        <option value="ADA">ADA</option>
                        <option value="DOGE">DOGE</option>
                        <option value="AVAX">AVAX</option>
                        <option value="TON">TON</option>
                        <option value="SUI">SUI</option>
                    </select>

                    <!-- Exchange Selector (Only exchanges with data) -->
                    <select class="form-select" style="width: 120px;" :value="selectedExchange" @change="updateExchange($event.target.value)">
                        <option value="Binance">Binance</option>
                        <option value="Bybit">Bybit</option>
                    </select>

                    <!-- Interval Selector (API Compliant) - SAME AS OPEN INTEREST -->
                    <select class="form-select" style="width: 120px;" :value="selectedInterval" @change="updateInterval($event.target.value)">
                        <option value="1m">1M</option>
                        <option value="3m">3M</option>
                        <option value="5m">5M</option>
                        <option value="15m">15M</option>
                        <option value="30m">30M</option>
                        <option value="1h">1H</option>
                        <option value="4h">4H</option>
                        <option value="6h">6H</option>
                        <option value="8h">8H</option>
                        <option value="12h">12H</option>
                        <option value="1d">1D</option>
                        <option value="1w">1W</option>
                    </select>

                    <!-- Date Range Selector -->
                    <select class="form-select" style="width: 120px;" :value="selectedTimeRange" @change="updateTimeRange($event.target.value)">
                        <template x-for="range in timeRanges" :key="range.value">
                            <option :value="range.value" x-text="range.label"></option>
                        </template>
                    </select>

                    <!-- Manual Refresh -->
                    <button class="btn btn-outline-primary d-flex align-items-center gap-2" type="button"
                        @click="refreshAll()">
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

        <!-- Summary Cards Row - Global Account -->
        <div class="row g-3">
            <div class="col-12">
                <h5 class="mb-3">Global Account (All Traders)</h5>
            </div>
            
            <!-- Current Ratio - Global -->
            <div class="col-md-4">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Current Ratio</span>
                        <span class="badge" :class="getSentimentBadge(globalCurrentRatio)" x-show="globalCurrentRatio !== null" x-text="getSentiment(globalCurrentRatio)"></span>
                        <span class="badge text-bg-secondary" x-show="globalCurrentRatio === null">Loading...</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="globalCurrentRatio !== null" x-text="formatRatio(globalCurrentRatio)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="globalCurrentRatio === null">...</div>
                        <small class="text-muted" x-show="globalChange !== null">
                            <span :class="globalChange >= 0 ? 'text-success' : 'text-danger'" x-text="formatChange(globalChange)"></span>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Range (H/L) - Global -->
            <div class="col-md-4">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Range (H/L)</span>
                        <span class="badge text-bg-info" x-show="globalMaxRatio && globalMinRatio">Range</span>
                        <span class="badge text-bg-secondary" x-show="!globalMaxRatio || !globalMinRatio">Loading...</span>
                    </div>
                    <div>
                        <div x-show="globalMaxRatio && globalMinRatio">
                            <div class="h5 mb-1 text-danger" x-text="formatRatio(globalMaxRatio)"></div>
                            <div class="h5 mb-1 text-success" x-text="formatRatio(globalMinRatio)"></div>
                        </div>
                        <div class="h3 mb-1 text-secondary" x-show="!globalMaxRatio || !globalMinRatio">...</div>
                    </div>
                </div>
            </div>

            <!-- Average Ratio - Global -->
            <div class="col-md-4">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Avg Ratio</span>
                        <span class="badge" :class="getSentimentBadge(globalAvgRatio)" x-show="globalAvgRatio !== null" x-text="getSentiment(globalAvgRatio)"></span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="globalAvgRatio !== null" x-text="formatRatio(globalAvgRatio)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="globalAvgRatio === null">...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards Row - Top Account -->
        <div class="row g-3">
            <div class="col-12">
                <h5 class="mb-3">Top Account (Smart Money)</h5>
            </div>
            
            <!-- Current Ratio - Top -->
            <div class="col-md-4">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Current Ratio</span>
                        <span class="badge" :class="getSentimentBadge(topCurrentRatio)" x-show="topCurrentRatio !== null" x-text="getSentiment(topCurrentRatio)"></span>
                        <span class="badge text-bg-secondary" x-show="topCurrentRatio === null">Loading...</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="topCurrentRatio !== null" x-text="formatRatio(topCurrentRatio)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="topCurrentRatio === null">...</div>
                        <small class="text-muted" x-show="topChange !== null">
                            <span :class="topChange >= 0 ? 'text-success' : 'text-danger'" x-text="formatChange(topChange)"></span>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Range (H/L) - Top -->
            <div class="col-md-4">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Range (H/L)</span>
                        <span class="badge text-bg-info" x-show="topMaxRatio && topMinRatio">Range</span>
                        <span class="badge text-bg-secondary" x-show="!topMaxRatio || !topMinRatio">Loading...</span>
                    </div>
                    <div>
                        <div x-show="topMaxRatio && topMinRatio">
                            <div class="h5 mb-1 text-danger" x-text="formatRatio(topMaxRatio)"></div>
                            <div class="h5 mb-1 text-success" x-text="formatRatio(topMinRatio)"></div>
                        </div>
                        <div class="h3 mb-1 text-secondary" x-show="!topMaxRatio || !topMinRatio">...</div>
                    </div>
                </div>
            </div>

            <!-- Average Ratio - Top -->
            <div class="col-md-4">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Avg Ratio</span>
                        <span class="badge" :class="getSentimentBadge(topAvgRatio)" x-show="topAvgRatio !== null" x-text="getSentiment(topAvgRatio)"></span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="topAvgRatio !== null" x-text="formatRatio(topAvgRatio)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="topAvgRatio === null">...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Global Account Chart -->
        <div class="row g-3">
            <div class="col-12">
                <div class="tradingview-chart-container">
                    <div class="chart-header">
                        <div class="d-flex align-items-center gap-3">
                            <h5 class="mb-0">Global Account Long-Short Ratio</h5>
                            <div class="chart-info">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="current-value" x-text="globalCurrentRatio !== null ? formatRatio(globalCurrentRatio) : '--'"></span>
                                </div>
                            </div>
                        </div>
                        <div class="chart-controls">
                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <!-- Time Range Buttons -->
                                <div class="time-range-selector">
                                    <template x-for="range in timeRanges" :key="range.value">
                                        <button type="button" 
                                                class="btn btn-sm time-range-btn"
                                                :class="selectedTimeRange === range.value ? 'btn-primary' : 'btn-outline-secondary'"
                                                @click="setTimeRange(range.value)"
                                                x-text="range.label">
                                        </button>
                                    </template>
                                </div>

                                <!-- Interval Dropdown -->
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle interval-dropdown-btn" 
                                            type="button" 
                                            data-bs-toggle="dropdown" 
                                            :title="'Chart Interval: ' + (chartIntervals.find(i => i.value === selectedInterval)?.label || '1H')">
                                        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" class="me-1">
                                            <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                                        </svg>
                                        <span x-text="chartIntervals.find(i => i.value === selectedInterval)?.label || '1H'"></span>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <template x-for="interval in chartIntervals" :key="interval.value">
                                            <li>
                                                <a class="dropdown-item" 
                                                   href="#" 
                                                   @click.prevent="setChartInterval(interval.value)"
                                                   :class="selectedInterval === interval.value ? 'active' : ''"
                                                   x-text="interval.label">
                                                </a>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="chart-body" style="position: relative;">
                        <canvas id="globalAccountChart"></canvas>
                    </div>
                    <div class="chart-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="chart-footer-text text-secondary">
                                📊 Ratio > 1 menunjukkan lebih banyak long positions (bullish sentiment)
                            </small>
                            <small class="text-muted">
                                <span class="badge text-bg-success">Coinglass API</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Account Chart -->
        <div class="row g-3">
            <div class="col-12">
                <div class="tradingview-chart-container">
                    <div class="chart-header">
                        <div class="d-flex align-items-center gap-3">
                            <h5 class="mb-0">Top Account Long-Short Ratio (Smart Money)</h5>
                            <div class="chart-info">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="current-value" x-text="topCurrentRatio !== null ? formatRatio(topCurrentRatio) : '--'"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="chart-body" style="position: relative;">
                        <canvas id="topAccountChart"></canvas>
                    </div>
                    <div class="chart-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="chart-footer-text text-secondary">
                                🎯 Top traders sentiment - often contrarian to retail
                            </small>
                            <small class="text-muted">
                                <span class="badge text-bg-success">Coinglass API</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trading Interpretation -->
        <div class="row g-3">
            <div class="col-12">
                <div class="df-panel p-4">
                    <h5 class="mb-3">📚 Memahami Long-Short Ratio</h5>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="p-3 rounded" style="background: rgba(34, 197, 94, 0.1); border-left: 4px solid #22c55e;">
                                <div class="fw-bold mb-2 text-success">🟢 Ratio > 1 (Bullish)</div>
                                <div class="small text-secondary">
                                    <ul class="mb-0 ps-3">
                                        <li>Lebih banyak long positions</li>
                                        <li>Sentimen bullish dominan</li>
                                        <li>Pasar optimis terhadap kenaikan harga</li>
                                        <li>Strategi: Hati-hati dengan overcrowding</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-3 rounded" style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444;">
                                <div class="fw-bold mb-2 text-danger">🔴 Ratio < 1 (Bearish)</div>
                                <div class="small text-secondary">
                                    <ul class="mb-0 ps-3">
                                        <li>Lebih banyak short positions</li>
                                        <li>Sentimen bearish dominan</li>
                                        <li>Pasar pesimis terhadap harga</li>
                                        <li>Strategi: Cari peluang untuk long</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-3 rounded" style="background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6;">
                                <div class="fw-bold mb-2 text-primary">⚡ Global vs Top Account</div>
                                <div class="small text-secondary">
                                    <ul class="mb-0 ps-3">
                                        <li>Global = Retail sentiment</li>
                                        <li>Top = Smart money sentiment</li>
                                        <li>Divergence = Opportunity</li>
                                        <li>Strategi: Follow smart money</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 mb-0">
                        <strong>💡 Tips Pro:</strong> Perhatikan divergence antara Global dan Top Account. Ketika retail bullish tapi smart money bearish, sering menandakan reversal. Kombinasikan dengan analisis teknikal untuk timing yang optimal.
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
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js" defer></script>

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

    <!-- Long-Short Ratio Modular Controller -->
    <script type="module" src="{{ asset('js/long-short-ratio-controller.js') }}" defer></script>

    <style>
        /* Skeleton placeholders */
        [x-cloak] { display: none !important; }
        
        /* Light Theme Chart Container */
        .tradingview-chart-container {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
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

        .chart-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .current-value {
            color: #3b82f6;
            font-size: 20px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }

        /* Chart Controls - Responsive Layout */
        .chart-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
            padding: 12px 20px;
        }

        .chart-controls > div {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-body {
            padding: 20px;
            height: 400px;
            position: relative;
            background: #ffffff;
        }

        .chart-footer-text {
            color: #64748b !important;
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

        /* Professional Time Range Controls */
        .time-range-selector {
            display: flex;
            gap: 6px;
            background: rgba(241, 245, 249, 0.8);
            border-radius: 8px;
            padding: 0.25rem;
        }

        .time-range-btn {
            padding: 6px 14px !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            border: none !important;
            border-radius: 6px !important;
            transition: all 0.2s ease !important;
            min-width: 44px;
            color: #64748b !important;
            background: transparent !important;
        }

        .time-range-btn:hover {
            color: #1e293b !important;
            background: rgba(241, 245, 249, 0.5) !important;
        }

        .time-range-btn.btn-primary {
            background: #3b82f6 !important;
            color: #ffffff !important;
        }

        .time-range-btn.btn-primary:hover {
            background: #2563eb !important;
        }

        .chart-controls {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .chart-controls .btn-outline-secondary {
            border-color: rgba(226, 232, 240, 0.8) !important;
            color: #64748b !important;
            background: rgba(241, 245, 249, 0.5) !important;
        }

        .chart-controls .btn-outline-secondary:hover {
            background: rgba(59, 130, 246, 0.1) !important;
            border-color: rgba(59, 130, 246, 0.3) !important;
            color: #3b82f6 !important;
        }

        /* Dropdown Menu Styling */
        .dropdown-menu {
            background: #ffffff !important;
            border: 1px solid rgba(226, 232, 240, 0.8) !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1) !important;
        }

        .dropdown-menu .dropdown-item {
            color: #1e293b !important;
            transition: all 0.2s ease !important;
            border-radius: 4px !important;
            margin: 0.125rem !important;
        }

        .dropdown-menu .dropdown-item:hover {
            background: rgba(59, 130, 246, 0.1) !important;
            color: #3b82f6 !important;
        }

        .dropdown-menu .dropdown-item.active {
            background: #3b82f6 !important;
            color: #fff !important;
        }

        /* Interval Dropdown Styling */
        .interval-dropdown-btn {
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            padding: 0.5rem 0.75rem !important;
            min-width: 70px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            border: 1px solid rgba(59, 130, 246, 0.15) !important;
            background: rgba(241, 245, 249, 0.8) !important;
            color: #64748b !important;
        }

        .interval-dropdown-btn:hover {
            color: #1e293b !important;
            border-color: rgba(59, 130, 246, 0.3) !important;
            background: rgba(241, 245, 249, 1) !important;
        }

        .interval-dropdown-btn:focus {
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25) !important;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .chart-controls {
                padding: 10px 16px;
                gap: 0.75rem;
            }

            .chart-controls > div {
                width: 100%;
                justify-content: flex-start;
            }

            .time-range-selector {
                width: 100%;
                justify-content: flex-start;
            }

            .chart-body {
                height: 350px;
                padding: 12px;
            }

            .current-value {
                font-size: 16px;
            }
        }

        @media (max-width: 576px) {
            .chart-controls {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }

            .chart-controls > div {
                width: 100%;
            }

            .time-range-selector {
                width: 100%;
                justify-content: space-between;
            }

            .time-range-btn {
                flex: 1;
                min-width: 35px;
            }
        }
    </style>
@endsection
