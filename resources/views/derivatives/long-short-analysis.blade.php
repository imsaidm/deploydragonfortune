@extends('layouts.app')

@section('title', 'Smart Money Analysis | DragonFortune')

@push('head')
    <style>
        :root {
            --bg-body: #0f172a;
            --bg-panel: #1e293b;
            --border-panel: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent-primary: #3b82f6;
            --accent-success: #10b981;
            --accent-danger: #ef4444;
            --accent-warning: #f59e0b;
        }
        
        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .df-card {
            background-color: var(--bg-panel);
            border: 1px solid var(--border-panel);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .df-card:hover {
            border-color: #475569;
        }

        .stat-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 600;
        }

        .stat-value {
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            color: var(--text-main);
        }

        .trend-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
        }
        .trend-up { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .trend-down { background: rgba(239, 68, 68, 0.15); color: #f87171; }

        .custom-select {
            background-color: #334155;
            border-color: #475569;
            color: white;
        }
        
        .insight-box {
            background: linear-gradient(180deg, rgba(30, 41, 59, 0) 0%, rgba(59, 130, 246, 0.05) 100%);
            border-left: 4px solid var(--accent-primary);
        }

        .table-modern {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.9rem;
        }
        .table-modern th {
            text-align: left;
            padding: 12px 16px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-panel);
            font-weight: 600;
            background-color: rgba(0,0,0,0.2);
        }
        .table-modern td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-panel);
            color: var(--text-main);
        }
        .table-modern tr:last-child td { border-bottom: none; }
        .table-modern tr:hover { background-color: rgba(255,255,255,0.03); }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--border-panel); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
    </style>
@endpush

