@extends('layouts.app')

@section('title', 'Liquidations Heatmap | DragonFortune')

@push('head')
    <!-- Resource Hints for Faster API Loading -->
    <link rel="dns-prefetch" href="{{ config('app.api_urls.internal') }}">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="{{ config('app.api_urls.internal') }}" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Preload critical resources for faster initial load -->
    <link rel="preload" href="{{ asset('js/liquidations-controller.js') }}" as="script" crossorigin="anonymous">
@endpush

@section('content')
    {{--
        Bitcoin: Liquidations Heatmap (Coinglass Model 3)
        Think like a trader • Build like an engineer • Visualize like a designer

        Interpretasi Trading:
        - Liquidations menunjukkan posisi yang dipaksa tutup
        - Long liquidations = Bearish pressure (longs forced out)
        - Short liquidations = Bullish pressure (shorts forced out)
        - High liquidation clusters = Key support/resistance levels
    --}}

    <div class="d-flex flex-column h-100 gap-3" x-data="liquidationsController()">
        <!-- Page Header -->
        <div class="derivatives-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h1 class="mb-0">Liquidations Heatmap</h1>
                        <span class="spinner-border spinner-border-sm text-primary" style="width: 16px; height: 16px;" x-show="!rawData" x-cloak></span>
                    </div>
                    <p class="mb-0 text-secondary">
                        Visualisasi area liquidation untuk identifikasi level support/resistance kunci.
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

                    <!-- Time Range Selector -->
                    <select class="form-select" style="width: 120px;" :value="selectedRange || '3d'" @change="updateRange && updateRange($event.target.value)">
                        <template x-for="range in (timeRanges || [])" :key="range.value">
                            <option :value="range.value" x-text="range.label"></option>
                        </template>
                    </select>
                </div>
            </div>
        </div>

        <!-- Summary Cards Row -->
        <div class="row g-3">
            <!-- Total Liquidations -->
            <div class="col-md-4">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Total Liquidations</span>
                        <span class="badge text-bg-primary" x-show="stats && stats.totalLiquidations > 0" x-cloak>Total</span>
                        <span class="badge text-bg-secondary" x-show="!stats || stats.totalLiquidations === 0" x-cloak>Loading...</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="stats && stats.totalLiquidations > 0" x-text="stats && formatValue ? formatValue(stats.totalLiquidations) : '...'" x-cloak></div>
                        <div class="h3 mb-1 text-secondary" x-show="!stats || stats.totalLiquidations === 0" x-cloak>...</div>
                        <small class="text-muted" x-show="stats && stats.count > 0" x-cloak>
                            <span x-text="stats ? stats.count : 0"></span> data points
                        </small>
                    </div>
                </div>
            </div>

            <!-- Max Liquidation -->
            <div class="col-md-4">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Max Liquidation</span>
                        <span class="badge text-bg-danger" x-show="stats && stats.maxLiquidation > 0" x-cloak>Peak</span>
                        <span class="badge text-bg-secondary" x-show="!stats || stats.maxLiquidation === 0" x-cloak>Loading...</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="stats && stats.maxLiquidation > 0" x-text="stats && formatValue ? formatValue(stats.maxLiquidation) : '...'" x-cloak></div>
                        <div class="h3 mb-1 text-secondary" x-show="!stats || stats.maxLiquidation === 0" x-cloak>...</div>
                    </div>
                </div>
            </div>

            <!-- Average Liquidation -->
            <div class="col-md-4">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Avg Liquidation</span>
                        <span class="badge text-bg-info" x-show="stats && stats.avgLiquidation > 0" x-cloak>Average</span>
                        <span class="badge text-bg-secondary" x-show="!stats || stats.avgLiquidation === 0" x-cloak>Loading...</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="stats && stats.avgLiquidation > 0" x-text="stats && formatValue ? formatValue(stats.avgLiquidation) : '...'" x-cloak></div>
                        <div class="h3 mb-1 text-secondary" x-show="!stats || stats.avgLiquidation === 0" x-cloak>...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Heatmap Chart -->
        <div class="row g-3">
            <div class="col-12">
                <div class="tradingview-chart-container">
                    <div class="chart-header">
                        <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <h5 class="mb-0">Liquidations Heatmap</h5>
                            </div>
                            
                            <!-- Interactive Controls -->
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <!-- Liquidity Threshold Slider -->
                                <div class="d-flex align-items-center gap-2" style="min-width: 250px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 12px; height: 12px; background: linear-gradient(90deg, #4ade80, #f59e0b); border-radius: 2px;"></div>
                                        <div style="width: 12px; height: 12px; background: linear-gradient(90deg, #f59e0b, #ef4444); border-radius: 2px;"></div>
                                    </div>
                                    <span class="small text-white-50">Liquidity Threshold = </span>
                                    <span class="small text-white fw-bold" x-text="liquidityThreshold ? liquidityThreshold.toFixed(1) : '0.2'">0.2</span>
                                    <input type="range" 
                                           class="form-range" 
                                           min="0" 
                                           max="1" 
                                           step="0.05" 
                                           :value="liquidityThreshold || 0.2"
                                           @input="updateThreshold && updateThreshold($event.target.value)"
                                           style="width: 120px;"
                                           title="Drag to filter liquidation intensity (lower = more colors visible)">
                                </div>

                                <!-- Zoom Controls -->
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-light" @click="zoomOut && zoomOut()" title="Zoom Out">
                                        <i class="fas fa-search-minus"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-light" @click="resetZoom && resetZoom()" title="Reset Zoom">
                                        <i class="fas fa-compress"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-light" @click="zoomIn && zoomIn()" title="Zoom In">
                                        <i class="fas fa-search-plus"></i>
                                    </button>
                                </div>

                                <!-- Screenshot Button -->
                                <button type="button" class="btn btn-sm btn-outline-light" title="Take Screenshot">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="chart-body" style="position: relative;">
                        <canvas id="liquidationsHeatmapChart"></canvas>
                    </div>
                    <div class="chart-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="chart-footer-text text-secondary">
                                📊 Area terang = liquidation tinggi. Gunakan untuk identifikasi level support/resistance
                            </small>
                            <small class="text-muted">
                                <span class="badge text-bg-success">Hyblock API</span>
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
                    <h5 class="mb-3">📚 Cara Baca Liquidations Heatmap</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="p-3 rounded" style="background: rgba(139, 92, 246, 0.1); border-left: 4px solid #8b5cf6;">
                                <div class="fw-bold mb-2" style="color: #8b5cf6;">🟣 Warna Gelap (Purple)</div>
                                <div class="small text-secondary">
                                    Liquidation rendah atau tidak ada aktivitas. Area ini kurang signifikan untuk trading.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded" style="background: rgba(34, 197, 94, 0.1); border-left: 4px solid #22c55e;">
                                <div class="fw-bold mb-2 text-success">🟢 Warna Hijau-Kuning</div>
                                <div class="small text-secondary">
                                    Liquidation sedang. Mulai ada aktivitas, perhatikan jika harga mendekati area ini.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded" style="background: rgba(251, 146, 60, 0.1); border-left: 4px solid #fb923c;">
                                <div class="fw-bold mb-2" style="color: #fb923c;">🟠 Warna Orange-Merah</div>
                                <div class="small text-secondary">
                                    Liquidation tinggi! Area ini sering jadi support/resistance kuat. Ideal untuk entry/exit.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        <strong>💡 Tips:</strong> Area dengan warna terang (kuning-orange) = banyak liquidation = level harga penting. Gunakan liquidity threshold slider untuk filter noise.
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

    <!-- Liquidations Controller -->
    <script type="module" src="{{ asset('js/liquidations-controller.js') }}" defer></script>

    <style>
        [x-cloak] { display: none !important; }
        
        /* Chart Container Styling (Dark theme like Coinglass) */
        .tradingview-chart-container {
            background: #0a0a0a;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.5);
        }

        .chart-header h5 {
            color: #ffffff;
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }

        .chart-body {
            padding: 0;
            height: 600px;
            position: relative;
            background: #0a0a0a; /* Dark background like Coinglass */
        }

        #liquidationsHeatmapChart {
            width: 100% !important;
            height: 100% !important;
        }

        .chart-footer {
            padding: 12px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.5);
        }

        .chart-footer-text {
            color: rgba(255, 255, 255, 0.7) !important;
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
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
            50% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
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
        }

        /* Interactive Controls Styling */
        .form-range {
            height: 4px;
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.3), rgba(139, 92, 246, 0.3));
            border-radius: 2px;
        }

        .form-range::-webkit-slider-thumb {
            width: 16px;
            height: 16px;
            background: #3b82f6;
            border-radius: 50%;
            cursor: pointer;
        }

        .form-range::-moz-range-thumb {
            width: 16px;
            height: 16px;
            background: #3b82f6;
            border-radius: 50%;
            cursor: pointer;
        }

        .btn-outline-light {
            color: rgba(255, 255, 255, 0.8);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .btn-outline-light:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .text-white-50 {
            color: rgba(255, 255, 255, 0.5);
        }
    </style>
@endsection
