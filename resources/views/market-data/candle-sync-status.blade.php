@extends('layouts.app')

@section('title', 'Candle Sync Status | DragonFortune')

@push('head')
<style>
    :root {
        --cs-bg: #f6f8fb;
        --cs-card: #ffffff;
        --cs-soft: #f1f5f9;
        --cs-border: #e2e8f0;
        --cs-text: #0f172a;
        --cs-muted: #64748b;
        --cs-green: #16a34a;
        --cs-red: #e11d48;
        --cs-amber: #d97706;
        --cs-blue: #2563eb;
    }

    .dark {
        --cs-bg: #0b1120;
        --cs-card: #111827;
        --cs-soft: #172033;
        --cs-border: #243044;
        --cs-text: #e5eefb;
        --cs-muted: #94a3b8;
    }

    body { background: var(--cs-bg); }

    .cs-page {
        color: var(--cs-text);
        padding: 24px;
    }

    .cs-head {
        align-items: flex-end;
        display: flex;
        gap: 16px;
        justify-content: space-between;
        margin-bottom: 16px;
    }

    .cs-title {
        font-size: 1.65rem;
        font-weight: 850;
        letter-spacing: 0;
        margin: 0;
    }

    .cs-subtitle {
        color: var(--cs-muted);
        font-size: .9rem;
        margin: 6px 0 0;
    }

    .cs-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: flex-end;
    }

    .cs-button {
        background: var(--cs-card);
        border: 1px solid var(--cs-border);
        border-radius: 8px;
        color: var(--cs-text);
        display: inline-flex;
        font-size: .84rem;
        font-weight: 800;
        padding: 9px 12px;
        text-decoration: none;
    }

    .cs-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        margin-bottom: 14px;
    }

    .cs-card {
        background: var(--cs-card);
        border: 1px solid var(--cs-border);
        border-radius: 8px;
        box-shadow: 0 16px 42px rgba(15, 23, 42, .06);
    }

    .cs-metric {
        padding: 16px;
    }

    .cs-label {
        color: var(--cs-muted);
        font-size: .7rem;
        font-weight: 800;
        letter-spacing: .07em;
        text-transform: uppercase;
    }

    .cs-value {
        font-size: 1.45rem;
        font-weight: 850;
        margin-top: 7px;
    }

    .cs-table-card {
        overflow: hidden;
    }

    .cs-table-head {
        align-items: center;
        border-bottom: 1px solid var(--cs-border);
        display: flex;
        justify-content: space-between;
        padding: 14px 16px;
    }

    .cs-section-title {
        font-size: 1rem;
        font-weight: 850;
    }

    .cs-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }

    .cs-table th {
        background: var(--cs-soft);
        border-bottom: 1px solid var(--cs-border);
        color: var(--cs-muted);
        font-size: .7rem;
        font-weight: 850;
        letter-spacing: .06em;
        padding: 10px 12px;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .cs-table td {
        border-bottom: 1px solid var(--cs-border);
        font-size: .84rem;
        padding: 12px;
        vertical-align: middle;
    }

    .cs-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }

    .cs-badge {
        border-radius: 999px;
        display: inline-flex;
        font-size: .72rem;
        font-weight: 850;
        padding: 4px 8px;
    }

    .cs-ok { background: rgba(22, 163, 74, .12); color: var(--cs-green); }
    .cs-warn { background: rgba(217, 119, 6, .13); color: var(--cs-amber); }
    .cs-bad { background: rgba(225, 29, 72, .12); color: var(--cs-red); }
    .cs-info { background: rgba(37, 99, 235, .12); color: var(--cs-blue); }
    .cs-muted { color: var(--cs-muted); }

    .cs-empty {
        color: var(--cs-muted);
        padding: 18px;
    }

    .cs-note {
        color: var(--cs-muted);
        font-size: .82rem;
        margin-top: 10px;
    }

    .cs-split {
        display: grid;
        gap: 14px;
        grid-template-columns: minmax(0, 1fr) 420px;
        margin-top: 14px;
    }

    @media (max-width: 1180px) {
        .cs-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .cs-split { grid-template-columns: 1fr; }
    }

    @media (max-width: 760px) {
        .cs-page { padding: 14px; }
        .cs-head { align-items: stretch; flex-direction: column; }
        .cs-grid { grid-template-columns: 1fr 1fr; }
        .cs-table-wrap { overflow-x: auto; }
    }
</style>
@endpush

