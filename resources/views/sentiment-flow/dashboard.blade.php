@extends('layouts.app')

@section('title', 'Sentiment & Flow Analysis | DragonFortune')

@push('head')
    <!-- Resource Hints for Faster API Loading -->
    <link rel="dns-prefetch" href="{{ config('app.api_urls.internal') }}">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="{{ config('app.api_urls.internal') }}" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="{{ asset('js/sentiment-flow/controller.js') }}" as="script" crossorigin="anonymous">
@endpush

@section('content')
    {{--
        Sentiment & Flow Analysis Dashboard
        Track market sentiment, funding dominance, and whale movements
        
        Data Source: Coinglass API
        - Fear & Greed Index (market sentiment indicator)
        - Funding Rate Exchange List (leverage positioning)
        - Hyperliquid Whale Alert (smart money movements)
    --}}

    <div class="d-flex flex-column h-100 gap-3" x-data="sentimentFlowController()">
        <!-- Page Header -->
        <div class="derivatives-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h1 class="mb-0">Sentiment & Flow Analysis</h1>
                        <span class="spinner-border spinner-border-sm text-primary" style="width: 16px; height: 16px;" x-show="isLoading" x-cloak></span>
                    </div>
                    <p class="mb-0 text-secondary">
                        Monitor market sentiment, funding dominance, and whale movements in real-time.
                    </p>
                </div>

                <!-- Manual Refresh -->
                <div class="d-flex gap-2 align-items-center flex-wrap">
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

        <!-- SECTION 1: FEAR & GREED INDEX -->
        <div class="row g-3">
            <div class="col-12">
                <div class="df-panel p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">Fear & Greed Index</h5>
                        <span class="badge text-bg-info">Market Sentiment</span>
                    </div>

                    <!-- Fear & Greed Summary -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="p-3 border rounded text-center">
                                <div class="small text-secondary mb-2">Current Index</div>
                                <div class="h1 mb-2" 
                                     x-text="fearGreedValue !== null ? fearGreedValue : '...'"
                                     :style="fearGreedValue !== null ? `color: ${getFearGreedColor(fearGreedValue)}` : ''">
                        </div>
                                <div class="badge" 
                                     :class="fearGreedValue >= 80 ? 'text-bg-danger' : fearGreedValue >= 60 ? 'text-bg-warning' : fearGreedValue >= 40 ? 'text-bg-info' : fearGreedValue >= 20 ? 'text-bg-success' : 'text-bg-success'"
                                     x-text="fearGreedSentiment || 'Loading...'">
                        </div>
                    </div>
                        </div>
                        <div class="col-md-8">
                            <div class="p-3 border rounded">
                                <div class="small text-secondary mb-2">Interpretation Guide</div>
                                <div class="d-flex flex-column gap-1 small">
                                    <div><span class="badge text-bg-danger">80-100</span> Extreme Greed - Potential correction</div>
                                    <div><span class="badge text-bg-warning">60-79</span> Greed - Market heating up</div>
                                    <div><span class="badge text-bg-info">40-59</span> Neutral - Balanced sentiment</div>
                                    <div><span class="badge text-bg-success">20-39</span> Fear - Potential opportunity</div>
                                    <div><span class="badge text-bg-success">0-19</span> Extreme Fear - Strong buy signal</div>
                        </div>
                    </div>
                </div>
            </div>

                    <!-- Fear & Greed Chart -->
                    <div class="tradingview-chart-container">
                        <div class="chart-header">
                            <div class="d-flex align-items-center gap-3">
                                <h6 class="mb-0">Fear & Greed Index History</h6>
                                <small class="text-muted" x-text="`${fearGreedHistory.length} data points`"></small>
                    </div>
                            <div class="chart-controls">
                                <!-- Time Range Buttons -->
                                <div class="time-range-selector">
                                    <template x-for="range in fearGreedTimeRanges" :key="range.value">
                                        <button type="button" 
                                                class="time-range-btn"
                                                :class="selectedFearGreedRange === range.value ? 'btn-primary' : ''"
                                                @click="updateFearGreedRange(range.value)"
                                                x-text="range.label">
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="chart-body" style="position: relative; height: 300px;">
                            <canvas id="fearGreedChart"></canvas>
                                    </div>
                        <div class="chart-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="chart-footer-text">
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-right: 4px;">
                                        <circle cx="6" cy="6" r="5" fill="none" stroke="currentColor" stroke-width="1"/>
                                        <path d="M6 3v3l2 2" stroke="currentColor" stroke-width="1" fill="none"/>
                                    </svg>
                                    Hover over chart to see detailed date and sentiment classification
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: FUNDING DOMINANCE -->
        <div class="row g-3 mt-2">
            <div class="col-12">
                <div class="df-panel p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Funding Rate Dominance</h5>
                        <span class="badge text-bg-info">Leverage Positioning</span>
                    </div>

                    <!-- Info Alert -->
                    <div class="alert alert-info mb-3">
                        <small>
                            <i class="fas fa-info-circle"></i> 
                            <strong>Sentiment Calculation:</strong> Berdasarkan analisa rata-rata funding rate dari semua exchange. 
                            <span class="badge text-bg-success">Bullish</span> jika &gt;0.001 (longs mendominasi, positioning crowded), 
                            <span class="badge text-bg-danger">Bearish</span> jika &lt;-0.001 (shorts mendominasi, positioning crowded), 
                            <span class="badge text-bg-secondary">Neutral</span> jika -0.001 s/d 0.001.
                            <br>
                            <strong>💡 Insight:</strong> Funding rate ekstrem (&gt;0.001 atau &lt;-0.001) sering menandakan positioning yang crowded dan potensi squeeze.
                            <br>
                            <strong>📊 Annualized Rate:</strong> Dihitung dari funding rate per interval × (365 hari × 24 jam / interval_hours). 
                            Contoh: funding rate 0.01 per 8 jam = 0.01 × (365×24/8) = 0.01 × 1095 = 10.95 (1095% annualized).
                        </small>
                    </div>

                    <!-- Aggregate Summary -->
                    <div class="row g-3 mb-4" x-show="fundingAggregate">
                        <div class="col-md-3">
                            <div class="p-3 border rounded text-center">
                                <div class="small text-secondary mb-2">Average Rate</div>
                                <div class="h4 mb-1" x-text="fundingAggregate ? formatFundingRate(fundingAggregate.avg_funding_rate) : '...'"></div>
                                <small class="text-muted" x-text="fundingAggregate ? fundingAggregate.sentiment : ''"></small>
                                    </div>
                                </div>
                        <div class="col-md-3">
                            <div class="p-3 border rounded text-center">
                                <div class="small text-secondary mb-2">Highest Rate</div>
                                <div class="h4 mb-1 text-success" x-text="fundingAggregate ? formatFundingRate(fundingAggregate.max_funding_rate) : '...'"></div>
                                <small class="text-muted">Most bullish</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 border rounded text-center">
                                <div class="small text-secondary mb-2">Lowest Rate</div>
                                <div class="h4 mb-1 text-danger" x-text="fundingAggregate ? formatFundingRate(fundingAggregate.min_funding_rate) : '...'"></div>
                                <small class="text-muted">Most bearish</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 border rounded text-center">
                                <div class="small text-secondary mb-2">Total Exchanges</div>
                                <div class="h4 mb-1" x-text="fundingExchanges.length || '...'"></div>
                                <small class="text-muted">Tracked</small>
                            </div>
                        </div>
                    </div>

                    <!-- Funding Rate Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Exchange</th>
                                    <th>Funding Rate</th>
                                    <th>Annualized</th>
                                    <th>Interval</th>
                                    <th>Sentiment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(exchange, index) in fundingExchanges" :key="index">
                                    <tr>
                                        <td>
                                            <strong x-text="exchange.exchange"></strong>
                                        </td>
                                        <td>
                                            <span :style="`color: ${getFundingColor(exchange.funding_rate)}`" 
                                                  x-text="formatFundingRate(exchange.funding_rate)">
                                            </span>
                                        </td>
                                        <td x-text="formatAnnualizedRate(exchange.annualized_rate)"></td>
                                        <td x-text="exchange.funding_rate_interval + 'h'"></td>
                                        <td>
                                            <span class="badge" 
                                                  :class="exchange.funding_rate > 0.001 ? 'text-bg-success' : exchange.funding_rate < -0.001 ? 'text-bg-danger' : 'text-bg-secondary'"
                                                  x-text="getFundingTrend(exchange.funding_rate)">
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                            </div>
                        </div>
                    </div>

        <!-- SECTION 3: WHALE ALERTS -->
        <div class="row g-3 mt-2">
            <div class="col-12">
                <div class="df-panel p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">🐋 Whale Alerts (Hyperliquid)</h5>
                        <div class="d-flex gap-2 align-items-center">
                            <span class="badge text-bg-info">Smart Money Moves</span>
                </div>
            </div>

                    <!-- Info Alert & Filter -->
                    <div class="alert alert-warning mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="small">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Live Data:</strong> Menampilkan whale positions terbaru dari Hyperliquid (real-time). Data akan ter-refresh setiap detik untuk update terbaru.
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <label class="small text-secondary mb-0">Filter Symbol:</label>
                                <select class="form-select form-select-sm" 
                                        style="width: 120px;"
                                        x-model="selectedWhaleSymbol"
                                        @change="updateWhaleSymbol($event.target.value)">
                                    <template x-for="symbol in availableWhaleSymbols" :key="symbol">
                                        <option :value="symbol" x-text="symbol"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Whale Aggregate Stats -->
                    <div class="row g-3 mb-4" x-show="whaleAggregate">
                        <div class="col-md-3">
                            <div class="p-3 border rounded text-center">
                                <div class="small text-secondary mb-2">Total Alerts</div>
                                <div class="h4 mb-1" x-text="whaleAggregate ? whaleAggregate.total_alerts : '...'"></div>
                                <small class="text-muted">All symbols</small>
                            </div>
                                </div>
                        <div class="col-md-3">
                            <div class="p-3 border rounded text-center">
                                <div class="small text-secondary mb-2">BTC Alerts</div>
                                <div class="h4 mb-1" x-text="whaleAggregate ? whaleAggregate.btc_alerts : '...'"></div>
                                <small class="text-muted">Bitcoin only</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 border rounded text-center">
                                <div class="small text-secondary mb-2">Long/Short Ratio</div>
                                <div class="h4 mb-1" x-text="whaleAggregate ? formatNumber(whaleAggregate.long_short_ratio) : '...'"></div>
                                <small class="text-muted">
                                    <span class="text-success" x-text="whaleAggregate ? whaleAggregate.long_count : 0"></span> / 
                                    <span class="text-danger" x-text="whaleAggregate ? whaleAggregate.short_count : 0"></span>
                                </small>
                    </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 border rounded text-center">
                                <div class="small text-secondary mb-2">Total Value</div>
                                <div class="h4 mb-1" x-text="whaleAggregate ? formatUSD(whaleAggregate.total_value_usd) : '...'"></div>
                                <small class="text-muted">USD value</small>
                            </div>
                        </div>
                    </div>

                    <!-- Whale Alerts Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>Symbol</th>
                                    <th>Size</th>
                                    <th>Type</th>
                                    <th>Entry Price</th>
                                    <th>Liq Price</th>
                                    <th>Value (USD)</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(alert, index) in whaleAlertsFiltered" :key="index">
                                    <tr>
                                        <td class="small" x-text="formatDate(alert.create_time)"></td>
                                        <td class="small">
                                            <code x-text="truncateAddress(alert.user)"></code>
                                        </td>
                                        <td><strong x-text="alert.symbol"></strong></td>
                                        <td x-text="formatNumber(Math.abs(alert.position_size))"></td>
                                        <td>
                                            <span class="badge" 
                                                  :class="getPositionBadgeClass(alert.position_type)"
                                                  x-text="alert.position_type">
                                            </span>
                                        </td>
                                        <td x-text="formatPrice(alert.entry_price)"></td>
                                        <td x-text="formatPrice(alert.liq_price)"></td>
                                        <td x-text="formatUSD(alert.position_value_usd)"></td>
                                        <td>
                                            <span class="badge" 
                                                  :class="getActionBadgeClass(alert.position_action)"
                                                  x-text="alert.position_action">
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="whaleAlertsFiltered.length === 0">
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox"></i> No whale alerts found for selected symbol
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 4: WHALE TRANSFERS (ON-CHAIN) - moved to On-Chain Metrics dashboard -->

    </div>
