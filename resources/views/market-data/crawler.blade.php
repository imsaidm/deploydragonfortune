@extends('layouts.app')

@section('title', 'Market Data Crawler | DragonFortune')

@push('head')
<style>
    /* ── Page Shell ── */
    .mc-page {
        padding: 1.5rem 1.75rem;
        max-width: 1100px;
        margin: 0 auto;
        font-family: -apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", sans-serif;
    }

    /* ── Header ── */
    .mc-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 1.25rem;
        gap: 1rem;
    }
    .mc-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--foreground, #111827);
        letter-spacing: -0.01em;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
    }
    .mc-subtitle {
        font-size: 0.78rem;
        color: var(--muted-foreground, #6b7280);
        margin: 0.2rem 0 0;
    }
    .mc-live-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.68rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--muted-foreground, #6b7280);
        border: 1px solid var(--border, #e5e7eb);
        background: var(--card, #fff);
        padding: 0.3rem 0.65rem;
        border-radius: 5px;
        cursor: pointer;
        transition: background 0.12s;
    }
    .mc-live-badge:hover {
        background: var(--muted, #f3f4f6);
    }
    .mc-live-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #10b981;
        animation: mc-ping 1.5s ease-in-out infinite;
    }
    @@keyframes mc-ping {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(1.3); }
    }

    /* ── Alert Boxes ── */
    .mc-alert {
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        padding: 0.75rem 1rem;
        border-radius: 6px;
        font-size: 0.82rem;
        margin-bottom: 1.25rem;
        border: 1px solid;
    }
    .mc-alert-success {
        background: #f0fdf4;
        border-color: #bbf7d0;
        color: #166534;
    }
    .mc-alert-error {
        background: #fff1f2;
        border-color: #fecdd3;
        color: #9f1239;
    }
    .mc-alert ul {
        margin: 0.35rem 0 0 1.1rem;
        padding: 0;
    }

    /* ── Card ── */
    .mc-card {
        background: var(--card, #fff);
        border: 1px solid var(--border, #e5e7eb);
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 1.25rem;
    }
    .mc-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.65rem 1.1rem;
        border-bottom: 1px solid var(--border, #e5e7eb);
        background: var(--muted, #f9fafb);
    }
    .mc-card-title {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--muted-foreground, #6b7280);
        margin: 0;
    }
    .mc-card-body {
        padding: 1.1rem 1.25rem;
    }

    /* ── Form Grid ── */
    .mc-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 0.85rem 1.25rem;
    }
    @@media (max-width: 700px) {
        .mc-form-grid { grid-template-columns: 1fr 1fr; }
    }
    @@media (max-width: 480px) {
        .mc-form-grid { grid-template-columns: 1fr; }
    }
    .mc-field { display: flex; flex-direction: column; gap: 0.3rem; }
    .mc-label {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--muted-foreground, #6b7280);
    }
    .mc-input, .mc-select {
        width: 100%;
        padding: 0.45rem 0.7rem;
        font-size: 0.82rem;
        border: 1px solid var(--border, #d1d5db);
        border-radius: 5px;
        background: var(--card, #fff);
        color: var(--foreground, #111827);
        outline: none;
        transition: border-color 0.12s, box-shadow 0.12s;
        box-sizing: border-box;
        appearance: none;
        -webkit-appearance: none;
    }
    .mc-input:focus, .mc-select:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 2.5px rgba(99, 102, 241, 0.12);
    }
    .mc-select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.6rem center;
        padding-right: 1.8rem;
        cursor: pointer;
    }
    .mc-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }

    /* ── Form Footer ── */
    .mc-form-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border, #e5e7eb);
        gap: 1rem;
        flex-wrap: wrap;
    }
    .mc-meta-row {
        display: flex;
        gap: 1.25rem;
    }
    .mc-meta-item {
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
    }
    .mc-meta-key {
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--muted-foreground, #9ca3af);
    }
    .mc-meta-val {
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--foreground, #374151);
    }
    .mc-meta-item + .mc-meta-item {
        border-left: 1px solid var(--border, #e5e7eb);
        padding-left: 1.25rem;
    }

    /* ── Submit Button ── */
    .mc-btn-submit {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.5rem 1.25rem;
        font-size: 0.82rem;
        font-weight: 600;
        color: #fff;
        background: #18181b;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background 0.12s, opacity 0.12s;
        white-space: nowrap;
    }
    .mc-btn-submit:hover { background: #27272a; }
    .mc-btn-submit:disabled { opacity: 0.55; cursor: not-allowed; }

    /* ── Table ── */
    .mc-table-wrap { overflow-x: auto; }
    .mc-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
    }
    .mc-table thead th {
        padding: 0.5rem 0.85rem;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--muted-foreground, #6b7280);
        border-bottom: 1px solid var(--border, #e5e7eb);
        background: var(--muted, #f9fafb);
        white-space: nowrap;
    }
    .mc-table thead th:last-child { text-align: right; }
    .mc-table tbody tr {
        border-bottom: 1px solid var(--border, #f3f4f6);
        transition: background 0.08s;
    }
    .mc-table tbody tr:hover { background: var(--muted, #f9fafb); }
    .mc-table tbody tr:last-child { border-bottom: none; }
    .mc-table td {
        padding: 0.55rem 0.85rem;
        color: var(--foreground, #374151);
        vertical-align: middle;
    }
    .mc-table td:last-child { text-align: right; }
    .mc-table .mc-empty {
        text-align: center;
        padding: 2.5rem;
        color: var(--muted-foreground, #9ca3af);
        font-size: 0.82rem;
    }

    /* ── Badges ── */
    .mc-badge {
        display: inline-block;
        padding: 1px 7px;
        border-radius: 4px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border: 1px solid;
    }
    .mc-badge-binance { background: #fffbeb; color: #92400e; border-color: #fde68a; }
    .mc-badge-bybit   { background: #fff7ed; color: #9a3412; border-color: #fed7aa; }
    .mc-badge-spot    { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
    .mc-badge-future  { background: #eef2ff; color: #3730a3; border-color: #c7d2fe; }

    /* ── Range column ── */
    .mc-range {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.73rem;
        color: var(--muted-foreground, #6b7280);
    }
    .mc-range-row { display: flex; align-items: center; gap: 0.4rem; }
    .mc-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
    .mc-dot-old { background: #d1d5db; }
    .mc-dot-new { background: #10b981; }

    /* ── Candles count ── */
    .mc-candles {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-weight: 700;
        font-size: 0.82rem;
        color: #4f46e5;
    }

    /* ── Footer note ── */
    .mc-footnote {
        text-align: center;
        font-size: 0.7rem;
        color: var(--muted-foreground, #9ca3af);
        margin-top: 0.75rem;
    }

    /* ── Spinner ── */
    @@keyframes mc-spin { to { transform: rotate(360deg); } }
    .mc-spinner {
        display: inline-block;
        width: 13px;
        height: 13px;
        border: 2px solid rgba(255,255,255,0.35);
        border-top-color: #fff;
        border-radius: 50%;
        animation: mc-spin 0.7s linear infinite;
    }
</style>
@endpush

@section('content')
<div class="mc-page">

    {{-- ── Header ── --}}
    <div class="mc-header">
        <div>
            <h1 class="mc-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#6366f1">
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                </svg>
                Market Data Crawler
            </h1>
            <p class="mc-subtitle">Fetch historical OHLCV candles from Binance / Bybit via CCXT and store locally.</p>
        </div>
        <button type="button" class="mc-live-badge" onclick="window.location.reload()" title="Click to refresh data">
            <span class="mc-live-dot"></span>
            Refresh Data
        </button>
    </div>

    {{-- ── Alerts ── --}}
    @if(session('success'))
    <div class="mc-alert mc-alert-success">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;margin-top:1px"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <div><strong>Dispatched:</strong> {{ session('success') }}</div>
    </div>
    @endif

    @if(isset($errors) && $errors->any())
    <div class="mc-alert mc-alert-error">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <div>
            <strong>Validation errors:</strong>
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    </div>
    @endif

    {{-- ── Config Card ── --}}
    <div class="mc-card">
        <div class="mc-card-header">
            <span class="mc-card-title">Crawl Configuration</span>
        </div>
        <div class="mc-card-body">
            <form method="POST" action="{{ route('market-data.store') }}" id="crawlerForm">
                @csrf
                <div class="mc-form-grid">

                    <div class="mc-field">
                        <label class="mc-label" for="exchange">Exchange</label>
                        <select id="exchange" name="exchange" class="mc-select" required>
                            <option value="binance" {{ old('exchange','binance')==='binance'?'selected':'' }}>Binance</option>
                            <option value="bybit"   {{ old('exchange')==='bybit'?'selected':'' }}>Bybit</option>
                        </select>
                    </div>

                    <div class="mc-field">
                        <label class="mc-label" for="type">Market Type</label>
                        <select id="type" name="type" class="mc-select" required>
                            <option value="spot"   {{ old('type','spot')==='spot'?'selected':'' }}>Spot</option>
                            <option value="future" {{ old('type')==='future'?'selected':'' }}>Future (USDⓈ-M)</option>
                        </select>
                    </div>

                    <div class="mc-field">
                        <label class="mc-label" for="symbol">Symbol</label>
                        <input id="symbol" name="symbol" type="text"
                            class="mc-input mc-mono"
                            value="{{ old('symbol', 'BTC/USDT') }}"
                            placeholder="BTC/USDT" required />
                    </div>

                    <div class="mc-field">
                        <label class="mc-label" for="timeframe">Timeframe</label>
                        <select id="timeframe" name="timeframe" class="mc-select mc-mono" required>
                            @foreach(['1m','3m','5m','15m','30m','1h','4h','1d'] as $tf)
                            <option value="{{ $tf }}" {{ old('timeframe','1h')===$tf?'selected':'' }}>{{ $tf }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mc-field">
                        <label class="mc-label" for="start_date">Start Date</label>
                        <input id="start_date" name="start_date" type="date"
                            class="mc-input mc-mono"
                            value="{{ old('start_date', now()->subDays(30)->format('Y-m-d')) }}" required />
                    </div>

                    <div class="mc-field">
                        <label class="mc-label" for="end_date">End Date</label>
                        <input id="end_date" name="end_date" type="date"
                            class="mc-input mc-mono"
                            value="{{ old('end_date', now()->format('Y-m-d')) }}" required />
                    </div>

                </div>

                <div class="mc-form-footer">
                    <div class="mc-meta-row">
                        <div class="mc-meta-item">
                            <span class="mc-meta-key">Queue</span>
                            <span class="mc-meta-val">database · crawler</span>
                        </div>
                        <div class="mc-meta-item">
                            <span class="mc-meta-key">Throttle</span>
                            <span class="mc-meta-val">2 req / sec</span>
                        </div>
                        <div class="mc-meta-item">
                            <span class="mc-meta-key">Duplicates</span>
                            <span class="mc-meta-val">Auto-upsert</span>
                        </div>
                    </div>
                    <button type="submit" class="mc-btn-submit" id="submitBtn">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Dispatch Crawl Job
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Datasets Table ── --}}
    <div class="mc-card">
        <div class="mc-card-header">
            <span class="mc-card-title">Crawled Datasets</span>
            <span style="font-size:0.68rem;color:var(--muted-foreground,#9ca3af);">{{ $datasets->count() }} {{ $datasets->count() === 1 ? 'dataset' : 'datasets' }}</span>
        </div>
        <div class="mc-table-wrap">
            <table class="mc-table">
                <thead>
                    <tr>
                        <th>Exchange</th>
                        <th>Type</th>
                        <th>Symbol</th>
                        <th>TF</th>
                        <th>Candles</th>
                        <th>History Range</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($datasets as $d)
                    <tr>
                        <td>
                            <span class="mc-badge {{ $d->exchange === 'binance' ? 'mc-badge-binance' : 'mc-badge-bybit' }}">
                                {{ $d->exchange }}
                            </span>
                        </td>
                        <td>
                            <span class="mc-badge {{ $d->type === 'spot' ? 'mc-badge-spot' : 'mc-badge-future' }}">
                                {{ $d->type }}
                            </span>
                        </td>
                        <td style="font-weight:600;font-family:ui-monospace,monospace;font-size:0.8rem;">{{ $d->symbol }}</td>
                        <td style="font-family:ui-monospace,monospace;font-size:0.75rem;color:var(--muted-foreground,#6b7280);">{{ $d->timeframe }}</td>
                        <td><span class="mc-candles">{{ number_format($d->total_candles) }}</span></td>
                        <td>
                            <div class="mc-range">
                                <div class="mc-range-row">
                                    <span class="mc-dot mc-dot-old"></span>
                                    {{ \Carbon\Carbon::createFromTimestampMs($d->oldest_ts)->format('d M Y H:i') }}
                                </div>
                                <div class="mc-range-row">
                                    <span class="mc-dot mc-dot-new"></span>
                                    {{ \Carbon\Carbon::createFromTimestampMs($d->newest_ts)->format('d M Y H:i') }}
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="mc-empty">
                            No datasets yet. Dispatch a crawl job above to get started.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="mc-footnote">Page auto-refreshes every 30 seconds to reflect crawl progress.</p>

</div>

<script>
    document.getElementById('crawlerForm').addEventListener('submit', function () {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="mc-spinner"></span> Dispatching…';
    });
</script>
@endsection
