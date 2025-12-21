@extends('layouts.app')

@section('title', 'Signal & Analytics | DragonFortune')

@push('head')
    <style>
        .sa-overview-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .sa-method-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .sa-method-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 220px;
        }

        .sa-method-meta {
            flex: 1;
            min-width: 220px;
        }

        .sa-method-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sa-card {
            padding: 14px;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.03);
        }

        .dark .sa-card {
            background: rgba(255, 255, 255, 0.06);
        }

        .sa-binance-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .sa-stat {
            padding: 8px 10px;
            border-radius: 10px;
            background: rgba(2, 6, 23, 0.04);
        }

        .dark .sa-stat {
            background: rgba(2, 6, 23, 0.35);
        }

        .sa-stat .label,
        .sa-kpi-item .label {
            font-size: 12px;
            color: rgba(100, 116, 139, 1);
        }

        .dark .sa-stat .label,
        .dark .sa-kpi-item .label {
            color: rgba(148, 163, 184, 1);
        }

        .sa-stat .value {
            font-weight: 700;
            font-size: 16px;
            margin-top: 2px;
        }

        .sa-kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .sa-kpi-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 10px;
            border-radius: 10px;
            background: rgba(2, 6, 23, 0.04);
        }

        .dark .sa-kpi-item {
            background: rgba(2, 6, 23, 0.35);
        }

        .sa-kpi-item .value {
            font-weight: 700;
        }

        @media (max-width: 992px) {
            .sa-overview-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sa-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .sa-binance-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 576px) {
            .sa-kpi-grid,
            .sa-binance-grid {
                grid-template-columns: 1fr;
            }
        }

        .sa-tab.is-active {
            background: rgba(59, 130, 246, 0.14);
            border-color: rgba(59, 130, 246, 0.45);
            color: rgba(37, 99, 235, 1);
        }

        .dark .sa-tab.is-active {
            background: rgba(59, 130, 246, 0.18);
            border-color: rgba(59, 130, 246, 0.45);
            color: rgba(147, 197, 253, 1);
        }

        .sa-binance-tab.is-active {
            background: rgba(59, 130, 246, 0.14);
            border-color: rgba(59, 130, 246, 0.45);
            color: rgba(37, 99, 235, 1);
        }

        .dark .sa-binance-tab.is-active {
            background: rgba(59, 130, 246, 0.18);
            border-color: rgba(59, 130, 246, 0.45);
            color: rgba(147, 197, 253, 1);
        }

        .sa-modal {
            position: fixed;
            inset: 0;
            display: none;
            z-index: 9999;
        }

        .sa-modal.is-open {
            display: block;
        }

        .sa-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
        }

        .sa-modal__panel {
            position: relative;
            max-width: 980px;
            margin: 48px auto;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.25);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }

        .dark .sa-modal__panel {
            background: rgba(15, 23, 42, 0.98);
            border-color: rgba(148, 163, 184, 0.18);
        }

        .sa-modal__header {
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
        }

        .sa-modal__body {
            padding: 14px 16px;
        }

        .sa-pre {
            background: rgba(2, 6, 23, 0.06);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 12px;
            padding: 12px;
            margin: 0;
            white-space: pre-wrap;
            overflow: auto;
            max-height: 70vh;
        }

        .dark .sa-pre {
            background: rgba(2, 6, 23, 0.5);
        }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-column h-100 gap-3">
        <div class="derivatives-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h1 class="mb-0">Signal & Analytics</h1>
                    </div>
                    <p class="mb-0 text-secondary">
                        Dashboard untuk memantau metode, positions, orders, signals, reminders, logs, dan data Binance Spot.
                    </p>
                </div>
            </div>
        </div>

        <div class="df-panel p-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <div class="fw-semibold">Realtime Overview</div>
                    <div class="small text-secondary">
                        Data source: <code id="sa-api-base">-</code> - Docs:
                        <a id="sa-open-docs" href="#" target="_blank" rel="noopener"><code>/docs</code></a>
                    </div>
                </div>
                <div class="small text-secondary">
                    Status: <span id="sa-health" class="fw-semibold">-</span>
                    <span id="sa-health-meta" class="ms-2"></span>
                </div>
            </div>

            <div class="sa-card sa-method-bar">
                <div class="sa-method-left">
                    <div class="small text-secondary">Metode</div>
                    <select id="sa-method-select" class="form-select form-select-sm">
                        <option value="">Loading...</option>
                    </select>
                </div>
                <div id="sa-method-meta" class="sa-method-meta small text-secondary">
                    Pair: - | TF: - | Exchange: -
                </div>
                <div class="sa-method-right">
                    <div class="small text-secondary">Status</div>
                    <div class="d-flex align-items-center gap-2">
                        <span id="sa-method-running" class="badge text-bg-secondary">Unknown</span>
                        <span id="sa-method-status" class="small text-secondary"></span>
                    </div>
                </div>
            </div>

            <div class="sa-overview-grid mt-3">
                <div class="sa-card d-flex flex-column h-100">
                    <div class="fw-semibold mb-2">QuantConnect KPI</div>
                    <div id="sa-kpi-grid" class="sa-kpi-grid"></div>
                    <div class="mt-auto pt-3">
                        <button id="sa-qc-detail" class="btn btn-outline-primary w-100" type="button">Detail</button>
                    </div>
                </div>

                <div class="sa-card d-flex flex-column h-100">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <div class="fw-semibold">Binance Spot</div>
                        <span id="sa-binance-live" class="badge text-bg-success">Live</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                        <div id="sa-binance-account" class="small text-secondary">Account: -</div>
                        <select id="sa-binance-account-select" class="form-select form-select-sm" style="width: 220px;">
                            <option value="">Loading...</option>
                        </select>
                    </div>
                    <div id="sa-binance-hint" class="small text-secondary mt-1"></div>
                    <div class="sa-binance-grid">
                        <div class="sa-stat">
                            <div class="label">Saldo Total</div>
                            <div id="sa-binance-total" class="value">-</div>
                        </div>
                        <div class="sa-stat">
                            <div class="label">Tersedia</div>
                            <div id="sa-binance-available" class="value">-</div>
                        </div>
                        <div class="sa-stat">
                            <div class="label">Locked</div>
                            <div id="sa-binance-locked" class="value">-</div>
                        </div>
                        <div class="sa-stat">
                            <div class="label">BTC Value</div>
                            <div id="sa-binance-btc" class="value">-</div>
                        </div>
                        <div class="sa-stat">
                            <div class="label">Assets</div>
                            <div id="sa-binance-assets" class="value">-</div>
                        </div>
                        <div class="sa-stat">
                            <div class="label">Updated</div>
                            <div id="sa-binance-updated" class="value">-</div>
                        </div>
                    </div>
                    <div class="mt-auto pt-3">
                        <button id="sa-binance-detail" class="btn btn-outline-primary w-100" type="button">Detail</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="sa-detail" class="df-panel p-4" style="display:none;">
            <div id="sa-detail-qc" style="display:none;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button class="btn btn-outline-secondary btn-sm sa-tab is-active" data-tab="positions" type="button">Positions</button>
                    <button class="btn btn-outline-secondary btn-sm sa-tab" data-tab="orders" type="button">Order History</button>
                    <button class="btn btn-outline-secondary btn-sm sa-tab" data-tab="signals" type="button">Signals</button>
                    <button class="btn btn-outline-secondary btn-sm sa-tab" data-tab="reminders" type="button">Reminders</button>
                    <button class="btn btn-outline-secondary btn-sm sa-tab" data-tab="logs" type="button">Logs</button>
                </div>
                <div class="small text-secondary">Klik row untuk lihat detail (GET by id).</div>
            </div>

            <div class="mt-3">
                <div id="sa-panel-positions" class="sa-panel">
                    <div class="d-flex align-items-center justify-content-between flex-wrap mb-2">
                        <div class="small text-secondary">Position yang sedang terbuka.</div>
                        <div id="sa-positions-status" class="small text-secondary"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th style="min-width: 120px;">Symbol</th>
                                    <th style="min-width: 90px;">Side</th>
                                    <th style="min-width: 110px;" class="text-end">Entry</th>
                                    <th style="min-width: 110px;" class="text-end">Mark</th>
                                    <th style="min-width: 110px;" class="text-end">Qty</th>
                                    <th style="min-width: 130px;" class="text-end">Unrealized PnL</th>
                                    <th style="min-width: 170px;">Since</th>
                                </tr>
                            </thead>
                            <tbody id="sa-positions-body" class="small"></tbody>
                        </table>
                    </div>
                </div>

                <div id="sa-panel-orders" class="sa-panel" style="display:none;">
                    <div class="d-flex align-items-end gap-2 flex-wrap mb-2">
                        <div class="d-flex flex-column">
                            <label class="small text-secondary mb-1" for="sa-orders-type">Type</label>
                            <select id="sa-orders-type" class="form-select form-select-sm">
                                <option value="">(all)</option>
                                <option value="entry">entry</option>
                                <option value="exit">exit</option>
                            </select>
                        </div>
                        <div class="d-flex flex-column">
                            <label class="small text-secondary mb-1" for="sa-orders-jenis">Jenis</label>
                            <select id="sa-orders-jenis" class="form-select form-select-sm">
                                <option value="">(all)</option>
                                <option value="long">long</option>
                                <option value="short">short</option>
                                <option value="buy">buy</option>
                                <option value="sell">sell</option>
                            </select>
                        </div>
                        <div id="sa-orders-status" class="small text-secondary"></div>
                        <div class="small text-secondary">TP/SL di row Exit = P/L.</div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th style="min-width: 170px;">Date Time</th>
                                    <th style="min-width: 120px;">Symbol</th>
                                    <th style="min-width: 90px;">Type</th>
                                    <th style="min-width: 90px;">Jenis</th>
                                    <th style="min-width: 110px;" class="text-end">Price</th>
                                    <th style="min-width: 110px;" class="text-end">Qty</th>
                                    <th style="min-width: 110px;" class="text-end">Total</th>
                                    <th style="min-width: 110px;" class="text-end">TP</th>
                                    <th style="min-width: 110px;" class="text-end">SL</th>
                                </tr>
                            </thead>
                            <tbody id="sa-orders-body" class="small"></tbody>
                        </table>
                    </div>
                </div>

                <div id="sa-panel-signals" class="sa-panel" style="display:none;">
                    <div class="d-flex align-items-end gap-2 flex-wrap mb-2">
                        <div class="d-flex flex-column">
                            <label class="small text-secondary mb-1" for="sa-signals-type">Type</label>
                            <select id="sa-signals-type" class="form-select form-select-sm">
                                <option value="">(all)</option>
                                <option value="entry">entry</option>
                                <option value="exit">exit</option>
                            </select>
                        </div>
                        <div class="d-flex flex-column">
                            <label class="small text-secondary mb-1" for="sa-signals-jenis">Jenis</label>
                            <select id="sa-signals-jenis" class="form-select form-select-sm">
                                <option value="">(all)</option>
                                <option value="long">long</option>
                                <option value="short">short</option>
                                <option value="buy">buy</option>
                                <option value="sell">sell</option>
                            </select>
                        </div>
                        <div id="sa-signals-status" class="small text-secondary"></div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th style="min-width: 170px;">Date Time</th>
                                    <th style="min-width: 120px;">Symbol</th>
                                    <th style="min-width: 90px;">Type</th>
                                    <th style="min-width: 90px;">Jenis</th>
                                    <th style="min-width: 120px;" class="text-end">Price</th>
                                    <th style="min-width: 120px;" class="text-end">Target TP</th>
                                    <th style="min-width: 120px;" class="text-end">Target SL</th>
                                    <th style="min-width: 130px;" class="text-end">Realisasi TP</th>
                                    <th style="min-width: 130px;" class="text-end">Realisasi SL</th>
                                </tr>
                            </thead>
                            <tbody id="sa-signals-body" class="small"></tbody>
                        </table>
                    </div>
                </div>

                <div id="sa-panel-reminders" class="sa-panel" style="display:none;">
                    <div class="d-flex align-items-end gap-2 flex-wrap mb-2">
                        <div id="sa-reminders-status" class="small text-secondary"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th style="min-width: 170px;">Date Time</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody id="sa-reminders-body" class="small"></tbody>
                        </table>
                    </div>
                </div>

                <div id="sa-panel-logs" class="sa-panel" style="display:none;">
                    <div class="d-flex align-items-end gap-2 flex-wrap mb-2">
                        <div id="sa-logs-status" class="small text-secondary"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th style="min-width: 170px;">Date Time</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody id="sa-logs-body" class="small"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            </div>

            <div id="sa-detail-binance" style="display:none;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button class="btn btn-outline-secondary btn-sm sa-binance-tab is-active" data-tab="assets" type="button">Positions / Assets</button>
                    <button class="btn btn-outline-secondary btn-sm sa-binance-tab" data-tab="open-orders" type="button">Open Orders</button>
                    <button class="btn btn-outline-secondary btn-sm sa-binance-tab" data-tab="orders" type="button">Order History</button>
                    <button class="btn btn-outline-secondary btn-sm sa-binance-tab" data-tab="trades" type="button">Trades</button>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="small text-secondary">Symbol</div>
                    <input id="sa-binance-symbol" class="form-control form-control-sm" style="width: 120px;" value="BTCUSDT" />
                </div>
            </div>

            <div class="mt-3">
                <div id="sa-binance-panel-assets" class="sa-panel">
                    <div class="d-flex align-items-center justify-content-between flex-wrap mb-2">
                        <div class="small text-secondary">Saldo spot non-zero (dihitung dari account balances).</div>
                        <div id="sa-binance-assets-status" class="small text-secondary"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th style="min-width: 90px;">Asset</th>
                                    <th style="min-width: 120px;" class="text-end">Free</th>
                                    <th style="min-width: 120px;" class="text-end">Locked</th>
                                    <th style="min-width: 140px;" class="text-end">Price (USDT)</th>
                                    <th style="min-width: 160px;" class="text-end">Value (USDT)</th>
                                </tr>
                            </thead>
                            <tbody id="sa-binance-assets-body" class="small"></tbody>
                        </table>
                    </div>
                </div>

                <div id="sa-binance-panel-open-orders" class="sa-panel" style="display:none;">
                    <div class="d-flex align-items-center justify-content-between flex-wrap mb-2">
                        <div class="small text-secondary">Open orders untuk symbol yang dipilih.</div>
                        <div id="sa-binance-open-orders-status" class="small text-secondary"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th style="min-width: 170px;">Time</th>
                                    <th style="min-width: 120px;">Symbol</th>
                                    <th style="min-width: 90px;">Side</th>
                                    <th style="min-width: 110px;">Type</th>
                                    <th style="min-width: 120px;" class="text-end">Price</th>
                                    <th style="min-width: 120px;" class="text-end">Orig Qty</th>
                                    <th style="min-width: 120px;" class="text-end">Executed</th>
                                    <th style="min-width: 110px;">Status</th>
                                </tr>
                            </thead>
                            <tbody id="sa-binance-open-orders-body" class="small"></tbody>
                        </table>
                    </div>
                </div>

                <div id="sa-binance-panel-orders" class="sa-panel" style="display:none;">
                    <div class="d-flex align-items-center justify-content-between flex-wrap mb-2">
                        <div class="small text-secondary">Riwayat orders (limit 50) untuk symbol yang dipilih.</div>
                        <div id="sa-binance-orders-status" class="small text-secondary"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th style="min-width: 170px;">Time</th>
                                    <th style="min-width: 120px;">Symbol</th>
                                    <th style="min-width: 90px;">Side</th>
                                    <th style="min-width: 110px;">Type</th>
                                    <th style="min-width: 120px;" class="text-end">Price</th>
                                    <th style="min-width: 120px;" class="text-end">Orig Qty</th>
                                    <th style="min-width: 120px;" class="text-end">Executed</th>
                                    <th style="min-width: 110px;">Status</th>
                                </tr>
                            </thead>
                            <tbody id="sa-binance-orders-body" class="small"></tbody>
                        </table>
                    </div>
                </div>

                <div id="sa-binance-panel-trades" class="sa-panel" style="display:none;">
                    <div class="d-flex align-items-center justify-content-between flex-wrap mb-2">
                        <div class="small text-secondary">Trades (limit 50) untuk symbol yang dipilih.</div>
                        <div id="sa-binance-trades-status" class="small text-secondary"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th style="min-width: 170px;">Time</th>
                                    <th style="min-width: 120px;">Symbol</th>
                                    <th style="min-width: 90px;">Side</th>
                                    <th style="min-width: 120px;" class="text-end">Price</th>
                                    <th style="min-width: 120px;" class="text-end">Qty</th>
                                    <th style="min-width: 140px;" class="text-end">Quote Qty</th>
                                </tr>
                            </thead>
                            <tbody id="sa-binance-trades-body" class="small"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            </div>
        </div>

        <div id="sa-modal" class="sa-modal" aria-hidden="true">
            <div class="sa-modal__backdrop" data-close="1"></div>
            <div class="sa-modal__panel">
                <div class="sa-modal__header">
                    <div class="fw-semibold" id="sa-modal-title">Detail</div>
                    <button id="sa-modal-close" class="btn btn-outline-secondary btn-sm" type="button">Close</button>
                </div>
                <div class="sa-modal__body">
                    <pre id="sa-modal-pre" class="sa-pre">Loading...</pre>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('js/signal-analytics/dashboard.js') }}" defer></script>
@endsection
