@extends('layouts.app')

@section('title', 'Strategy - ' . ucfirst($creator))

@push('head')
{{-- Lightweight Charts by TradingView --}}
<script src="https://unpkg.com/lightweight-charts@4.1.3/dist/lightweight-charts.standalone.production.js"></script>
<style>
    :root {
        --iq-bg: #f0fdf4;
        --iq-card: rgba(255, 255, 255, 0.75);
        --iq-border: rgba(255, 255, 255, 0.85);
        --iq-text: #1f2937;
        --iq-muted: #6b7280;
        --iq-pill: rgba(255, 255, 255, 0.55);
        --iq-shadow: 0 8px 32px rgba(0, 0, 0, 0.07);
        --iq-green: #00c853;
        --iq-red: #ff3d57;
        --iq-amber: #ffab00;
    }

    .dark {
        --iq-bg: #0f172a;
        --iq-card: rgba(30, 41, 59, 0.7);
        --iq-border: rgba(51, 65, 85, 0.6);
        --iq-text: #f1f5f9;
        --iq-muted: #94a3b8;
        --iq-pill: rgba(30, 41, 59, 0.6);
        --iq-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    }

    * {
        box-sizing: border-box;
    }

    body {
        background: var(--iq-bg);
    }

    .iq-wrapper {
        padding: 28px;
        color: var(--iq-text);
    }

    .iq-glass {
        background: var(--iq-card);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--iq-border);
        border-radius: 18px;
        box-shadow: var(--iq-shadow);
    }

    /* KPI */
    .kpi-val {
        font-size: 2.2rem;
        font-weight: 900;
        letter-spacing: -0.03em;
    }

    .kpi-green {
        background: linear-gradient(135deg, #00c853, #059669);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .kpi-success {
        background: linear-gradient(135deg, #34d399, #059669);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .kpi-danger {
        background: linear-gradient(135deg, #ff3d57, #dc2626);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    /* Chart wrapper */
    #overviewChart {
        width: 100%;
        height: 380px;
        border-radius: 12px;
        overflow: hidden;
    }

    /* Table */
    .iq-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 6px;
    }

    .iq-table th {
        color: var(--iq-muted);
        font-size: 0.78rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .07em;
        padding: 0 14px 6px;
        border: none;
    }

    .iq-table td {
        padding: 11px 14px;
        background: var(--iq-pill);
        vertical-align: middle;
        font-size: 0.88rem;
    }

    .iq-table tr td:first-child {
        border-radius: 12px 0 0 12px;
    }

    .iq-table tr td:last-child {
        border-radius: 0 12px 12px 0;
    }

    .iq-table tr:hover td {
        filter: brightness(1.05);
        cursor: pointer;
    }

    .badge-side {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-weight: 700;
        font-size: .82rem;
    }

    /* Pagination clean */
    nav[aria-label="Pagination navigation"] {
        display: flex;
        justify-content: flex-end;
        padding: 12px 0;
        gap: 4px;
    }

    nav[aria-label="Pagination navigation"] span,
    nav[aria-label="Pagination navigation"] a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 34px;
        border-radius: 8px;
        background: var(--iq-pill);
        border: 1px solid var(--iq-border);
        color: var(--iq-text);
        font-size: .85rem;
        text-decoration: none;
        padding: 0 8px;
    }

    nav[aria-label="Pagination navigation"] [aria-current="page"] span {
        background: #00c853;
        color: #fff;
        border-color: #00c853;
    }

    nav[aria-label="Pagination navigation"] .flex.justify-between {
        display: none !important;
    }

    /* ===  DRILL-DOWN MODAL === */
    #drillModal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(8px);
    }

    #drillModal.open {
        display: flex;
    }

    #drillModal .modal-box {
        background: #0f1724;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        width: 92vw;
        max-width: 1100px;
        max-height: 92vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 32px 80px rgba(0, 0, 0, 0.6);
    }

    #drillModal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 18px 22px 14px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        gap: 12px;
        flex-wrap: wrap;
    }

    #drillModal .modal-close {
        background: rgba(255, 255, 255, 0.08);
        border: none;
        color: #fff;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.2rem;
        line-height: 1;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #drillModal .modal-close:hover {
        background: #ff3d57;
    }

    .modal-stat {
        font-size: .78rem;
        color: rgba(255, 255, 255, 0.5);
        margin-bottom: 2px;
    }

    .modal-val {
        font-size: 1.05rem;
        font-weight: 700;
        color: #fff;
    }

    .tf-btn {
        background: rgba(255, 255, 255, 0.07);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: #fff;
        border-radius: 6px;
        padding: 5px 12px;
        font-size: .8rem;
        cursor: pointer;
        transition: all .15s;
    }

    .tf-btn:hover,
    .tf-btn.active {
        background: #00c853;
        border-color: #00c853;
        color: #000;
        font-weight: 700;
    }

    #drillChart {
        flex: 1;
        min-height: 0;
        width: 100%;
    }

    .modal-info-bar {
        background: rgba(0, 0, 0, 0.3);
        border-top: 1px solid rgba(255, 255, 255, 0.07);
        padding: 10px 22px;
        display: flex;
        gap: 24px;
        flex-wrap: wrap;
        font-size: .8rem;
        color: rgba(255, 255, 255, 0.6);
    }

    .modal-info-bar span b {
        color: #fff;
    }

    .select-strategy {
        appearance: none;
        background: var(--iq-pill);
        border: 1px solid var(--iq-border);
        color: var(--iq-text);
        padding: .45rem 2.2rem .45rem 1rem;
        border-radius: 999px;
        font-size: .9rem;
    }
