@extends('layouts.app')

@section('title', 'Liquidation Heatmap Advanced | DragonFortune')

@push('head')
    {{-- Chart.js and Matrix are loaded via app.js (Vite) --}}
    <style>
        :root {
            /* Light/Playful Theme - Default */
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --accent-primary: #0ea5e9; /* Sky Blue */
            --accent-success: #10b981;
            --accent-danger: #ef4444;
            --accent-warning: #f59e0b;
            
            /* Heatmap Colors (Low to High Intensity) */
            --hm-color-1: rgba(14, 165, 233, 0.1);
            --hm-color-2: rgba(14, 165, 233, 0.3);
            --hm-color-3: rgba(245, 158, 11, 0.5); /* Yellow start */
            --hm-color-4: rgba(245, 158, 11, 0.8);
            --hm-color-5: rgba(239, 68, 68, 0.9); /* Red Hot */
        }
        
        body {
            background-color: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .df-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px; /* Playful rounded corners */
            box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05); /* Soft shadow */
            transition: transform 0.2s ease;
        }

        .df-card:hover {
            transform: translateY(-2px);
        }

        .insight-badge {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 6px 12px;
            border-radius: 20px;
        }

        .insight-critical { background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); }
        .insight-watching { background: rgba(14, 165, 233, 0.1); color: var(--accent-primary); }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(45deg, var(--text-primary), var(--text-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Custom Select */
        .form-select-custom {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background-color: var(--bg-card);
            font-weight: 600;
            color: var(--text-primary);
            cursor: pointer;
        }
        .form-select-custom:focus {
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
            border-color: var(--accent-primary);
        }

        /* Chart Tooltip Customization */
        .chartjs-tooltip {
            opacity: 1;
            position: absolute;
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-primary);
            border-radius: 8px;
            pointer-events: none;
            transform: translate(-50%, 0);
            transition: all 0.1s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
            padding: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            z-index: 100;
        }
    </style>
@endpush

@section('content')
<div x-data="liquidationHeatmap()" class="d-flex flex-column gap-4 pb-5">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 fw-bold mb-1" style="color: var(--text-primary)">Liquidation Magnets</h1>
            <p class="mb-0 small text-muted">Visualize cumulative leverage clusters affecting price action.</p>
        </div>
        
        <div class="d-flex gap-2">
            <!-- Symbol Selector -->
            <select x-model="symbol" @change="fetchData()" class="form-select form-select-custom shadow-sm" style="width: 140px;">
                @foreach($symbols as $s)
                    <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>
            
            <!-- Interval Selector -->
            <select x-model="interval" @change="fetchData()" class="form-select form-select-custom shadow-sm" style="width: 100px;">
                @foreach($intervals as $i)
                    <option value="{{ $i }}">{{ $i }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Insights Panel -->
    <div class="row g-4">
        <div class="col-12">
            <div class="df-card p-4 d-flex align-items-center justify-content-between flex-wrap gap-4">
                
                <!-- Magnet Insight -->
                <div class="d-flex align-items-start gap-3" style="max-width: 600px;">
                    <div class="p-3 rounded-circle" style="background: rgba(14, 165, 233, 0.1); color: var(--accent-primary);">
                        <i data-feather="magnet" style="width: 24px; height: 24px;"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="text-uppercase fw-bold text-muted small">Market Insight</span>
                            <span class="insight-badge" :class="insight.sentiment === 'Critical' ? 'insight-critical' : 'insight-watching'" x-text="insight.sentiment">--</span>
                        </div>
                        <p class="mb-0 text-secondary" style="line-height: 1.5;" x-html="insight.text">Loading market intelligence...</p>
                    </div>
                </div>

                <!-- Stats -->
                <div class="d-flex gap-5 border-start ps-5 border-2">
                    <div>
                        <div class="text-uppercase fw-bold text-muted small mb-1">Strongest Magnet</div>
                        <div class="stat-value" x-text="'$' + fmt(insight.magnet_price)">--</div>
                    </div>
                    <div>
                        <div class="text-uppercase fw-bold text-muted small mb-1">Nearby Liquidity (5%)</div>
                        <div class="stat-value text-success" x-text="'$' + fmtK(insight.local_fuel)">--</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Chart -->
    <div class="df-card p-4 flex-grow-1 position-relative" style="min-height: 500px;">
        <div class="d-flex justify-content-between mb-3">
            <h5 class="fw-bold m-0 text-primary">Heatmap Visualization</h5>
            <div class="d-flex gap-3 small text-muted">
                <div class="d-flex align-items-center gap-1"><span class="rounded-circle" style="width: 10px; height: 10px; background: var(--hm-color-2)"></span> Low</div>
                <div class="d-flex align-items-center gap-1"><span class="rounded-circle" style="width: 10px; height: 10px; background: var(--hm-color-3)"></span> Mid</div>
                <div class="d-flex align-items-center gap-1"><span class="rounded-circle" style="width: 10px; height: 10px; background: var(--hm-color-5)"></span> Extreme</div>
                <div class="d-flex align-items-center gap-1 border-start ps-3"><span style="width: 15px; height: 2px; background: #1e293b"></span> Price</div>
            </div>
        </div>
        
        <canvas id="heatmapChart"></canvas>
        
        <!-- Loading Overlay -->
        <div x-show="loading" class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" 
             style="background: rgba(255,255,255,0.7); border-radius: 16px; z-index: 10;">
             <div class="spinner-border text-primary" role="status"></div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('liquidationHeatmap', () => ({
            symbol: '{{ $symbols->first() }}',
            interval: '{{ $intervals->first() }}',
            loading: false,
            insight: { text: 'Initializing...', magnet_price: 0, local_fuel: 0, sentiment: 'Loading' },
            chart: null,

            init() {
                if (typeof feather !== 'undefined') feather.replace();
                this.waitForChart();
            },

            waitForChart() {
                if (window.Chart) {
                    this.initChart();
                    this.fetchData();
                } else {
                    console.log('Waiting for Chart.js to load...');
                    setTimeout(() => this.waitForChart(), 100);
                }
            },

            async fetchData() {
                this.loading = true;
                try {
                    const response = await fetch(`{{ route('data.liquidation-heatmap.heatmap') }}?symbol=${this.symbol}&interval=${this.interval}`);
                    const json = await response.json();
                    
                    if (json.success) {
                        this.updateData(json.data);
                    }
                } catch (error) {
                    console.error('Error fetching heatmap:', error);
                    this.insight.text = `<span class="text-danger">Failed to load data. Please retry.</span>`;
                } finally {
                    this.loading = false;
                }
            },

            updateData(data) {
                // Update Insights
                this.insight = data.insights;

                // Update Chart
                if (this.chart) {
                    // Update Matrix Dataset
                    this.chart.data.datasets[0].data = data.heatmap;
                    
                    // Update Price Line Dataset
                    this.chart.data.datasets[1].data = data.price_line;
                    
                    this.chart.update();
                }
            },

            initChart() {
                const ctx = document.getElementById('heatmapChart').getContext('2d');
                
                // Use window.Chart to ensure we use the global instance from app.js
                this.chart = new window.Chart(ctx, {
                    type: 'matrix',
                    data: {
                        datasets: [
                            {
                                label: 'Liquidation Intensity',
                                data: [],
                                backgroundColor: (c) => {
                                    const v = c.raw?.v || 0;
                                    // Dynamic color logic based on intensity (simplified for demo)
                                    // Ideally, normalized against max value of the set
                                    if(v > 10000000) return 'rgba(239, 68, 68, 0.9)'; // High
                                    if(v > 5000000) return 'rgba(245, 158, 11, 0.7)'; // Mid
                                    return 'rgba(14, 165, 233, 0.2)'; // Low
                                },
                                width: ({chart}) => (chart.chartArea || {}).width / 60, // approximate width
                                height: ({chart}) => (chart.chartArea || {}).height / 20, // approximate height
                                order: 2
                            },
                            {
                                type: 'line',
                                label: 'Price (Close)',
                                data: [], // Filled via API
                                borderColor: '#1e293b',
                                borderWidth: 2,
                                pointRadius: 0,
                                tension: 0.1,
                                order: 1 // Draw on top
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: () => '',
                                    label: (ctx) => {
                                        const v = ctx.raw;
                                        if(v.v) return [`Price: $${v.y}`, `Liquidations: $${this.fmtK(v.v)}`];
                                        return `Price: $${v.y}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: { unit: 'hour' },
                                grid: { display: false },
                                ticks: { maxTicksLimit: 8 }
                            },
                            y: {
                                type: 'linear',
                                position: 'right',
                                grid: { color: '#f1f5f9' }
                            }
                        }
                    }
                });
            },

            fmt(n) { return new Intl.NumberFormat('en-US').format(n); },
            fmtK(n) {
                if (n >= 1000000000) return (n / 1000000000).toFixed(2) + 'B';
                if (n >= 1000000) return (n / 1000000).toFixed(2) + 'M';
                if (n >= 1000) return (n / 1000).toFixed(2) + 'K';
                return n.toFixed(0);
            }
        }));
    });
</script>
@endsection