@section('content')
<div class="d-flex flex-column gap-4" x-data="longShortV2()">
    
    <!-- Header Controls -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <h1 class="h4 mb-0 fw-bold text-white tracking-tight">
                Smart Money Analytics
            </h1>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-20 rounded-pill px-3 py-2">V3.0 Premium</span>
        </div>

        <div class="d-flex gap-2">
            <select x-model="filters.exchange" @change="fetchData()" class="form-select form-select-sm custom-select">
                <template x-for="ex in available.exchanges">
                    <option :value="ex" x-text="ex"></option>
                </template>
            </select>
            <select x-model="filters.pair" @change="fetchData()" class="form-select form-select-sm custom-select">
                <template x-for="p in available.pairs">
                    <option :value="p" x-text="p"></option>
                </template>
            </select>
            <select x-model="filters.interval" @change="fetchData()" class="form-select form-select-sm custom-select">
                <option value="5m">5m</option>
                <option value="15m">15m</option>
                <option value="1h">1h</option>
                <option value="4h">4h</option>
                <option value="1d">1d</option>
            </select>
            <!-- <button @click="fetchData()" class="btn btn-sm btn-primary d-flex align-items-center justify-content-center" style="width: 32px;">
                <i data-feather="refresh-cw" width="14" :class="loading ? 'spin' : ''"></i>
            </button> -->
        </div>
    </div>

    <!-- Main Grid -->
    <div class="row g-4">
        <!-- Left Column: KPIs & Insight -->
        <div class="col-lg-3 d-flex flex-column gap-4">
            <!-- Sentiment Box -->
            <div class="df-card p-4 insight-box">
                <div class="stat-label mb-2">Market Sentiment</div>
                <div class="d-flex align-items-baseline gap-2 mb-3">
                    <span class="fw-bold fs-4" :class="data.sentimentColor" x-text="data.sentiment || '--'">--</span>
                </div>
                <!-- Insight Text -->
                <div class="text-main opacity-75 small" 
                     style="font-size: 0.85rem; line-height: 1.6;"
                     x-html="data.insightText">
                     Loading intelligence...
                </div>
            </div>

            <!-- Smart Money KPI -->
            <div class="df-card p-4">
                <div class="d-flex justify-content-between mb-3">
                    <span class="stat-label text-success">Smart Money (Top)</span>
                    <i data-feather="briefcase" width="16" class="text-success opacity-50"></i>
                </div>
                <div class="d-flex justify-content-between align-items-end mb-4">
                    <div>
                        <div class="stat-value fs-2" x-text="fmt(data.stats.top_ratio)">--</div>
                        <span class="text-muted small">L/S Ratio</span>
                    </div>
                    <div class="text-end">
                        <div class="trend-badge" :class="parseFloat(data.stats.top_delta_ratio) >= 0 ? 'trend-up' : 'trend-down'"
                             x-text="fmtDiff(data.stats.top_delta_ratio)">--</div>
                        <div class="text-muted small mt-1">1h Change</div>
                    </div>
                </div>
                
                <!-- Net Position -->
                <div class="d-flex justify-content-between small mb-2">
                    <span class="text-muted">Net Exposure</span>
                    <span class="font-monospace fw-bold" :class="data.stats.top_net_position >= 0 ? 'text-success' : 'text-danger'"
                          x-text="fmt(data.stats.top_net_position) + '%'">--</span>
                </div>
                <div class="progress bg-gray-200" style="height: 6px;">
                    <div class="progress-bar bg-success" :style="'width: ' + ((data.stats.top_net_position + 100)/2) + '%'"></div>
                </div>
            </div>

            <!-- Retail KPI -->
            <div class="df-card p-4">
                <div class="d-flex justify-content-between mb-3">
                    <span class="stat-label text-info">Retail Crowd (Global)</span>
                    <i data-feather="users" width="16" class="text-info opacity-50"></i>
                </div>
                 <div class="d-flex justify-content-between align-items-end mb-4">
                    <div>
                        <div class="stat-value fs-2" x-text="fmt(data.stats.global_ratio)">--</div>
                        <span class="text-muted small">L/S Ratio</span>
                    </div>
                </div>

                <div class="d-flex justify-content-between small mb-2">
                    <span class="text-muted">Net Exposure</span>
                    <span class="font-monospace fw-bold" :class="data.stats.global_net_position >= 0 ? 'text-info' : 'text-warning'"
                          x-text="fmt(data.stats.global_net_position) + '%'">--</span>
                </div>
                <div class="progress bg-gray-200" style="height: 6px;">
                    <div class="progress-bar bg-info" :style="'width: ' + ((data.stats.global_net_position + 100)/2) + '%'"></div>
                </div>
            </div>
        </div>

        <!-- Middle Column: Chart -->
        <div class="col-lg-9">
            <div class="df-card p-4 h-100 d-flex flex-column">
                <div class="d-flex justify-content-between mb-4">
                    <div>
                        <span class="stat-label d-block mb-1">Divergence Analysis</span>
                        <h2 class="h5 fw-bold mb-0 text-main" x-text="chartMode === 'ratio' ? 'Ratio Trend Overlay' : 'Long Exposure Composition'">Ratio Trend Overlay</h2>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <!-- Mode Toggle -->
                        <div class="btn-group btn-group-sm">
                            <button @click="toggleChartMode('ratio')" class="btn btn-toggle" :class="chartMode === 'ratio' ? 'active' : ''">Ratio</button>
                            <button @click="toggleChartMode('exposure')" class="btn btn-toggle" :class="chartMode === 'exposure' ? 'active' : ''">Position %</button>
                        </div>

                        <div class="d-flex gap-4 small align-items-center border-start ps-3 border-gray-200">
                            <span class="d-flex align-items-center gap-2 text-muted"><span class="rounded-circle bg-success" style="width: 10px; height: 10px;"></span> Smart Money</span>
                            <span class="d-flex align-items-center gap-2 text-muted"><span class="rounded-circle bg-primary" style="width: 10px; height: 10px;"></span> Retail Crowd</span>
                        </div>
                    </div>
                </div>
                <div class="flex-grow-1 position-relative" style="min-height: 400px;">
                    <canvas id="mainChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom: Data Table -->
    <div class="row">
        <div class="col-12">
            <div class="df-card overflow-hidden">
                <div class="p-4 border-bottom border-secondary border-opacity-10">
                    <h3 class="h6 mb-0 fw-bold">Historical Data Points</h3>
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th class="text-success">Smart Ratio</th>
                                <th class="text-success">Smart Net</th>
                                <th class="text-primary">Retail Ratio</th>
                                <th class="text-primary">Retail Net</th>
                                <th>Divergence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in data.table.slice(0, 50)" :key="row.time">
                                <tr>
                                    <td class="font-monospace text-muted" x-text="formatTime(row.time)"></td>
                                    
                                    <td class="fw-bold text-success" x-text="fmt(row.top_ratio)"></td>
                                    <td x-text="fmt(row.top_long - row.top_short) + '%'"></td>
                                    
                                    <td class="text-primary" x-text="fmt(row.global_ratio)"></td>
                                    <td x-text="fmt(row.global_long - row.global_short) + '%'"></td>
                                    
                                    <!-- Divergence Calculation -->
                                    <td class="font-monospace fw-bold" x-text="fmt(row.top_ratio - row.global_ratio)" 
                                        :class="(row.top_ratio - row.global_ratio) > 0 ? 'text-success' : 'text-danger'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('longShortV2', () => {
            let chartInstance = null;

            return {
                loading: false,
                chartMode: 'ratio', // 'ratio' or 'exposure'
                filters: {
                    exchange: '{{ $exchanges->first() ?? "Binance" }}',
                    pair: '{{ $pairs->first() ?? "BTCUSDT" }}',
                    interval: '1h'
                },
                available: {
                    exchanges: @json($exchanges),
                    pairs: @json($pairs),
                },
                data: {
                    sentiment: 'Neutral',
                    sentimentColor: 'text-secondary',
                    insightText: 'Initializing...',
                    stats: {
                        top_ratio: 0, top_net_position: 0, top_delta_ratio: 0,
                        global_ratio: 0, global_net_position: 0
                    },
                    table: [],
                    chart: {} // Store raw chart data
                },

                init() {
                    setTimeout(() => {
                        this.initChart();
                        this.fetchData();
                    }, 100);
                    if (typeof feather !== 'undefined') feather.replace();
                },

                async fetchData() {
                    this.loading = true;
                    try {
                        const params = new URLSearchParams(this.filters);
                        const response = await fetch(`{{ route('api.long-short-analysis.data') }}?${params.toString()}`);
                        if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);
                        const result = await response.json();

                        if (result.success) {
                            this.processData(result.data);
                        } else {
                            this.data.insightText = `<span class='text-danger'>Error: ${result.message}</span>`;
                        }
                    } catch (error) {
                         console.error('API Error:', error);
                         this.data.insightText = "<span class='text-danger'>Connection/Data Error. Please refresh.</span>";
                    } finally {
                        this.loading = false;
                    }
                },

                processData(data) {
                    this.data.stats = data.latest_stats;
                    this.data.sentiment = data.sentiment.signal;
                    this.data.insightText = data.insight;
                    this.data.table = data.table_data;
                    this.data.chart = data.chart_data; // Save raw data for toggling
                    
                    const colorMap = {
                        'text-green-500': 'text-success',
                        'text-red-500': 'text-danger',
                        'text-blue-500': 'text-primary',
                        'text-orange-500': 'text-warning',
                        'text-gray-500': 'text-secondary'
                    };
                    this.data.sentimentColor = colorMap[data.sentiment.signal_color] || 'text-dark';

                    this.updateChart();
                },

                toggleChartMode(mode) {
                    this.chartMode = mode;
                    this.updateChart();
                },

                fmt(num) { return num ? parseFloat(num).toFixed(2) : '--'; },
                fmtDiff(num) { if(!num) return '--'; const n = parseFloat(num); return (n >= 0 ? '+' : '') + n.toFixed(2); },
                formatTime(ts) { return new Date(ts).toLocaleString(); },

                initChart() {
                    const canvas = document.getElementById('mainChart');
                    if (!canvas) return;
                    const ctx = canvas.getContext('2d');
                    
                    Chart.defaults.font.family = "'Inter', sans-serif";
                    Chart.defaults.color = '#64748b'; 
                    
                    if (chartInstance) chartInstance.destroy();

                    chartInstance = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: [],
                            datasets: [
                                {
                                    label: 'Smart Money',
                                    data: [],
                                    borderColor: '#10b981',
                                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                    borderWidth: 2,
                                    tension: 0.3,
                                    fill: true,
                                    pointRadius: 0,
                                    pointHoverRadius: 4,
                                    yAxisID: 'y'
                                },
                                {
                                    label: 'Retail Crowd',
                                    data: [],
                                    borderColor: '#3b82f6',
                                    backgroundColor: 'transparent',
                                    borderWidth: 2,
                                    borderDash: [4, 4],
                                    tension: 0.3,
                                    fill: false,
                                    pointRadius: 0,
                                    pointHoverRadius: 4,
                                    yAxisID: 'y'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#ffffff',
                                    titleColor: '#1e293b',
                                    bodyColor: '#475569',
                                    borderColor: '#e2e8f0',
                                    borderWidth: 1,
                                    padding: 12,
                                    cornerRadius: 8,
                                    displayColors: true,
                                    boxPadding: 4,
                                    callbacks: {
                                        label: (ctx) => {
                                            const val = ctx.raw;
                                            const label = ctx.dataset.label;
                                            return label + ': ' + val + (this.chartMode === 'exposure' ? '%' : '');
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    grid: { color: '#f1f5f9', tickColor: 'transparent', borderDash: [2, 2] },
                                    ticks: { 
                                        color: '#64748b', 
                                        font: {size: 11},
                                        callback: (value) => value + (this.chartMode === 'exposure' ? '%' : '') 
                                    },
                                    position: 'right',
                                    border: { display: false }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { color: '#64748b', maxTicksLimit: 8, font: {size: 11} }
                                }
                            }
                        }
                    });
                },

                updateChart() {
                    if (!chartInstance || !this.data.chart.labels) return;
                    
                    const cData = this.data.chart;
                    chartInstance.data.labels = cData.labels;

                    if (this.chartMode === 'ratio') {
                        chartInstance.data.datasets[0].label = 'Smart Money Ratio';
                        chartInstance.data.datasets[0].data = cData.top_series;
                        chartInstance.data.datasets[1].label = 'Retail Ratio';
                        chartInstance.data.datasets[1].data = cData.global_series;
                        chartInstance.options.scales.y.title = { display: true, text: 'Long/Short Ratio' };
                    } else { // exposure
                        chartInstance.data.datasets[0].label = 'Smart Money Long Exposure';
                        chartInstance.data.datasets[0].data = cData.top_long_series;
                        chartInstance.data.datasets[1].label = 'Retail Long Exposure';
                        chartInstance.data.datasets[1].data = cData.global_long_series;
                        chartInstance.options.scales.y.title = { display: true, text: 'Long Position %' };
                    }
                    
                    chartInstance.update();
                }
            };
        });
    });