</style>
@endpush

@section('content')
<div class="iq-wrapper">

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="fw-black mb-0" style="font-size:2rem; letter-spacing:-.03em;">{{ ucfirst($creator) }} Dashboard</h1>
            <p class="mb-0" style="color:var(--iq-muted); font-size:.95rem;">Click any marker on chart to inspect trade detail</p>
        </div>
        <form action="{{ route('strategies.creator', ['creator' => $creator]) }}" method="GET">
            <select name="strategy_id" class="select-strategy" onchange="this.form.submit()">
                @foreach($methods as $m)
                <option value="{{ $m->id }}" {{ $selectedStrategy->id == $m->id ? 'selected' : '' }}>{{ $m->nama_metode }} ({{ $m->pair }})</option>
                @endforeach
            </select>
        </form>
    </div>

    @if($selectedStrategy)

    {{-- KPI ROW --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="iq-glass p-4 h-100">
                <div style="font-size:.8rem; color:var(--iq-muted); text-transform:uppercase; letter-spacing:.07em;">Equity Balance</div>
                <div class="kpi-val kpi-green mt-1">${{ number_format($selectedStrategy->closing_balance ?: $selectedStrategy->opening_balance, 2) }}</div>
                <small style="color:var(--iq-muted);">Start: ${{ number_format($selectedStrategy->opening_balance, 2) }}</small>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="iq-glass p-4 text-center h-100">
                <div style="font-size:.8rem; color:#00c853; text-transform:uppercase; letter-spacing:.07em;">Profit Exits</div>
                <div class="kpi-val kpi-success">{{ $tpCount }}</div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="iq-glass p-4 text-center h-100">
                <div style="font-size:.8rem; color:var(--iq-red); text-transform:uppercase; letter-spacing:.07em;">Loss Exits</div>
                <div class="kpi-val kpi-danger">{{ $slCount }}</div>
            </div>
        </div>
    </div>

    {{-- OVERVIEW CHART --}}
    <div class="iq-glass p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">Equity + Price Overview</h6>
            <small style="color:var(--iq-muted);">💡 Klik panah mana saja untuk inspeksi trade</small>
        </div>
        {{-- OVERVIEW LEGEND --}}
        <div style="display:flex; gap:18px; flex-wrap:wrap; font-size:11px; margin-bottom:12px; padding:7px 10px; background:rgba(0,0,0,0.05); border-radius:8px;">
            <span><span style="display:inline-block;width:22px;height:3px;background:#00c853;vertical-align:middle;border-radius:2px;margin-right:4px;"></span>Equity (saldo akun)</span>
            <span><span style="display:inline-block;width:22px;height:3px;background:#ffab00;vertical-align:middle;border-radius:2px;margin-right:4px;"></span>Market Price (harga coin)</span>
            <span><span style="color:#00c853;font-weight:700;margin-right:3px;">▲</span>Panah hijau = Trade Exit Profit (TP)</span>
            <span><span style="color:#ff5252;font-weight:700;margin-right:3px;">▼</span>Panah merah = Trade Exit Loss (SL)</span>
        </div>
        <div id="overviewChart"></div>
    </div>


    {{-- SIGNAL TABLE --}}
    <div class="iq-glass p-4">
        <h6 class="fw-bold mb-3">Signal History</h6>
        @if($signals->count() > 0)
        <div class="table-responsive">
            <table class="iq-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Entry</th>
                        <th>Side</th>
                        <th>TP / SL Target</th>
                        <th>Exit Price</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($signals as $sig)
                    @php
                    $entry = (float)$sig->price_entry;
                    $exit = (float)$sig->actual_price_exit;
                    $tp_val = (float)$sig->target_tp;
                    $sl_val = (float)$sig->target_sl;
                    $isLong = in_array(strtolower($sig->jenis), ['long','buy']);
                    $pnl = $entry > 0 ? ($isLong ? ($exit - $entry) : ($entry - $exit)) : 0;
                    $pnlPct = $entry > 0 ? ($pnl / $entry) * 100 * ($sig->leverage ?: 1) : 0;
                    $isExited = $exit > 0;
                    $isWin = $pnl >= 0;
                    @endphp
                    <tr>
                        <td><b>{{ \Carbon\Carbon::parse($sig->datetime)->format('M d, H:i') }}</b></td>
                        <td>${{ number_format($entry, 2) }} <small style="color:var(--iq-muted)">({{ $sig->leverage }}x)</small></td>
                        <td>
                            <span class="badge-side" style="color:{{ $isLong ? '#00c853' : '#ff3d57' }}">
                                {{ $isLong ? '▲ LONG' : '▼ SHORT' }}
                            </span>
                        </td>
                        <td>
                            <div style="color:#00c853; font-size:.8rem">TP: ${{ number_format($tp_val, 2) }}</div>
                            <div style="color:#ff3d57; font-size:.8rem">SL: ${{ number_format($sl_val, 2) }}</div>
                        </td>
                        <td>
                            @if($isExited)
                            ${{ number_format($exit, 2) }}
                            @else <span style="color:var(--iq-muted)">-</span> @endif
                        </td>
                        <td>
                            @if($isExited)
                            <span style="font-weight:700; color:{{ $isWin ? '#00c853' : '#ff3d57' }}">
                                {{ $isWin ? 'TP' : 'SL' }} {{ number_format($pnlPct, 2) }}%
                            </span>
                            @else
                            <span style="color:#60a5fa; font-weight:700">ACTIVE</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $signals->appends(request()->query())->links() }}</div>
        @endif
    </div>
    @endif
