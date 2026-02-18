@extends('layouts.app')

@section('title', 'Price Level Checker | DragonFortune')

@push('head')
<style>
    .pc-wrap { max-width: 900px; margin: 0 auto; padding: 2rem 1rem; }
    .pc-card {
        background: rgba(255,255,255,0.65);
        backdrop-filter: blur(14px);
        border: 1px solid rgba(148,163,184,0.2);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 8px 32px rgba(0,0,0,0.08);
        margin-bottom: 1.5rem;
    }
    .dark .pc-card { background: rgba(15,23,42,0.75); border-color: rgba(148,163,184,0.12); }

    .pc-title {
        font-size: 1.4rem; font-weight: 800;
        background: linear-gradient(135deg, #f59e0b, #ef4444);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        margin-bottom: 0.2rem;
    }
    .pc-sub { font-size: 0.84rem; color: #64748b; margin-bottom: 1.75rem; }
    .dark .pc-sub { color: #94a3b8; }

    .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media(max-width:640px) { .form-grid-3, .form-grid-2 { grid-template-columns: 1fr; } }

    .field-group { display: flex; flex-direction: column; gap: 0.35rem; }
    .field-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; }
    .dark .field-label { color: #94a3b8; }
    .field-input, .field-select {
        width: 100%; padding: 0.6rem 0.85rem; border-radius: 10px;
        border: 1.5px solid rgba(203,213,225,0.8);
        background: rgba(255,255,255,0.9); font-size: 0.9rem; color: #1e293b;
        transition: border-color 0.15s, box-shadow 0.15s; outline: none; appearance: none;
    }
    .dark .field-input, .dark .field-select { background: rgba(30,41,59,0.8); border-color: rgba(71,85,105,0.6); color: #e2e8f0; }
    .field-input:focus, .field-select:focus { border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.15); }
    .field-select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.75rem center; padding-right: 2.25rem;
    }

    .divider { height: 1px; background: rgba(148,163,184,0.15); margin: 1.25rem 0; }

    .submit-btn {
        width: 100%; padding: 0.75rem; border-radius: 12px; border: none;
        background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
        color: white; font-size: 0.95rem; font-weight: 700; cursor: pointer;
        transition: opacity 0.15s, transform 0.1s; box-shadow: 0 4px 14px rgba(245,158,11,0.35);
        margin-top: 0.5rem;
    }
    .submit-btn:hover { opacity: 0.9; transform: translateY(-1px); }
    .submit-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

    /* Direction toggle */
    .dir-toggle { display: flex; gap: 0.5rem; }
    .dir-btn {
        flex: 1; padding: 0.6rem; border-radius: 10px; border: 1.5px solid rgba(203,213,225,0.8);
        background: transparent; font-size: 0.88rem; font-weight: 700; cursor: pointer;
        transition: all 0.15s; color: #64748b;
    }
    .dir-btn.long.active  { background: rgba(34,197,94,0.15);  border-color: #22c55e; color: #15803d; }
    .dir-btn.short.active { background: rgba(239,68,68,0.12);  border-color: #ef4444; color: #b91c1c; }
    .dark .dir-btn { color: #94a3b8; border-color: rgba(71,85,105,0.6); }
    .dark .dir-btn.long.active  { color: #86efac; }
    .dark .dir-btn.short.active { color: #fca5a5; }

    /* Outcome banner */
    .outcome-banner {
        border-radius: 16px; padding: 1.25rem 1.5rem;
        display: flex; align-items: center; gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .outcome-tp  { background: rgba(34,197,94,0.12);  border: 1.5px solid rgba(34,197,94,0.35); }
    .outcome-sl  { background: rgba(239,68,68,0.10);  border: 1.5px solid rgba(239,68,68,0.30); }
    .outcome-open{ background: rgba(59,130,246,0.10); border: 1.5px solid rgba(59,130,246,0.25); }
    .outcome-icon { font-size: 2rem; }
    .outcome-label { font-size: 1.1rem; font-weight: 800; }
    .outcome-tp   .outcome-label { color: #15803d; }
    .outcome-sl   .outcome-label { color: #b91c1c; }
    .outcome-open .outcome-label { color: #1d4ed8; }
    .dark .outcome-tp   .outcome-label { color: #86efac; }
    .dark .outcome-sl   .outcome-label { color: #fca5a5; }
    .dark .outcome-open .outcome-label { color: #93c5fd; }
    .outcome-detail { font-size: 0.85rem; color: #64748b; margin-top: 0.2rem; }
    .dark .outcome-detail { color: #94a3b8; }

    /* Stats row */
    .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
    @media(max-width:540px) { .stats-row { grid-template-columns: 1fr; } }
    .stat-box {
        background: rgba(248,250,252,0.8); border: 1px solid rgba(148,163,184,0.15);
        border-radius: 12px; padding: 0.85rem 1rem; text-align: center;
    }
    .dark .stat-box { background: rgba(30,41,59,0.6); }
    .stat-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 0.3rem; }
    .stat-value { font-size: 1.05rem; font-weight: 800; color: #1e293b; }
    .dark .stat-value { color: #e2e8f0; }

    /* Timeline table */
    .tl-table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
    .tl-table th { text-align: left; padding: 0.5rem 0.75rem; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; border-bottom: 1.5px solid rgba(148,163,184,0.2); }
    .tl-table td { padding: 0.55rem 0.75rem; border-bottom: 1px solid rgba(148,163,184,0.08); color: #334155; font-variant-numeric: tabular-nums; }
    .dark .tl-table td { color: #cbd5e1; }
    .tl-table tr:last-child td { border-bottom: none; }
    .tl-tp td { background: rgba(34,197,94,0.06); }
    .tl-sl td { background: rgba(239,68,68,0.06); }
    .badge-hit { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; }
    .badge-tp { background: rgba(34,197,94,0.15); color: #15803d; }
    .badge-sl { background: rgba(239,68,68,0.12); color: #b91c1c; }
    .badge-near { background: rgba(245,158,11,0.12); color: #b45309; }
    .dark .badge-tp { color: #86efac; }
    .dark .badge-sl { color: #fca5a5; }
    .dark .badge-near { color: #fcd34d; }

    .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #b91c1c; border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.88rem; margin-bottom: 1.25rem; }
    .dark .alert-error { color: #fca5a5; }

    .section-title { font-size: 0.9rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
    .dark .section-title { color: #e2e8f0; }
</style>
@endpush

@section('content')
<div class="pc-wrap">

    {{-- ── Form Card ── --}}
    <div class="pc-card">
        <div class="pc-title">🎯 Price Level Checker</div>
        <div class="pc-sub">Cek kapan pertama kali harga menyentuh level TP / SL berdasarkan data candle lokal.</div>

        @if($errors->any())
        <div class="alert-error">
            <strong>Error:</strong>
            <ul style="margin:0.4rem 0 0 1.2rem;padding:0;">
                @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('market-data.price-check') }}" id="pcForm">
            @csrf

            {{-- Dataset selector --}}
            <div class="field-group" style="margin-bottom:1rem;">
                <label class="field-label">Dataset (Exchange · Type · Symbol · Timeframe)</label>
                <select name="_dataset" id="datasetSelect" class="field-select" required>
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
                        {{ strtoupper($d->exchange) }} · {{ $d->type }} · {{ $d->symbol }} · {{ $d->timeframe }}
                        &nbsp;({{ $oldest }} – {{ $newest }})
                    </option>
                    @endforeach
                </select>
                {{-- Hidden fields populated by JS --}}
                <input type="hidden" name="exchange"  id="hExchange"  value="{{ old('exchange', $validated['exchange'] ?? '') }}">
                <input type="hidden" name="type"      id="hType"      value="{{ old('type', $validated['type'] ?? '') }}">
                <input type="hidden" name="symbol"    id="hSymbol"    value="{{ old('symbol', $validated['symbol'] ?? '') }}">
                <input type="hidden" name="timeframe" id="hTimeframe" value="{{ old('timeframe', $validated['timeframe'] ?? '') }}">
            </div>

            {{-- Direction --}}
            <div class="field-group" style="margin-bottom:1rem;">
                <label class="field-label">Arah Posisi</label>
                <div class="dir-toggle">
                    <button type="button" class="dir-btn long {{ (old('direction', $validated['direction'] ?? 'long')) === 'long' ? 'active' : '' }}" data-dir="long">📈 LONG</button>
                    <button type="button" class="dir-btn short {{ (old('direction', $validated['direction'] ?? '')) === 'short' ? 'active' : '' }}" data-dir="short">📉 SHORT</button>
                </div>
                <input type="hidden" name="direction" id="hDirection" value="{{ old('direction', $validated['direction'] ?? 'long') }}">
            </div>

            {{-- Prices --}}
            <div class="form-grid-3" style="margin-bottom:1rem;">
                <div class="field-group">
                    <label class="field-label" for="entry_price">Entry Price ($)</label>
                    <input id="entry_price" name="entry_price" type="number" step="0.01" class="field-input"
                        placeholder="e.g. 1965.25" value="{{ old('entry_price', $validated['entry_price'] ?? '') }}" required>
                </div>
                <div class="field-group">
                    <label class="field-label" for="tp_price">Take Profit ($)</label>
                    <input id="tp_price" name="tp_price" type="number" step="0.01" class="field-input"
                        placeholder="e.g. 1866.99" value="{{ old('tp_price', $validated['tp_price'] ?? '') }}">
                </div>
                <div class="field-group">
                    <label class="field-label" for="sl_price">Stop Loss ($)</label>
                    <input id="sl_price" name="sl_price" type="number" step="0.01" class="field-input"
                        placeholder="e.g. 2024.21" value="{{ old('sl_price', $validated['sl_price'] ?? '') }}">
                </div>
            </div>

            {{-- Date range --}}
            <div class="form-grid-2" style="margin-bottom:1rem;">
                <div class="field-group">
                    <label class="field-label" for="from_date">Dari Tanggal (WIB)</label>
                    <input id="from_date" name="from_date" type="date" class="field-input"
                        value="{{ old('from_date', $validated['from_date'] ?? now()->subDays(7)->format('Y-m-d')) }}" required>
                </div>
                <div class="field-group">
                    <label class="field-label" for="to_date">Sampai Tanggal (WIB)</label>
                    <input id="to_date" name="to_date" type="date" class="field-input"
                        value="{{ old('to_date', $validated['to_date'] ?? now()->format('Y-m-d')) }}" required>
                </div>
            </div>

            <div class="divider"></div>
            <button type="submit" class="submit-btn" id="pcSubmit">🔍 Cek Harga</button>
        </form>
    </div>

    {{-- ── Results ── --}}
    @isset($outcome)

    {{-- Outcome Banner --}}
    @php
        $bannerClass = match($outcome) { 'tp' => 'outcome-tp', 'sl' => 'outcome-sl', default => 'outcome-open' };
        $bannerIcon  = match($outcome) { 'tp' => '🎯', 'sl' => '🛑', default => '⏳' };
        $bannerText  = match($outcome) { 'tp' => 'TAKE PROFIT TERCAPAI', 'sl' => 'STOP LOSS KENA', default => 'MASIH OPEN / TIDAK TERSENTUH' };
    @endphp
    <div class="outcome-banner {{ $bannerClass }}">
        <div class="outcome-icon">{{ $bannerIcon }}</div>
        <div>
            <div class="outcome-label">{{ $bannerText }}</div>
            <div class="outcome-detail">
                @if($outcome === 'tp' && $firstTp)
                    Pertama kali TP tersentuh: <strong>{{ $firstTp['wib']->format('d M Y, H:i') }} WIB</strong>
                    &nbsp;·&nbsp; Low: ${{ number_format($firstTp['candle']->low, 4) }}
                @elseif($outcome === 'sl' && $firstSl)
                    Pertama kali SL tersentuh: <strong>{{ $firstSl['wib']->format('d M Y, H:i') }} WIB</strong>
                    &nbsp;·&nbsp; High: ${{ number_format($firstSl['candle']->high, 4) }}
                @else
                    Tidak ada candle yang menyentuh TP atau SL dalam range yang dipilih.
                @endif
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-label">Entry</div>
            <div class="stat-value" style="color:#3b82f6;">${{ number_format($entry, 2) }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Take Profit</div>
            <div class="stat-value" style="color:#22c55e;">{{ $tp ? '$'.number_format($tp, 2) : '—' }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Stop Loss</div>
            <div class="stat-value" style="color:#ef4444;">{{ $sl ? '$'.number_format($sl, 2) : '—' }}</div>
        </div>
    </div>

    {{-- Timeline --}}
    @if(count($timeline) > 0)
    <div class="pc-card">
        <div class="section-title">📋 Timeline Candle Relevan</div>
        <div style="overflow-x:auto;">
        <table class="tl-table">
            <thead>
                <tr>
                    <th>Waktu (WIB)</th>
                    <th>Open</th>
                    <th>High</th>
                    <th>Low</th>
                    <th>Close</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($timeline as $row)
                <tr class="{{ $row['hit_tp'] ? 'tl-tp' : ($row['hit_sl'] ? 'tl-sl' : '') }}">
                    <td><strong>{{ $row['wib']->format('d M Y, H:i') }}</strong></td>
                    <td>${{ number_format($row['open'],  4) }}</td>
                    <td style="color:#22c55e;font-weight:600;">${{ number_format($row['high'],  4) }}</td>
                    <td style="color:#ef4444;font-weight:600;">${{ number_format($row['low'],   4) }}</td>
                    <td>${{ number_format($row['close'], 4) }}</td>
                    <td>
                        @if($row['hit_tp'])
                            <span class="badge-hit badge-tp">🎯 HIT TP</span>
                        @elseif($row['hit_sl'])
                            <span class="badge-hit badge-sl">🛑 HIT SL</span>
                        @else
                            <span class="badge-hit badge-near">⚠️ Near</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
    @else
    <div class="pc-card" style="text-align:center;color:#94a3b8;padding:2rem;">
        Tidak ada candle yang mendekati atau menyentuh level TP/SL dalam range ini.
    </div>
    @endif

    @endisset

</div>

<script>
// Dataset select → populate hidden fields
const sel = document.getElementById('datasetSelect');
function syncDataset() {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('hExchange').value  = opt.dataset.exchange  || '';
    document.getElementById('hType').value      = opt.dataset.type      || '';
    document.getElementById('hSymbol').value    = opt.dataset.symbol    || '';
    document.getElementById('hTimeframe').value = opt.dataset.timeframe || '';
    // Auto-fill date range from dataset bounds
    if (opt.dataset.oldest) document.getElementById('from_date').value = opt.dataset.oldest;
    if (opt.dataset.newest) document.getElementById('to_date').value   = opt.dataset.newest;
}
sel.addEventListener('change', syncDataset);
if (sel.value) syncDataset(); // on page load if pre-selected

// Direction toggle
document.querySelectorAll('.dir-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.dir-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('hDirection').value = btn.dataset.dir;
    });
});

// Submit loading
document.getElementById('pcForm').addEventListener('submit', function() {
    const btn = document.getElementById('pcSubmit');
    btn.disabled = true;
    btn.textContent = '⏳ Menganalisis...';
});
</script>
@endsection
