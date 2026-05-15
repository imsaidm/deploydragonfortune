@extends('layouts.app')

@section('title', 'Strategy - ' . ucfirst($creator))

@push('head')
<script src="https://unpkg.com/lightweight-charts@4.1.3/dist/lightweight-charts.standalone.production.js"></script>
<style>
    :root {
        --sd-bg: #f6f8fb;
        --sd-surface: #ffffff;
        --sd-soft: #f1f5f9;
        --sd-border: #e2e8f0;
        --sd-text: #0f172a;
        --sd-muted: #64748b;
        --sd-green: #16a34a;
        --sd-red: #e11d48;
        --sd-blue: #2563eb;
        --sd-amber: #f59e0b;
        --sd-shadow: 0 18px 50px rgba(15, 23, 42, .08);
    }

    .dark {
        --sd-bg: #0b1120;
        --sd-surface: #111827;
        --sd-soft: #172033;
        --sd-border: #243044;
        --sd-text: #e5eefb;
        --sd-muted: #94a3b8;
        --sd-shadow: 0 18px 50px rgba(0, 0, 0, .28);
    }

    body {
        background: var(--sd-bg);
    }

    .strategy-shell {
        color: var(--sd-text);
        padding: 24px;
    }

    .strategy-topbar {
        align-items: flex-start;
        display: flex;
        gap: 18px;
        justify-content: space-between;
        margin-bottom: 18px;
    }

    .strategy-title {
        font-size: 1.85rem;
        font-weight: 850;
        letter-spacing: 0;
        line-height: 1.1;
        margin: 0;
    }

    .strategy-subtitle {
        color: var(--sd-muted);
        font-size: .93rem;
        margin: 6px 0 0;
    }

    .strategy-select {
        appearance: none;
        background: var(--sd-surface);
        border: 1px solid var(--sd-border);
        border-radius: 8px;
        color: var(--sd-text);
        min-width: 290px;
        padding: 10px 38px 10px 12px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
    }

    .strategy-card {
        background: var(--sd-surface);
        border: 1px solid var(--sd-border);
        border-radius: 8px;
        box-shadow: var(--sd-shadow);
    }

    .metric-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: 1.35fr repeat(4, minmax(140px, 1fr));
        margin-bottom: 14px;
    }

    .metric-card {
        min-height: 112px;
        padding: 17px;
    }

    .metric-label {
        color: var(--sd-muted);
        font-size: .72rem;
        font-weight: 750;
        letter-spacing: .07em;
        text-transform: uppercase;
    }

    .metric-value {
        font-size: 1.65rem;
        font-weight: 850;
        line-height: 1.15;
        margin-top: 8px;
    }

    .metric-note {
        color: var(--sd-muted);
        font-size: .82rem;
        margin-top: 8px;
    }

    .metric-positive {
        color: var(--sd-green);
    }

    .metric-negative {
        color: var(--sd-red);
    }

    .chart-card {
        padding: 0;
        overflow: hidden;
    }

    .chart-toolbar {
        align-items: center;
        border-bottom: 1px solid var(--sd-border);
        display: flex;
        gap: 12px;
        justify-content: space-between;
        padding: 14px 16px;
    }

    .chart-heading {
        align-items: center;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .chart-title {
        font-size: 1rem;
        font-weight: 800;
    }

    .market-chip,
    .source-chip {
        align-items: center;
        background: var(--sd-soft);
        border: 1px solid var(--sd-border);
        border-radius: 999px;
        color: var(--sd-muted);
        display: inline-flex;
        font-size: .78rem;
        font-weight: 700;
        gap: 6px;
        padding: 5px 9px;
    }

    .live-dot {
        background: var(--sd-green);
        border-radius: 999px;
        box-shadow: 0 0 0 5px rgba(22, 163, 74, .12);
        height: 8px;
        width: 8px;
    }

    .tf-group {
        background: var(--sd-soft);
        border: 1px solid var(--sd-border);
        border-radius: 8px;
        display: inline-flex;
        gap: 4px;
        padding: 4px;
    }

    .tf-button {
        background: transparent;
        border: 0;
        border-radius: 6px;
        color: var(--sd-muted);
        font-size: .82rem;
        font-weight: 750;
        min-width: 42px;
        padding: 7px 9px;
    }

    .tf-button.active {
        background: var(--sd-text);
        color: var(--sd-surface);
    }

    .chart-legend {
        align-items: center;
        color: var(--sd-muted);
        display: flex;
        flex-wrap: wrap;
        gap: 13px;
        font-size: .78rem;
        padding: 10px 16px;
    }

    .legend-item {
        align-items: center;
        display: inline-flex;
        gap: 6px;
    }

    .legend-line,
    .legend-dot {
        display: inline-block;
    }

    .legend-line {
        border-radius: 999px;
        height: 3px;
        width: 22px;
    }

    .legend-dot {
        border-radius: 999px;
        height: 9px;
        width: 9px;
    }

    #marketChart {
        height: 570px;
        width: 100%;
    }

    .below-chart {
        display: grid;
        gap: 14px;
        grid-template-columns: minmax(0, 1fr) 360px;
        margin-top: 14px;
    }

    .panel-card {
        padding: 18px;
    }

    .panel-title {
        font-size: 1rem;
        font-weight: 800;
        margin: 0 0 14px;
    }

    .strategy-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }

    .strategy-table th {
        border-bottom: 1px solid var(--sd-border);
        color: var(--sd-muted);
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .06em;
        padding: 0 12px 10px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .strategy-table td {
        border-bottom: 1px solid var(--sd-border);
        color: var(--sd-text);
        font-size: .88rem;
        padding: 13px 12px;
        vertical-align: middle;
    }

    .strategy-table tbody tr {
        transition: background .15s ease;
    }

    .strategy-table tbody tr:hover {
        background: var(--sd-soft);
        cursor: pointer;
    }

    .side-badge,
    .result-badge {
        border-radius: 999px;
        display: inline-flex;
        font-size: .76rem;
        font-weight: 800;
        padding: 4px 8px;
    }

    .side-long {
        background: rgba(22, 163, 74, .12);
        color: var(--sd-green);
    }

    .side-short {
        background: rgba(225, 29, 72, .12);
        color: var(--sd-red);
    }

    .result-open {
        background: rgba(37, 99, 235, .12);
        color: var(--sd-blue);
    }

    .result-win {
        background: rgba(22, 163, 74, .12);
        color: var(--sd-green);
    }

    .result-loss {
        background: rgba(225, 29, 72, .12);
        color: var(--sd-red);
    }

    .inspector-empty,
    .inspector-row {
        border-bottom: 1px solid var(--sd-border);
        padding: 10px 0;
    }

    .inspector-label {
        color: var(--sd-muted);
        font-size: .76rem;
        font-weight: 750;
        text-transform: uppercase;
    }

    .inspector-value {
        font-size: .98rem;
        font-weight: 800;
        margin-top: 3px;
    }

    .inspector-empty {
        color: var(--sd-muted);
        font-size: .9rem;
    }

    .qc-link {
        border: 1px solid var(--sd-border);
        border-radius: 8px;
        color: var(--sd-text);
        display: inline-flex;
        font-size: .82rem;
        font-weight: 750;
        padding: 8px 10px;
        text-decoration: none;
    }

    .qc-link:hover {
        color: var(--sd-blue);
    }

    .pagination-wrap {
        margin-top: 12px;
    }

    .page-note {
        color: var(--sd-muted);
        font-size: .83rem;
        margin-top: 12px;
    }

    @media (max-width: 1180px) {
        .metric-grid,
        .below-chart {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 760px) {
        .strategy-shell {
            padding: 14px;
        }

        .strategy-topbar,
        .chart-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .strategy-select,
        .metric-grid,
        .below-chart {
            grid-template-columns: 1fr;
            min-width: 0;
            width: 100%;
        }

        #marketChart {
            height: 470px;
        }
    }
</style>
@endpush

@section('content')
<div class="strategy-shell">
    <div class="strategy-topbar">
        <div>
            <h1 class="strategy-title">{{ ucfirst($creator) }} Strategy Desk</h1>
            <p class="strategy-subtitle">
                Live {{ $strategyMeta['symbol'] }} chart with QuantConnect signals, exchange candles, TP/SL exits, and account equity.
            </p>
        </div>

        <form action="{{ route('strategies.creator', ['creator' => $creator]) }}" method="GET">
            <select name="strategy_id" class="strategy-select" onchange="this.form.submit()" aria-label="Select strategy">
                @foreach($methods as $m)
                <option value="{{ $m->id }}" {{ $selectedStrategy->id == $m->id ? 'selected' : '' }}>
                    {{ $m->nama_metode }} ({{ $m->pair }})
                </option>
                @endforeach
            </select>
        </form>
    </div>

    @if($selectedStrategy)
    <div class="metric-grid">
        <div class="strategy-card metric-card">
            <div class="metric-label">Equity balance</div>
            <div class="metric-value metric-positive">${{ number_format($selectedStrategy->closing_balance ?: $selectedStrategy->opening_balance, 2) }}</div>
            <div class="metric-note">Started at ${{ number_format($selectedStrategy->opening_balance, 2) }}</div>
        </div>
        <div class="strategy-card metric-card">
            <div class="metric-label">Live price</div>
            <div class="metric-value" id="livePrice">Loading</div>
            <div class="metric-note" id="liveStatus">{{ strtoupper($strategyMeta['exchange']) }} {{ strtoupper($strategyMeta['market_type']) }}</div>
        </div>
        <div class="strategy-card metric-card">
            <div class="metric-label">Win rate</div>
            <div class="metric-value">{{ number_format($strategyMeta['metrics']['winrate'], 1) }}%</div>
            <div class="metric-note">CAGR {{ number_format($strategyMeta['metrics']['cagr'], 1) }}%</div>
        </div>
        <div class="strategy-card metric-card">
            <div class="metric-label">Profit / loss exits</div>
            <div class="metric-value"><span class="metric-positive">{{ $tpCount }}</span> / <span class="metric-negative">{{ $slCount }}</span></div>
            <div class="metric-note">{{ $activeTrades }} active signal{{ $activeTrades === 1 ? '' : 's' }}</div>
        </div>
        <div class="strategy-card metric-card">
            <div class="metric-label">QC timeframe</div>
            <div class="metric-value">{{ strtoupper($strategyMeta['base_tf']) }}</div>
            <div class="metric-note">Chart choices stay at or below strategy TF</div>
        </div>
    </div>

    <div class="strategy-card chart-card">
        <div class="chart-toolbar">
            <div class="chart-heading">
                <span class="chart-title">{{ $strategyMeta['symbol'] }} Execution Chart</span>
                <span class="market-chip"><span class="live-dot"></span><span id="streamLabel">Connecting stream</span></span>
                <span class="source-chip" id="dataSource">Candles: loading</span>
            </div>
            <div class="tf-group" aria-label="Timeframes">
                @foreach($timeframeOptions as $tf)
                <button type="button" class="tf-button {{ $tf === $strategyMeta['base_tf'] ? 'active' : '' }}" data-tf="{{ $tf }}">{{ $tf }}</button>
                @endforeach
            </div>
        </div>

        <div class="chart-legend">
            <span class="legend-item"><span class="legend-dot" style="background:#16a34a"></span>Entry long dot</span>
            <span class="legend-item"><span class="legend-dot" style="background:#ef4444"></span>Entry short dot</span>
            <span class="legend-item"><span class="legend-line" style="background:#22c55e"></span>Equity curve</span>
            <span class="legend-item"><span class="legend-line" style="background:#2563eb"></span>Entry price</span>
            <span class="legend-item"><span class="legend-line" style="background:#22c55e"></span>TP target</span>
            <span class="legend-item"><span class="legend-line" style="background:#f43f5e"></span>SL target</span>
        </div>

        <div id="marketChart"></div>
    </div>

    <div class="below-chart">
        <div class="strategy-card panel-card">
            <h2 class="panel-title">Signal History</h2>
            @if($signals->count() > 0)
            <div class="table-responsive">
                <table class="strategy-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Entry</th>
                            <th>Side</th>
                            <th>TP / SL</th>
                            <th>Exit</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($signals as $sig)
                        @php
                            $entry = (float) $sig->price_entry;
                            $exit = (float) $sig->actual_price_exit;
                            $tpVal = (float) $sig->target_tp;
                            $slVal = (float) $sig->target_sl;
                            $isLong = in_array(strtolower($sig->jenis), ['long', 'buy']);
                            $isExited = $exit > 0;
                            $pnl = $entry > 0 ? ($isLong ? ($exit - $entry) : ($entry - $exit)) : 0;
                            $pnlPct = $entry > 0 ? ($pnl / $entry) * 100 * ($sig->leverage ?: 1) : 0;
                            $isWin = $pnl >= 0;
                        @endphp
                        <tr data-trade-id="{{ $sig->id }}">
                            <td><strong>{{ \Carbon\Carbon::parse($sig->datetime)->format('M d, H:i') }}</strong></td>
                            <td>${{ number_format($entry, 2) }} <span style="color:var(--sd-muted)">({{ $sig->leverage ?: 1 }}x)</span></td>
                            <td>
                                <span class="side-badge {{ $isLong ? 'side-long' : 'side-short' }}">{{ $isLong ? 'LONG' : 'SHORT' }}</span>
                            </td>
                            <td>
                                <div style="color:var(--sd-green)">TP ${{ number_format($tpVal, 2) }}</div>
                                <div style="color:var(--sd-red)">SL ${{ number_format($slVal, 2) }}</div>
                            </td>
                            <td>{{ $isExited ? '$' . number_format($exit, 2) : '-' }}</td>
                            <td>
                                @if($isExited)
                                <span class="result-badge {{ $isWin ? 'result-win' : 'result-loss' }}">{{ $isWin ? 'TP' : 'SL' }} {{ number_format($pnlPct, 2) }}%</span>
                                @else
                                <span class="result-badge result-open">ACTIVE</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $signals->appends(request()->query())->links() }}</div>
            @else
            <div class="inspector-empty">No entry signals found for this strategy yet.</div>
            @endif
        </div>

        <aside class="strategy-card panel-card">
            <h2 class="panel-title">Trade Inspector</h2>
            <div id="tradeInspector">
                <div class="inspector-empty">Click an entry dot, exit arrow, or history row to inspect the trade.</div>
            </div>
            @if(!empty($strategyMeta['quantconnect_url']))
            <div style="margin-top:14px">
                <a class="qc-link" href="{{ $strategyMeta['quantconnect_url'] }}" target="_blank" rel="noopener noreferrer">Open QuantConnect backtest</a>
            </div>
            @endif
            @if(!empty($strategyMeta['description']))
            <p class="page-note">{{ $strategyMeta['description'] }}</p>
            @endif
        </aside>
    </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    window.strategyDashboard = {
        candleEndpoint: @json(route('api.strategies.candles', ['strategy' => $selectedStrategy->id])),
        strategy: @json($strategyMeta),
        timeframes: @json($timeframeOptions),
        defaultTf: @json(in_array($strategyMeta['base_tf'], $timeframeOptions, true) ? $strategyMeta['base_tf'] : end($timeframeOptions)),
        equity: @json($chartData),
        markers: @json($signalMarkers ?? []),
        trades: @json($tradesList ?? []),
    };
</script>
<script src="{{ asset('js/strategy-dashboard.js') }}?v={{ filemtime(public_path('js/strategy-dashboard.js')) }}"></script>
@endsection