</div>

<div id="drillModal">
    <div class="modal-box">
        <div class="modal-header">
            {{-- Left: trade info chips --}}
            <div class="d-flex flex-wrap gap-4 align-items-center" id="drillInfo"></div>
            {{-- Right: TF buttons + close --}}
            <div class="d-flex gap-2 align-items-center flex-shrink-0">
                <button class="tf-btn active" data-tf="5m">5m</button>
                <button class="tf-btn" data-tf="15m">15m</button>
                <button class="tf-btn" data-tf="1h">1h</button>
                <button class="modal-close" id="drillClose">✕</button>
            </div>
        </div>
        {{-- CHART LEGEND --}}
        <div style="display:flex; gap:20px; padding:8px 22px; background:rgba(0,0,0,0.2); border-bottom:1px solid rgba(255,255,255,0.06); font-size:11px; flex-wrap:wrap;">
            <span style="color:rgba(255,255,255,0.5)">📊 <b style="color:#fff">Candlestick</b> = pergerakan harga pasar ETH/coin per candle</span>
            <span>🔵 <b style="color:#60a5fa">Garis titik-titik</b> = Harga Entry</span>
            <span>🟢 <b style="color:#00c853">Garis putus-putus atas</b> = Target TP</span>
            <span>🔴 <b style="color:#ff3d57">Garis putus-putus bawah</b> = Target SL</span>
            <span>🔵 <b style="color:#60a5fa">↑ Panah biru</b> = Waktu Entry</span>
            <span id="drillExitLegend"></span>
        </div>
        <div id="drillChart"></div>
        <div class="modal-info-bar" id="drillInfoBar"></div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // ─── DATA FROM BLADE ─────────────────────────────────────────────
        const chartData = @json($chartData); // [{time, value}]
        const allMarkers = @json($allMarkers ?? []); // [{time, position, color, shape, text, balance, trade_id}]
        const trades = @json($tradesList ?? []); // [{id, pair, side, entry_price, target_tp, target_sl, exit_price, entry_time, exit_time, ...}]
        const strategyPair = "{{ $selectedStrategy->pair ?? 'BTCUSDT' }}".replace(/[\/\s]/g, '').toUpperCase();
        const binancePair = strategyPair.includes('USDT') ? strategyPair : strategyPair + 'USDT';

        const isDark = document.documentElement.classList.contains('dark');

        // ─── CHART COLOURS ────────────────────────────────────────────────
        const CHART_BG = isDark ? '#0f172a' : '#f0fdf4';
        const GRID_COLOR = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
        const TEXT_COLOR = isDark ? '#94a3b8' : '#6b7280';

        // ─── OVERVIEW CHART ───────────────────────────────────────────────
        const overviewChart = LightweightCharts.createChart(document.getElementById('overviewChart'), {
            layout: {
                background: {
                    color: 'transparent'
                },
                textColor: TEXT_COLOR
            },
            grid: {
                vertLines: {
                    color: GRID_COLOR
                },
                horzLines: {
                    color: GRID_COLOR
                }
            },
            rightPriceScale: {
                visible: true
            },
            leftPriceScale: {
                visible: true,
                borderVisible: false
            },
            crosshair: {
                mode: LightweightCharts.CrosshairMode.Normal
            },
            timeScale: {
                timeVisible: true,
                secondsVisible: false
            },
            handleScroll: true,
            handleScale: true,
        });
        overviewChart.timeScale().fitContent();

        // Equity Histogram
        const equityHisto = overviewChart.addHistogramSeries({
            color: 'rgba(0, 200, 83, 0.35)',
            priceScaleId: 'left',
            lastValueVisible: false,
            priceLineVisible: false,
        });

        // Equity Line
        const equityLine = overviewChart.addLineSeries({
            color: '#00c853',
            lineWidth: 3,
            priceScaleId: 'left',
            lastValueVisible: true,
            priceLineVisible: false,
            title: 'Equity',
        });

        // Price Line (right axis – will be filled after Binance fetch)
        const priceLine = overviewChart.addLineSeries({
            color: '#ffab00',
            lineWidth: 2,
            priceScaleId: 'right',
            lastValueVisible: true,
            title: 'Price',
        });

        if (chartData.length) {
            equityHisto.setData(chartData.map(d => ({
                time: d.time,
                value: d.value,
                color: 'rgba(0,200,83,0.25)'
            })));
            equityLine.setData(chartData);

            // Markers (TP/SL arrows) — each marker has an 'id' for direct click detection
            equityLine.setMarkers(allMarkers.map(m => ({
                time: m.time,
                position: m.position,
                color: m.color,
                shape: m.shape,
                text: m.text,
                id: String(m.trade_id), // KEY: enables hoveredObjectId on click
            })));

            // Fetch Binance price for overview (match equity range)
            const startTs = chartData[0].time * 1000;
            const endTs = chartData[chartData.length - 1].time * 1000;
            fetchBinanceKlines(binancePair, '1h', startTs, endTs, 1000).then(candles => {
                if (candles.length) {
                    priceLine.setData(candles.map(c => ({
                        time: c.time,
                        value: c.close
                    })));
                }
            });

            overviewChart.timeScale().fitContent();
        }

        // ─── CLICK MARKER → OPEN DRILL-DOWN ──────────────────────────────
        // Build a quick lookup map: trade_id → marker
        const markerById = {};
        allMarkers.forEach(m => {
            markerById[String(m.trade_id)] = m;
        });

        overviewChart.subscribeClick(param => {
            if (!param || !param.time) return;
            console.log('[CLICK] param.time=', param.time, 'hoveredObjectId=', param.hoveredObjectId);

            let hit = null;

            // ① BEST: user clicked exactly on a marker (LWC gives us the id)
            if (param.hoveredObjectId) {
                hit = markerById[String(param.hoveredObjectId)];
                console.log('[CLICK] Direct marker hit via hoveredObjectId:', hit);
            }

            // ② FALLBACK: find the closest marker within 30 minutes only
            if (!hit) {
                const clickedTime = param.time;
                const MAX_MARGIN = 30 * 60; // 30 min — very tight to avoid wrong picks
                let bestDiff = Infinity;
                for (const m of allMarkers) {
                    const diff = Math.abs(m.time - clickedTime);
                    if (diff < bestDiff && diff <= MAX_MARGIN) {
                        bestDiff = diff;
                        hit = m;
                    }
                }
                if (hit) console.log('[CLICK] Fallback time-based hit (diff=', bestDiff, 's):', hit);
                else console.log('[CLICK] No marker found within 30min of click time', clickedTime);
            }

            if (!hit) return;
            const trade = trades.find(t => String(t.id) === String(hit.trade_id));
            if (!trade) {
                console.warn('[CLICK] Trade not found for trade_id', hit.trade_id);
                return;
            }
            console.log('[CLICK] Opening trade:', trade.id, 'is_profit=', trade.is_profit, 'marker_color=', hit.color);
            openDrillDown(trade);
        });

        // ─── HOVER CURSOR: Show pointer near markers ──────────────────────
        overviewChart.subscribeCrosshairMove(param => {
            if (!param || !param.time) {
                document.getElementById('overviewChart').style.cursor = 'default';
                return;
            }
            const t = param.time;
            const HOVER_MARGIN = 4 * 3600;
            const near = allMarkers.some(m => Math.abs(m.time - t) <= HOVER_MARGIN);
            document.getElementById('overviewChart').style.cursor = near ? 'pointer' : 'default';
        });

        // ─── DRILL-DOWN MODAL ─────────────────────────────────────────────
        let drillChartInstance = null;
        let currentTrade = null;
        let currentTf = '5m';

        async function openDrillDown(trade) {
            currentTrade = trade;
            renderDrillInfo(trade);
            // FIX: Show modal FIRST so the container has real dimensions, then render chart
            document.getElementById('drillModal').classList.add('open');
            document.getElementById('drillInfoBar').innerHTML = '<span style="opacity:.5">Loading candlestick data from Binance...</span>';
            await loadDrillChart(trade, currentTf);
        }

        function renderDrillInfo(trade) {
            const side = trade.side.includes('long') || trade.side.includes('buy') ? 'LONG' : 'SHORT';
            const isLong = side === 'LONG';
            const exitStr = trade.is_exited ? '$' + trade.exit_price.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) : 'ACTIVE';
            const result = trade.is_exited ? (trade.is_profit ? '✅ PROFIT' : '❌ LOSS') : '⏳ Active';
            document.getElementById('drillInfo').innerHTML = `
            <div><div class="modal-stat">Pair</div><div class="modal-val">${trade.pair.replace(/[\/\s]/g,'').toUpperCase()}</div></div>
            <div><div class="modal-stat">Side</div><div class="modal-val" style="color:${isLong?'#00c853':'#ff3d57'}">${side}</div></div>
            <div><div class="modal-stat">Entry</div><div class="modal-val">$${trade.entry_price.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</div></div>
            <div><div class="modal-stat">TP Target</div><div class="modal-val" style="color:#00c853">$${trade.target_tp.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</div></div>
            <div><div class="modal-stat">SL Target</div><div class="modal-val" style="color:#ff3d57">$${trade.target_sl.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</div></div>
            <div><div class="modal-stat">Exit</div><div class="modal-val">${exitStr}</div></div>
            <div><div class="modal-stat">Result</div><div class="modal-val">${result}</div></div>
        `;
        }

        async function loadDrillChart(trade, tf) {
            const el = document.getElementById('drillChart');

            // Destroy old chart
            if (drillChartInstance) {
                drillChartInstance.remove();
                drillChartInstance = null;
            }

            // Time window: entry → exit (or entry + 7 days for actives)
            const startTs = trade.entry_time * 1000;
            const endTs = trade.is_exited ? (trade.exit_time * 1000 + 3600000) : (trade.entry_time * 1000 + 7 * 86400000);

            const pair = trade.pair.replace(/[\/\s]/g, '').toUpperCase();
            const bpair = pair.includes('USDT') ? pair : pair + 'USDT';
            const candles = await fetchBinanceKlines(bpair, tf, startTs, endTs, 1000);

            drillChartInstance = LightweightCharts.createChart(el, {
                layout: {
                    background: {
                        color: '#0f1724'
                    },
                    textColor: '#94a3b8'
                },
                grid: {
                    vertLines: {
                        color: 'rgba(255,255,255,0.04)'
                    },
                    horzLines: {
                        color: 'rgba(255,255,255,0.04)'
                    }
                },
                crosshair: {
                    mode: LightweightCharts.CrosshairMode.Normal
                },
                timeScale: {
                    timeVisible: true,
                    secondsVisible: tf === '5m' || tf === '15m'
                },
                height: 400,
            });

            const candleSeries = drillChartInstance.addCandlestickSeries({
                upColor: '#00c853',
                downColor: '#ff3d57',
                borderUpColor: '#00c853',
                borderDownColor: '#ff3d57',
                wickUpColor: '#00c853',
                wickDownColor: '#ff3d57',
            });

            if (candles.length) {
                candleSeries.setData(candles);

                // Price line markers
                const isLong = trade.side.includes('long') || trade.side.includes('buy');
                const markers = [];

                // Entry marker
                markers.push({
                    time: trade.entry_time,
                    position: isLong ? 'belowBar' : 'aboveBar',
                    color: '#60a5fa',
                    shape: 'arrowUp',
                    text: 'Entry $' + trade.entry_price
                });

                // Exit marker
                if (trade.is_exited) {
                    markers.push({
                        time: trade.exit_time,
                        position: isLong ? 'aboveBar' : 'belowBar',
                        color: trade.is_profit ? '#00c853' : '#ff3d57',
                        shape: 'arrowDown',
                        text: (trade.is_profit ? 'TP Exit' : 'SL Exit') + ' $' + trade.exit_price
                    });
                }

                markers.sort((a, b) => a.time - b.time);
                candleSeries.setMarkers(markers);

                // TP horizontal line
                const tpLine = drillChartInstance.addLineSeries({
                    color: 'rgba(0,200,83,0.7)',
                    lineWidth: 1,
                    lineStyle: LightweightCharts.LineStyle.Dashed,
                    lastValueVisible: true,
                    priceLineVisible: false,
                    title: 'TP Target'
                });
                tpLine.setData([{
                    time: candles[0].time,
                    value: trade.target_tp
                }, {
                    time: candles[candles.length - 1].time,
                    value: trade.target_tp
                }]);

                // SL horizontal line
                const slLine = drillChartInstance.addLineSeries({
                    color: 'rgba(255,61,87,0.7)',
                    lineWidth: 1,
                    lineStyle: LightweightCharts.LineStyle.Dashed,
                    lastValueVisible: true,
                    priceLineVisible: false,
                    title: 'SL Target'
                });
                slLine.setData([{
                    time: candles[0].time,
                    value: trade.target_sl
                }, {
                    time: candles[candles.length - 1].time,
                    value: trade.target_sl
                }]);

                // Entry horizontal line
                const entryLine = drillChartInstance.addLineSeries({
                    color: 'rgba(96,165,250,0.7)',
                    lineWidth: 1,
                    lineStyle: LightweightCharts.LineStyle.Dotted,
                    lastValueVisible: false,
                    priceLineVisible: false,
                    title: 'Entry'
                });
                entryLine.setData([{
                    time: candles[0].time,
                    value: trade.entry_price
                }, {
                    time: candles[candles.length - 1].time,
                    value: trade.entry_price
                }]);

                // Info bar analysis
                const highs = candles.map(c => c.high);
                const lows = candles.map(c => c.low);
                const maxHigh = Math.max(...highs);
                const minLow = Math.min(...lows);

                // For LONG: price needs to go UP to hit TP, DOWN to hit SL
                // For SHORT: price needs to go DOWN to hit TP, UP to hit SL
                const tpDiff = isLong ?
                    (trade.target_tp - maxHigh) // positive = not reached, negative = passed
                    :
                    (minLow - trade.target_tp); // positive = not reached, negative = passed

                const slHit = isLong ?
                    (minLow <= trade.target_sl) // long: SL triggered if low dropped below SL
                    :
                    (maxHigh >= trade.target_sl); // short: SL triggered if high broke above SL

                const tpHit = parseFloat(tpDiff) <= 0;

                let tpStatus, tpColor;
                if (tpHit) {
                    tpStatus = `✅ Harga sempat melewati TP! (maks ${isLong ? '$'+maxHigh.toLocaleString() : '$'+minLow.toLocaleString()})`;
                    tpColor = '#00c853';
                } else {
                    tpStatus = `❌ Tidak sampai TP — kurang $${Math.abs(tpDiff).toFixed(2)} lagi (harga maks ${isLong ? '$'+maxHigh.toLocaleString() : '$'+minLow.toLocaleString()})`;
                    tpColor = '#ff9800';
                }

                let slStatus, slColor;
                if (slHit) {
                    slStatus = `🔴 Harga menyentuh SL (harga min $${minLow.toLocaleString()})`;
                    slColor = '#ff3d57';
                } else {
                    slStatus = `✅ SL tidak tersentuh`;
                    slColor = '#00c853';
                }

                // Update exit legend dynamically
                const exitLeg = document.getElementById('drillExitLegend');
                if (exitLeg) {
                    const exitColor = trade.is_profit ? '#00c853' : '#ff3d57';
                    const exitIcon = trade.is_profit ? '▲' : '▼';
                    exitLeg.innerHTML = `<span>${exitIcon} <b style="color:${exitColor}">${trade.is_profit ? 'Panah hijau atas' : 'Panah merah bawah'}</b> = Waktu Exit (${trade.is_profit ? 'Profit' : 'Loss'})</span>`;
                }

                document.getElementById('drillInfoBar').innerHTML = `
                    <span>📈 Harga tertinggi di periode ini: <b>$${maxHigh.toLocaleString()}</b></span>
                    <span>📉 Harga terendah di periode ini: <b>$${minLow.toLocaleString()}</b></span>
                    <span style="color:${tpColor}">🎯 Status TP ($${trade.target_tp.toLocaleString()}): <b>${tpStatus}</b></span>
                    <span style="color:${slColor}">🛑 Status SL ($${trade.target_sl.toLocaleString()}): <b>${slStatus}</b></span>
                `;

                drillChartInstance.timeScale().fitContent();
            }
        }

        // ─── TIMEFRAME BUTTONS ────────────────────────────────────────────
        document.querySelectorAll('.tf-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                document.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentTf = btn.dataset.tf;
                if (currentTrade) await loadDrillChart(currentTrade, currentTf);
            });
        });

        // ─── CLOSE MODAL ─────────────────────────────────────────────────
        document.getElementById('drillClose').addEventListener('click', () => {
            document.getElementById('drillModal').classList.remove('open');
            if (drillChartInstance) {
                drillChartInstance.remove();
                drillChartInstance = null;
            }
            currentTrade = null;
        });

        // ─── BINANCE HELPER ──────────────────────────────────────────────
        async function fetchBinanceKlines(symbol, interval, startMs, endMs, limit = 1000) {
            try {
                const url = `https://api.binance.com/api/v3/klines?symbol=${symbol}&interval=${interval}&startTime=${startMs}&endTime=${endMs}&limit=${limit}`;
                const res = await fetch(url);
                const data = await res.json();
                if (!Array.isArray(data)) return [];
                return data.map(d => ({
                    time: Math.floor(parseInt(d[0]) / 1000),
                    open: parseFloat(d[1]),
                    high: parseFloat(d[2]),
                    low: parseFloat(d[3]),
                    close: parseFloat(d[4]),
                }));
            } catch (e) {
                console.error('Binance fetch error', e);
                return [];
            }
        }
    });
</script>
@endsection