@extends('layouts.app')

@section('title', 'Liquidation Heatmap Pro | DragonFortune')

@push('head')
<script>
    // Global flag to attempt to disable the aggressive app.js "auto-refresh" scrubber
    window.__AUTO_REFRESH_DISABLED__ = true;
</script>
<!-- Chart.js Dependencies for Production (OVERRIDE app.js version) -->
<script>
    // Clear any existing Chart.js from app.js to prevent conflicts
    if (window.Chart) {
        console.log('Removing old Chart.js from app.js');
        delete window.Chart;
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script>
    // Verify Chart.js and Date Adapter are loaded
    if (window.Chart && window.Chart.registry) {
        console.log('Chart.js loaded successfully with Date Adapter');
        try {
            const timeScale = window.Chart.registry.getScale('time');
            console.log('Time scale registered:', timeScale ? 'YES' : 'NO');
        } catch (e) {
            console.error('Time scale check failed:', e);
        }
    } else {
        console.error('Chart.js failed to load from CDN');
    }
</script>
    <style>
        :root {
            --df-bg-deep: #0d1117;
            --df-bg-card: #161b22;
            --df-bg-hover: #1c2128;
            --df-border: #30363d;
            --df-accent: #58a6ff;
            --df-text-main: #e6edf3;
            --df-text-dim: #8b949e;
            --df-success: #3fb950;
            --df-danger: #f85149;
            --df-warning: #d29922;
            --df-glow-cyan: rgba(88, 166, 255, 0.4);
            
            /* Heatmap Colors */
            --hm-low: #0c1e33;
            --hm-mid: #2b57e6;
            --hm-high: #9333ea;
            --hm-extreme: #f97316;
            --hm-hot: #ffffff;
        }

        /* Force Dark Background for the whole section */
        .pro-dashboard-wrapper {
            background-color: var(--df-bg-deep);
            color: var(--df-text-main);
            min-height: 100vh;
            padding: 20px;
        }

        .text-dim { color: #8b949e !important; }
        .text-white { color: #ffffff !important; }
        .text-highlight { color: #58a6ff !important; }
        .text-success-pro { color: #3fb950 !important; }
        .text-danger-pro { color: #f85149 !important; }

        .pro-dashboard-wrapper .form-select {
            background-color: var(--df-bg-card);
            border-color: var(--df-border);
            color: #ffffff;
            font-weight: 600;
        }

        .pro-dashboard-wrapper .input-group-text {
            background-color: var(--df-bg-hover);
            border-color: var(--df-border);
            color: var(--df-text-dim);
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
        }
        
        .pro-dashboard {
            display: grid;
            grid-template-columns: 320px 1fr 280px;
            grid-template-rows: auto 1fr;
            height: 100%;
            min-height: 700px;
            gap: 1.5rem;
            padding: 1rem;
        }

        .pane {
            background: #161b22 !important;
            border: 1px solid #30363d !important;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .bg-hover {
            background-color: #1c2128 !important;
        }

        /* Sidebar Stats */
        .sidebar-stat {
            padding: 1.25rem;
            border-bottom: 1px solid var(--df-border);
        }

        .pro-tag {
            font-size: 0.65rem;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .pro-tag-pro {
            background: var(--df-accent); /* Using df-accent for blue */
            color: white;
        }

        .pro-tag-critical {
            background: #ef4444;
            color: white;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.4);
        }

        .pro-tag-watching {
            background: #f59e0b;
            color: white;
        }

        .pro-tag-stable {
            background: #10b981;
            color: white;
        }

        .stat-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #8b949e !important;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .stat-main {
            font-size: 1.5rem;
            font-weight: 800;
            color: #ffffff !important;
        }

        .stat-sub {
            font-size: 0.85rem;
            color: var(--df-text-dim);
        }

        .text-glow-orange {
            color: #f97316 !important;
            text-shadow: 0 0 12px rgba(249, 115, 22, 0.4);
        }

        .text-glow-blue {
            color: #58a6ff !important;
            text-shadow: 0 0 12px rgba(88, 166, 255, 0.4);
        }

        /* Range Card Styles */
        .range-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 2rem;
        }

        .range-card {
            background: rgba(22, 27, 34, 0.4);
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .range-card:hover {
            border-color: #58a6ff;
            background: rgba(88, 166, 255, 0.05);
            transform: translateY(-2px);
        }

        .range-card.active {
            border-color: #58a6ff;
            background: rgba(88, 166, 255, 0.15);
            box-shadow: 0 0 15px rgba(88, 166, 255, 0.1);
        }

        .range-card.empty {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        .range-badge {
            font-size: 0.6rem;
            padding: 1px 4px;
            border-radius: 3px;
            font-weight: 800;
        }

        .text-glow-orange {
            color: #f97316 !important;
            text-shadow: 0 0 12px rgba(249, 115, 22, 0.4);
        }

        #heatmapChart {
            flex-grow: 1;
            padding: 10px;
            min-height: 550px;
        }

        @media (max-width: 1366px) {
            .pro-dashboard {
                grid-template-columns: 280px 1fr !important;
                grid-template-rows: auto auto !important;
                height: auto !important;
            }
            .pro-dashboard > .pane:last-child {
                grid-column: span 2;
                height: 400px;
            }
        }

        @media (max-width: 1024px) {
            .pro-dashboard {
                grid-template-columns: 1fr !important;
            }
            .pro-dashboard > .pane {
                grid-column: span 1;
                height: 500px;
            }
        }
    </style>
@endpush

@section('content')
<div x-data="heatmapPro" class="pro-dashboard-wrapper">
    
    <!-- Top Header / Controls -->
    <div class="d-flex justify-content-between align-items-center mb-4 px-2 pb-3 border-bottom border-border">
        <div class="d-flex align-items-center gap-3">
            <div class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary">
                <i data-feather="terminal" style="width: 20px; height: 20px;"></i>
            </div>
            <div>
                <h4 class="fw-bold m-0 text-white">Liquidation Heatmap <span class="text-primary">Advanced</span></h4>
                <div class="d-flex align-items-center gap-2 small">
                    <span class="text-dim">Market Intelligence Engine</span>
                    <span class="pro-tag pro-tag-pro">Pro Alpha</span>
                    <span :class="sentimentClass()" class="pro-tag ms-2" x-text="data.insights.sentiment"></span>
                </div>
            </div>
        </div>

        <div class="d-flex gap-3">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-card border-border text-dim">Active Coin</span>
                <select x-model="symbol" @change="onSymbolChange()" class="form-select bg-card border-border text-white fw-bold" style="width: 150px; font-size: 1rem;">
                    @foreach($symbols as $s) <option value="{{ $s }}">{{ $s }}</option> @endforeach
                </select>
            </div>
            
            <div class="d-flex align-items-center bg-card border border-border rounded px-3 py-1">
                <span class="text-dim small me-2">Autosync:</span>
                <span class="text-success small fw-bold">60s</span>
            </div>
        </div>
    </div>

    <!-- Multi-Range Insights Grid -->
    <div class="range-grid">
        <template x-for="(rangeInfo, rName) in symbolSummary" :key="rName">
            <div class="range-card" 
                 x-show="rangeInfo.has_data"
                 :class="{ 'active': range === rName }"
                 @click="switchRange(rName)">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-white" x-text="rName"></span>
                    <span class="range-badge" 
                          :class="rangeInfo.sentiment === 'CRITICAL' ? 'bg-danger' : (rangeInfo.sentiment === 'WATCHING' ? 'bg-warning' : 'bg-success')"
                          x-text="rangeInfo.sentiment"></span>
                </div>
                <div class="small">
                    <div class="text-dim" style="font-size: 0.7rem;">Magnet Price</div>
                    <div class="fw-bold text-white" x-text="'$' + fmt(rangeInfo.magnet_price)">--</div>
                </div>
            </div>
        </template>
        <div x-show="Object.keys(symbolSummary).length > 0 && !Object.values(symbolSummary).some(r => r.has_data)" class="text-dim small p-3">
            <i data-feather="info" class="me-1"></i> No liquidation data found in current records. Please update data from server.
        </div>
        <div x-show="Object.keys(symbolSummary).length === 0" class="text-dim small p-3">
            Scanning all timeframes for <span x-text="symbol" class="text-primary fw-bold"></span>...
        </div>
    </div>

    <!-- Main Dashboard Grid -->
    <div class="pro-dashboard">
        <!-- Sidebar: Market Bias & Magnet -->
        <div class="pane shadow-sm">
            <div class="p-3 border-bottom border-border bg-hover d-flex align-items-center gap-2">
                <i data-feather="activity" class="text-primary" style="width: 16px;"></i>
                <span class="fw-bold small text-white">MARKET BIAS</span>
            </div>
            
            <div class="sidebar-stat">
                <div class="stat-label">Liquidity Distribution (50:50)</div>
                <div class="stat-main d-flex justify-content-between">
                    <span class="text-success" x-text="data.insights.bias.long_pct + '%'">--%</span>
                    <span class="text-danger" x-text="data.insights.bias.short_pct + '%'">--%</span>
                </div>
                <div class="bias-container">
                    <div class="bias-fill" :style="'width: ' + data.insights.bias.long_pct + '%'"></div>
                </div>
                <div class="d-flex justify-content-between small text-dim mt-1">
                    <span class="text-white opacity-50">Long Liquidity</span>
                    <span class="text-white opacity-50">Short Liquidity</span>
                </div>
            </div>

            <div class="sidebar-stat" style="background: linear-gradient(90deg, rgba(249, 115, 22, 0.05) 0%, transparent 100%); border-left: 3px solid #f97316;">
                <div class="stat-label" style="color: #f97316 !important;">Strongest Magnet</div>
                <div class="stat-main text-glow-orange" x-text="'$' + fmt(data.insights.magnet_price)">--</div>
                <div class="small text-white opacity-50 mt-1" x-text="magnetDistance()">--</div>
            </div>

            <div class="sidebar-stat flex-grow-1">
                <div class="stat-label">Analysis Narrative</div>
                <div class="text-white small opacity-75" style="line-height: 1.6" x-html="data.insights.text">
                    Wait for market data to populate insights...
                </div>
            </div>

            <div class="p-3 bg-hover">
                <div class="stat-label">Total Visualized Fuel</div>
                <div class="stat-main" style="font-size: 1.2rem;" x-text="'$' + fmtK(data.insights.total_fuel)">--</div>
            </div>
        </div>

        <!-- Center: Heatmap Chart -->
        <div class="pane position-relative">
            <div class="p-2 border-bottom border-border d-flex justify-content-between align-items-center bg-transparent">
                <div class="d-flex gap-3 small text-white opacity-50 px-2">
                    <span class="d-flex align-items-center gap-1"><span class="rounded-1" style="width: 12px; height: 12px; background: var(--hm-low)"></span> Base</span>
                    <span class="d-flex align-items-center gap-1"><span class="rounded-1" style="width: 12px; height: 12px; background: var(--hm-mid)"></span> Level 1</span>
                    <span class="d-flex align-items-center gap-1"><span class="rounded-1" style="width: 12px; height: 12px; background: var(--hm-high)"></span> Level 2</span>
                    <span class="d-flex align-items-center gap-1"><span class="rounded-1" style="width: 12px; height: 12px; background: var(--hm-extreme)"></span> High Intensity</span>
                </div>
                <div class="small fw-bold text-success pe-2" x-show="!loading">
                    <i data-feather="check-circle" style="width: 12px;"></i> LIVE
                </div>
            </div>
            
            <div id="chartContainer" class="flex-grow-1 h-100 w-100 position-relative">
                <!-- Chart Empty Overlay -->
                <div x-show="!data.heatmap || data.heatmap.length === 0" 
                     x-cloak
                     class="position-absolute top-50 start-50 translate-middle text-center w-100 p-4"
                     style="z-index: 10;">
                    <div class="mb-3 opacity-25">
                        <i data-feather="bar-chart-2" style="width: 64px; height: 64px;"></i>
                    </div>
                    <h5 class="text-white opacity-75">No Liquidations Detected</h5>
                    <p class="small text-dim mb-0" x-text="'No data points found for ' + symbol + ' in ' + range + ' range.'"></p>
                    <p class="small text-dim">Verification: DB ID is present but related leverage data is empty.</p>
                </div>
                
                <canvas id="heatmapChart"></canvas>
            </div>
        </div>

        <!-- Right: Major Liquidity Walls -->
        <div class="pane">
            <div class="p-3 border-bottom border-border bg-hover d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i data-feather="list" class="text-primary" style="width: 16px;"></i>
                    <span class="fw-bold small text-white">MAJOR WALLS</span>
                </div>
                <span class="pro-tag pro-tag-pro">Insights</span>
            </div>
            
            <div class="flex-grow-1 overflow-auto">
                <template x-for="wall in data.insights.major_walls" :key="wall.price">
                    <div class="wall-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold" :class="wall.type === 'Long' ? 'text-success' : 'text-danger'" x-text="'$' + fmt(wall.price)">--</span>
                            <span class="small text-dim" x-text="fmtK(wall.volume)">--</span>
                        </div>
                        <div class="wall-bar" :style="'width: ' + ((wall.volume / data.insights.magnet_strength) * 100) + '%'"></div>
                        <div class="d-flex justify-content-between mt-1" style="font-size: 0.65rem;">
                            <span class="text-white opacity-50" x-text="wall.type + ' Cluster'"></span>
                            <span class="text-white opacity-50" x-text="Math.abs(wall.distance_pct).toFixed(2) + '% distance'"></span>
                        </div>
                    </div>
                </template>
            </div>

            <div class="p-3 border-top border-border">
                <div class="small text-white text-center opacity-50">Update Loop: 60s</div>
                <div class="text-center mt-1" style="font-size: 0.6rem; color: #8b949e;">
                     Last sync: <span x-text="lastUpdated || 'Never'"></span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('heatmapPro', () => {
            let _chart = null; // Private local variable to prevent Alpine reactivity proxying
            let _currentMagnetStrength = 1; // Store magnet strength to avoid reactive access in Chart callbacks
            
            // Plain formatting functions for Chart.js callbacks (NOT reactive)
            const _fmt = (n) => { 
                if (!n && n !== 0) return '--';
                if (n === 0) return '0.00';
                if (Math.abs(n) < 1) return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
                return new Intl.NumberFormat('en-US').format(Math.round(n)); 
            };
            
            const _fmtK = (n) => {
                if (n >= 1000000000) return (n / 1000000000).toFixed(2) + 'B';
                if (n >= 1000000) return (n / 1000000).toFixed(2) + 'M';
                if (n >= 1000) return (n / 1000).toFixed(2) + 'K';
                return n.toFixed(2);
            };
            
            return {
            loading: false,
            symbol: '{{ $symbols->first() }}',
            range: '12h',
            symbolSummary: {},
            lastUpdated: null,
            data: {
                current_price: 0,
                insights: {
                    magnet_price: 0,
                    magnet_strength: 0,
                    total_fuel: 0,
                    bias: { long_pct: 50, short_pct: 50 },
                    major_walls: [],
                    text: 'Analyzing...',
                    sentiment: 'Neutral'
                }
            },

            init() {
                console.log('Alpine: Heatmap Pro (Coin-Centric) initialized');
                if (window.feather) feather.replace();
                this.waitForChart();
                this.onSymbolChange(); // Auto-start
                
                setInterval(() => this.fetchData(), 60000);
            },

            waitForChart() {
                // Ensure dependencies like Chart.js and adapter are ready
                if (window.Chart && typeof window.Chart.registry?.getScale('time') !== 'undefined') {
                    console.log('Chart.js + Time Adapter Ready');
                    this.initChart();
                } else {
                    console.log('Waiting for Chart.js/Adapter...');
                    setTimeout(() => this.waitForChart(), 200);
                }
            },

            async onSymbolChange() {
                this.loading = true;
                try {
                    const res = await fetch(`{{ route('data.liquidation-heatmap.summary') }}?symbol=${this.symbol}`);
                    const json = await res.json();
                    if (json.success) {
                        this.symbolSummary = json.summary;
                        // Pick best range
                        const best = Object.keys(this.symbolSummary).find(r => this.symbolSummary[r].has_data);
                        if (best && (!this.symbolSummary[this.range] || !this.symbolSummary[this.range].has_data)) {
                            this.range = best;
                        }
                    }
                } catch (e) { console.error('Summary Fetch Error:', e); }
                this.fetchData();
            },

            switchRange(r) {
                if (this.range === r) return;
                this.range = r;
                this.fetchData();
            },

            async fetchData() {
                if (!this.range) return;
                this.loading = true;
                try {
                    console.log(`Syncing ${this.symbol} ${this.range} data...`);
                    const response = await fetch(`{{ route('data.liquidation-heatmap.heatmap') }}?symbol=${this.symbol}&interval=${this.range}`);
                    const json = await response.json();
                    
                    if (json.success) {
                        this.data = json.data;
                        this.lastUpdated = new Date().toLocaleTimeString();
                        this.updateChart(json.data);
                        this.$nextTick(() => { if(window.feather) feather.replace(); });
                        console.log('Sync completed at ' + this.lastUpdated);
                    } else {
                        console.warn('Sync warning:', json.message);
                        this.data.insights.text = `<span class="text-danger">${json.message || 'No data found.'}</span>`;
                        if (_chart) {
                            _chart.data.datasets[0].data = [];
                            _chart.data.datasets[1].data = [];
                            _chart.update();
                        }
                    }
                } catch (e) { 
                    console.error('Data Sync Error:', e); 
                    this.data.insights.text = `<span class="text-danger">Critical Error: ${e.message}</span>`;
                }
                finally { this.loading = false; }
            },

            initChart() {
                if (_chart) return;
                
                // Safety check for Chart.js being ready
                if (typeof window.Chart === 'undefined') {
                    console.warn('Chart.js not yet loaded, retrying...');
                    setTimeout(() => this.initChart(), 500);
                    return;
                }

                const canvas = document.getElementById('heatmapChart');
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                
                _chart = new window.Chart(ctx, {
                    type: 'scatter',
                    data: {
                        datasets: [
                            {
                                label: 'Liquidation Density',
                                data: [],
                                pointStyle: 'rect',
                                clip: false,
                                pointRadius: (c) => {
                                    const v = c.raw?.v || 0;
                                    const max = _currentMagnetStrength || 1;
                                    return Math.min(12, Math.max(3, (v / max) * 15));
                                },
                                backgroundColor: (c) => {
                                    const v = c.raw?.v || 0;
                                    const max = _currentMagnetStrength || 1;
                                    const norm = v / (max * 0.7);
                                    if(norm > 0.8) return '#ffffff'; // White Hot
                                    if(norm > 0.6) return '#f97316'; // Orange
                                    if(norm > 0.4) return '#9333ea'; // Purple
                                    return '#2563eb'; // Blue Base
                                },
                                borderWidth: 0,
                                order: 2
                            },
                            {
                                label: 'Index Price',
                                data: [],
                                type: 'line',
                                borderColor: '#ffffff',
                                borderWidth: 2,
                                pointRadius: 0,
                                hitRadius: 10, // Make it easier to hover
                                clip: false,
                                tension: 0.1,
                                fill: false,
                                order: 1,
                                shadowBlur: 15,
                                shadowColor: 'rgba(255, 255, 255, 0.8)'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        interaction: {
                            intersect: true,
                            mode: 'nearest',
                            axis: 'xy'
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                enabled: true,
                                backgroundColor: 'rgba(13, 17, 23, 0.95)',
                                titleColor: '#ffffff',
                                bodyColor: '#8b949e',
                                borderColor: '#30363d',
                                borderWidth: 1,
                                padding: 12,
                                displayColors: false,
                                callbacks: {
                                    label: (c) => {
                                        if (c.datasetIndex === 0) {
                                             return [
                                                 `Liquidation: $${this.fmtK(c.raw.v)}`,
                                                 `Price Level: $${this.fmt(c.raw.y)}`
                                             ];
                                        }
                                        return `Price: $${this.fmt(c.raw.y)}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: { unit: 'hour', displayFormats: { hour: 'HH:mm' } },
                                grid: { color: 'rgba(48, 54, 61, 0.1)' },
                                ticks: { color: '#8b949e', font: { size: 10 } }
                            },
                            y: {
                                position: 'right',
                                grid: { color: 'rgba(48, 54, 61, 0.1)' },
                                ticks: { 
                                    color: '#ffffff', 
                                    font: { size: 10, weight: 'bold' },
                                    callback: (v) => '$' + this.fmt(v)
                                }
                            }
                        }
                    }
                });
            },

            updateChart(data) {
                // CRITICAL FIX: Destroy and recreate chart instead of update-in-place
                // This completely avoids Alpine reactivity proxy issues
                
                if (_chart) {
                    _chart.destroy();
                    _chart = null;
                }
                
                
                // Convert to plain object
                const plainData = JSON.parse(JSON.stringify(data));
                
                // CRITICAL FIX: Calculate actual max from chart data, not backend
                // After sampling, backend magnet_strength may not match actual max
                const heatmapData = plainData.heatmap || [];
                const actualMax = heatmapData.length > 0 
                    ? Math.max(...heatmapData.map(p => p.v || 0))
                    : (plainData.insights?.magnet_strength || 1);
                _currentMagnetStrength = actualMax;
                
                const canvas = document.getElementById('heatmapChart');
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                
                // Calculate Y axis range
                const allY = [
                    ...(plainData.price_line || []).map(p => p.c),
                    ...(plainData.heatmap || []).map(p => p.y)
                ];
                
                let yMin, yMax;
                if (allY.length > 0) {
                    const minP = Math.min(...allY);
                    const maxP = Math.max(...allY);
                    const range = maxP - minP;
                    const buffer = range > 0 ? range * 0.15 : minP * 0.05;
                    yMin = minP - buffer;
                    yMax = maxP + buffer;
                }
                
                // Create fresh chart
                _chart = new window.Chart(ctx, {
                    type: 'scatter',
                    data: {
                        datasets: [
                            {
                                label: 'Liquidation Density',
                                data: plainData.heatmap || [],
                                pointStyle: 'rect',
                                clip: false,
                                pointRadius: (c) => {
                                    const v = c.raw?.v || 0;
                                    const max = _currentMagnetStrength || 1;
                                    // Smaller points for cleaner visualization
                                    return Math.min(8, Math.max(1.5, (v / max) * 10));
                                },
                                backgroundColor: (c) => {
                                    const v = c.raw?.v || 0;
                                    // CRITICAL FIX: Calculate max from actual data, not backend magnet_strength
                                    // After sampling, magnet_strength may not match actual max in chart
                                    const max = _currentMagnetStrength || 1;
                                    const norm = v / max;
                                    // More selective thresholds for better visual hierarchy
                                    if(norm > 0.95) return '#ffffff';      // High Intensity (white) - top 5% only
                                    if(norm > 0.80) return '#f97316';      // Level 2 (orange) - top 20%
                                    if(norm > 0.50) return '#9333ea';      // Level 1 (purple) - top 50%
                                    return '#2563eb';                      // Base (blue) - rest
                                },
                                borderWidth: 0,
                                order: 2
                            },
                            {
                                label: 'Index Price',
                                data: (plainData.price_line || []).map(p => ({ x: p.x, y: p.c })),
                                type: 'line',
                                borderColor: '#ffffff',
                                borderWidth: 2,
                                pointRadius: 0,
                                hitRadius: 10,
                                clip: false,
                                tension: 0.1,
                                fill: false,
                                order: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        interaction: {
                            intersect: true,
                            mode: 'nearest',
                            axis: 'xy'
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                enabled: true,
                                backgroundColor: 'rgba(13, 17, 23, 0.95)',
                                titleColor: '#ffffff',
                                bodyColor: '#8b949e',
                                borderColor: '#30363d',
                                borderWidth: 1,
                                padding: 12,
                                displayColors: false,
                                callbacks: {
                                    label: (c) => {
                                        if (c.datasetIndex === 0) {
                                            return [
                                                `Liquidation: $${_fmtK(c.raw.v)}`,
                                                `Price Level: $${_fmt(c.raw.y)}`
                                            ];
                                        }
                                        return `Price: $${_fmt(c.raw.y)}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: { unit: 'hour', displayFormats: { hour: 'HH:mm' } },
                                grid: { color: 'rgba(48, 54, 61, 0.1)' },
                                ticks: { color: '#8b949e', font: { size: 10 } }
                            },
                            y: {
                                position: 'right',
                                grid: { color: 'rgba(48, 54, 61, 0.1)' },
                                ticks: { 
                                    color: '#ffffff', 
                                    font: { size: 10, weight: 'bold' },
                                    callback: (v) => '$' + _fmt(v)
                                },
                                min: yMin,
                                max: yMax
                            }
                        }
                    }
                });
            },

            sentimentClass() {
                const s = this.data.insights.sentiment;
                if (s === 'CRITICAL') return 'pro-tag-critical';
                if (s === 'WATCHING') return 'pro-tag-watching';
                return 'pro-tag-stable';
            },

            magnetDistance() {
                const magnet = this.data.insights.magnet_price;
                const current = this.data.current_price;
                if (!magnet || !current) return '--';
                const dist = ((magnet - current) / current) * 100;
                return Math.abs(dist).toFixed(2) + '% ' + (dist > 0 ? 'above' : 'below') + ' current price';
            },

            fmt(n) { 
                if (!n && n !== 0) return '--';
                if (n === 0) return '0.00';
                if (Math.abs(n) < 1) return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
                return new Intl.NumberFormat('en-US').format(Math.round(n)); 
            },
            
            fmtK(n) {
                if (n >= 1000000000) return (n / 1000000000).toFixed(2) + 'B';
                if (n >= 1000000) return (n / 1000000).toFixed(2) + 'M';
                if (n >= 1000) return (n / 1000).toFixed(2) + 'K';
                return n.toFixed(2);
            }
        };
    });
});
</script>
@endsection
