@extends('layouts.app')

@section('title', 'Liquidation Heatmap Pro | DragonFortune')

@push('head')
<script>
    // Global flag to attempt to disable the aggressive app.js "auto-refresh" scrubber
    window.__AUTO_REFRESH_DISABLED__ = true;
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

        /* Bias Meter */
        .bias-container {
            height: 8px;
            background: var(--df-danger);
            border-radius: 4px;
            overflow: hidden;
            display: flex;
            margin: 10px 0;
        }
        .bias-fill {
            background: var(--df-success);
            height: 100%;
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Major Walls List */
        .wall-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--df-border);
            transition: background 0.2s;
            cursor: default;
        }
        .wall-item:hover { background: var(--df-bg-hover); }

        .wall-bar {
            height: 4px;
            background: var(--df-accent);
            border-radius: 2px;
            margin-top: 4px;
            opacity: 0.6;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--df-bg-deep); }
        ::-webkit-scrollbar-thumb { background: var(--df-border); border-radius: 3px; }

        .loading-overlay {
            position: absolute;
            inset: 0;
            background: rgba(13, 17, 23, 0.8);
            backdrop-filter: blur(4px);
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.3s;
        }

        /* Chart adjustments */
        #heatmapChart {
            flex-grow: 1;
            padding: 10px;
            min-height: 500px;
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
                <span class="input-group-text bg-card border-border text-dim">Asset</span>
                <select x-model="symbol" @change="fetchData()" class="form-select bg-card border-border text-white fw-bold" style="width: 120px;">
                    @foreach($symbols as $s) <option value="{{ $s }}">{{ $s }}</option> @endforeach
                </select>
            </div>
            
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-card border-border text-dim">Range</span>
                <select x-model="range" @change="fetchData()" class="form-select bg-card border-border text-white fw-bold" style="width: 100px;">
                    @foreach($intervals as $i) <option value="{{ $i }}">{{ $i }}</option> @endforeach
                </select>
            </div>
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

            <div class="sidebar-stat">
                <div class="stat-label">Strongest Magnet</div>
                <div class="stat-main text-highlight" x-text="'$' + fmt(data.insights.magnet_price)">--</div>
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
            
            <div id="chartContainer" class="flex-grow-1 h-100 w-100">
                <canvas id="heatmapChart"></canvas>
            </div>

            <!-- No overlay for now to debug -->
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
            
            return {
                symbol: '{{ $symbols->first() }}',
                range: '{{ $intervals->first() }}',
                loading: false,
                data: {
                    current_price: 0,
                    insights: {
                        magnet_price: 0,
                        magnet_strength: 0,
                        total_fuel: 0,
                        bias: { long_pct: 50, short_pct: 50 },
                        major_walls: [],
                        text: 'Analyzing...',
                        sentiment: 'STABLE'
                    }
                },

            init() {
                console.log('Alpine: heatmapPro component initialized');
                if (window.feather) feather.replace();
                this.waitForChart();
                
                // Set interval for data refresh
                setInterval(() => this.fetchData(), 60000);
            },

            waitForChart() {
                if (window.Chart) {
                    this.initChart();
                    this.fetchData();
                } else {
                    setTimeout(() => this.waitForChart(), 100);
                }
            },

            async fetchData() {
                this.loading = true;
                try {
                    const response = await fetch(`{{ route('data.liquidation-heatmap.heatmap') }}?symbol=${this.symbol}&interval=${this.range}`);
                    const json = await response.json();
                    
                    if (json.success) {
                        console.log('Heatmap Data Received:', {
                            heatmap_points: json.data.heatmap.length,
                            price_points: json.data.price_line.length,
                            insights: json.data.insights
                        });
                        
                        this.data.insights = json.data.insights;
                        this.data.current_price = json.data.current_price;
                        
                        // Pass raw data directly to chart
                        this.updateChart(json.data);
                        
                        this.$nextTick(() => { if(window.feather) feather.replace(); });
                    }
                } catch (error) {
                    console.error('Heatmap Fetch Error:', error);
                } finally {
                    this.loading = false;
                }
            },

            initChart() {
                const ctx = document.getElementById('heatmapChart').getContext('2d');
                
                _chart = new window.Chart(ctx, {
                    type: 'matrix',
                    data: {
                        datasets: [
                            {
                                label: 'Liquidation Density',
                                data: [],
                                backgroundColor: (c) => {
                                    const v = c.raw?.v || 0;
                                    const max = this.data.insights.magnet_strength || 1;
                                    const norm = v / (max * 0.8); // Scale for better visibility
                                    
                                    if(v === 0) return 'rgba(0,0,0,0)';
                                    if(norm > 0.8) return '#ffffff'; // Hot
                                    if(norm > 0.6) return '#f97316'; // Extreme
                                    if(norm > 0.4) return '#9333ea'; // High
                                    if(norm > 0.1) return '#2b57e6'; // Mid
                                    return 'rgba(43, 87, 230, 0.15)'; // Low
                                },
                                // Dynamic size based on range/density
                                width: ({chart}) => chart.chartArea ? chart.chartArea.width / 400 : 2,
                                height: ({chart}) => chart.chartArea ? chart.chartArea.height / 80 : 2,
                                order: 2
                            },
                            {
                                type: 'line',
                                label: 'Price (Index)',
                                data: [],
                                borderColor: '#00d2ff',
                                borderWidth: 2,
                                pointRadius: 0,
                                clip: {left: 0, top: 0, right: 0, bottom: 0},
                                shadowBlur: 10,
                                shadowColor: 'rgba(0, 210, 255, 0.5)',
                                order: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 400 },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                enabled: true,
                                mode: 'index',
                                intersect: false,
                                backgroundColor: 'rgba(22, 27, 34, 0.95)',
                                titleColor: '#8b949e',
                                bodyColor: '#c9d1d9',
                                borderColor: '#30363d',
                                borderWidth: 1,
                                padding: 12,
                                displayColors: false,
                                callbacks: {
                                    title: (ctx) => {
                                        const date = new Date(ctx[0].raw.x);
                                        return date.toLocaleString();
                                    },
                                    label: (ctx) => {
                                        const raw = ctx.raw;
                                        if (raw.v) {
                                            return [
                                                ` Price: $${this.fmt(raw.y)}`,
                                                ` Liquidity: $${this.fmtK(raw.v)}`
                                            ];
                                        }
                                        return ` Price: $${this.fmt(raw.y)}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: { unit: 'hour', displayFormats: { hour: 'HH:mm' } },
                                grid: { color: 'rgba(48, 54, 61, 0.2)' },
                                ticks: { color: '#8b949e', font: { size: 10 } }
                            },
                            y: {
                                position: 'right',
                                grid: { color: 'rgba(48, 54, 61, 0.2)' },
                                ticks: { 
                                    color: '#8b949e', 
                                    font: { size: 10 },
                                    callback: (v) => '$' + this.fmt(v)
                                }
                            }
                        }
                    }
                });
            },

            updateChart(data) {
                if (!_chart) return;
                console.log('Updating chart with data:', data);
                
                // Update Matrix - Directly poke the data to avoid proxying
                _chart.data.datasets[0].data = data.heatmap || [];
                
                // Update Price Line
                _chart.data.datasets[1].data = data.price_line || [];
                
                // Adjust Y axis range for better visibility (padding)
                if (data.price_line && data.price_line.length > 0) {
                    const prices = data.price_line.map(p => p.y);
                    const min = Math.min(...prices) * 0.98;
                    const max = Math.max(...prices) * 1.02;
                    
                    if (isFinite(min) && isFinite(max)) {
                        _chart.options.scales.y.min = min;
                        _chart.options.scales.y.max = max;
                    }
                }

                _chart.update('none');
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

            fmt(n) { return new Intl.NumberFormat('en-US').format(Math.round(n)); },
            fmtK(n) {
                if (n >= 1000000000) return (n / 1000000000).toFixed(2) + 'B';
                if (n >= 1000000) return (n / 1000000).toFixed(2) + 'M';
                if (n >= 1000) return (n / 1000).toFixed(2) + 'K';
                return n.toFixed(0);
            }
        };
    });
});
</script>
@endsection