</script>
<style>
    /* Vibrant Light Theme Variables */
    :root {
        --bg-body: #f8fafc;        /* Slate 50 */
        --bg-panel: #ffffff;       /* White */
        --border-panel: #e2e8f0;   /* Slate 200 */
        --text-main: #1e293b;      /* Slate 800 */
        --text-muted: #64748b;     /* Slate 500 */
        --accent-primary: #3b82f6; 
        --accent-success: #10b981;
        --accent-danger: #ef4444;
        --accent-warning: #f59e0b;
        --shadow-card: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    }
    
    /* Specific overrides */
    .df-card {
        background-color: var(--bg-panel) !important;
        border: 1px solid var(--border-panel) !important;
        box-shadow: var(--shadow-card) !important;
        border-radius: 12px;
    }
    
    body { background-color: var(--bg-body) !important; color: var(--text-main) !important; }
    h1, h2, h3, h4, h5, h6 { color: #0f172a !important; }
    .stat-label { color: #64748b !important; font-weight: 700 !important; letter-spacing: 0.05em; }
    .stat-value { color: #1e293b !important; }
    .text-muted { color: #94a3b8 !important; }
    
    /* Table Styling */
    .table-modern th { background-color: #f1f5f9 !important; color: #475569 !important; border-bottom: 2px solid #e2e8f0 !important; }
    .table-modern td { color: #334155 !important; border-bottom: 1px solid #f1f5f9 !important; }
    .table-modern tr:hover { background-color: #f8fafc !important; }

    /* Custom Select & Buttons */
    .custom-select { background-color: #ffffff !important; border: 1px solid #cbd5e1 !important; color: #334155 !important; }
    .btn-toggle { border: 1px solid #e2e8f0; color: var(--text-muted); background: white; }
    .btn-toggle.active { background: var(--accent-primary); color: white; border-color: var(--accent-primary); }
    
    /* Insight Box */
    .insight-box { background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%) !important; border-left: 4px solid var(--accent-primary) !important; }
    .badge { font-weight: 600; letter-spacing: 0.02em; }
</style>
@endsection
