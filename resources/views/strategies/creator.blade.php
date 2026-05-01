@extends('layouts.app')

@section('title', 'Strategy - ' . ucfirst($creator))

@push('head')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<style>
    /* CSS Variables for Glassmorphism & Themes */
    :root {
        --iq-bg-gradient: linear-gradient(135deg, #e0f8eb 0%, #ffffff 100%);
        --iq-card-bg: rgba(255, 255, 255, 0.7);
        --iq-card-border: rgba(255, 255, 255, 0.8);
        --iq-text-main: #1f2937;
        --iq-text-muted: #6b7280;
        --iq-accent: #10b981;
        /* Emerald 500 */
        --iq-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        --iq-pill-bg: rgba(255, 255, 255, 0.5);
    }

    .dark {
        --iq-bg-gradient: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        --iq-card-bg: rgba(31, 41, 55, 0.6);
        --iq-card-border: rgba(55, 65, 81, 0.5);
        --iq-text-main: #f9fafb;
        --iq-text-muted: #9ca3af;
        --iq-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        --iq-pill-bg: rgba(55, 65, 81, 0.5);
    }

    /* Overall Layout Wrapper */
    .iq-wrapper {
        background: var(--iq-bg-gradient);
        border-radius: 24px;
        padding: 32px;
        color: var(--iq-text-main);
        min-height: calc(100vh - 100px);
        position: relative;
        overflow: hidden;
    }

    /* Decorative Orbs */
    .iq-orb {
        position: absolute;
        border-radius: 50%;
        filter: blur(80px);
        z-index: 0;
        opacity: 0.6;
    }

    .iq-orb-1 {
        top: -100px;
        left: -100px;
        width: 300px;
        height: 300px;
        background: rgba(16, 185, 129, 0.3);
    }

    .iq-orb-2 {
        bottom: -100px;
        right: -100px;
        width: 400px;
        height: 400px;
        background: rgba(52, 211, 153, 0.2);
    }

    /* Glass Panels */
    .iq-glass {
        background: var(--iq-card-bg);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--iq-card-border);
        border-radius: 16px;
        box-shadow: var(--iq-shadow);
        z-index: 1;
        position: relative;
    }

    /* Select Dropdown styling */
    .iq-select {
        appearance: none;
        background: var(--iq-pill-bg) url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='currentColor' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e") no-repeat right .75rem center/16px 12px;
        border: 1px solid var(--iq-card-border);
        color: var(--iq-text-main);
        padding: 0.5rem 2.5rem 0.5rem 1rem;
        border-radius: 999px;
        font-weight: 500;
        cursor: pointer;
        backdrop-filter: blur(10px);
        box-shadow: var(--iq-shadow);
        outline: none;
    }

    .iq-select:focus {
        border-color: var(--iq-accent);
    }

    /* Header text */
    .iq-title-main {
        font-size: 2.5rem;
        font-weight: 800;
        letter-spacing: -0.025em;
        margin-bottom: 0.25rem;
    }

    .iq-subtitle {
        color: var(--iq-text-muted);
        font-size: 1.1rem;
        font-weight: 400;
    }

    /* Primary KPI boxes (Balance, TP, SL) */
    .iq-kpi-box {
        padding: 24px;
        text-align: left;
        display: flex;
        flex-direction: column;
        justify-content: center;
        height: 100%;
    }

    .iq-kpi-label {
        font-size: 0.9rem;
        color: var(--iq-text-muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 8px;
    }

    .iq-kpi-val {
        font-size: 2.25rem;
        font-weight: 800;
        line-height: 1.1;
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .iq-val-primary {
        background-image: linear-gradient(135deg, #10b981, #059669);
    }

    .iq-val-success {
        background-image: linear-gradient(135deg, #34d399, #10b981);
    }

    .iq-val-danger {
        background-image: linear-gradient(135deg, #f87171, #ef4444);
    }

    /* Micro badges */
    .iq-badge {
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--iq-pill-bg);
        border: 1px solid var(--iq-card-border);
    }

    /* API Key Pill */
    .iq-api-pill {
        background: rgba(0, 0, 0, 0.05);
        border-radius: 8px;
        padding: 4px 8px;
        font-family: monospace;
        font-size: 0.8rem;
        color: var(--iq-text-main);
    }

    .dark .iq-api-pill {
        background: rgba(255, 255, 255, 0.1);
    }

    /* Table styling */
    .iq-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 8px;
    }

    .iq-table th {
        color: var(--iq-text-muted);
        font-weight: 500;
        font-size: 0.85rem;
        text-transform: uppercase;
        padding: 0 16px 8px 16px;
        border: none;
    }

    .iq-table td {
        padding: 12px 16px;
        background: var(--iq-pill-bg);
        vertical-align: middle;
    }

    .iq-table tr td:first-child {
        border-top-left-radius: 12px;
        border-bottom-left-radius: 12px;
    }

    .iq-table tr td:last-child {
        border-top-right-radius: 12px;
        border-bottom-right-radius: 12px;
    }

    /* Pagination adjustments for Glassmorphism */
    .pagination {
        margin-bottom: 0;
        gap: 4px;
    }

    .page-item .page-link {
        border-radius: 8px !important;
        background: var(--iq-pill-bg) !important;
        border: 1px solid var(--iq-card-border) !important;
        color: var(--iq-text-main) !important;
        padding: 6px 14px;
        box-shadow: none;
    }

    .page-item.active .page-link {
        background: var(--iq-accent) !important;
        color: white !important;
        border-color: var(--iq-accent) !important;
    }

    .page-item.disabled .page-link {
        opacity: 0.5;
        pointer-events: none;
    }
</style>
@endpush

@section('content')
<div class="iq-wrapper">
    <div class="iq-orb iq-orb-1"></div>
    <div class="iq-orb iq-orb-2"></div>

    <div class="position-relative" style="z-index: 2;">

        <!-- Header Section -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3">
            <div>
                <h1 class="iq-title-main">Welcome, {{ ucfirst($creator) }} 👋</h1>
                <p class="iq-subtitle">Monitor your active strategies and PnL intuitively.</p>
            </div>

            <!-- Strategy Selector -->
            <div>
                <form action="{{ route('strategies.creator', ['creator' => $creator]) }}" method="GET" id="strategy-form">
                    <select name="strategy_id" class="iq-select" onchange="document.getElementById('strategy-form').submit()">
                        @foreach($methods as $method)
                        <option value="{{ $method->id }}" {{ $selectedStrategy && $selectedStrategy->id == $method->id ? 'selected' : '' }}>
                            {{ $method->nama_metode }} ({{ $method->pair }})
                        </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        @if($selectedStrategy)
        <!-- Top Info Pills -->
        <div class="d-flex flex-wrap gap-3 mb-4">
            <div class="iq-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M12 6v6l4 2" />
                </svg>
                Timeframe: {{ $selectedStrategy->tf }}
            </div>
            <div class="iq-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12a9 9 0 0 1-9 9m9-9a9 9 0 0 0-9-9m9 9H3m9 9a9 9 0 0 1-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 0 1 9-9" />
                </svg>
                Exchange: {{ strtoupper($selectedStrategy->exchange) }}
            </div>
            <div class="iq-badge">
                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background: {{ $selectedStrategy->onactive ? '#10b981' : '#f87171' }}"></span>
                {{ $selectedStrategy->onactive ? 'Active' : 'Inactive' }}
            </div>


        </div>

        <!-- Main KPI Row -->
        <div class="row g-4 mb-5">
            <!-- Balance Panel -->
            <div class="col-lg-6">
                <div class="iq-glass iq-kpi-box text-center text-lg-start">
                    <div class="iq-kpi-label">Current Balance / Equity</div>
                    <div class="d-flex align-items-baseline justify-content-center justify-content-lg-start gap-3">
                        <div class="iq-kpi-val iq-val-primary">${{ number_format((float)($selectedStrategy->closing_balance ?? $selectedStrategy->opening_balance ?? 0), 2) }}</div>
                        @if(($selectedStrategy->cagr ?? 0) > 0)
                        <div class="text-success" style="font-weight: 600; font-size: 1.1rem;">
                            +{{ number_format((float)$selectedStrategy->cagr, 2) }}%
                        </div>
                        @endif
                    </div>
                    <div class="mt-2 text-muted" style="font-size: 0.85rem;">
                        Starting Balance: ${{ number_format((float)($selectedStrategy->opening_balance ?? 0), 2) }}
                    </div>
                </div>
            </div>

            <!-- TP / SL Focus -->
            <div class="col-lg-3 col-6">
                <div class="iq-glass iq-kpi-box text-center">
                    <div class="iq-kpi-label" style="color: #10b981;">Target Achieved (TP)</div>
                    <div class="iq-kpi-val iq-val-success">{{ $tpCount }}</div>
                    <div class="mt-2 text-muted" style="font-size: 0.85rem;">
                        {{ number_format((float)$selectedStrategy->winrate, 1) }}% Win Rate
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="iq-glass iq-kpi-box text-center">
                    <div class="iq-kpi-label" style="color: #ef4444;">Stop Hit (SL)</div>
                    <div class="iq-kpi-val iq-val-danger">{{ $slCount }}</div>
                    <div class="mt-2 text-muted" style="font-size: 0.85rem;">
                        {{ number_format((float)$selectedStrategy->drawdown, 1) }}% Drawdown
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Chart -->
        <div class="iq-glass p-4 mb-5">
            <h5 class="fw-bold mb-4" style="font-size: 1.1rem;">Project Overview</h5>
            <div id="equityChart" style="min-height: 300px;"></div>
        </div>

        <!-- Clean Signal Table -->
        <div class="mb-3">
            <h5 class="fw-bold mb-3" style="font-size: 1.1rem;">Recent Movement</h5>
            @if($signals->count() > 0)
            <div class="table-responsive">
                <table class="iq-table" id="signalsTable">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Entry</th>
                            <th>Side</th>
                            <th>Leverage</th>
                            <th>Target TP</th>
                            <th>Stop Loss</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($signals as $signal)
                        <tr>
                            <td><span style="font-weight: 500;">{{ \Carbon\Carbon::parse($signal->datetime)->format('M d, H:i') }}</span></td>
                            <td>${{ number_format((float)$signal->price_entry, 2) }}</td>
                            <td>
                                @if(strtolower($signal->jenis) == 'long' || strtolower($signal->jenis) == 'buy')
                                <div style="display:inline-flex; align-items:center; color:#10b981; font-weight:600; gap:4px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                                        <polyline points="17 6 23 6 23 12" />
                                    </svg>
                                    LONG
                                </div>
                                @elseif(strtolower($signal->jenis) == 'short' || strtolower($signal->jenis) == 'sell')
                                <div style="display:inline-flex; align-items:center; color:#ef4444; font-weight:600; gap:4px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="23 18 13.5 8.5 8.5 13.5 1 6" />
                                        <polyline points="17 18 23 18 23 12" />
                                    </svg>
                                    SHORT
                                </div>
                                @else
                                <span class="text-muted">{{ strtoupper($signal->jenis ?? '-') }}</span>
                                @endif
                            </td>
                            <td><span style="background:var(--iq-card-border); padding:2px 8px; border-radius:4px; font-size:0.8rem;">{{ $signal->leverage }}x</span></td>
                            <td style="color: #10b981; font-weight:500;">${{ number_format((float)$signal->target_tp, 2) }}</td>
                            <td style="color: #ef4444; font-weight:500;">${{ number_format((float)$signal->target_sl, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            <div class="mt-4 d-flex justify-content-end">
                {{ $signals->appends(request()->query())->links('pagination::bootstrap-5') }}
            </div>
            @else
            <div class="iq-glass p-4 text-center text-muted">
                No recent movement detected for this strategy.
            </div>
            @endif
        </div>

        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if($selectedStrategy && count($chartData) > 0)
        var chartData = @json($chartData);
        var tpPoints = @json($tpPoints ?? []);
        var slPoints = @json($slPoints ?? []);

        var options = {
            series: [{
                name: 'Equity',
                type: 'area',
                data: chartData
            }, {
                name: 'Target Achieved',
                type: 'scatter',
                data: tpPoints
            }, {
                name: 'Stop Hit',
                type: 'scatter',
                data: slPoints
            }],
            chart: {
                type: 'line',
                height: 300,
                background: 'transparent',
                toolbar: {
                    show: false
                },
                fontFamily: 'inherit'
            },
            theme: {
                mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light'
            },
            colors: ['#10b981', '#10b981', '#ef4444'],
            fill: {
                type: ['gradient', 'solid', 'solid'],
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.4,
                    opacityTo: 0.01,
                    stops: [0, 100]
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth',
                width: [3, 0, 0] // Only area has line
            },
            markers: {
                size: [0, 6, 6], // Dots for TP and SL
                strokeWidth: 1,
                hover: {
                    size: 8
                }
            },
            xaxis: {
                type: 'datetime',
                labels: {
                    datetimeUTC: false,
                    style: {
                        colors: 'var(--iq-text-muted)'
                    }
                },
                axisBorder: {
                    show: false
                },
                axisTicks: {
                    show: false
                }
            },
            yaxis: {
                labels: {
                    formatter: function(value) {
                        return "$" + value.toFixed(2);
                    },
                    style: {
                        colors: 'var(--iq-text-muted)'
                    }
                }
            },
            grid: {
                borderColor: 'var(--iq-card-border)',
                strokeDashArray: 4,
                yaxis: {
                    lines: {
                        show: true
                    }
                },
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            tooltip: {
                theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                x: {
                    format: 'dd MMM yyyy HH:mm'
                }
            }
        };

        var chart = new ApexCharts(document.querySelector("#equityChart"), options);
        chart.render();

        window.addEventListener('theme-toggle', () => {
            setTimeout(() => {
                let isDark = document.documentElement.classList.contains('dark');
                chart.updateOptions({
                    theme: {
                        mode: isDark ? 'dark' : 'light'
                    }
                });
            }, 100);
        });
        @elseif($selectedStrategy)
        document.querySelector("#equityChart").innerHTML = '<div class="d-flex justify-content-center align-items-center h-100" style="color:var(--iq-text-muted);">No order history available for chart.</div>';
        @endif
    });
</script>
@endsection