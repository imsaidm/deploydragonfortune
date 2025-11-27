@extends('layouts.app')

@section('title', 'Aggregated Liquidations | DragonFortune')

@push('head')
    <!-- Resource Hints -->
    <link rel="dns-prefetch" href="{{ config('app.api_urls.internal') }}">
    <link rel="preconnect" href="{{ config('app.api_urls.internal') }}" crossorigin>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endpush

@section('content')
    {{--
        Aggregated Liquidations
        Historical liquidation data across exchanges
        
        Trading Interpretation:
        - Long liquidations = Bearish pressure (longs forced out)
        - Short liquidations = Bullish pressure (shorts forced out)
        - High liquidations = Market volatility and potential reversals
    --}}

    <div class="d-flex flex-column h-100 gap-3" x-data="liquidationsAggregatedController()">
        <!-- Page Header -->
        <div class="derivatives-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h1 class="mb-0">Aggregated Liquidations</h1>
                        <span class="spinner-border spinner-border-sm text-primary" style="width: 16px; height: 16px;" x-show="rawData.length === 0" x-cloak></span>
                    </div>
                    <p class="mb-0 text-secondary">
                        Historical liquidation data aggregated across multiple exchanges.
                    </p>
                </div>

                <!-- Global Controls -->
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <!-- Symbol Selector -->
                    <select class="form-select" style="width: 120px;" :value="selectedSymbol || 'BTC'" @change="updateSymbol && updateSymbol($event.target.value)">
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

                    <!-- Interval Selector -->
                    <select class="form-select" style="width: 100px;" :value="selectedInterval || '1d'" @change="updateInterval && updateInterval($event.target.value)">
                        <template x-for="interval in (chartIntervals || [])" :key="interval.value">
                            <option :value="interval.value" x-text="interval.label"></option>
                        </template>
                    </select>

                    <!-- Time Range Selector -->
                    <select class="form-select" style="width: 100px;" :value="selectedTimeRange || '1w'" @change="updateRange && updateRange($event.target.value)">
                        <template x-for="range in (timeRanges || [])" :key="range.value">
                            <option :value="range.value" x-text="range.label"></option>
                        </template>
                    </select>

                    <!-- Manual Refresh -->
                    <button class="btn btn-outline-primary d-flex align-items-center gap-2" type="button"
                        @click="refreshAll && refreshAll()">
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
            <!-- Total Liquidations -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Total Liquidations</span>
                        <span class="badge text-bg-primary" x-show="stats && stats.total > 0" x-cloak>Total</span>
                        <span class="badge text-bg-secondary" x-show="!stats || stats.total === 0" x-cloak>Loading...</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="stats && stats.total > 0" x-text="stats && formatValue ? formatValue(stats.total) : '...'" x-cloak></div>
                        <div class="h3 mb-1 text-secondary" x-show="!stats || stats.total === 0" x-cloak>...</div>
                        <small class="text-muted" x-show="stats && stats.count > 0" x-cloak>
                            <span x-text="stats ? stats.count : 0"></span> data points
                        </small>
                    </div>
                </div>
            </div>

            <!-- Long Liquidations -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Long Liquidations</span>
                        <span class="badge text-bg-danger" x-show="stats && stats.totalLong > 0" x-cloak>Longs</span>
                        <span class="badge text-bg-secondary" x-show="!stats || stats.totalLong === 0" x-cloak>Loading...</span>
                    </div>
                    <div>
                        <div class="h3 mb-1 text-danger" x-show="stats && stats.totalLong > 0" x-text="stats && formatValue ? formatValue(stats.totalLong) : '...'" x-cloak></div>
                        <div class="h3 mb-1 text-secondary" x-show="!stats || stats.totalLong === 0" x-cloak>...</div>
                    </div>
                </div>
            </div>

            <!-- Short Liquidations -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Short Liquidations</span>
                        <span class="badge text-bg-success" x-show="stats && stats.totalShort > 0" x-cloak>Shorts</span>
                        <span class="badge text-bg-secondary" x-show="!stats || stats.totalShort === 0" x-cloak>Loading...</span>
                    </div>
                    <div>
                        <div class="h3 mb-1 text-success" x-show="stats && stats.totalShort > 0" x-text="stats && formatValue ? formatValue(stats.totalShort) : '...'" x-cloak></div>
                        <div class="h3 mb-1 text-secondary" x-show="!stats || stats.totalShort === 0" x-cloak>...</div>
                    </div>
                </div>
            </div>

            <!-- Long/Short Ratio -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Long/Short Ratio</span>
                        <span class="badge text-bg-info" x-show="stats && stats.longShortRatio > 0" x-cloak>Ratio</span>
                        <span class="badge text-bg-secondary" x-show="!stats || stats.longShortRatio === 0" x-cloak>Loading...</span>
                    </div>
                    <div>
                        <div class="h3 mb-1 text-info" x-show="stats && stats.longShortRatio > 0" x-text="stats && stats.longShortRatio ? stats.longShortRatio.toFixed(2) : '...'" x-cloak></div>
                        <div class="h3 mb-1 text-secondary" x-show="!stats || stats.longShortRatio === 0" x-cloak>...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Chart -->
        <div class="row g-3">
            <div class="col-12">
                <div class="tradingview-chart-container">
                    <div class="chart-header">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <h5 class="mb-0">Liquidations History</h5>
                        </div>
                    </div>
                    <div class="chart-body" style="height: 500px;">
                        <canvas id="liquidationsAggregatedChart"></canvas>
                    </div>
                    <div class="chart-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="chart-footer-text text-secondary">
                                📊 Red bars = Long liquidations (bearish), Green bars = Short liquidations (bullish)
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
                    <h5 class="mb-3">📚 Cara Baca Aggregated Liquidations</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="p-3 rounded" style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444;">
                                <div class="fw-bold mb-2 text-danger">🔴 Long Liquidations</div>
                                <div class="small text-secondary">
                                    Posisi long yang dipaksa tutup. Menunjukkan bearish pressure. Spike besar = potential bottom.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded" style="background: rgba(34, 197, 94, 0.1); border-left: 4px solid #22c55e;">
                                <div class="fw-bold mb-2 text-success">🟢 Short Liquidations</div>
                                <div class="small text-secondary">
                                    Posisi short yang dipaksa tutup. Menunjukkan bullish pressure. Spike besar = potential top.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded" style="background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6;">
                                <div class="fw-bold mb-2 text-info">📊 Long/Short Ratio</div>
                                <div class="small text-secondary">
                                    Ratio > 1 = More longs liquidated (bearish). Ratio < 1 = More shorts liquidated (bullish).
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        <strong>💡 Tips:</strong> Liquidation spikes sering terjadi di market extremes. Gunakan sebagai konfirmasi reversal signal.
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

    <!-- Liquidations Aggregated Controller -->
    <script type="module" src="{{ asset('js/liquidations-aggregated-controller.js') }}" defer></script>

    <style>
        [x-cloak] { display: none !important; }
        
        /* Chart Container (Light theme like Open Interest) */
        .tradingview-chart-container {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.03) 0%, rgba(139, 92, 246, 0.03) 100%);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.1);
        }

        .chart-header {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(59, 130, 246, 0.1);
            background: rgba(255, 255, 255, 0.5);
        }

        .chart-header h5 {
            color: #1f2937;
            font-size: 16px;
            font-weight: 600;
        }

        .chart-body {
            padding: 20px;
            background: rgba(255, 255, 255, 0.3);
        }

        .chart-footer {
            padding: 12px 20px;
            border-top: 1px solid rgba(59, 130, 246, 0.1);
            background: rgba(255, 255, 255, 0.5);
        }

        .chart-footer-text {
            color: #6b7280 !important;
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
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
            50% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
        }

        /* Panel styling */
        .df-panel {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
            border: 1px solid rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .df-panel:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.15);
        }
    </style>
@endsection
