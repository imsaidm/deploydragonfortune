@extends('layouts.app')

@section('title', 'Basis & Term Structure | DragonFortune')

@push('head')
    <!-- Resource Hints for Faster API Loading -->
    <link rel="dns-prefetch" href="{{ config('app.api_urls.internal') }}">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="{{ config('app.api_urls.internal') }}" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    
    <!-- Preload critical resources for faster initial load -->
    <link rel="preload" href="{{ asset('js/basis-controller.js') }}" as="script" crossorigin="anonymous">
@endpush

@section('content')
    {{--
        Bitcoin: Basis & Term Structure Dashboard (Coinglass)
        Think like a trader • Build like an engineer • Visualize like a designer

        Interpretasi Trading:
        - Basis = Futures Price - Spot Price
        - Positive Basis (Contango) = Normal market condition
        - Negative Basis (Backwardation) = Bullish signal, strong demand
        - Basis widening = Increasing leverage demand
    --}}

    <div class="d-flex flex-column h-100 gap-3" x-data="basisController()">
        <!-- Page Header -->
        <div class="derivatives-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h1 class="mb-0">Basis & Term Structure</h1>
                        <span class="spinner-border spinner-border-sm text-primary" style="width: 16px; height: 16px;" x-show="rawData.length === 0" x-cloak></span>
                    </div>
                    <p class="mb-0 text-secondary">
                        Pantau spread antara futures dan spot price. Contango menunjukkan kondisi normal, Backwardation menunjukkan demand kuat.
                    </p>
                </div>

                <!-- Global Controls -->
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <!-- Symbol Selector (11 Verified Symbols) -->
                    <select class="form-select" style="width: 120px;" :value="selectedSymbol" @change="updateSymbol($event.target.value)">
                        <option value="BTC">BTC</option>
                        <option value="ETH">ETH</option>
                        <option value="SOL">SOL</option>
                        <option value="DOGE">DOGE</option>
                        <option value="XRP">XRP</option>
                        <option value="ONDO">ONDO</option>
                        <option value="BNB">BNB</option>
                        <option value="ADA">ADA</option>
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

        <!-- Summary Cards Row -->
        <div class="row g-3">
            <!-- Current Basis -->
            <div class="col-md-4">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Current Basis</span>
                        <span class="badge" :class="getStructureBadge(currentBasis)" x-show="currentBasis !== null" x-text="getMarketStructure(currentBasis)"></span>
                        <span class="badge text-bg-secondary" x-show="currentBasis === null">Loading...</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="currentBasis !== null" x-text="formatBasis(currentBasis)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="currentBasis === null">...</div>
                        <small class="text-muted" x-show="basisChange !== null">
                            <span :class="basisChange >= 0 ? 'text-success' : 'text-danger'" x-text="formatChange(basisChange)"></span>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Range (H/L) -->
            <div class="col-md-4">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Range (H/L)</span>
                        <span class="badge text-bg-info" x-show="maxBasis && minBasis">Range</span>
                        <span class="badge text-bg-secondary" x-show="!maxBasis || !minBasis">Loading...</span>
                    </div>
                    <div>
                        <div x-show="maxBasis && minBasis">
                            <div class="h5 mb-1 text-danger" x-text="formatBasis(maxBasis)"></div>
                            <div class="h5 mb-1 text-success" x-text="formatBasis(minBasis)"></div>
                        </div>
                        <div class="h3 mb-1 text-secondary" x-show="!maxBasis || !minBasis">...</div>
                    </div>
                </div>
            </div>

            <!-- Average Basis -->
            <div class="col-md-4">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Avg Basis</span>
                        <span class="badge" :class="getStructureBadge(avgBasis)" x-show="avgBasis !== null" x-text="getMarketStructure(avgBasis)"></span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="avgBasis !== null" x-text="formatBasis(avgBasis)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="avgBasis === null">...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Chart -->
        <div class="row g-3">
            <div class="col-12">
                <div class="tradingview-chart-container">
                    <div class="chart-header">
                        <div class="d-flex align-items-center gap-3">
                            <h5 class="mb-0">Futures Basis</h5>
                            <div class="chart-info">
                                <span class="current-value" x-text="currentBasis !== null ? formatBasis(currentBasis) : '--'"></span>
                            </div>
                        </div>
                        <div class="chart-controls">
                            <div class="d-flex flex-wrap align-items-center gap-3">
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
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle interval-dropdown-btn" 
                                            type="button" 
                                            data-bs-toggle="dropdown">
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
                        <canvas id="basisMainChart"></canvas>
                    </div>
                    <div class="chart-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="chart-footer-text text-secondary">
                                📊 Positive basis (Contango) = Normal market | Negative basis (Backwardation) = Strong demand
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
                    <h5 class="mb-3">📚 Memahami Basis & Term Structure</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="p-3 rounded" style="background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6;">
                                <div class="fw-bold mb-2 text-primary">🔵 Contango (Positive Basis)</div>
                                <div class="small text-secondary">
                                    <ul class="mb-0 ps-3">
                                        <li>Futures price > Spot price</li>
                                        <li>Normal market condition</li>
                                        <li>Carry cost included in futures</li>
                                        <li>Strategi: Cash and carry arbitrage</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded" style="background: rgba(34, 197, 94, 0.1); border-left: 4px solid #22c55e;">
                                <div class="fw-bold mb-2 text-success">🟢 Backwardation (Negative Basis)</div>
                                <div class="small text-secondary">
                                    <ul class="mb-0 ps-3">
                                        <li>Futures price < Spot price</li>
                                        <li>Strong bullish signal</li>
                                        <li>High spot demand</li>
                                        <li>Strategi: Long futures position</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded" style="background: rgba(245, 158, 11, 0.1); border-left: 4px solid #f59e0b;">
                                <div class="fw-bold mb-2 text-warning">⚡ Basis Widening</div>
                                <div class="small text-secondary">
                                    <ul class="mb-0 ps-3">
                                        <li>Increasing spread</li>
                                        <li>Rising leverage demand</li>
                                        <li>Market heating up</li>
                                        <li>Strategi: Monitor for reversal</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        <strong>💡 Tips Pro:</strong> Basis yang sangat negatif (backwardation) sering menandakan strong bullish sentiment. Kombinasikan dengan volume dan open interest untuk konfirmasi.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <!-- Chart.js with Date Adapter -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js" defer></script>

    <!-- Initialize Chart.js ready promise -->
    <script>
        window.chartJsReady = new Promise((resolve) => {
            if (typeof Chart !== 'undefined') {
                resolve();
                return;
            }
            let checkCount = 0;
            const checkInterval = setInterval(() => {
                checkCount++;
                if (typeof Chart !== 'undefined') {
                    clearInterval(checkInterval);
                    resolve();
                } else if (checkCount > 40) {
                    clearInterval(checkInterval);
                    resolve();
                }
            }, 50);
        });
    </script>

    <!-- Basis Controller -->
    <script type="module" src="{{ asset('js/basis-controller.js') }}" defer></script>

    <style>
        [x-cloak] { display: none !important; }
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
        .chart-body {
            padding: 20px;
            height: 400px;
            position: relative;
            background: #ffffff;
        }
        .chart-footer {
            padding: 12px 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
            background: rgba(59, 130, 246, 0.02);
        }
        .current-value {
            color: #3b82f6;
            font-size: 20px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        .pulse-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }
        .pulse-success {
            background-color: #22c55e;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
            50% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
        }
        .df-panel {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
            border: 1px solid rgba(59, 130, 246, 0.1);
            transition: all 0.3s ease;
        }
        .df-panel:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.15);
        }
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
        .time-range-btn.btn-primary {
            background: #3b82f6 !important;
            color: #ffffff !important;
        }
    </style>
@endsection
