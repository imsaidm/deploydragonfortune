@extends('layouts.app')

@section('title', 'Funding Rate Analytics (Advanced) | DragonFortune')

@push('head')
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preload" href="{{ asset('js/funding-rate-advanced-controller.js') }}" as="script" crossorigin="anonymous">
@endpush

@section('content')
    {{--
        Advanced Funding Rate Dashboard
        Inspired by QuantWaji professional interface
        Integrated with Coinglass API - Real-time data
    --}}

    <div class="funding-advanced-dashboard" x-data="fundingRateAdvancedController()">
        
        <!-- Page Header with controls -->
        <div class="dashboard-header mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="mb-1">Funding Rate Analytics</h1>
                    <p class="text-muted mb-0">Real-time funding rate analysis across major exchanges</p>
                </div>
                
                <div class="d-flex gap-2 align-items-center">
                    <!-- Symbol Selector -->
                    <select class="form-select form-select-sm" style="width: 100px;" x-model="selectedSymbol" @change="refreshData()">
                        <option value="BTC">BTC</option>
                        <option value="ETH">ETH</option>
                        <option value="SOL">SOL</option>
                    </select>
                    
                    <!-- Time Range -->
                    <select class="form-select form-select-sm" style="width: 100px;" x-model="timeRange" @change="refreshData()">
                        <option value="24h">24H</option>
                        <option value="7d">7D</option>
                        <option value="30d">30D</option>
                    </select>
                    
                    <!-- Refresh Button -->
                    <button class="btn btn-sm btn-outline-primary" @click="refreshData()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Prominent Actual Funding Display -->
        <div class="actual-funding-banner mb-4">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <div class="actual-funding-main">
                        <div class="actual-label">AVERAGE FUNDING RATE (8H)</div>
                        <div class="actual-value" :class="metrics.avgFunding >= 0 ? 'positive' : 'negative'">
                            <span x-text="metrics.avgFunding"></span><span class="percent">%</span>
                        </div>
                        <div class="actual-sublabel">
                            Annualized ~<span x-text="(parseFloat(metrics.avgFunding || 0) * 3 * 365).toFixed(2)"></span>%
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <div class="mini-stat">
                                <span class="mini-label">Binance</span>
                                <span class="mini-value" :class="getBinanceFunding() >= 0 ? 'text-success' : 'text-danger'" x-text="getBinanceFunding().toFixed(4) + '%'"></span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="mini-stat">
                                <span class="mini-label">OKX</span>
                                <span class="mini-value" :class="getOKXFunding() >= 0 ? 'text-success' : 'text-danger'" x-text="getOKXFunding().toFixed(4) + '%'"></span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="mini-stat">
                                <span class="mini-label">Bybit</span>
                                <span class="mini-value" :class="getBybitFunding() >= 0 ? 'text-success' : 'text-danger'" x-text="getBybitFunding().toFixed(4) + '%'"></span>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="mini-stat">
                                <span class="mini-label">Bitget</span>
                                <span class="mini-value" :class="getBitgetFunding() >= 0 ? 'text-success' : 'text-danger'" x-text="getBitgetFunding().toFixed(4) + '%'"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metrics Row -->
        <div class="metrics-row mb-4">
            <div class="row g-3">
                <!-- Average Funding (replaced Wallet) -->
                <div class="col-md-3 col-sm-6">
                    <div class="metric-card">
                        <div class="metric-label">AVG Funding</div>
                        <div class="metric-value" :class="metrics.avgFunding >= 0 ? 'text-success' : 'text-danger'" x-text="metrics.avgFunding + '%'"></div>
                        <div class="metric-sublabel">Across <span x-text="exchangeSnapshots.length"></span> exchanges</div>
                    </div>
                </div>

                <!-- Exchange Count (replaced 1W Volume) -->
                <div class="col-md-3 col-sm-6">
                    <div class="metric-card">
                        <div class="metric-label">Exchanges</div>
                        <div class="metric-value" x-text="exchangeSnapshots.length"></div>
                        <div class="metric-sublabel">
                            <span x-text="exchangeSnapshots.filter(e => e.margin_type === 'USDT').length"></span> USDT / 
                            <span x-text="exchangeSnapshots.filter(e => e.margin_type === 'COIN').length"></span> COIN
                        </div>
                    </div>
                </div>

                <!-- Basis/Premium -->
                <div class="col-md-3 col-sm-6">
                    <div class="metric-card">
                        <div class="metric-label">Basis/Premium</div>
                        <div class="metric-value" x-text="metrics.basis + '%'"></div>
                        <div class="metric-sublabel">Annualized</div>
                    </div>
                </div>

                <!-- Min Funding -->
                <div class="col-md-3 col-sm-6">
                    <div class="metric-card">
                        <div class="metric-label">Min Funding</div>
                        <div class="metric-value text-danger" x-text="metrics.minFunding + '%'"></div>
                        <div class="metric-sublabel" x-text="metrics.minExchange"></div>
                    </div>
                </div>

                <!-- Max Funding -->
                <div class="col-md-3 col-sm-6">
                    <div class="metric-card">
                        <div class="metric-label">Max Funding</div>
                        <div class="metric-value text-success" x-text="metrics.maxFunding + '%'"></div>
                        <div class="metric-sublabel" x-text="metrics.maxExchange"></div>
                    </div>
                </div>

                <!-- Spread -->
                <div class="col-md-3 col-sm-6">
                    <div class="metric-card">
                        <div class="metric-label">Spread</div>
                        <div class="metric-value" x-text="metrics.spread + ' bps'"></div>
                        <div class="metric-sublabel">Max - Min</div>
                    </div>
                </div>

                <!-- Next Funding -->
                <div class="col-md-3 col-sm-6">
                    <div class="metric-card">
                        <div class="metric-label">Next Funding</div>
                        <div class="metric-value countdown" x-text="nextFundingCountdown"></div>
                        <div class="metric-sublabel">HH:MM:SS</div>
                    </div>
                </div>

                <!-- Data Quality -->
                <div class="col-md-3 col-sm-6">
                    <div class="metric-card">
                        <div class="metric-label">Data Quality</div>
                        <div class="metric-value">
                            <span class="badge" :class="isLoading ? 'bg-warning' : 'bg-success'" x-text="isLoading ? 'Loading...' : 'Live'"></span>
                        </div>
                        <div class="metric-sublabel">Last sync: <span x-text="lastSync"></span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="row g-4 mb-4">
            
            <!-- Left Column: Charts & Tables (60%) -->
            <div class="col-lg-7">
                
                <!-- Actual vs Predicted Funding -->
                <div class="chart-panel mb-4">
                    <div class="chart-panel-header">
                        <h5>Actual vs Predicted Funding</h5>
                        <div class="stats-row">
                            <span class="stat-item">MAE: <strong x-text="predictionStats.mae"></strong></span>
                            <span class="stat-item">MSE: <strong x-text="predictionStats.mse"></strong></span>
                            <span class="stat-item">Correlation: <strong x-text="predictionStats.correlation"></strong></span>
                        </div>
                    </div>
                    <div class="chart-panel-body">
                        <canvas id="actualVsPredictedChart"></canvas>
                    </div>
                </div>

                <!-- Per-Exchange Snapshot Table -->
                <div class="table-panel">
                    <div class="table-panel-header">
                        <h5>Per-Exchange Snapshot</h5>
                        <div class="table-controls">
                            <button class="btn btn-sm btn-outline-secondary" @click="sortTable('funding')">
                                <i class="bi bi-sort-down"></i> Sort
                            </button>
                        </div>
                    </div>
                    <div class="table-panel-body">
                        <div class="table-responsive">
                            <table class="table table-hover exchange-snapshot-table">
                                <thead>
                                    <tr>
                                        <th>Exchange</th>
                                        <th>Funding</th>
                                        <th>Predicted</th>
                                        <th>Interval</th>
                                        <th>Next Funding</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(exchange, idx) in exchangeSnapshots" :key="idx">
                                        <tr>
                                            <td><strong x-text="exchange.name"></strong></td>
                                            <td :class="exchange.funding > 0 ? 'text-success' : 'text-danger'">
                                                <span x-text="(exchange.funding * 100).toFixed(4) + '%'"></span>
                                            </td>
                                            <td class="text-muted" x-text="(exchange.predicted * 100).toFixed(4) + '%'"></td>
                                            <td x-text="exchange.interval + 'h'"></td>
                                            <td x-text="formatNextFunding(exchange.nextFunding)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Middle Column: Spread Matrix (20%) -->
            <div class="col-lg-2">
                <div class="matrix-panel">
                    <div class="matrix-panel-header">
                        <h6>Spread Matrix</h6>
                    </div>
                    <div class="matrix-panel-body">
                        <table class="spread-matrix">
                            <thead>
                                <tr>
                                    <th>Ex1</th>
                                    <th>Ex2</th>
                                    <th>Spread</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="spread in spreadMatrix" :key="spread.pair">
                                    <tr>
                                        <td x-text="spread.ex1"></td>
                                        <td x-text="spread.ex2"></td>
                                        <td :class="getSpreadColor(spread.value)" x-text="spread.value + ' bps'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column: Insights (20%) -->
            <div class="col-lg-3">
                <div class="insights-panel">
                    <div class="insights-panel-header">
                        <h6>
                            <i class="bi bi-lightbulb"></i> Insights
                        </h6>
                    </div>
                    <div class="insights-panel-body">
                        <!-- Loading state -->
                        <div x-show="isLoading" class="insight-item insight-info">
                            <div class="insight-icon"><i class="bi bi-hourglass-split"></i></div>
                            <div class="insight-content">
                                <div class="insight-message">Loading insights...</div>
                            </div>
                        </div>
                        
                        <!-- Empty state -->
                        <div x-show="!isLoading && insights.length === 0" class="insight-item insight-info">
                            <div class="insight-icon"><i class="bi bi-info-circle"></i></div>
                            <div class="insight-content">
                                <div class="insight-message">No insights available</div>
                            </div>
                        </div>
                        
                        <!-- Insights list -->
                        <template x-for="insight in insights" :key="insight.id">
                            <div class="insight-item" :class="'insight-' + insight.type">
                                <div class="insight-icon">
                                    <template x-if="insight.type === 'warning'">
                                        <i class="bi bi-exclamation-triangle"></i>
                                    </template>
                                    <template x-if="insight.type === 'info'">
                                        <i class="bi bi-info-circle"></i>
                                    </template>
                                    <template x-if="insight.type === 'success'">
                                        <i class="bi bi-check-circle"></i>
                                    </template>
                                </div>
                                <div class="insight-content">
                                    <div class="insight-message" x-text="insight.message"></div>
                                    <div class="insight-time text-muted" x-text="insight.time"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

        </div>

        <!-- Bottom Analytics Section -->
        <div class="row g-4">
            
            <!-- History + Overlays Chart -->
            <div class="col-lg-8">
                <div class="chart-panel">
                    <div class="chart-panel-header">
                        <h5>History + Overlays</h5>
                        <div class="overlay-toggles">
                            <button class="btn btn-sm" :class="overlayFunding ? 'btn-primary' : 'btn-outline-secondary'" @click="overlayFunding = !overlayFunding">Funding</button>
                            <button class="btn btn-sm" :class="overlayPrice ? 'btn-primary' : 'btn-outline-secondary'" @click="overlayPrice = !overlayPrice">Price</button>
                            <button class="btn btn-sm" :class="overlayOI ? 'btn-primary' : 'btn-outline-secondary'" @click="overlayOI = !overlayOI">OI</button>
                        </div>
                    </div>
                    <div class="chart-panel-body">
                        <canvas id="historyOverlaysChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Right Side: Heatmap & Distribution -->
            <div class="col-lg-4">
                
                <!-- Heatmap (Mini Sparklines) -->
                <div class="chart-panel mb-3">
                    <div class="chart-panel-header">
                        <h6>Heatmap (Sparklines)</h6>
                    </div>
                    <div class="chart-panel-body p-2">
                        <template x-for="exchange in exchangeSnapshots.slice(0, 6)" :key="exchange.name">
                            <div class="sparkline-row">
                                <span class="sparkline-label" x-text="exchange.name"></span>
                                <div class="sparkline-chart">
                                    <!-- Dummy sparkline visualization -->
                                    <canvas :id="'sparkline-' + exchange.name" width="100" height="20"></canvas>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Distribution (Histogram) -->
                <div class="chart-panel mb-3">
                    <div class="chart-panel-header">
                        <h6>Distribution</h6>
                    </div>
                    <div class="chart-panel-body">
                        <canvas id="distributionChart" height="150"></canvas>
                    </div>
                </div>

                <!-- Metrics Panel -->
                <div class="metrics-small-panel">
                    <div class="metric-small-item">
                        <span class="label">Annualized</span>
                        <span class="value" x-text="additionalMetrics.annualized + '%'"></span>
                    </div>
                    <div class="metric-small-item">
                        <span class="label">Slope</span>
                        <span class="value" x-text="additionalMetrics.slope"></span>
                    </div>
                </div>

            </div>

        </div>

    </div>