@section('content')
<div class="cs-page">
    <div class="cs-head">
        <div>
            <h1 class="cs-title">Candle Sync Status</h1>
            <p class="cs-subtitle">Health check for market_candles, active strategy markets, freshness, and recent candle gaps.</p>
        </div>
        <div class="cs-actions">
            <a class="cs-button" href="{{ route('market-data.index') }}">Manual crawler</a>
            <a class="cs-button" href="{{ route('market-data.price-checker') }}">Price checker</a>
            <a class="cs-button" href="{{ route('market-data.candle-sync-status') }}">Refresh</a>
        </div>
    </div>

    <div class="cs-grid">
        <div class="cs-card cs-metric">
            <div class="cs-label">Datasets</div>
            <div class="cs-value">{{ number_format($summary['datasets']) }}</div>
        </div>
        <div class="cs-card cs-metric">
            <div class="cs-label">Active Markets</div>
            <div class="cs-value">{{ number_format($summary['active_markets']) }}</div>
        </div>
        <div class="cs-card cs-metric">
            <div class="cs-label">Missing Active</div>
            <div class="cs-value {{ $summary['missing_active_markets'] ? 'cs-bad' : '' }}">{{ number_format($summary['missing_active_markets']) }}</div>
        </div>
        <div class="cs-card cs-metric">
            <div class="cs-label">Stale</div>
            <div class="cs-value {{ $summary['stale'] ? 'cs-warn' : '' }}">{{ number_format($summary['stale']) }}</div>
        </div>
        <div class="cs-card cs-metric">
            <div class="cs-label">Recent Gaps</div>
            <div class="cs-value {{ $summary['with_gaps'] ? 'cs-warn' : '' }}">{{ number_format($summary['with_gaps']) }}</div>
        </div>
        <div class="cs-card cs-metric">
            <div class="cs-label">Total Candles</div>
            <div class="cs-value">{{ number_format($summary['total_candles']) }}</div>
        </div>
    </div>

    <div class="cs-card cs-table-card">
        <div class="cs-table-head">
            <div class="cs-section-title">Stored Candle Datasets</div>
            <div class="cs-note">Run <span class="cs-mono">php artisan queue:work</span> to process queued sync jobs.</div>
        </div>
        <div class="cs-table-wrap">
            <table class="cs-table">
                <thead>
                    <tr>
                        <th>Market</th>
                        <th>TF</th>
                        <th>Candles</th>
                        <th>Oldest</th>
                        <th>Newest</th>
                        <th>DB Updated</th>
                        <th>Status</th>
                        <th>Gap</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($datasets as $dataset)
                    <tr>
                        <td>
                            <strong class="cs-mono">{{ strtoupper($dataset->exchange) }} {{ strtoupper($dataset->type) }}</strong>
                            <div class="cs-muted cs-mono">{{ $dataset->symbol }}</div>
                            @if($dataset->is_active_strategy_market)
                                <span class="cs-badge cs-info">active strategy</span>
                            @endif
                        </td>
                        <td class="cs-mono">{{ $dataset->timeframe }}</td>
                        <td class="cs-mono">{{ number_format($dataset->total_candles) }}</td>
                        <td>{{ $dataset->oldest->format('d M Y H:i') }}</td>
                        <td>{{ $dataset->newest->format('d M Y H:i') }}</td>
                        <td>
                            @if($dataset->last_updated_at)
                                {{ $dataset->last_updated_at->diffForHumans() }}
                            @else
                                <span class="cs-muted">unknown</span>
                            @endif
                        </td>
                        <td>
                            @if($dataset->is_stale)
                                <span class="cs-badge cs-warn">stale</span>
                            @else
                                <span class="cs-badge cs-ok">fresh</span>
                            @endif
                        </td>
                        <td>
                            @if($dataset->has_gap)
                                <span class="cs-badge cs-warn">{{ round($dataset->largest_gap_ms / 60000) }}m gap</span>
                            @else
                                <span class="cs-badge cs-ok">ok</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="cs-empty">No candles stored yet. Start the scheduler and worker, or dispatch a manual crawl.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="cs-split">
        <div class="cs-card cs-table-card">
            <div class="cs-table-head">
                <div class="cs-section-title">Active Strategy Markets</div>
            </div>
            <div class="cs-table-wrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Strategy</th>
                            <th>Market</th>
                            <th>TF</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activeMarkets as $market)
                        <tr>
                            <td>
                                <strong>{{ $market['strategy_name'] }}</strong>
                                <div class="cs-muted">ID {{ $market['strategy_id'] }}</div>
                            </td>
                            <td class="cs-mono">{{ $market['exchange'] }} {{ $market['type'] }} {{ $market['symbol'] }}</td>
                            <td class="cs-mono">{{ $market['timeframe'] }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="cs-empty">No active strategies found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="cs-card cs-table-card">
            <div class="cs-table-head">
                <div class="cs-section-title">Missing Active Markets</div>
            </div>
            @if($missingActiveMarkets->isEmpty())
                <div class="cs-empty">All active strategy markets have candle datasets.</div>
            @else
                <div class="cs-table-wrap">
                    <table class="cs-table">
                        <thead>
                            <tr>
                                <th>Market</th>
                                <th>Command</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($missingActiveMarkets as $market)
                            <tr>
                                <td>
                                    <strong>{{ $market['symbol'] }}</strong>
                                    <div class="cs-muted">{{ $market['exchange'] }} {{ $market['type'] }} {{ $market['timeframe'] }}</div>
                                </td>
                                <td class="cs-mono">market-candles:sync --strategy={{ $market['strategy_id'] }} --timeframe={{ $market['timeframe'] }} --days=30 --backfill</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
