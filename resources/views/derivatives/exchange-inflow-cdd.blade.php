@extends('layouts.app')

@section('title', 'Exchange Inflow CDD | DragonFortune')

@push('head')
    <!-- Resource Hints for Faster API Loading -->
    <link rel="dns-prefetch" href="{{ config('app.api_urls.internal') }}">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="{{ config('app.api_urls.internal') }}" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
@endpush

@section('content')
    {{--
        Bitcoin: Exchange Inflow CDD Dashboard
        Think like a trader • Build like an engineer • Visualize like a designer

        Interpretasi Trading:
        - CDD (Coin Days Destroyed) mengukur "age" dari coins yang bergerak
        - Exchange Inflow CDD tinggi → Old coins masuk exchange → Potensi selling pressure
        - Spike CDD → Long-term holders mulai distribute → Bearish signal
        - Low CDD → Mostly young coins moving → Normal trading activity
    --}}

    <div class="d-flex flex-column h-100 gap-3" x-data="createCDDController()">
        <!-- Page Header -->
        <div class="derivatives-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h1 class="mb-0">Exchange Inflow CDD</h1>
                        <span class="spinner-border spinner-border-sm text-primary" style="width: 16px; height: 16px;" x-show="rawData.length === 0" x-cloak></span>
                    </div>
                    <p class="mb-0 text-secondary">
                        Monitor umur koin yang masuk ke exchange untuk mendeteksi distribusi long-term holder.
                    </p>
                </div>

                <!-- Global Controls -->
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <!-- Exchange Selector -->
                    <select class="form-select" style="width: 200px;" :value="selectedExchange" @change="updateExchange($event.target.value)">
                        <template x-for="ex in exchangeOptions" :key="ex.value">
                            <option :value="ex.value" x-text="ex.label" :selected="ex.value === selectedExchange"></option>
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
                    
                    <!-- Interval Selector (Hidden - only 1D available for CryptoQuant plan) -->
                    <!-- <select class="form-select" style="width: 120px;" :value="selectedInterval" @change="updateInterval($event.target.value)">
                        <template x-for="interval in chartIntervals" :key="interval.value">
                            <option :value="interval.value" x-text="interval.label"></option>
                        </template>
                    </select> -->
                    <!-- <span class="badge text-bg-info">Daily Data Only</span> -->

                    <!-- Date Range Selector -->
                    <div class="d-flex align-items-center gap-2">
                        <select class="form-select" style="width: 120px;" :value="selectedTimeRange" @change="updateTimeRange($event.target.value)">
                            <template x-for="range in timeRanges" :key="range.value">
                                <option :value="range.value" x-text="range.label" :selected="range.value === selectedTimeRange"></option>
                            </template>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards Row (Compact) -->
        <div class="row g-3">
            <!-- Current CDD -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Current CDD</span>
                        <span class="badge text-bg-primary" x-show="currentCDD !== null">Latest</span>
                        <span class="badge text-bg-secondary" x-show="currentCDD === null">Loading...</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="currentCDD !== null" x-text="formatCDD(currentCDD)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="currentCDD === null">...</div>
                        <small class="text-muted" x-show="cddChange !== null">
                            <span :class="cddChange >= 0 ? 'text-danger' : 'text-success'" x-text="formatChange(cddChange)"></span>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Z-Score Quick Status -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Z-Score Alert</span>
                        <span class="badge" 
                              x-show="zScore !== null"
                              :class="zScore !== null && Math.abs(zScore) > 2 ? 'text-bg-danger' : zScore !== null && Math.abs(zScore) > 1 ? 'text-bg-warning' : 'text-bg-success'">
                            <span x-show="zScore !== null && Math.abs(zScore) > 2">⚠️ EXTREME</span>
                            <span x-show="zScore !== null && Math.abs(zScore) <= 2 && Math.abs(zScore) > 1">⚡ High</span>
                            <span x-show="zScore !== null && Math.abs(zScore) <= 1">✓ Normal</span>
                        </span>
                    </div>
                    <div>
                        <div class="h3 mb-1" 
                             x-show="zScore !== null" 
                             x-text="zScore !== null ? zScore.toFixed(2) : '...'"
                             :class="zScore !== null && Math.abs(zScore) > 2 ? 'text-danger' : zScore !== null && Math.abs(zScore) > 1 ? 'text-warning' : 'text-success'"></div>
                        <div class="h3 mb-1 text-secondary" x-show="zScore === null">...</div>
                        <small class="text-muted" x-show="zScore !== null">
                            <span x-show="zScore !== null && Math.abs(zScore) > 2">Anomaly Detected</span>
                            <span x-show="zScore !== null && Math.abs(zScore) <= 2 && Math.abs(zScore) > 1">Above Average</span>
                            <span x-show="zScore !== null && Math.abs(zScore) <= 1">Within Range</span>
                        </small>
                    </div>
                </div>
            </div>

            <!-- MA Cross Quick Status -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">MA Trend</span>
                        <span class="badge" 
                              x-show="ma7 !== null && ma30 !== null"
                              :class="maCrossSignal === 'warning' ? 'text-bg-danger' : maCrossSignal === 'safe' ? 'text-bg-success' : 'text-bg-secondary'">
                            <span x-show="maCrossSignal === 'warning'">⚠️ RISING</span>
                            <span x-show="maCrossSignal === 'safe'">✓ FALLING</span>
                            <span x-show="maCrossSignal === 'neutral'">➡️ NEUTRAL</span>
                        </span>
                        <span class="badge text-bg-warning" x-show="ma7 === null || ma30 === null">
                            Need 30D
                        </span>
                    </div>
                    <div>
                        <div x-show="ma7 !== null && ma30 !== null">
                            <div class="h4 mb-0" x-text="formatCDD(ma7)"></div>
                            <small class="text-muted">vs <span x-text="formatCDD(ma30)"></span></small>
                    </div>
                        <div class="h3 mb-1 text-secondary" x-show="ma7 === null || ma30 === null">...</div>
                </div>
                </div>
            </div>

            <!-- Momentum -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Momentum</span>
                        <span class="badge" :class="momentum > 0 ? 'text-bg-danger' : momentum < 0 ? 'text-bg-success' : 'text-bg-secondary'">
                            <span x-show="momentum > 0">📈 Distribution</span>
                            <span x-show="momentum < 0">📉 Accumulation</span>
                            <span x-show="momentum === 0">➡️ Neutral</span>
                        </span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="avgCDD !== null" x-text="formatCDD(avgCDD)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="avgCDD === null">...</div>
                        <small class="text-muted" x-show="momentum !== null">
                            <span :class="momentum >= 0 ? 'text-danger' : 'text-success'" x-text="formatPercentage(momentum)"></span>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Chart (TradingView Style) -->
        <div class="row g-3">
            <div class="col-12">
                <div class="tradingview-chart-container">
                    <div class="chart-header">
                        <div class="d-flex align-items-center gap-3">
                            <h5 class="mb-0">Exchange Inflow CDD Chart</h5>
                            </div>
                        <div class="chart-controls d-flex align-items-center gap-2">
                            <!-- BTC Price Overlay Toggle -->
                            <button 
                                @click="togglePriceOverlay()" 
                                class="btn btn-sm"
                                :class="showPriceOverlay ? 'btn-warning' : 'btn-outline-secondary'"
                                style="font-size: 0.75rem; padding: 0.35rem 0.75rem;">
                                <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" class="me-1">
                                    <path d="M0 3a2 2 0 0 1 2-2h13.5a.5.5 0 0 1 0 1H15v2a1 1 0 0 1 1 1v8.5a1.5 1.5 0 0 1-1.5 1.5h-12A2.5 2.5 0 0 1 0 12.5V3zm1 1.732V12.5A1.5 1.5 0 0 0 2.5 14h12a.5.5 0 0 0 .5-.5V5H2a1.99 1.99 0 0 1-1-.268zM1 3a1 1 0 0 0 1 1h12V2H2a1 1 0 0 0-1 1z"/>
                                    </svg>
                                <span x-text="showPriceOverlay ? 'BTC Price ON' : 'BTC Price OFF'"></span>
                                </button>
                            
                            <!-- Daily Data Badge -->
                            <span class="badge text-bg-secondary" style="font-size: 0.75rem; padding: 0.5rem 0.75rem;">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" class="me-1">
                                        <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                                    </svg>
                                Daily Data
                            </span>
                            </div>
                            </div>
                    <div class="chart-body" style="position: relative;">
                        <canvas id="cdd-chart"></canvas>
                    </div>
                    <div class="chart-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="chart-footer-text">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-right: 4px;">
                                    <circle cx="6" cy="6" r="5" fill="none" stroke="currentColor" stroke-width="1"/>
                                    <path d="M6 3v3l2 2" stroke="currentColor" stroke-width="1" fill="none"/>
                                </svg>
                                CDD tinggi menunjukkan old coins masuk exchange - potensi selling pressure dari long-term holders
                            </small>
                            <small class="text-muted">
                                <span class="badge text-bg-success">CryptoQuant API</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analysis Charts Section -->
        <div class="row g-3 mb-3">
            <!-- Z-Score Distribution Chart -->
            <div class="col-md-6">
                <div class="tradingview-chart-container" style="min-height: 380px;">
                    <div class="chart-header">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <h5 class="mb-0">Analisis Distribusi Z-Score</h5>
                            <div class="d-flex gap-2">
                                <span class="badge" 
                                      x-show="zScore !== null"
                                      :class="zScore !== null && Math.abs(zScore) > 2 ? 'text-bg-danger' : zScore !== null && Math.abs(zScore) > 1 ? 'text-bg-warning' : 'text-bg-success'">
                                    <span x-show="zScore !== null && Math.abs(zScore) > 2">⚠️ EXTREME</span>
                                    <span x-show="zScore !== null && Math.abs(zScore) <= 2 && Math.abs(zScore) > 1">⚡ HIGH</span>
                                    <span x-show="zScore !== null && Math.abs(zScore) <= 1">✓ NORMAL</span>
                                </span>
                    </div>
                        </div>
                        </div>
                    <div class="chart-body" style="position: relative; padding: 20px; height: 240px;">
                        <canvas id="zscore-distribution-chart"></canvas>
                    </div>
                    <div class="chart-footer">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex gap-3 small">
                                <div>
                                    <span class="badge bg-warning text-dark" x-text="zScoreHighEvents"></span>
                                    <span class="text-muted ms-1">Event CDD Tinggi (>2σ)</span>
                                </div>
                                <div>
                                    <span class="badge bg-danger" x-text="zScoreExtremeEvents"></span>
                                    <span class="text-muted ms-1">Event Ekstrem (>3σ)</span>
                                </div>
                            </div>
                            <small class="text-muted">
                                Z-Score mengukur seberapa ekstrem nilai CDD saat ini
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Moving Average Trend Chart -->
            <div class="col-md-6">
                <div class="tradingview-chart-container" style="min-height: 380px;">
                    <div class="chart-header">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <h5 class="mb-0">Moving Average Trend</h5>
                            <div class="d-flex gap-2">
                                <span class="badge" 
                                      x-show="ma7 !== null && ma30 !== null"
                                      :class="maCrossSignal === 'warning' ? 'text-bg-danger' : maCrossSignal === 'safe' ? 'text-bg-success' : 'text-bg-secondary'">
                                    <span x-show="maCrossSignal === 'warning'">⚠️ RISING</span>
                                    <span x-show="maCrossSignal === 'safe'">✓ FALLING</span>
                                    <span x-show="maCrossSignal === 'neutral'">➡️ NEUTRAL</span>
                                </span>
                    </div>
                        </div>
                        </div>
                    <div class="chart-body" style="position: relative; padding: 20px; height: 240px;">
                        <canvas id="ma-trend-chart"></canvas>
                    </div>
                    <div class="chart-footer">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex gap-3 small">
                                <div>
                                    <span style="display: inline-block; width: 12px; height: 3px; background: rgb(59, 130, 246); margin-right: 4px;"></span>
                                    <span class="text-muted">MA 7 Hari: <strong x-show="ma7 !== null" x-text="formatCDD(ma7)"></strong></span>
                                </div>
                                <div>
                                    <span style="display: inline-block; width: 12px; height: 3px; background: rgb(239, 68, 68); margin-right: 4px;"></span>
                                    <span class="text-muted">MA 30 Hari: <strong x-show="ma30 !== null" x-text="formatCDD(ma30)"></strong></span>
                                </div>
                            </div>
                            <small class="text-muted">
                                MA7 > MA30 = Tekanan meningkat
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
                    <h5 class="mb-3">📚 Memahami Exchange Inflow CDD</h5>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="p-3 rounded" style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444;">
                                <div class="fw-bold mb-2 text-danger">🔴 CDD Spike (Tinggi)</div>
                                <div class="small text-secondary">
                                    <ul class="mb-0 ps-3">
                                        <li>Old coins (long-term holders) masuk exchange</li>
                                        <li>Potensi selling pressure meningkat</li>
                                        <li>Signal distribusi dari smart money</li>
                                        <li>Strategi: Hati-hati, potensi koreksi</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-3 rounded" style="background: rgba(34, 197, 94, 0.1); border-left: 4px solid #22c55e;">
                                <div class="fw-bold mb-2 text-success">🟢 CDD Rendah</div>
                                <div class="small text-secondary">
                                    <ul class="mb-0 ps-3">
                                        <li>Mostly young coins yang bergerak</li>
                                        <li>Normal trading activity</li>
                                        <li>Long-term holders masih HODL</li>
                                        <li>Strategi: Trend masih sehat</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-3 rounded" style="background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6;">
                                <div class="fw-bold mb-2 text-primary">⚡ CDD Menurun Drastis</div>
                                <div class="small text-secondary">
                                    <ul class="mb-0 ps-3">
                                        <li>Fase accumulation potensial</li>
                                        <li>Long-term holders confident HODL</li>
                                        <li>Supply shock bisa terjadi</li>
                                        <li>Strategi: Peluang entry yang baik</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3 mb-0">
                        <strong>💡 Tips Pro:</strong> CDD adalah indikator on-chain yang powerful untuk melihat behavior long-term holders. Spike CDD sering mendahului top market, sedangkan CDD rendah menunjukkan confidence holders dan potensi bullish.
                        <hr class="my-2">
                        <div class="small">
                            <strong>📊 Z-Score:</strong> Mengukur deviasi dari average. Z > 2 = anomaly/distribution besar. Z > 3 = extreme event (top market potential).<br>
                            <strong>📈 MA Cross:</strong> MA7 > MA30 = Pressure naik (WARNING). MA7 < MA30 = Pressure turun (SAFE). Monitor divergence dengan price untuk konfirmasi signal.<br>
                            <strong>⚠️ Data Requirement:</strong> MA Cross needs minimum 30 days of data. Select ≥1M (1 Month) time range for full metrics.
                    </div>
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

    <!-- Exchange Inflow CDD Modular Controller -->
    <script type="module">
        // Import modular components
        import { CDDUtils } from '{{ asset('js/exchange-inflow-cdd/utils.js') }}';
        import { ExchangeInflowCDDAPIService } from '{{ asset('js/exchange-inflow-cdd/api-service.js') }}';
        import { ChartManager } from '{{ asset('js/exchange-inflow-cdd/chart-manager.js') }}';
        import { createCDDController } from '{{ asset('js/exchange-inflow-cdd/controller.js') }}';

        // Register controller globally for Alpine
        window.createCDDController = createCDDController;
        
        console.log('✅ CDD modular components loaded');
    </script>

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

        .chart-controls .btn-group {
            background: rgba(241, 245, 249, 0.8);
            border-radius: 6px;
            padding: 2px;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .chart-controls .btn {
            border: none;
            padding: 6px 12px;
            color: #64748b;
            background: transparent;
            transition: all 0.2s;
        }

        .chart-controls .btn:hover {
            color: #1e293b;
            background: rgba(241, 245, 249, 1);
        }

        .chart-controls .btn-primary,
        .chart-controls .btn.btn-primary {
            background: #3b82f6;
            color: #fff;
        }

        .chart-controls .btn-outline-secondary {
            color: #64748b;
            border-color: rgba(226, 232, 240, 0.8);
        }

        .chart-controls .btn-outline-secondary:hover {
            background: rgba(241, 245, 249, 1);
            color: #1e293b;
        }

        .chart-body {
            padding: 20px;
            height: 500px;
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

        .chart-controls {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
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
            .derivatives-header h1 {
                font-size: 1.5rem;
            }
            
            .chart-body {
                height: 350px;
                padding: 12px;
            }
            
            .chart-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .chart-controls {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
                gap: 0.75rem;
            }

            .df-panel:hover {
                transform: translateY(-2px);
            }
        }

        /* Light Mode Support */
        .chart-footer-text {
            color: var(--bs-body-color, #6c757d);
            transition: color 0.3s ease;
        }

        /* Light mode chart styling */
        @media (prefers-color-scheme: light) {
            .tradingview-chart-container {
                background: #ffffff;
                border: 1px solid rgba(226, 232, 240, 0.8);
                box-shadow: none;
            }

            .chart-header {
                background: rgba(59, 130, 246, 0.03);
                border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            }

            .chart-header h5 {
                color: #1e293b;
            }

            .chart-body {
                background: #ffffff;
            }

            .chart-footer {
                background: rgba(59, 130, 246, 0.02);
                border-top: 1px solid rgba(0, 0, 0, 0.08);
            }

            .chart-footer-text {
                color: #64748b !important;
            }
        }
    </style>
@endsection
