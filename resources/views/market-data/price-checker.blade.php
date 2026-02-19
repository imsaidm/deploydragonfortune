@extends('layouts.app')

@section('title', 'Price Level Checker | DragonFortune')

@push('head')
{{-- jQuery + DataTables --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script>
    var $jq = jQuery.noConflict(true);
</script>
{{-- DataTables CSS --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<style>
    /* ── Shell ── */
    .pc-page {
        padding: 1.5rem 1.75rem;
        max-width: 960px;
        margin: 0 auto;
        font-family: -apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", sans-serif;
    }

    /* ── Header ── */
    .pc-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 1.25rem;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .pc-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--foreground, #111827);
        letter-spacing: -0.01em;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
    }
    .pc-subtitle {
        font-size: 0.78rem;
        color: var(--muted-foreground, #6b7280);
        margin: 0.2rem 0 0;
    }
    .pc-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #4b5563;
        border: 1px solid #e5e7eb;
        background: #fff;
        padding: 0.3rem 0.75rem;
        border-radius: 5px;
        cursor: pointer;
        transition: background 0.12s;
        text-decoration: none;
    }
    .pc-back-btn:hover { background: #f9fafb; }

    /* ── Card ── */
    .pc-card {
        background: var(--card, #fff);
        border: 1px solid var(--border, #e5e7eb);
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 1.25rem;
    }
    .pc-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.65rem 1.1rem;
        border-bottom: 1px solid var(--border, #e5e7eb);
        background: var(--muted, #f9fafb);
    }
    .pc-card-title {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--muted-foreground, #6b7280);
        margin: 0;
    }
    .pc-card-body { padding: 1.1rem 1.25rem; }

    /* ── Alert ── */
    .pc-alert {
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        padding: 0.7rem 0.9rem;
        border-radius: 6px;
        font-size: 0.8rem;
        margin-bottom: 1.1rem;
        border: 1px solid #fecdd3;
        background: #fff1f2;
        color: #9f1239;
    }
    .pc-alert ul { margin: 0.3rem 0 0 1rem; padding: 0; }

    /* ── Form Grid ── */
    .pc-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.8rem 1rem; }
    .pc-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem 1rem; }
    @media (max-width: 640px) { .pc-grid-3, .pc-grid-2 { grid-template-columns: 1fr; } }

    .pc-field { display: flex; flex-direction: column; gap: 0.28rem; }
    .pc-label {
        font-size: 0.63rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--muted-foreground, #71717a);
    }
    .pc-input, .pc-select {
        width: 100%;
        padding: 0.42rem 0.65rem;
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
    .pc-input:focus, .pc-select:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 2.5px rgba(99,102,241,0.12);
    }
    .pc-input.mono, .pc-select.mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.8rem;
    }
    .pc-select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.6rem center;
        padding-right: 1.8rem;
        cursor: pointer;
    }

    /* ── Section spacers ── */
    .pc-row { margin-bottom: 0.85rem; }
    .pc-divider {
        height: 1px;
        background: var(--border, #e5e7eb);
        margin: 1rem 0;
    }

    /* ── Segmented Control (Long / Short) ── */
    .pc-seg {
        display: flex;
        border: 1px solid var(--border, #d1d5db);
        border-radius: 5px;
        overflow: hidden;
    }
    .pc-seg-btn {
        flex: 1;
        padding: 0.42rem 0;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        border: none;
        background: transparent;
        color: #9ca3af;
        cursor: pointer;
        transition: background 0.1s, color 0.1s;
        border-right: 1px solid var(--border, #d1d5db);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
    }
    .pc-seg-btn:last-child { border-right: none; }
    .pc-seg-btn.long.active  { background: #14532d; color: #fff; }
    .pc-seg-btn.short.active { background: #7f1d1d; color: #fff; }
    .pc-seg-btn:not(.active):hover { background: #f9fafb; color: #374151; }

    /* ── Submit Button ── */
    .pc-btn-submit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        width: 100%;
        padding: 0.55rem;
        font-size: 0.85rem;
        font-weight: 600;
        color: #fff;
        background: #18181b;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background 0.12s, opacity 0.12s;
        letter-spacing: 0.01em;
    }
    .pc-btn-submit:hover   { background: #27272a; }
    .pc-btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

    /* ── Outcome Banner ── */
    .pc-outcome {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        padding: 0.85rem 1.1rem;
        border-radius: 6px;
        border: 1px solid;
        margin-bottom: 1.1rem;
    }
    .pc-outcome.tp   { background: #f0fdf4; border-color: #86efac; }
    .pc-outcome.sl   { background: #fff1f2; border-color: #fecdd3; }
    .pc-outcome.open { background: #eff6ff; border-color: #bfdbfe; }
    .pc-outcome-icon { font-size: 1.5rem; line-height: 1; flex-shrink: 0; margin-top: 0.1rem; }
    .pc-outcome-label {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.25rem;
    }
    .pc-outcome.tp   .pc-outcome-label { color: #166534; }
    .pc-outcome.sl   .pc-outcome-label { color: #9f1239; }
    .pc-outcome.open .pc-outcome-label { color: #1e40af; }
    .pc-outcome-detail {
        font-size: 0.8rem;
        color: #4b5563;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        line-height: 1.5;
    }

    /* ── Stats Row ── */
    .pc-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.65rem;
        margin-bottom: 1.1rem;
    }
    @media (max-width: 480px) { .pc-stats { grid-template-columns: 1fr; } }
    .pc-stat {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 0.6rem 0.85rem;
    }
    .pc-stat-label {
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #9ca3af;
        margin-bottom: 0.2rem;
    }
    .pc-stat-value {
        font-size: 0.92rem;
        font-weight: 700;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }

    /* ── Table (DataTables Overrides) ── */
    .pc-table-wrap { padding: 0.5rem 0.2rem; }
    .pc-table {
        width: 100% !important;
        border-collapse: collapse;
        font-size: 0.78rem;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }
    .pc-table thead th {
        padding: 0.45rem 0.85rem;
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--muted-foreground, #6b7280);
        border-bottom: 1px solid var(--border, #e5e7eb);
        background: var(--muted, #f9fafb);
        white-space: nowrap;
    }
    /* DataTables specific UI tweaks */
    div.dataTables_wrapper div.dataTables_length label,
    div.dataTables_wrapper div.dataTables_filter label,
    div.dataTables_wrapper div.dataTables_info {
        font-size: 0.72rem;
        color: #71717a;
        margin-bottom: 0.5rem;
    }
    div.dataTables_wrapper div.dataTables_filter input {
        border: 1px solid #d1d5db;
        border-radius: 4px;
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        outline: none;
    }
    div.dataTables_wrapper div.dataTables_paginate .paginate_button {
        font-size: 0.72rem;
        padding: 0.15rem 0.45rem;
    }
    div.dataTables_wrapper div.dataTables_paginate .paginate_button.current {
        background: #18181b !important;
        color: white !important;
        border: 1px solid #18181b !important;
    }

    .pc-table tbody tr {
        border-bottom: 1px solid #f3f4f6;
        transition: background 0.07s;
    }
    .pc-table tbody tr:hover { background: #f9fafb; }
    .pc-table tbody tr:last-child { border-bottom: none; }
    .pc-table td {
        padding: 0.48rem 0.85rem;
        color: var(--foreground, #374151);
        vertical-align: middle;
    }
    .pc-table tr.row-tp td { background: #f0fdf4; }
    .pc-table tr.row-sl td { background: #fff1f2; }

    /* ── Badge ── */
    .pc-badge {
        display: inline-block;
        padding: 1px 7px;
        border-radius: 4px;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        border: 1px solid;
        font-family: -apple-system, BlinkMacSystemFont, "Inter", sans-serif;
    }
    .pc-badge-tp   { background: #f0fdf4; color: #166534; border-color: #86efac; }
    .pc-badge-sl   { background: #fff1f2; color: #9f1239; border-color: #fecdd3; }
    .pc-badge-near { background: #fffbeb; color: #92400e; border-color: #fde68a; }

    .pc-empty {
        text-align: center;
        padding: 2.5rem;
        color: #9ca3af;
        font-size: 0.82rem;
    }

    /* ── Spinner ── */
    @keyframes pc-spin { to { transform: rotate(360deg); } }
    .pc-spinner {
        display: inline-block;
        width: 13px; height: 13px;
        border: 2px solid rgba(255,255,255,0.3);
        border-top-color: #fff;
        border-radius: 50%;
        animation: pc-spin 0.7s linear infinite;
    }
</style>
@endpush

@section('content')
<div class="pc-page">

    {{-- ── Header ── --}}
    <div class="pc-header">
        <div>
            <h1 class="pc-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#6366f1">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                Price Level Checker
            </h1>
            <p class="pc-subtitle">Cek kapan pertama kali harga menyentuh level TP / SL berdasarkan data candle lokal.</p>
        </div>
        <a href="{{ route('market-data.index') }}" class="pc-back-btn">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            Crawler
        </a>
    </div>

    {{-- ── Form Card ── --}}
    <div class="pc-card">
        <div class="pc-card-header">
            <span class="pc-card-title">Check Configuration</span>
            <span style="font-size:0.68rem;color:var(--muted-foreground,#9ca3af);">Local OHLCV candle DB</span>
        </div>
        <div class="pc-card-body">

            @if($errors->any())
            <div class="pc-alert">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div>
                    <strong>Validation error:</strong>
                    <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            </div>
            @endif

            <form method="POST" action="{{ route('market-data.price-check') }}" id="pcForm">
                @csrf

                {{-- Dataset --}}
                <div class="pc-field pc-row">
                    <label class="pc-label" for="datasetSelect">Dataset — Exchange · Type · Symbol · Timeframe</label>
                    <select name="_dataset" id="datasetSelect" class="pc-select" required>
                        <option value="">— Pilih dataset —</option>
                        @foreach($datasets as $d)
                        @php
                            $key = "{$d->exchange}|{$d->type}|{$d->symbol}|{$d->timeframe}";
                            $old = isset($validated) ? "{$validated['exchange']}|{$validated['type']}|{$validated['symbol']}|{$validated['timeframe']}" : '';
                            $oldest = \Carbon\Carbon::createFromTimestampMs($d->oldest_ts)->addHours(7)->format('d M Y');
                            $newest = \Carbon\Carbon::createFromTimestampMs($d->newest_ts)->addHours(7)->format('d M Y');
                        @endphp
                        <option value="{{ $key }}"
                            data-exchange="{{ $d->exchange }}"
                            data-type="{{ $d->type }}"
                            data-symbol="{{ $d->symbol }}"
                            data-timeframe="{{ $d->timeframe }}"
                            data-oldest="{{ \Carbon\Carbon::createFromTimestampMs($d->oldest_ts)->format('Y-m-d') }}"
                            data-newest="{{ \Carbon\Carbon::createFromTimestampMs($d->newest_ts)->format('Y-m-d') }}"
                            {{ $old === $key ? 'selected' : '' }}>
                            {{ strtoupper($d->exchange) }} · {{ strtoupper($d->type) }} · {{ $d->symbol }} · {{ $d->timeframe }}
                            &nbsp;({{ $oldest }} – {{ $newest }})
                        </option>
                        @endforeach
                    </select>
                    {{-- Hidden fields populated by JS --}}
                    <input type="hidden" name="exchange"  id="hExchange"  value="{{ old('exchange',  $validated['exchange']  ?? '') }}">
                    <input type="hidden" name="type"      id="hType"      value="{{ old('type',      $validated['type']      ?? '') }}">
                    <input type="hidden" name="symbol"    id="hSymbol"    value="{{ old('symbol',    $validated['symbol']    ?? '') }}">
                    <input type="hidden" name="timeframe" id="hTimeframe" value="{{ old('timeframe', $validated['timeframe'] ?? '') }}">
                </div>

                {{-- Direction — Segmented Control --}}
                <div class="pc-field pc-row">
                    <label class="pc-label">Arah Posisi</label>
                    <div class="pc-seg" id="dirSeg">
                        <button type="button" class="pc-seg-btn long {{ (old('direction', $validated['direction'] ?? 'long')) === 'long' ? 'active' : '' }}" data-dir="long">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                            LONG
                        </button>
                        <button type="button" class="pc-seg-btn short {{ (old('direction', $validated['direction'] ?? '')) === 'short' ? 'active' : '' }}" data-dir="short">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
                            SHORT
                        </button>
                    </div>
                    <input type="hidden" name="direction" id="hDirection" value="{{ old('direction', $validated['direction'] ?? 'long') }}">
                </div>

                {{-- Prices --}}
                <div class="pc-grid-3 pc-row">
                    <div class="pc-field">
                        <label class="pc-label" for="entry_price">Entry Price ($)</label>
                        <input id="entry_price" name="entry_price" type="number" step="0.01"
                            class="pc-input mono" placeholder="e.g. 1965.25"
                            value="{{ old('entry_price', $validated['entry_price'] ?? '') }}" required>
                    </div>
                    <div class="pc-field">
                        <label class="pc-label" for="tp_price">Take Profit ($) <span style="font-weight:400;opacity:.6">optional</span></label>
                        <input id="tp_price" name="tp_price" type="number" step="0.01"
                            class="pc-input mono" placeholder="e.g. 2100.00"
                            value="{{ old('tp_price', $validated['tp_price'] ?? '') }}">
                    </div>
                    <div class="pc-field">
                        <label class="pc-label" for="sl_price">Stop Loss ($) <span style="font-weight:400;opacity:.6">optional</span></label>
                        <input id="sl_price" name="sl_price" type="number" step="0.01"
                            class="pc-input mono" placeholder="e.g. 1900.00"
                            value="{{ old('sl_price', $validated['sl_price'] ?? '') }}">
                    </div>
                </div>

                {{-- Date Range --}}
                <div class="pc-grid-2 pc-row">
                    <div class="pc-field">
                        <label class="pc-label" for="from_date">Dari Tanggal (WIB)</label>
                        <input id="from_date" name="from_date" type="date"
                            class="pc-input mono"
                            value="{{ old('from_date', $validated['from_date'] ?? now()->subDays(7)->format('Y-m-d')) }}" required>
                    </div>
                    <div class="pc-field">
                        <label class="pc-label" for="to_date">Sampai Tanggal (WIB)</label>
                        <input id="to_date" name="to_date" type="date"
                            class="pc-input mono"
                            value="{{ old('to_date', $validated['to_date'] ?? now()->format('Y-m-d')) }}" required>
                    </div>
                </div>

                <div class="pc-divider"></div>

                <button type="submit" class="pc-btn-submit" id="pcSubmit">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    Cek Harga
                </button>

            </form>
        </div>
    </div>

    {{-- ── Results ── --}}
    @isset($outcome)

    {{-- Outcome Banner --}}
    @php
        $bannerClass = match($outcome) { 'tp' => 'tp', 'sl' => 'sl', default => 'open' };
        $bannerIcon  = match($outcome) { 'tp' => '🎯', 'sl' => '🛑', default => '⏳' };
        $bannerText  = match($outcome) { 'tp' => 'TAKE PROFIT TERCAPAI', 'sl' => 'STOP LOSS KENA', default => 'MASIH OPEN — TIDAK TERSENTUH' };
    @endphp
    <div class="pc-outcome {{ $bannerClass }}">
        <div class="pc-outcome-icon">{{ $bannerIcon }}</div>
        <div>
            <div class="pc-outcome-label">{{ $bannerText }}</div>
            <div class="pc-outcome-detail">
                @if($outcome === 'tp' && $firstTp)
                    TP pertama tersentuh:
                    <strong>{{ $firstTp['wib']->format('d M Y, H:i') }} WIB</strong>
                    &nbsp;·&nbsp; Low: ${{ number_format($firstTp['candle']->low, 4) }}
                @elseif($outcome === 'sl' && $firstSl)
                    SL pertama tersentuh:
                    <strong>{{ $firstSl['wib']->format('d M Y, H:i') }} WIB</strong>
                    &nbsp;·&nbsp; High: ${{ number_format($firstSl['candle']->high, 4) }}
                @else
                    Tidak ada candle yang menyentuh TP atau SL dalam range yang dipilih.
                @endif
            </div>
        </div>
    </div>

    {{-- Stats ── --}}
    <div class="pc-stats">
        <div class="pc-stat">
            <div class="pc-stat-label">Entry</div>
            <div class="pc-stat-value" style="color:#4f46e5;">${{ number_format($entry, 4) }}</div>
        </div>
        <div class="pc-stat">
            <div class="pc-stat-label">Take Profit</div>
            <div class="pc-stat-value" style="color:#166534;">{{ $tp ? '$'.number_format($tp, 4) : '—' }}</div>
        </div>
        <div class="pc-stat">
            <div class="pc-stat-label">Stop Loss</div>
            <div class="pc-stat-value" style="color:#9f1239;">{{ $sl ? '$'.number_format($sl, 4) : '—' }}</div>
        </div>
    </div>

    {{-- Timeline ── --}}
    @if(count($timeline) > 0)
    <div class="pc-card">
        <div class="pc-card-header">
            <span class="pc-card-title">Timeline Candle Relevan</span>
            <span style="font-size:0.68rem;color:var(--muted-foreground,#9ca3af);">{{ count($timeline) }} candle</span>
        </div>
        <div class="pc-table-wrap">
            <table class="pc-table" id="timelineTable">
                <thead>
                    <tr>
                        <th>Waktu (WIB)</th>
                        <th>Open</th>
                        <th>High</th>
                        <th>Low</th>
                        <th>Close</th>
                        <th style="width: 80px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($timeline as $row)
                    <tr class="{{ $row['hit_tp'] ? 'row-tp' : ($row['hit_sl'] ? 'row-sl' : '') }}">
                        <td style="font-weight:600;white-space:nowrap;">{{ $row['wib']->format('d M Y, H:i') }}</td>
                        <td>${{ number_format($row['open'],  4) }}</td>
                        <td style="color:#166534;font-weight:600;">${{ number_format($row['high'],  4) }}</td>
                        <td style="color:#9f1239;font-weight:600;">${{ number_format($row['low'],   4) }}</td>
                        <td>${{ number_format($row['close'], 4) }}</td>
                        <td>
                            @if($row['hit_tp'])
                                <span class="pc-badge pc-badge-tp">HIT TP</span>
                            @elseif($row['hit_sl'])
                                <span class="pc-badge pc-badge-sl">HIT SL</span>
                            @else
                                <span class="pc-badge pc-badge-near">NEAR</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="pc-card">
        <div class="pc-empty">
            Tidak ada candle yang mendekati atau menyentuh level TP / SL dalam range ini.
        </div>
    </div>
    @endif

    @endisset

</div>

@endsection

@section('scripts')
<script>
// ── DataTables Init ──
$jq(function() {
    if ($jq('#timelineTable').length) {
        $jq('#timelineTable').DataTable({
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'asc']], // Urut berdasarkan waktu
            language: {
                search:       'Cari:',
                lengthMenu:   'Tampilkan _MENU_',
                info:         'Menampilkan _START_–_END_ dari _TOTAL_',
                paginate: { previous: '‹', next: '›' }
            }
        });
    }
});

// ── Dataset select → populate hidden fields ──
const sel = document.getElementById('datasetSelect');
function syncDataset() {
    const opt = sel.options[sel.selectedIndex];
    if(!opt) return;
    document.getElementById('hExchange').value  = opt.dataset.exchange  || '';
    document.getElementById('hType').value      = opt.dataset.type      || '';
    document.getElementById('hSymbol').value    = opt.dataset.symbol    || '';
    document.getElementById('hTimeframe').value = opt.dataset.timeframe || '';
    if (opt.dataset.oldest) document.getElementById('from_date').value = opt.dataset.oldest;
    if (opt.dataset.newest) document.getElementById('to_date').value   = opt.dataset.newest;
}
if(sel) sel.addEventListener('change', syncDataset);
if (sel && sel.value) syncDataset();

// ── Direction Segmented Control ──
document.querySelectorAll('.pc-seg-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.pc-seg-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('hDirection').value = btn.dataset.dir;
    });
});

// ── Submit loading state ──
const pcForm = document.getElementById('pcForm');
if(pcForm) {
    pcForm.addEventListener('submit', function () {
        const btn = document.getElementById('pcSubmit');
        btn.disabled = true;
        btn.innerHTML = '<span class="pc-spinner"></span> Menganalisis…';
    });
}
</script>
@endsection
