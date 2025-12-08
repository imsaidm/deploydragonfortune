<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Signal & Analytics | DragonFortune</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        :root {
            --bg-dark: #0a0f1a;
            --bg-card: #0f1629;
            --bg-card-hover: #151d35;
            --bg-soft: #1a2340;
            --text-primary: #f0f4fc;
            --text-secondary: #7a8599;
            --accent-green: #00d4aa;
            --accent-red: #ff4757;
            --accent-blue: #5b8def;
            --accent-yellow: #ffc312;
            --accent-purple: #a855f7;
            --accent-orange: #ff9f43;
            --border-color: rgba(255,255,255,0.06);
            --glow-green: 0 0 20px rgba(0, 212, 170, 0.3);
            --glow-red: 0 0 20px rgba(255, 71, 87, 0.3);
            --glow-blue: 0 0 20px rgba(91, 141, 239, 0.3);
        }
        * { box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, var(--bg-dark) 0%, #0d1321 50%, #0a1628 100%);
            color: var(--text-primary);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .df-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            transition: all 0.3s ease;
        }
        .df-card:hover { background: var(--bg-card-hover); }
        .df-card-glow-green { box-shadow: var(--glow-green); border-color: rgba(0, 212, 170, 0.3); }
        .df-card-glow-red { box-shadow: var(--glow-red); border-color: rgba(255, 71, 87, 0.3); }
        .df-card-glow-blue { box-shadow: var(--glow-blue); border-color: rgba(91, 141, 239, 0.3); }
        
        /* Signal Gauge */
        .signal-gauge {
            width: 200px;
            height: 120px;
            position: relative;
            margin: 0 auto;
        }
        .gauge-bg {
            width: 200px;
            height: 100px;
            border-radius: 100px 100px 0 0;
            background: linear-gradient(90deg, var(--accent-red) 0%, var(--accent-yellow) 50%, var(--accent-green) 100%);
            position: relative;
            overflow: hidden;
        }
        .gauge-mask {
            position: absolute;
            width: 160px;
            height: 80px;
            background: var(--bg-card);
            border-radius: 80px 80px 0 0;
            bottom: 0;
            left: 20px;
        }
        .gauge-needle {
            position: absolute;
            width: 4px;
            height: 70px;
            background: white;
            bottom: 10px;
            left: 98px;
            transform-origin: bottom center;
            transition: transform 0.8s ease;
            border-radius: 2px;
            box-shadow: 0 0 10px rgba(255,255,255,0.5);
        }
        .gauge-center {
            position: absolute;
            width: 16px;
            height: 16px;
            background: white;
            border-radius: 50%;
            bottom: 2px;
            left: 92px;
        }
        
        /* Price Display */
        .price-display {
            font-size: 2.5rem;
            font-weight: 800;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: -1px;
        }
        .price-change { font-size: 1.1rem; font-weight: 600; }
        
        /* Signal Badge */
        .signal-badge {
            font-size: 1.8rem;
            padding: 0.8rem 2.5rem;
            border-radius: 50px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }
        .signal-buy { 
            background: linear-gradient(135deg, var(--accent-green), #00b894);
            color: white;
            box-shadow: var(--glow-green);
        }
        .signal-sell { 
            background: linear-gradient(135deg, var(--accent-red), #e74c3c);
            color: white;
            box-shadow: var(--glow-red);
        }
        .signal-neutral { 
            background: linear-gradient(135deg, var(--accent-yellow), #f39c12);
            color: #1a1a2e;
        }
        
        /* Metric Cards */
        .metric-value { font-size: 1.8rem; font-weight: 700; }
        .metric-label { 
            color: var(--text-secondary); 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            letter-spacing: 0.1em;
            margin-bottom: 0.25rem;
        }
        
        /* Factor Pills */
        .factor-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            background: var(--bg-soft);
            border: 1px solid var(--border-color);
            transition: all 0.2s;
        }
        .factor-pill:hover { transform: translateY(-2px); }
        .factor-bullish { border-color: var(--accent-green); color: var(--accent-green); }
        .factor-bearish { border-color: var(--accent-red); color: var(--accent-red); }
        
        /* Progress Bars */
        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
            background: var(--bg-soft);
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        /* Tables */
        .table-dark-custom {
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-primary);
            --bs-table-border-color: var(--border-color);
        }
        .table-dark-custom th {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* Tabs */
        .nav-pills-custom .nav-link {
            color: var(--text-secondary);
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .nav-pills-custom .nav-link:hover { color: var(--text-primary); }
        .nav-pills-custom .nav-link.active {
            background: var(--accent-blue);
            border-color: var(--accent-blue);
            color: white;
        }
        
        /* Pulse Animation */
        @keyframes pulse-glow {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse-live {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--accent-green);
            animation: pulse-glow 2s ease-in-out infinite;
        }
        
        /* Heatmap Cell */
        .heatmap-cell {
            width: 100%;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: 4px;
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-dark); }
        ::-webkit-scrollbar-thumb { background: var(--bg-soft); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-blue); }
    </style>
</head>
<body>
    <div class="container-fluid py-4" x-data="signalAnalytics()" x-init="init()">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <a href="/" class="text-decoration-none text-secondary small mb-2 d-inline-block">&larr; Dashboard</a>
                <div class="d-flex align-items-center gap-3">
                    <h1 class="mb-0 fw-bold">Signal & Analytics</h1>
                    <div class="d-flex align-items-center gap-2">
                        <div class="pulse-live"></div>
                        <span class="text-secondary small">LIVE</span>
                    </div>
                </div>
                <p class="text-secondary mb-0 mt-1">Multi-factor BTC signal engine dengan AI enhancement</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <div class="text-end me-3">
                    <div class="text-secondary small">Last Update</div>
                    <div class="small" x-text="lastUpdate || '--'"></div>
                </div>
                <button class="btn btn-primary px-4" @click="fetchData()" :disabled="loading">
                    <span x-show="loading" class="spinner-border spinner-border-sm me-2"></span>
                    <span x-show="!loading">↻</span> Refresh
                </button>
            </div>
        </div>

        <!-- Top Row: Price + Main Signal -->
        <div class="row g-4 mb-4">
            <!-- Live Price Card -->
            <div class="col-lg-4">
                <div class="df-card h-100" :class="priceChange >= 0 ? 'df-card-glow-green' : 'df-card-glow-red'">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="metric-label">BTC/USDT</div>
                            <div class="price-display" x-text="formatPrice(currentPrice)">$--</div>
                        </div>
                        <img src="https://assets.coingecko.com/coins/images/1/small/bitcoin.png" width="48" height="48" style="border-radius:50%;">
                    </div>
                    <div class="d-flex gap-4">
                        <div>
                            <div class="text-secondary small">24h Change</div>
                            <div class="price-change" :class="priceChange >= 0 ? 'text-success' : 'text-danger'">
                                <span x-text="priceChange >= 0 ? '+' : ''"></span><span x-text="priceChange?.toFixed(2) || '0'"></span>%
                            </div>
                        </div>
                        <div>
                            <div class="text-secondary small">24h Volume</div>
                            <div class="fw-semibold" x-text="formatVolume(volume24h)">$--</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Signal Card -->
            <div class="col-lg-4">
                <div class="df-card h-100 text-center" :class="signalGlowClass()">
                    <div class="metric-label mb-3">Final Signal (Rules + AI)</div>
                    <div class="signal-badge mb-3" :class="signalBadgeClass()" x-text="blended.decision || 'NEUTRAL'">NEUTRAL</div>
                    
                    <!-- Signal Gauge -->
                    <div class="signal-gauge mb-3">
                        <div class="gauge-bg">
                            <div class="gauge-mask"></div>
                            <div class="gauge-needle" :style="'transform: rotate(' + gaugeAngle() + 'deg)'"></div>
                            <div class="gauge-center"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 px-2">
                            <span class="small text-danger">SELL</span>
                            <span class="small text-warning">NEUTRAL</span>
                            <span class="small text-success">BUY</span>
                        </div>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="metric-label">Score</div>
                            <div class="h5 mb-0" :class="signal.score > 0 ? 'text-success' : signal.score < 0 ? 'text-danger' : ''" 
                                 x-text="signal.score?.toFixed(1) || '0'">0</div>
                        </div>
                        <div class="col-4">
                            <div class="metric-label">Confidence</div>
                            <div class="h5 mb-0" x-text="formatPct(blended.confidence)">0%</div>
                        </div>
                        <div class="col-4">
                            <div class="metric-label">AI Prob</div>
                            <div class="h5 mb-0" x-text="ai.probability ? formatPct(ai.probability) : '--'">--</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quality & Regime Card -->
            <div class="col-lg-4">
                <div class="df-card h-100">
                    <div class="row h-100">
                        <div class="col-6 border-end border-secondary border-opacity-25">
                            <div class="metric-label mb-2">Signal Quality</div>
                            <div class="metric-value mb-2" x-text="formatPct(quality.score)">0%</div>
                            <span class="badge" :class="qualityBadgeClass()" x-text="quality.status || 'LOW'">LOW</span>
                            <div class="progress-bar-custom mt-3">
                                <div class="progress-bar-fill" 
                                     :class="quality.score >= 0.8 ? 'bg-success' : quality.score >= 0.5 ? 'bg-warning' : 'bg-danger'"
                                     :style="'width:' + ((quality.score || 0) * 100) + '%'"></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="metric-label mb-2">Market Regime</div>
                            <div class="h4 mb-2" x-text="regime || 'UNKNOWN'">--</div>
                            <p class="small text-secondary mb-0" x-text="regimeReason || ''">--</p>
                            <div class="mt-3">
                                <span class="badge bg-secondary" x-text="'Trend: ' + (features.momentum?.trend_score?.toFixed(1) || '0')"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Row: Factors & Position Sizing -->
        <div class="row g-4 mb-4">
            <!-- Active Factors -->
            <div class="col-lg-8">
                <div class="df-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Active Signal Factors</h5>
                        <span class="badge bg-secondary" x-text="(signal.factors || []).length + ' factors'">0 factors</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <template x-for="(factor, idx) in signal.factors || []" :key="idx">
                            <div class="factor-pill" :class="factor.weight > 0 ? 'factor-bullish' : 'factor-bearish'">
                                <span class="badge" :class="factor.weight > 0 ? 'bg-success' : 'bg-danger'" 
                                      x-text="(factor.weight > 0 ? '+' : '') + factor.weight">0</span>
                                <span x-text="factor.reason">--</span>
                            </div>
                        </template>
                        <template x-if="!signal.factors || signal.factors.length === 0">
                            <div class="text-secondary">No active factors</div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Position Sizing -->
            <div class="col-lg-4">
                <div class="df-card h-100">
                    <h5 class="mb-3">💰 Position Sizing</h5>
                    <div class="mb-3">
                        <label class="form-label small text-secondary">Portfolio Size (USD)</label>
                        <input type="number" class="form-control bg-dark text-white border-secondary" 
                               x-model.number="portfolioSize" @input="calculatePosition()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-secondary">Risk per Trade (%)</label>
                        <input type="range" class="form-range" min="0.5" max="5" step="0.5" 
                               x-model.number="riskPercent" @input="calculatePosition()">
                        <div class="d-flex justify-content-between small text-secondary">
                            <span>0.5%</span>
                            <span x-text="riskPercent + '%'" class="fw-bold text-primary"></span>
                            <span>5%</span>
                        </div>
                    </div>
                    <div class="bg-dark rounded p-3">
                        <div class="row text-center">
                            <div class="col-6 border-end border-secondary">
                                <div class="text-secondary small">Position Size</div>
                                <div class="h5 mb-0 text-success" x-text="'$' + positionSize.toLocaleString()">$0</div>
                            </div>
                            <div class="col-6">
                                <div class="text-secondary small">BTC Amount</div>
                                <div class="h5 mb-0" x-text="btcAmount.toFixed(4) + ' BTC'">0 BTC</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Third Row: Factor Monitor Grid -->
        <div class="df-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">📊 Factor Monitor</h5>
                <div class="nav nav-pills-custom gap-2">
                    <button class="nav-link active">Live</button>
                </div>
            </div>
            <div class="row g-3">
                <!-- Funding -->
                <div class="col-md-4 col-lg-2">
                    <div class="bg-dark rounded p-3 h-100">
                        <div class="metric-label">Funding Rate</div>
                        <div class="h4 mb-1" :class="fundingColor()" x-text="features.funding?.heat_score?.toFixed(2) || '--'">--</div>
                        <div class="small text-secondary">Heat Score (Z)</div>
                        <div class="progress-bar-custom mt-2">
                            <div class="progress-bar-fill" :style="fundingBarStyle()" :class="fundingColor().replace('text-', 'bg-')"></div>
                        </div>
                    </div>
                </div>
                <!-- Open Interest -->
                <div class="col-md-4 col-lg-2">
                    <div class="bg-dark rounded p-3 h-100">
                        <div class="metric-label">Open Interest</div>
                        <div class="h4 mb-1" :class="oiColor()" x-text="formatChange(features.open_interest?.pct_change_24h)">--</div>
                        <div class="small text-secondary">24h Change</div>
                        <div class="small mt-1" x-text="'6h: ' + formatChange(features.open_interest?.pct_change_6h)"></div>
                    </div>
                </div>
                <!-- Whale Activity -->
                <div class="col-md-4 col-lg-2">
                    <div class="bg-dark rounded p-3 h-100">
                        <div class="metric-label">Whale Pressure</div>
                        <div class="h4 mb-1" :class="whaleColor()" x-text="features.whales?.pressure_score?.toFixed(2) || '--'">--</div>
                        <div class="small text-secondary">Exchange Flow</div>
                        <div class="small mt-1">CEX: <span x-text="formatPct(features.whales?.cex_ratio)">--</span></div>
                    </div>
                </div>
                <!-- ETF Flows -->
                <div class="col-md-4 col-lg-2">
                    <div class="bg-dark rounded p-3 h-100">
                        <div class="metric-label">ETF Flow</div>
                        <div class="h4 mb-1" :class="etfColor()" x-text="formatUsd(features.etf?.latest_flow)">--</div>
                        <div class="small text-secondary">Latest Daily</div>
                        <div class="small mt-1">Streak: <span x-text="features.etf?.streak || 0"></span> days</div>
                    </div>
                </div>
                <!-- Sentiment -->
                <div class="col-md-4 col-lg-2">
                    <div class="bg-dark rounded p-3 h-100">
                        <div class="metric-label">Fear & Greed</div>
                        <div class="h4 mb-1" :class="sentimentColor()" x-text="features.sentiment?.value || '--'">--</div>
                        <div class="small text-secondary" x-text="features.sentiment?.classification || '--'">--</div>
                        <div class="progress-bar-custom mt-2">
                            <div class="progress-bar-fill" :style="'width:' + (features.sentiment?.value || 0) + '%'" :class="sentimentColor().replace('text-', 'bg-')"></div>
                        </div>
                    </div>
                </div>
                <!-- Taker Flow -->
                <div class="col-md-4 col-lg-2">
                    <div class="bg-dark rounded p-3 h-100">
                        <div class="metric-label">Taker Buy %</div>
                        <div class="h4 mb-1" :class="takerColor()" x-text="formatPct(features.microstructure?.taker_flow?.buy_ratio)">--</div>
                        <div class="small text-secondary">Order Flow</div>
                        <div class="small mt-1" x-text="takerBias()"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fourth Row: Charts + Trade Info -->
        <div class="row g-4 mb-4">
            <!-- Signal History Chart -->
            <div class="col-lg-8">
                <div class="df-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">📈 Signal & Price History</h5>
                    </div>
                    <div style="height: 300px;">
                        <canvas x-ref="historyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Performance Stats -->
            <div class="col-lg-4">
                <div class="df-card h-100">
                    <h5 class="mb-3">🎯 Backtest Performance</h5>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="bg-dark rounded p-3 text-center">
                                <div class="metric-label">Win Rate</div>
                                <div class="h3 mb-0 text-success" x-text="formatPct(performance.backtest?.metrics?.win_rate)">--</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-dark rounded p-3 text-center">
                                <div class="metric-label">Profit Factor</div>
                                <div class="h3 mb-0 text-info" x-text="performance.backtest?.metrics?.profit_factor?.toFixed(2) || '--'">--</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-dark rounded p-3 text-center">
                                <div class="metric-label">Expectancy</div>
                                <div class="h4 mb-0" x-text="formatChange(performance.backtest?.metrics?.expectancy_pct)">--</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-dark rounded p-3 text-center">
                                <div class="metric-label">Total Trades</div>
                                <div class="h4 mb-0" x-text="performance.backtest?.total || '--'">--</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="bg-dark rounded p-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-secondary small">AI Filtered Win Rate</span>
                                    <span class="text-success fw-bold" x-text="formatPct(performance.backtest?.metrics?.filtered_win_rate)">--</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-secondary small">Max Drawdown</span>
                                    <span class="text-danger" x-text="formatChange(performance.backtest?.metrics?.max_drawdown_pct)">--</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-secondary small">Avg Return/Trade</span>
                                    <span x-text="formatChange(performance.backtest?.metrics?.avg_return_all_pct)">--</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fifth Row: Detailed Tables -->
        <div class="row g-4 mb-4">
            <!-- Long/Short Analysis -->
            <div class="col-lg-6">
                <div class="df-card h-100">
                    <h5 class="mb-3">⚖️ Long/Short Analysis</h5>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="bg-dark rounded p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-secondary small">Global Accounts</span>
                                    <span class="badge" :class="lsBadgeClass(features.long_short?.global?.net_ratio)" 
                                          x-text="features.long_short?.bias?.global || '--'">--</span>
                                </div>
                                <div class="d-flex gap-2">
                                    <div class="flex-grow-1">
                                        <div class="small text-success mb-1">Long <span x-text="formatPct(features.long_short?.global?.long_ratio)"></span></div>
                                        <div class="progress-bar-custom">
                                            <div class="progress-bar-fill bg-success" :style="'width:' + ((features.long_short?.global?.long_ratio || 0) * 100) + '%'"></div>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="small text-danger mb-1">Short <span x-text="formatPct(features.long_short?.global?.short_ratio)"></span></div>
                                        <div class="progress-bar-custom">
                                            <div class="progress-bar-fill bg-danger" :style="'width:' + ((features.long_short?.global?.short_ratio || 0) * 100) + '%'"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-dark rounded p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-secondary small">Top Traders</span>
                                    <span class="badge" :class="lsBadgeClass(features.long_short?.top?.net_ratio)" 
                                          x-text="features.long_short?.bias?.top || '--'">--</span>
                                </div>
                                <div class="d-flex gap-2">
                                    <div class="flex-grow-1">
                                        <div class="small text-success mb-1">Long <span x-text="formatPct(features.long_short?.top?.long_ratio)"></span></div>
                                        <div class="progress-bar-custom">
                                            <div class="progress-bar-fill bg-success" :style="'width:' + ((features.long_short?.top?.long_ratio || 0) * 100) + '%'"></div>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="small text-danger mb-1">Short <span x-text="formatPct(features.long_short?.top?.short_ratio)"></span></div>
                                        <div class="progress-bar-custom">
                                            <div class="progress-bar-fill bg-danger" :style="'width:' + ((features.long_short?.top?.short_ratio || 0) * 100) + '%'"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center">
                        <span class="text-secondary small">Smart Money Divergence: </span>
                        <span class="fw-bold" :class="features.long_short?.divergence > 0 ? 'text-success' : 'text-danger'"
                              x-text="(features.long_short?.divergence > 0 ? '+' : '') + (features.long_short?.divergence?.toFixed(3) || '0')">0</span>
                    </div>
                </div>
            </div>

            <!-- Liquidations -->
            <div class="col-lg-6">
                <div class="df-card h-100">
                    <h5 class="mb-3">💥 Liquidations (24h)</h5>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded p-3 text-center">
                                <div class="metric-label">Longs Liquidated</div>
                                <div class="h4 mb-0 text-danger" x-text="formatUsd(features.liquidations?.sum_24h?.longs)">--</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-success bg-opacity-10 border border-success border-opacity-25 rounded p-3 text-center">
                                <div class="metric-label">Shorts Liquidated</div>
                                <div class="h4 mb-0 text-success" x-text="formatUsd(features.liquidations?.sum_24h?.shorts)">--</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-secondary small">Liquidation Ratio</span>
                            <span class="badge" :class="liqRatioBadge()" x-text="liqRatioText()">--</span>
                        </div>
                        <div class="progress-bar-custom" style="height: 12px;">
                            <div class="d-flex h-100">
                                <div class="bg-danger" :style="'width:' + liqLongPct() + '%'"></div>
                                <div class="bg-success" :style="'width:' + liqShortPct() + '%'"></div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <span class="small text-danger" x-text="liqLongPct().toFixed(0) + '% Longs'">--</span>
                            <span class="small text-success" x-text="liqShortPct().toFixed(0) + '% Shorts'">--</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Signal History -->
        <div class="df-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">📋 Recent Signal History</h5>
                <span class="badge bg-secondary" x-text="(performance.recent_history || []).length + ' signals'">0 signals</span>
            </div>
            <div class="table-responsive">
                <table class="table table-dark-custom table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Signal</th>
                            <th>Score</th>
                            <th>Confidence</th>
                            <th>Price</th>
                            <th>Future Price</th>
                            <th>Forward Return</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, idx) in performance.recent_history || []" :key="idx">
                            <tr>
                                <td class="small" x-text="formatDateTime(row.generated_at)">--</td>
                                <td>
                                    <span class="badge" :class="signalBadgeClass(row.signal)" x-text="row.signal">--</span>
                                </td>
                                <td x-text="row.score?.toFixed(2) || '--'">--</td>
                                <td x-text="formatPct(row.confidence)">--</td>
                                <td x-text="row.price_now ? '$' + parseFloat(row.price_now).toLocaleString() : '--'">--</td>
                                <td x-text="row.price_future ? '$' + parseFloat(row.price_future).toLocaleString() : '--'">--</td>
                                <td :class="pnlClass(row.forward_return_pct)" x-text="formatChange(row.forward_return_pct)">--</td>
                                <td>
                                    <span class="badge" :class="resultBadge(row)" x-text="resultText(row)">--</span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!performance.recent_history || performance.recent_history.length === 0">
                            <td colspan="8" class="text-center text-secondary py-4">No signal history yet</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Data Health -->
        <div class="df-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">🔧 Data Health</h5>
                <span class="badge" :class="healthBadge()" x-text="features.health?.is_degraded ? 'DEGRADED' : 'HEALTHY'">--</span>
            </div>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="metric-label">Completeness</div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="h4 mb-0" x-text="formatPct(features.health?.completeness)">--</div>
                        <div class="progress-bar-custom flex-grow-1">
                            <div class="progress-bar-fill bg-success" :style="'width:' + ((features.health?.completeness || 0) * 100) + '%'"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="metric-label">Missing Sections</div>
                    <div x-text="(features.health?.missing_sections || []).join(', ') || 'None'" class="small"></div>
                </div>
                <div class="col-md-4">
                    <div class="metric-label">Quality Flags</div>
                    <div class="d-flex flex-wrap gap-1">
                        <template x-for="(flag, idx) in quality.flags || []" :key="idx">
                            <span class="badge" :class="'bg-' + (flag.severity || 'secondary')" x-text="flag.label || flag.code">--</span>
                        </template>
                        <span x-show="!quality.flags || quality.flags.length === 0" class="text-secondary small">No flags</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function signalAnalytics() {
            return {
                loading: false,
                lastUpdate: null,
                
                // Price data
                currentPrice: null,
                priceChange: 0,
                volume24h: null,
                
                // Signal data
                signal: {},
                ai: {},
                blended: { decision: 'NEUTRAL', confidence: 0 },
                quality: { score: 0, status: 'LOW', flags: [] },
                features: {},
                performance: { backtest: { metrics: {} }, recent_history: [] },
                regime: null,
                regimeReason: null,
                
                // Position sizing
                portfolioSize: 10000,
                riskPercent: 2,
                positionSize: 0,
                btcAmount: 0,
                
                // Charts
                historyChart: null,

                init() {
                    this.fetchData();
                    // Auto-refresh every 60 seconds
                    setInterval(() => this.fetchData(), 60000);
                },

                async fetchData() {
                    this.loading = true;
                    try {
                        const response = await fetch('/api/signal/analytics?symbol=BTC&tf=1h&backtest_days=90');
                        const data = await response.json();
                        
                        if (data.success) {
                            this.signal = data.signal || {};
                            this.ai = data.ai || {};
                            this.blended = data.blended || { decision: 'NEUTRAL', confidence: 0 };
                            this.quality = data.signal?.quality || { score: 0, status: 'LOW', flags: [] };
                            this.features = data.features || {};
                            this.performance = data.performance || { backtest: { metrics: {} }, recent_history: [] };
                            this.regime = data.signal?.meta?.regime || data.features?.momentum?.regime;
                            this.regimeReason = data.signal?.meta?.regime_reason || data.features?.momentum?.regime_reason;
                            
                            // Price data
                            this.currentPrice = data.features?.microstructure?.price?.last_close;
                            this.priceChange = data.features?.microstructure?.price?.pct_change_24h || data.features?.momentum?.momentum_1d_pct;
                            
                            this.lastUpdate = new Date().toLocaleTimeString();
                            this.calculatePosition();
                            this.$nextTick(() => this.renderCharts());
                        }
                    } catch (error) {
                        console.error('Fetch error:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                calculatePosition() {
                    const riskAmount = this.portfolioSize * (this.riskPercent / 100);
                    // Adjust position based on signal strength and quality
                    const signalMultiplier = Math.min(Math.abs(this.signal.score || 0) / 5, 1);
                    const qualityMultiplier = this.quality.score || 0.5;
                    
                    this.positionSize = Math.round(riskAmount * signalMultiplier * qualityMultiplier * 10);
                    this.btcAmount = this.currentPrice ? this.positionSize / this.currentPrice : 0;
                },

                renderCharts() {
                    this.renderHistoryChart();
                },

                renderHistoryChart() {
                    const canvas = this.$refs.historyChart;
                    if (!canvas) return;
                    
                    if (this.historyChart) this.historyChart.destroy();
                    
                    const history = this.performance.recent_history || [];
                    if (history.length === 0) return;
                    
                    const labels = history.map(h => new Date(h.generated_at).toLocaleDateString()).reverse();
                    const scores = history.map(h => h.score || 0).reverse();
                    const returns = history.map(h => h.forward_return_pct || 0).reverse();
                    
                    this.historyChart = new Chart(canvas, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'Signal Score',
                                    data: scores,
                                    borderColor: '#5b8def',
                                    backgroundColor: 'rgba(91, 141, 239, 0.1)',
                                    yAxisID: 'y',
                                    tension: 0.4,
                                    fill: true,
                                },
                                {
                                    label: 'Forward Return %',
                                    data: returns,
                                    borderColor: '#00d4aa',
                                    backgroundColor: 'rgba(0, 212, 170, 0.1)',
                                    yAxisID: 'y1',
                                    tension: 0.4,
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { intersect: false, mode: 'index' },
                            plugins: {
                                legend: { 
                                    position: 'top',
                                    labels: { color: '#7a8599' }
                                }
                            },
                            scales: {
                                x: { 
                                    grid: { color: 'rgba(255,255,255,0.05)' },
                                    ticks: { color: '#7a8599' }
                                },
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    grid: { color: 'rgba(255,255,255,0.05)' },
                                    ticks: { color: '#5b8def' },
                                    title: { display: true, text: 'Score', color: '#5b8def' }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    grid: { drawOnChartArea: false },
                                    ticks: { color: '#00d4aa', callback: v => v + '%' },
                                    title: { display: true, text: 'Return %', color: '#00d4aa' }
                                }
                            }
                        }
                    });
                },

                // Helper functions
                gaugeAngle() {
                    const score = this.signal.score || 0;
                    // Map score (-5 to +5) to angle (-90 to +90)
                    const clamped = Math.max(-5, Math.min(5, score));
                    return (clamped / 5) * 90;
                },

                signalBadgeClass(signal) {
                    const s = signal || this.blended.decision;
                    if (s === 'BUY') return 'signal-buy';
                    if (s === 'SELL') return 'signal-sell';
                    return 'signal-neutral';
                },

                signalGlowClass() {
                    const s = this.blended.decision;
                    if (s === 'BUY') return 'df-card-glow-green';
                    if (s === 'SELL') return 'df-card-glow-red';
                    return 'df-card-glow-blue';
                },

                qualityBadgeClass() {
                    const s = this.quality.status;
                    if (s === 'HIGH') return 'bg-success';
                    if (s === 'MEDIUM') return 'bg-warning text-dark';
                    return 'bg-danger';
                },

                healthBadge() {
                    return this.features.health?.is_degraded ? 'bg-danger' : 'bg-success';
                },

                lsBadgeClass(ratio) {
                    if (!ratio) return 'bg-secondary';
                    if (ratio > 0.03) return 'bg-success';
                    if (ratio < -0.03) return 'bg-danger';
                    return 'bg-warning text-dark';
                },

                fundingColor() {
                    const heat = this.features.funding?.heat_score;
                    if (!heat) return 'text-secondary';
                    if (heat > 1.5) return 'text-danger';
                    if (heat < -1.5) return 'text-success';
                    return 'text-warning';
                },

                fundingBarStyle() {
                    const heat = this.features.funding?.heat_score || 0;
                    const width = Math.min(Math.abs(heat) / 3 * 100, 100);
                    return 'width:' + width + '%';
                },

                oiColor() {
                    const change = this.features.open_interest?.pct_change_24h;
                    if (!change) return 'text-secondary';
                    if (change > 2) return 'text-success';
                    if (change < -2) return 'text-danger';
                    return 'text-warning';
                },

                whaleColor() {
                    const pressure = this.features.whales?.pressure_score;
                    if (!pressure) return 'text-secondary';
                    if (pressure > 1) return 'text-danger';
                    if (pressure < -1) return 'text-success';
                    return 'text-warning';
                },

                etfColor() {
                    const flow = this.features.etf?.latest_flow;
                    if (!flow) return 'text-secondary';
                    return flow > 0 ? 'text-success' : 'text-danger';
                },

                sentimentColor() {
                    const value = this.features.sentiment?.value;
                    if (!value) return 'text-secondary';
                    if (value >= 70) return 'text-danger';
                    if (value <= 30) return 'text-success';
                    return 'text-warning';
                },

                takerColor() {
                    const ratio = this.features.microstructure?.taker_flow?.buy_ratio;
                    if (!ratio) return 'text-secondary';
                    if (ratio > 0.55) return 'text-success';
                    if (ratio < 0.45) return 'text-danger';
                    return 'text-warning';
                },

                takerBias() {
                    const ratio = this.features.microstructure?.taker_flow?.buy_ratio;
                    if (!ratio) return '--';
                    if (ratio > 0.55) return '🟢 Buyers dominating';
                    if (ratio < 0.45) return '🔴 Sellers dominating';
                    return '🟡 Balanced';
                },

                liqLongPct() {
                    const longs = this.features.liquidations?.sum_24h?.longs || 0;
                    const shorts = this.features.liquidations?.sum_24h?.shorts || 0;
                    const total = longs + shorts;
                    return total > 0 ? (longs / total) * 100 : 50;
                },

                liqShortPct() {
                    return 100 - this.liqLongPct();
                },

                liqRatioBadge() {
                    const longPct = this.liqLongPct();
                    if (longPct > 60) return 'bg-success';
                    if (longPct < 40) return 'bg-danger';
                    return 'bg-warning text-dark';
                },

                liqRatioText() {
                    const longPct = this.liqLongPct();
                    if (longPct > 60) return 'Long Flush (Bullish)';
                    if (longPct < 40) return 'Short Squeeze (Bearish)';
                    return 'Balanced';
                },

                pnlClass(value) {
                    if (value === null || value === undefined) return 'text-secondary';
                    return value > 0 ? 'text-success' : 'text-danger';
                },

                resultBadge(row) {
                    if (!row.signal || !row.forward_return_pct) return 'bg-secondary';
                    const isWin = (row.signal === 'BUY' && row.forward_return_pct > 0) || 
                                  (row.signal === 'SELL' && row.forward_return_pct < 0);
                    return isWin ? 'bg-success' : 'bg-danger';
                },

                resultText(row) {
                    if (!row.signal || row.forward_return_pct === null) return 'Pending';
                    const isWin = (row.signal === 'BUY' && row.forward_return_pct > 0) || 
                                  (row.signal === 'SELL' && row.forward_return_pct < 0);
                    return isWin ? 'WIN' : 'LOSS';
                },

                // Formatting
                formatPrice(v) {
                    if (!v) return '$--';
                    return '$' + parseFloat(v).toLocaleString('en-US', { maximumFractionDigits: 0 });
                },

                formatVolume(v) {
                    if (!v) return '$--';
                    const abs = Math.abs(v);
                    if (abs >= 1e12) return '$' + (v / 1e12).toFixed(1) + 'T';
                    if (abs >= 1e9) return '$' + (v / 1e9).toFixed(1) + 'B';
                    if (abs >= 1e6) return '$' + (v / 1e6).toFixed(1) + 'M';
                    return '$' + v.toLocaleString();
                },

                formatUsd(v) {
                    if (v === null || v === undefined) return '--';
                    const abs = Math.abs(v);
                    if (abs >= 1e9) return '$' + (v / 1e9).toFixed(2) + 'B';
                    if (abs >= 1e6) return '$' + (v / 1e6).toFixed(1) + 'M';
                    if (abs >= 1e3) return '$' + (v / 1e3).toFixed(1) + 'K';
                    return '$' + v.toFixed(0);
                },

                formatPct(v) {
                    if (v === null || v === undefined) return '--';
                    return (v * 100).toFixed(1) + '%';
                },

                formatChange(v) {
                    if (v === null || v === undefined) return '--';
                    const prefix = v > 0 ? '+' : '';
                    return prefix + v.toFixed(2) + '%';
                },

                formatDateTime(v) {
                    if (!v) return '--';
                    return new Date(v).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                }
            };
        }
    </script>
</body>
</html>
