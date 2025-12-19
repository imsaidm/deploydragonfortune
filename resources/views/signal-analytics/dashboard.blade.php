@extends('layouts.app')

@section('title', 'Signal & Analytics | DragonFortune')

@push('head')
    <style>
        .sa-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 12px;
        }

        .sa-kpis {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .sa-kpi {
            padding: 10px 12px;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.03);
        }

        .dark .sa-kpi {
            background: rgba(255, 255, 255, 0.06);
        }

        .sa-kpi .label {
            font-size: 12px;
            color: rgba(100, 116, 139, 1);
        }

        .dark .sa-kpi .label {
            color: rgba(148, 163, 184, 1);
        }

        .sa-kpi .value {
            font-weight: 700;
            font-size: 18px;
            margin-top: 2px;
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
                        Dashboard untuk memantau metode, orders, signals, reminders, dan logs (data dari API).
                    </p>
                </div>
            </div>
        </div>

        <div class="df-panel p-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div class="d-flex flex-column">
                    <div class="fw-semibold">API Status</div>
                    <div class="small text-secondary">
                        Base URL: <code id="sa-api-base">-</code> &middot; Docs:
                        <a id="sa-open-docs" href="#" target="_blank" rel="noopener"><code>/docs</code></a>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button id="sa-refresh-all" class="btn btn-outline-primary btn-sm">Refresh All</button>
                    <div id="sa-last-refresh" class="small text-secondary"></div>
                </div>
            </div>

            <div class="sa-grid">
                <div class="p-3 bg-body-tertiary rounded">
                    <div class="small text-secondary mb-1">Health</div>
                    <div id="sa-health" class="fw-semibold">-</div>
                    <div id="sa-health-meta" class="small text-secondary mt-1"></div>
                </div>

                <div class="p-3 bg-body-tertiary rounded">
                    <div class="small text-secondary mb-1">Metode</div>
                    <select id="sa-method-select" class="form-select form-select-sm">
                        <option value="">Loading...</option>
                    </select>
                    <div class="d-flex align-items-center gap-2 flex-wrap mt-2">
                        <button id="sa-method-detail" class="btn btn-outline-secondary btn-sm" disabled>Detail</button>
                        <div id="sa-method-status" class="small text-secondary"></div>
                    </div>
                </div>

                <div class="p-3 bg-body-tertiary rounded">
                    <div class="small text-secondary mb-1">Filter waktu</div>
                    <div class="d-flex gap-2 flex-wrap">
                        <div class="d-flex flex-column">
                            <label class="small text-secondary mb-1" for="sa-from">From</label>
                            <input id="sa-from" type="datetime-local" class="form-control form-control-sm">
                        </div>
                        <div class="d-flex flex-column">
                            <label class="small text-secondary mb-1" for="sa-to">To</label>
                            <input id="sa-to" type="datetime-local" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap mt-2">
                        <div class="d-flex flex-column">
                            <label class="small text-secondary mb-1" for="sa-limit">Limit</label>
                            <input id="sa-limit" type="number" min="1" max="500" step="1" class="form-control form-control-sm" value="50">
                        </div>
                        <div class="d-flex flex-column">
                            <label class="small text-secondary mb-1" for="sa-offset">Offset</label>
                            <input id="sa-offset" type="number" min="0" step="1" class="form-control form-control-sm" value="0">
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-body-tertiary rounded">
                    <div class="small text-secondary mb-2">KPI</div>
                    <div class="sa-kpis">
                        <div class="sa-kpi">
                            <div class="label">PSR</div>
                            <div id="sa-kpi-psr" class="value">-</div>
                        </div>
                        <div class="sa-kpi">
                            <div class="label">CAGR</div>
                            <div id="sa-kpi-cagr" class="value">-</div>
                        </div>
                        <div class="sa-kpi">
                            <div class="label">Win Rate</div>
                            <div id="sa-kpi-win" class="value">-</div>
                        </div>
                        <div class="sa-kpi">
                            <div class="label">Loss Rate</div>
                            <div id="sa-kpi-loss" class="value">-</div>
                        </div>
                    </div>
                    <div id="sa-kpi-extra" class="small text-secondary mt-2"></div>
                </div>
            </div>
        </div>

        <div class="df-panel p-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <div>
                    <div class="fw-semibold">Balance</div>
                    <div class="small text-secondary">Dari data Orders (method yang dipilih).</div>
                </div>
                <div id="sa-balance-meta" class="small text-secondary"></div>
            </div>
            <div style="position: relative; height: 280px;">
                <canvas id="sa-balance-chart"></canvas>
            </div>
            <div id="sa-balance-empty" class="small text-secondary mt-2" style="display:none;">Belum ada data orders.</div>
        </div>

        <div class="df-panel p-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button class="btn btn-outline-secondary btn-sm sa-tab is-active" data-tab="orders" type="button">Orders</button>
                    <button class="btn btn-outline-secondary btn-sm sa-tab" data-tab="signals" type="button">Signals</button>
                    <button class="btn btn-outline-secondary btn-sm sa-tab" data-tab="reminders" type="button">Reminders</button>
                    <button class="btn btn-outline-secondary btn-sm sa-tab" data-tab="logs" type="button">Logs</button>
                </div>
                <div class="small text-secondary">Klik row untuk lihat detail (GET by id).</div>
            </div>

            <div class="mt-3">
                <div id="sa-panel-orders" class="sa-panel">
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
                        <button id="sa-orders-refresh" class="btn btn-outline-primary btn-sm" type="button">Refresh</button>
                        <div id="sa-orders-status" class="small text-secondary"></div>
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
                                    <th style="min-width: 120px;" class="text-end">Balance</th>
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
                        <button id="sa-signals-refresh" class="btn btn-outline-primary btn-sm" type="button">Refresh</button>
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
                        <button id="sa-reminders-refresh" class="btn btn-outline-primary btn-sm" type="button">Refresh</button>
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
                        <button id="sa-logs-refresh" class="btn btn-outline-primary btn-sm" type="button">Refresh</button>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <script src="{{ asset('js/signal-analytics/dashboard.js') }}" defer></script>
@endsection