@endsection

@section('scripts')
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js" defer></script>

    <!-- Controller -->
    <script type="module" src="{{ asset('js/funding-rate-advanced-controller.js') }}" defer></script>

    <style>
        /* Advanced Dashboard Styles */
        .funding-advanced-dashboard {
            padding: 1rem;
        }

        /* Actual Funding Banner - Hero Display */
        .actual-funding-banner {
            background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);
            border-radius: 16px;
            padding: 1.5rem 2rem;
            color: white;
        }

        .actual-funding-main {
            text-align: center;
        }

        .actual-label {
            font-size: 0.75rem;
            letter-spacing: 0.1em;
            color: rgba(255,255,255,0.7);
            margin-bottom: 0.5rem;
        }

        .actual-value {
            font-size: 3rem;
            font-weight: 800;
            font-family: 'Courier New', monospace;
            line-height: 1;
        }

        .actual-value.positive {
            color: #4ade80;
        }

        .actual-value.negative {
            color: #f87171;
        }

        .actual-value .percent {
            font-size: 1.5rem;
        }

        .actual-sublabel {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.6);
            margin-top: 0.5rem;
        }

        .mini-stat {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 0.75rem;
            text-align: center;
        }

        .mini-label {
            display: block;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.7);
            margin-bottom: 0.25rem;
        }

        .mini-value {
            display: block;
            font-size: 1rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }

        /* Metrics Row */
        .metrics-row .metric-card {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
            border: 1px solid rgba(59, 130, 246, 0.1);
            border-radius: 12px;
            padding: 1rem;
            transition: all 0.3s ease;
        }

        .metrics-row .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.15);
        }

        .metric-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            font-family: 'Courier New', monospace;
        }

        .metric-sublabel {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 0.25rem;
        }

        /* Chart Panels */
        .chart-panel, .table-panel, .matrix-panel, .insights-panel {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .chart-panel-header, .table-panel-header, .matrix-panel-header, .insights-panel-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chart-panel-header h5, .table-panel-header h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
        }

        .matrix-panel-header h6, .insights-panel-header h6 {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
        }

        .chart-panel-body, .table-panel-body, .matrix-panel-body, .insights-panel-body {
            padding: 1.25rem;
        }

        /* Stats Row */
        .stats-row {
            display: flex;
            gap: 1rem;
            font-size: 0.75rem;
        }

        .stat-item strong {
            color: #3b82f6;
        }

        /* Exchange Snapshot Table */
        .exchange-snapshot-table {
            font-size: 0.75rem;
            margin: 0;
        }

        .exchange-snapshot-table thead th {
            background: rgba(241, 245, 249, 0.8);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.65rem;
            color: #64748b;
            border: none;
            padding: 0.5rem;
        }

        .exchange-snapshot-table tbody td {
            padding: 0.5rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .exchange-snapshot-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }

        /* Spread Matrix */
        .spread-matrix {
            width: 100%;
            font-size: 0.7rem;
        }

        .spread-matrix th, .spread-matrix td {
            padding: 0.5rem 0.25rem;
            text-align: center;
        }

        .spread-high {
            color: #ef4444;
            font-weight: 600;
        }

        .spread-medium {
            color: #f59e0b;
        }

        .spread-low {
            color: #22c55e;
        }

        /* Insights Panel */
        .insights-panel-body {
            max-height: 500px;
            overflow-y: auto;
        }

        .insight-item {
            display: flex;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            border-left: 3px solid;
        }

        .insight-warning {
            background: rgba(239, 68, 68, 0.1);
            border-left-color: #ef4444;
        }

        .insight-info {
            background: rgba(59, 130, 246, 0.1);
            border-left-color: #3b82f6;
        }

        .insight-success {
            background: rgba(34, 197, 94, 0.1);
            border-left-color: #22c55e;
        }

        .insight-icon {
            font-size: 1.25rem;
        }

        .insight-message {
            font-size: 0.8rem;
            font-weight: 500;
            color: #1e293b;
        }

        .insight-time {
            font-size: 0.7rem;
            margin-top: 0.25rem;
        }

        /* Overlay Toggles */
        .overlay-toggles {
            display: flex;
            gap: 0.5rem;
        }

        /* Sparklines */
        .sparkline-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0;
        }

        .sparkline-label {
            font-size: 0.7rem;
            font-weight: 600;
            min-width: 60px;
        }

        .sparkline-chart {
            flex: 1;
        }

        /* Metrics Small Panel */
        .metrics-small-panel {
            background: rgba(241, 245, 249, 0.5);
            border-radius: 8px;
            padding: 1rem;
        }

        .metric-small-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .metric-small-item:last-child {
            border-bottom: none;
        }

        .metric-small-item .label {
            font-size: 0.75rem;
            color: #64748b;
        }

        .metric-small-item .value {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-row {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .exchange-snapshot-table {
                font-size: 0.65rem;
            }

            .overlay-toggles {
                flex-wrap: wrap;
            }
        }
    </style>
@endsection
