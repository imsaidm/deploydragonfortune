@extends('layouts.app')

@section('title', 'Open Interest Advanced | DragonFortune')

@push('head')
    {{-- Chart.js is bundled in app.js --}}
    <style>
        .df-panel-glass {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--white, #000000ff);
        }
        .stat-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary, #9ca3af);
        }
        .chart-container-wrapper {
            position: relative;
            width: 100%;
            height: 500px;
        }
        .ai-panel {
            max-height: 500px;
            overflow-y: auto;
        }
        .ai-insight-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }
    </style>
@endpush

@section('content')
<div class="d-flex flex-column gap-3" id="oi-dashboard">
    <!-- Page Header -->
    <div class="derivatives-header mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h1 class="h3 mb-0 fw-bold">Open Interest Analysis</h1>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Advanced</span>
                </div>
                <p class="mb-0 text-secondary small">
                    Deep dive into aggregated Open Interest, Stablecoin margins, and AI-driven market regime analysis.
                </p>
            </div>

            <!-- Controls -->
            <div class="d-flex gap-2 align-items-center bg-dark bg-opacity-25 p-2 rounded">
                 <div class="input-group input-group-sm w-auto">
                    <span class="input-group-text bg-transparent border-secondary text-secondary">Symbol</span>
                    <select id="symbol-select" class="form-select bg-dark text-light border-secondary">
                        <option value="BTC">BTC</option>
                        <option value="ETH">ETH</option>
                        <option value="SOL">SOL</option>
                        <option value="XRP">XRP</option>
                        <option value="BNB">BNB</option>
                    </select>
                </div>
                
                <div class="text-secondary small ms-2 border-start ps-3 border-secondary">
                    Updated: <span id="last-updated" class="text-white font-monospace">--:--:--</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3">
        <!-- Total OI -->
        <div class="col-12 col-md-6 col-lg-3">
            <div class="df-panel p-3 h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="text-uppercase text-muted small fw-bold mb-2">Total Open Interest</h6>
                    <i class="text-primary" data-feather="dollar-sign" style="width: 16px;"></i>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="stat-value" id="stat-total-oi">--</span>
                    <span class="small fw-medium" id="stat-oi-change">--</span>
                </div>
                <div class="mt-2 text-muted small">Aggregated USD Value</div>
            </div>
        </div>

        <!-- Market Regime -->
        <div class="col-12 col-md-6 col-lg-3">
            <div class="df-panel p-3 h-100 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="stat-label">Market Regime</span>
                    <i class="text-info" data-feather="activity" style="width: 16px;"></i>
                </div>
                <div class="stat-value text-truncate" id="stat-regime" style="font-size: 1.25rem;">--</div>
                <div class="text-secondary small mt-1">AI Sentiment Analysis</div>
            </div>
        </div>

        <!-- Risk Level -->
        <div class="col-12 col-md-6 col-lg-3">
            <div class="df-panel p-3 h-100 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="stat-label">Risk Assessment</span>
                    <i class="text-warning" data-feather="alert-triangle" style="width: 16px;"></i>
                </div>
                <span class="stat-value" id="stat-risk">--</span>
                <div class="text-secondary small mt-1">Leverage & Volatility</div>
            </div>
        </div>

        <!-- Avg Funding -->
        <div class="col-12 col-md-6 col-lg-3">
            <div class="df-panel p-3 h-100 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="text-uppercase text-muted small fw-bold mb-2">Average Open Interest</h6>
                    <i class="text-success" data-feather="bar-chart-2" style="width: 16px;"></i>
                </div>
                <h2 class="mb-0 fw-bold text-success" id="stat-avg-oi">--</h2>
                <div class="mt-2 text-muted small">Global Weight Average (24h)</div>
            </div>
        </div>
    </div>

    <!-- Charts & Analysis Grid -->
    <div class="row g-3 mt-1">
            <!-- Advanced Metrics Row -->
            <div class="row g-3 mb-4">
                <!-- Coin-M Breakdown -->
                <div class="col-md-4">
                    <div class="df-panel p-3 h-100">
                        <h6 class="text-uppercase text-muted small fw-bold mb-3">Margin Composition</h6>
                        <div style="height: 200px; position: relative;">
                            <canvas id="chart-composition"></canvas>
                        </div>
                        <div class="d-flex justify-content-between mt-2 small">
                            <span class="text-warning">Coin-Margined</span>
                            <span class="text-info">Stablecoin-Margined</span>
                        </div>
                    </div>
                </div>

                <!-- Stablecoin Dominance -->
                <div class="col-md-4">
                    <div class="df-panel p-3 h-100 text-center">
                        <h6 class="text-uppercase text-muted small fw-bold mb-3">Stablecoin Dominance</h6>
                        <div style="height: 160px; position: relative; display: flex; align-items: center; justify-content: center;">
                            <canvas id="chart-dominance"></canvas>
                            <div style="position: absolute; top: 60%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                                <h3 class="mb-0 fw-bold text-info" id="stat-dominance-val">--%</h3>
                                <small class="text-muted">USDT Ratio</small>
                            </div>
                        </div>
                        <div class="mt-3 small text-start text-muted px-2">
                             Shows the proportion of Open Interest backed by Stablecoins (USDT/USDC). Higher ratio = Potential "Dry Powder".
                        </div>
                    </div>
                </div>

                <!-- OI Volatility -->
                <div class="col-md-4">
                    <div class="df-panel p-3 h-100">
                        <h6 class="text-uppercase text-muted small fw-bold mb-3">OI Volatility (1H)</h6>
                         <div style="height: 180px;">
                            <canvas id="chart-volatility"></canvas>
                        </div>
                        <div class="d-flex justify-content-between mt-2 small text-muted">
                            <span>Movement Intensity</span>
                            <span id="stat-vol-val" class="fw-bold text-light">--</span>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Main Chart -->
        <div class="col-12 col-lg-8">
            <div class="df-panel h-100 d-flex flex-column">
                <div class="p-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold fs-6">
                        <span class="text-primary me-2">●</span> Aggregated Open Interest vs Price
                    </h5>
                    <!-- <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary active" onclick="OpenInterestNewController.setFrame('1h')">1H</button>
                        <button class="btn btn-outline-secondary" onclick="OpenInterestNewController.setFrame('4h')">4H</button>
                    </div> -->
                </div>
                <div class="flex-grow-1 p-0 chart-container-wrapper" style="min-height: 450px;">
                    <canvas id="chart-container" class="w-100 h-100"></canvas>
                </div>
            </div>
        </div>

        <!-- Right Panel: AI & Stablecoin -->
        <div class="col-12 col-lg-4">
            <div class="d-flex flex-column h-100 gap-3">
                
                <!-- AI Analysis -->
                <div class="df-panel flex-grow-1 d-flex flex-column">
                    <div class="p-3 border-bottom border-secondary border-opacity-25">
                        <h5 class="mb-0 fw-bold fs-6 text-info">
                            <i data-feather="cpu" class="me-2" style="width: 16px;"></i> AI Market Analysis
                        </h5>
                    </div>
                    <div class="p-3 ai-panel df-scrollbar flex-grow-1" id="ai-analysis-content">
                        <!-- Content injected by JS -->
                        <div class="placeholder-glow">
                            <span class="placeholder col-7 mb-2"></span>
                            <span class="placeholder col-4 mb-2"></span>
                            <span class="placeholder col-6"></span>
                        </div>
                    </div>
                </div>

                <!-- Stablecoin Chart -->
                 <div class="df-panel" style="height: 250px;">
                    <div class="p-3 border-bottom border-secondary border-opacity-25">
                        <h5 class="mb-0 fw-bold fs-6 text-success">
                             Stablecoin Margin OI
                        </h5>
                    </div>
                    <div class="p-0 position-relative w-100 h-100" style="max-height: 200px;">
                         <canvas id="stablecoin-mini-chart" class="w-100 h-100"></canvas>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/open-interest-new-controller.js') }}?v={{ time() }}"></script>
<!-- Initialize Feather Icons if available in layout, otherwise safe fail -->
<script>
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Direct Clock Fix
    (function() {
        function updateClock() {
            const el = document.getElementById('last-updated');
            if (el) {
                el.textContent = new Date().toLocaleTimeString();
            }
        }
        setInterval(updateClock, 1000);
        updateClock(); // Initial run
    })();
</script>
@endsection