@endsection

@section('scripts')
    <!-- Chart.js with Date Adapter - Load async -->
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

    <!-- Sentiment & Flow Controller - Load with defer -->
    <script type="module" src="{{ asset('js/sentiment-flow/controller.js') }}" defer></script>

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

        .chart-body {
            padding: 20px;
            background: #ffffff;
        }

        /* Pulse animation */
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

        /* Panel styling */
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

        /* Table styling */
        .table-hover tbody tr:hover {
            background-color: rgba(59, 130, 246, 0.05);
        }

        /* Time Range Selector Styling (ETF Flows style) */
        .time-range-selector {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            background: linear-gradient(135deg, 
                rgba(241, 245, 249, 0.8) 0%, 
                rgba(226, 232, 240, 0.8) 100%);
            border: 1px solid rgba(59, 130, 246, 0.15);
            border-radius: 8px;
            padding: 0.25rem;
        }

        .time-range-btn {
            padding: 6px 14px;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.2s;
            white-space: nowrap;
            color: #64748b;
            background: transparent;
            border: none;
        }

        .time-range-btn:hover {
            color: #1e293b;
            background: rgba(241, 245, 249, 1);
        }

        .time-range-btn.btn-primary {
            background: #3b82f6 !important;
            color: white !important;
        }

        .time-range-btn.btn-primary:hover {
            background: #2563eb !important;
            color: white !important;
        }

        .chart-controls {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .derivatives-header h1 {
                font-size: 1.5rem;
            }
            
            .chart-body {
                height: 250px;
                padding: 12px;
            }
        }
    </style>
@endsection
