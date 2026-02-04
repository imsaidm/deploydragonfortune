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
        
        <!-- Loading Overlay -->
        <div class="loading-overlay" x-show="isLoading && !lastUpdate" x-cloak>
            <div class="loading-content">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="mb-2">Loading Funding Rate Data</h5>
                <p class="text-muted mb-0">Fetching real-time data from database...</p>
            </div>
        </div>
        
        <!-- Real-time indicator -->
        <div class="realtime-indicator" x-show="lastUpdate">
            <span class="pulse-dot" :class="isLoading ? 'pulse-loading' : 'pulse-live'"></span>
            <span x-text="isLoading ? 'Updating...' : 'Live'"></span>
            <span class="text-muted ms-2" x-text="'• ' + lastSync"></span>
        </div>

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

        <!-- 📊 RISK ASSESSMENT PANEL -->
        <div class="risk-assessment-panel mb-4" x-show="aiAnalysis.market_status !== 'Loading...'">
            <div class="panel-header">
                <h5><i class="bi bi-shield-exclamation"></i> Risk Assessment</h5>
                <span class="badge bg-light text-dark">Algorithmic Market Analysis</span>
            </div>
            <div class="panel-body">
                <!-- Top Row: Key Metrics (5 cards) -->
                <div class="row g-3 mb-3">
                    <!-- Market Status -->
                    <div class="col-lg col-6">
                        <div class="ai-card">
                            <span class="ai-label">Market Status</span>
                            <span class="ai-value" 
                                  :class="{
                                      'status-healthy': aiAnalysis.market_status === 'Sehat',
                                      'status-hot': aiAnalysis.market_status === 'Panas',
                                      'status-unhealthy': aiAnalysis.market_status === 'Tidak Sehat'
                                  }"
                                  x-text="aiAnalysis.market_status || 'Loading...'"></span>
                        </div>
                    </div>
                    
                    <!-- Crowd Positioning -->
                    <div class="col-lg col-6">
                        <div class="ai-card">
                            <span class="ai-label">Crowd Positioning</span>
                            <span class="ai-value positioning" x-text="aiAnalysis.crowd_positioning || 'Loading...'"></span>
                        </div>
                    </div>
                    
                    <!-- Leverage Condition (NEW) -->
                    <div class="col-lg col-6">
                        <div class="ai-card">
                            <span class="ai-label">Leverage Condition</span>
                            <span class="ai-value" 
                                  :class="{
                                      'leverage-low': aiAnalysis.leverage_condition === 'Rendah',
                                      'leverage-increasing': aiAnalysis.leverage_condition === 'Meningkat',
                                      'leverage-excessive': aiAnalysis.leverage_condition === 'Berlebihan'
                                  }"
                                  x-text="aiAnalysis.leverage_condition || 'Loading...'"></span>
                        </div>
                    </div>
                    
                    <!-- Primary Risk -->
                    <div class="col-lg col-6">
                        <div class="ai-card">
                            <span class="ai-label">Primary Risk</span>
                            <span class="ai-value risk" x-text="aiAnalysis.primary_risk || 'Loading...'"></span>
                        </div>
                    </div>
                    
                    <!-- Risk Stance -->
                    <div class="col-lg col-6">
                        <div class="ai-card">
                            <span class="ai-label">Risk Stance</span>
                            <span class="ai-value" 
                                  :class="{
                                      'stance-aggressive': aiAnalysis.risk_stance === 'Agresif',
                                      'stance-neutral': aiAnalysis.risk_stance === 'Netral',
                                      'stance-defensive': aiAnalysis.risk_stance === 'Defensif'
                                  }"
                                  x-text="aiAnalysis.risk_stance || 'Loading...'"></span>
                        </div>
                    </div>
                </div>

                <!-- Bottom Row: Reasons/Insights -->
                <div class="ai-reasons">
                    <h6><i class="bi bi-lightbulb-fill"></i> Key Insights:</h6>
                    <ul>
                        <template x-for="(reason, idx) in aiAnalysis.reasons" :key="idx">
                            <li x-text="reason"></li>
                        </template>
                        <template x-if="!aiAnalysis.reasons || aiAnalysis.reasons.length === 0">
                            <li class="text-muted">Loading analysis...</li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Metrics Row -->
        <div class="metrics-row mb-4">
            <div class="row g-3">
                <!-- Exchange Count -->
                <div class="col-md-3 col-sm-6">
                    <div class="metric-card">
                        <div class="metric-label">Exchanges</div>
                        <div class="metric-value" x-text="exchangeSnapshots.length"></div>
                        <div class="metric-sublabel">Tracked Exchanges</div>
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

        <!-- Enhanced Statistics & Sentiment Row -->
        <div class="row g-4 mb-4">
            <!-- Sentiment Gauge -->
            <div class="col-md-4">
                <div class="chart-panel">
                    <div class="chart-panel-header">
                        <h6>Market Sentiment</h6>
                    </div>
                    <div class="chart-panel-body text-center">
                        <div style="height: 120px; width: 100%; position: relative;">
                            <canvas id="sentimentGaugeChart"></canvas>
                        </div>
                        <div class="mt-2">
                            <h5 class="mb-1" x-text="statistics.sentiment"></h5>
                            <p class="text-muted small mb-0">
                                <span class="text-success" x-text="statistics.positive_count"></span> positive / 
                                <span class="text-danger" x-text="statistics.negative_count"></span> negative
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Statistics Grid -->
            <div class="col-md-8">
                <div class="row g-3">
                    <div class="col-md-4 col-sm-6">
                        <div class="metric-card">
                            <div class="metric-label">Median</div>
                            <div class="metric-value" x-text="statistics.median + '%'"></div>
                            <div class="metric-sublabel">Middle Value</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="metric-card">
                            <div class="metric-label">Std Dev</div>
                            <div class="metric-value" x-text="statistics.std_dev + '%'"></div>
                            <div class="metric-sublabel">Volatility</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="metric-card">
                            <div class="metric-label">P25</div>
                            <div class="metric-value" x-text="statistics.p25 + '%'"></div>
                            <div class="metric-sublabel">25th Percentile</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="metric-card">
                            <div class="metric-label">P75</div>
                            <div class="metric-value" x-text="statistics.p75 + '%'"></div>
                            <div class="metric-sublabel">75th Percentile</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="metric-card">
                            <div class="metric-label">Annualized</div>
                            <div class="metric-value text-warning" x-text="statistics.annualized_apy + '%'"></div>
                            <div class="metric-sublabel">Cost/Year</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="metric-card">
                            <div class="metric-label">Score</div>
                            <div class="metric-value" :class="{
                                'text-danger': statistics.sentiment_score < 33,
                                'text-warning': statistics.sentiment_score >= 33 && statistics.sentiment_score < 67,
                                'text-success': statistics.sentiment_score >= 67
                            }" x-text="statistics.sentiment_score"></div>
                            <div class="metric-sublabel">0-100</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="chart-panel">
                    <div class="chart-panel-header">
                        <h5>Funding Rate OHLC</h5>
                        <select x-model="selectedExchange" @change="refreshData()" class="form-select form-select-sm" style="width: 120px;">
                            <option value="Binance">Binance</option>
                            <option value="Bybit">Bybit</option>
                        </select>
                    </div>
                    <div class="chart-panel-body">
                        <div style="height: 250px; position: relative;">
                            <canvas id="candlestickChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-panel">
                    <div class="chart-panel-header">
                        <h5>Multi-Exchange Comparison</h5>
                    </div>
                    <div class="chart-panel-body">
                        <div style="height: 250px; position: relative;">
                            <canvas id="comparisonChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Arbitrage Opportunities -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="table-panel">
                    <div class="table-panel-header">
                        <h5>🎯 Top Arbitrage Opportunities</h5>
                        <span class="badge bg-success" x-text="arbitrageOpportunities.length + ' opportunities'"></span>
                    </div>
                    <div class="table-panel-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Long Exchange</th>
                                        <th>Short Exchange</th>
                                        <th>Spread</th>
                                        <th>Est. Annual Profit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(opp, idx) in arbitrageOpportunities.slice(0, 10)" :key="idx">
                                        <tr>
                                            <td x-text="idx + 1"></td>
                                            <td>
                                                <strong x-text="opp.longExchange"></strong>
                                                <span class="text-muted small"> (<span x-text="opp.longRate"></span>%)</span>
                                            </td>
                                            <td>
                                                <strong x-text="opp.shortExchange"></strong>
                                                <span class="text-muted small"> (<span x-text="opp.shortRate"></span>%)</span>
                                            </td>
                                            <td><span class="badge bg-success" x-text="opp.spreadBps + ' bps'"></span></td>
                                            <td class="text-success fw-bold" x-text="opp.annualizedProfit + '%'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="row g-4 mb-4">
            
            <!-- Left Column: Tables & Charts (70%) -->
            <div class="col-lg-8">
                
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
                                        <th>Funding Rate</th>
                                        <th>Interval</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(exchange, idx) in exchangeSnapshots" :key="idx">
                                        <tr>
                                            <td><strong x-text="exchange.name"></strong></td>
                                            <td :class="exchange.funding > 0 ? 'text-success' : 'text-danger'">
                                                <span x-text="(exchange.funding * 100).toFixed(4) + '%'"></span>
                                            </td>
                                            <td x-text="exchange.interval + 'h'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Spread Matrix & Distribution (30%) -->
            <div class="col-lg-4">
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
                
                <!-- Distribution (Histogram) -->
                <div class="chart-panel mb-3">
                    <div class="chart-panel-header">
                        <h6>Distribution</h6>
                    </div>
                    <div class="chart-panel-body">
                        <canvas id="distributionChart" height="150"></canvas>
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
            position: relative;
        }

        /* Hide elements until Alpine loads */
        [x-cloak] { display: none !important; }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .loading-content {
            text-align: center;
            padding: 2rem;
        }

        .loading-content .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        /* Real-time Indicator */
        .realtime-indicator {
            position: fixed;
            top: 70px;
            right: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.95);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 1000;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .pulse-live {
            background: #22c55e;
            box-shadow: 0 0 0 rgba(34, 197, 94, 0.4);
        }

        .pulse-loading {
            background: #f59e0b;
            box-shadow: 0 0 0 rgba(245, 158, 11, 0.4);
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
            100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
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

        /* 📊 Risk Assessment Panel */
        .risk-assessment-panel {
            background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);
            border-radius: 16px;
            padding: 0;
            color: white;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
            overflow: hidden;
        }

        .risk-assessment-panel .panel-header {
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .risk-assessment-panel .panel-header h5 {
            margin: 0;
            font-weight: 700;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .risk-assessment-panel .panel-body {
            padding: 1.5rem;
        }

        .risk-assessment-panel .ai-card {
            text-align: center;
            padding: 1.25rem 1rem;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .risk-assessment-panel .ai-card:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .risk-assessment-panel .ai-label {
            display: block;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.9;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .risk-assessment-panel .ai-value {
            display: block;
            font-size: 1.5rem;
            font-weight: 800;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            line-height: 1.2;
        }

        /* Market Status Colors */
        .risk-assessment-panel .status-healthy { 
            color: #4ade80 !important; 
            text-shadow: 0 0 20px rgba(74, 222, 128, 0.5);
        }
        
        .risk-assessment-panel .status-hot { 
            color: #fbbf24 !important; 
            text-shadow: 0 0 20px rgba(251, 191, 36, 0.5);
        }
        
        .risk-assessment-panel .status-unhealthy { 
            color: #f87171 !important; 
            text-shadow: 0 0 20px rgba(248, 113, 113, 0.5);
        }

        /* Risk Stance Colors */
        .risk-assessment-panel .stance-aggressive { 
            color: #4ade80 !important;
            text-shadow: 0 0 20px rgba(74, 222, 128, 0.5);
        }
        
        .risk-assessment-panel .stance-neutral { 
            color: #fbbf24 !important;
            text-shadow: 0 0 20px rgba(251, 191, 36, 0.5);
        }
        
        .risk-assessment-panel .stance-defensive { 
            color: #f87171 !important;
            text-shadow: 0 0 20px rgba(248, 113, 113, 0.5);
        }

        /* Positioning & Risk Values */
        .risk-assessment-panel .ai-value.positioning,
        .risk-assessment-panel .ai-value.risk {
            color: #fef3c7;
        }

        /* Reasons Section */
        .risk-assessment-panel .ai-reasons {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-top: 0.5rem;
        }

        .risk-assessment-panel .ai-reasons h6 {
            margin-bottom: 1rem;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .risk-assessment-panel .ai-reasons ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .risk-assessment-panel .ai-reasons li {
            padding: 0.6rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .risk-assessment-panel .ai-reasons li:last-child {
            border-bottom: none;
        }

        .risk-assessment-panel .ai-reasons li:before {
            content: "→ ";
            margin-right: 0.75rem;
            font-weight: bold;
            opacity: 0.8;
        }

        /* Leverage Condition Colors */
        .risk-assessment-panel .leverage-low { 
            color: #4ade80 !important;
            text-shadow: 0 0 20px rgba(74, 222, 128, 0.5);
        }
        
        .risk-assessment-panel .leverage-increasing { 
            color: #fbbf24 !important;
            text-shadow: 0 0 20px rgba(251, 191, 36, 0.5);
        }
        
        .risk-assessment-panel .leverage-excessive { 
            color: #f87171 !important;
            text-shadow: 0 0 20px rgba(248, 113, 113, 0.5);
        }

        /* Responsive Risk Assessment Panel */
        @media (max-width: 768px) {
            .risk-assessment-panel .ai-value {
                font-size: 1rem;
            }
            
            .risk-assessment-panel .ai-label {
                font-size: 0.6rem;
            }
            
            .risk-assessment-panel .ai-card {
                padding: 0.75rem 0.5rem;
            }
            
            .risk-assessment-panel .row.g-3 {
                --bs-gutter-x: 0.5rem;
                --bs-gutter-y: 0.5rem;
            }
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
