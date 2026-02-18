@extends('layouts.app')

@section('title', 'Market Data Crawler | DragonFortune')

@push('head')
<style>
    .crawler-wrap {
        max-width: 960px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }
    .crawler-card {
        background: rgba(255,255,255,0.6);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(148,163,184,0.2);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 8px 32px rgba(0,0,0,0.08);
    }
    .dark .crawler-card {
        background: rgba(15,23,42,0.7);
        border-color: rgba(148,163,184,0.12);
    }
    .crawler-title {
        font-size: 1.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 0.25rem;
    }
    .crawler-sub {
        font-size: 0.85rem;
        color: rgba(100,116,139,1);
        margin-bottom: 1.75rem;
    }
    .dark .crawler-sub { color: rgba(148,163,184,1); }
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    @media (max-width: 540px) { .form-grid { grid-template-columns: 1fr; } }
    .field-group { display: flex; flex-direction: column; gap: 0.35rem; }
    .field-label {
        font-size: 0.78rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: rgba(71,85,105,1);
    }
    .dark .field-label { color: rgba(148,163,184,1); }
    .field-input, .field-select {
        width: 100%;
        padding: 0.6rem 0.85rem;
        border-radius: 10px;
        border: 1.5px solid rgba(203,213,225,0.8);
        background: rgba(255,255,255,0.9);
        font-size: 0.9rem;
        color: #1e293b;
        transition: border-color 0.15s, box-shadow 0.15s;
        outline: none;
        appearance: none;
    }
    .dark .field-input, .dark .field-select {
        background: rgba(30,41,59,0.8);
        border-color: rgba(71,85,105,0.6);
        color: rgba(226,232,240,1);
    }
    .field-input:focus, .field-select:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
    }
    .field-select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        padding-right: 2.25rem;
    }
    .submit-btn {
        width: 100%;
        padding: 0.75rem;
        border-radius: 12px;
        border: none;
        background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        color: white;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: opacity 0.15s, transform 0.1s, box-shadow 0.15s;
        box-shadow: 0 4px 14px rgba(59,130,246,0.35);
        margin-top: 0.5rem;
    }
    .submit-btn:hover { opacity: 0.92; transform: translateY(-1px); }
    .submit-btn:active { transform: translateY(0); }
    .submit-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
    .alert-success {
        background: rgba(34,197,94,0.12);
        border: 1px solid rgba(34,197,94,0.35);
        color: #15803d;
        border-radius: 12px;
        padding: 0.85rem 1rem;
        font-size: 0.88rem;
        margin-bottom: 1.25rem;
    }
    .dark .alert-success { background: rgba(34,197,94,0.1); color: #86efac; }
    .alert-error {
        background: rgba(239,68,68,0.1);
        border: 1px solid rgba(239,68,68,0.3);
        color: #b91c1c;
        border-radius: 12px;
        padding: 0.85rem 1rem;
        font-size: 0.88rem;
        margin-bottom: 1.25rem;
    }
    .dark .alert-error { background: rgba(239,68,68,0.08); color: #fca5a5; }
    .divider { height: 1px; background: rgba(148,163,184,0.15); margin: 1.25rem 0; }
    .info-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8rem;
        color: rgba(100,116,139,1);
        margin-top: 0.75rem;
    }
    .dark .info-row { color: rgba(148,163,184,1); }
    .badge-info {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        background: rgba(59,130,246,0.12);
        color: #3b82f6;
        white-space: nowrap;
    }
</style>
@endpush

@section('content')
<div class="crawler-wrap">
    <div class="crawler-card">

        <div class="crawler-title">📡 Market Data Crawler</div>
        <div class="crawler-sub">
            Fetch historical OHLCV candles from Binance / Bybit via CCXT and store them locally.
        </div>

        @if(session('success'))
        <div class="alert-success">✅ {{ session('success') }}</div>
        @endif

        @if($errors->any())
        <div class="alert-error">
            <strong>Please fix the following:</strong>
            <ul style="margin:0.5rem 0 0 1.25rem; padding:0;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('market-data.store') }}" id="crawlerForm">
            @csrf
            <div class="form-grid">

                <div class="field-group">
                    <label class="field-label" for="exchange">Exchange</label>
                    <select id="exchange" name="exchange" class="field-select" required>
                        <option value="binance" {{ old('exchange','binance')==='binance'?'selected':'' }}>🟡 Binance</option>
                        <option value="bybit"   {{ old('exchange')==='bybit'?'selected':'' }}>🟠 Bybit</option>
                    </select>
                </div>

                <div class="field-group">
                    <label class="field-label" for="type">Market Type</label>
                    <select id="type" name="type" class="field-select" required>
                        <option value="spot"   {{ old('type','spot')==='spot'?'selected':'' }}>Spot</option>
                        <option value="future" {{ old('type')==='future'?'selected':'' }}>Future (USDⓈ-M)</option>
                    </select>
                </div>

                <div class="field-group">
                    <label class="field-label" for="symbol">Symbol</label>
                    <select id="symbol" name="symbol" class="field-select" required>
                        <option value="BTC/USDT" {{ old('symbol','BTC/USDT')==='BTC/USDT'?'selected':'' }}>₿ BTC/USDT</option>
                        <option value="ETH/USDT" {{ old('symbol')==='ETH/USDT'?'selected':'' }}>Ξ ETH/USDT</option>
                    </select>
                </div>

                <div class="field-group">
                    <label class="field-label" for="timeframe">Timeframe</label>
                    <select id="timeframe" name="timeframe" class="field-select" required>
                        @foreach(['1m','3m','5m','15m','30m','1h','4h','1d'] as $tf)
                        <option value="{{ $tf }}" {{ old('timeframe','1h')===$tf?'selected':'' }}>{{ $tf }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field-group">
                    <label class="field-label" for="start_date">Start Date</label>
                    <input id="start_date" name="start_date" type="date" class="field-input"
                        value="{{ old('start_date', now()->subDays(30)->format('Y-m-d')) }}" required />
                </div>

                <div class="field-group">
                    <label class="field-label" for="end_date">End Date</label>
                    <input id="end_date" name="end_date" type="date" class="field-input"
                        value="{{ old('end_date', now()->format('Y-m-d')) }}" required />
                </div>

            </div>

            <div class="divider"></div>

            <button type="submit" class="submit-btn" id="submitBtn">
                🚀 Dispatch Crawl Job
            </button>
        </form>

        <div class="info-row"><span class="badge-info">⚡ Queue</span> Runs in background via Laravel Queue (database driver).</div>
        <div class="info-row"><span class="badge-info">🛡️ Throttle</span> Rate limited: max 2 API requests/second.</div>
        <div class="info-row"><span class="badge-info">💾 Upsert</span> Duplicate candles are automatically skipped/updated.</div>

    </div>

    {{-- ── Datasets Table ── --}}
    <div style="background:rgba(255,255,255,0.6);backdrop-filter:blur(12px);border:1px solid rgba(148,163,184,0.2);border-radius:20px;padding:1.5rem 2rem;box-shadow:0 8px 32px rgba(0,0,0,0.08);margin-top:1.5rem;">
        <div style="font-size:1rem;font-weight:700;color:#1e293b;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;">
            📊 Crawled Datasets
            <span style="font-size:0.78rem;font-weight:400;color:rgba(148,163,184,1);">(refresh page to update)</span>
        </div>

        @if($datasets->isEmpty())
            <div style="text-align:center;padding:2rem;color:rgba(148,163,184,1);font-size:0.88rem;">
                No data crawled yet. Dispatch a job above to get started.
            </div>
        @else
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
            <thead>
                <tr>
                    @foreach(['Exchange','Type','Symbol','Timeframe','Candles','Oldest','Newest'] as $h)
                    <th style="text-align:left;padding:0.5rem 0.75rem;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:rgba(100,116,139,1);border-bottom:1.5px solid rgba(148,163,184,0.2);">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($datasets as $d)
                @php
                    $exColor  = $d->exchange === 'binance' ? 'background:rgba(240,185,11,0.15);color:#b45309;' : 'background:rgba(249,115,22,0.15);color:#c2410c;';
                    $typeColor= $d->type === 'spot' ? 'background:rgba(34,197,94,0.12);color:#15803d;' : 'background:rgba(168,85,247,0.12);color:#7e22ce;';
                @endphp
                <tr style="border-bottom:1px solid rgba(148,163,184,0.1);">
                    <td style="padding:0.6rem 0.75rem;"><span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;{{ $exColor }}">{{ $d->exchange }}</span></td>
                    <td style="padding:0.6rem 0.75rem;"><span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;{{ $typeColor }}">{{ $d->type }}</span></td>
                    <td style="padding:0.6rem 0.75rem;font-weight:700;color:#334155;">{{ $d->symbol }}</td>
                    <td style="padding:0.6rem 0.75rem;"><code style="background:rgba(148,163,184,0.1);padding:2px 6px;border-radius:4px;font-size:12px;">{{ $d->timeframe }}</code></td>
                    <td style="padding:0.6rem 0.75rem;font-weight:700;color:#3b82f6;font-variant-numeric:tabular-nums;">{{ number_format($d->total_candles) }}</td>
                    <td style="padding:0.6rem 0.75rem;font-size:0.8rem;color:#64748b;">{{ \Carbon\Carbon::createFromTimestampMs($d->oldest_ts)->format('d M Y H:i') }}</td>
                    <td style="padding:0.6rem 0.75rem;font-size:0.8rem;color:#64748b;">{{ \Carbon\Carbon::createFromTimestampMs($d->newest_ts)->format('d M Y H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

</div>

<script>
document.getElementById('crawlerForm').addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = '⏳ Dispatching...';
});

</script>
@endsection
