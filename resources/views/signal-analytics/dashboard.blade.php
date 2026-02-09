@extends('layouts.app')

@section('title', 'Signal & Analytics | DragonFortune')

@push('head')
    <style>
        .derivatives-header {
            margin-bottom: 0 !important;
            padding: 0.75rem 1rem !important;
        }

        .sa-overview-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .sa-method-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .sa-method-left {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 220px;
        }

        .sa-method-meta {
            flex: 1;
            min-width: 220px;
        }

        .sa-method-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sa-card {
            padding: 10px;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.03);
        }

        .dark .sa-card {
            background: rgba(255, 255, 255, 0.06);
        }

        .sa-binance-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-top: 6px;
        }

        .sa-stat {
            padding: 6px 8px;
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
            font-size: 15px;
            margin-top: 1px;
        }

        .sa-kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
        }

        .sa-kpi-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 6px 8px;
            border-radius: 10px;
            background: rgba(2, 6, 23, 0.04);
        }

        .dark .sa-kpi-item {
            background: rgba(2, 6, 23, 0.35);
        }

        .sa-kpi-item .value {
            font-weight: 700;
        }

        .sa-message-snippet {
            max-width: 350px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            white-space: normal;
            word-break: break-all;
            font-size: 12px;
            line-height: 1.4;
        }

        @media (max-width: 992px) {
            .sa-message-snippet {
                max-width: 200px;
                -webkit-line-clamp: 1;
            }
        }

        @media (max-width: 576px) {
            .sa-message-snippet {
                max-width: 150px;
            }
        }

        /* Modern Table Styling */
        .sa-data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }

        .sa-data-table thead th {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(139, 92, 246, 0.08) 100%);
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgba(71, 85, 105, 1);
            padding: 12px 10px;
            border-bottom: 2px solid rgba(59, 130, 246, 0.2);
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .dark .sa-data-table thead th {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.12) 0%, rgba(139, 92, 246, 0.12) 100%);
            color: rgba(148, 163, 184, 1);
            border-bottom-color: rgba(59, 130, 246, 0.3);
        }

        .sa-data-table tbody tr {
            transition: all 0.15s ease;
        }

        .sa-data-table tbody tr:nth-child(even) {
            background: rgba(241, 245, 249, 0.5);
        }

        .dark .sa-data-table tbody tr:nth-child(even) {
            background: rgba(30, 41, 59, 0.4);
        }

        .sa-data-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.12) !important;
        }

        .dark .sa-data-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.2) !important;
        }

        .sa-data-table tbody td {
            padding: 10px;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            vertical-align: middle;
        }

        .dark .sa-data-table tbody td {
            border-bottom-color: rgba(51, 65, 85, 0.6);
        }

        .sa-data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* DateTime column styling */
        .sa-data-table td:first-child {
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 12px;
            color: rgba(100, 116, 139, 1);
            white-space: nowrap;
        }

        .dark .sa-data-table td:first-child {
            color: rgba(148, 163, 184, 1);
        }

        /* Table container styling */
        .sa-table-wrapper {
            background: rgba(255, 255, 255, 0.6);
            border-radius: 12px;
            border: 1px solid rgba(226, 232, 240, 0.8);
            overflow: hidden;
        }

        .dark .sa-table-wrapper {
            background: rgba(15, 23, 42, 0.4);
            border-color: rgba(51, 65, 85, 0.6);
        }

        /* Pagination styling */
        .sa-pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 12px;
            background: rgba(241, 245, 249, 0.5);
            border-top: 1px solid rgba(226, 232, 240, 0.8);
        }

        .dark .sa-pagination {
            background: rgba(30, 41, 59, 0.4);
            border-top-color: rgba(51, 65, 85, 0.6);
        }

        .sa-pagination button {
            min-width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid rgba(203, 213, 225, 0.8);
            background: white;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .dark .sa-pagination button {
            background: rgba(30, 41, 59, 0.8);
            border-color: rgba(71, 85, 105, 0.6);
            color: rgba(226, 232, 240, 1);
        }

        .sa-pagination button:hover:not(:disabled) {
            background: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.4);
        }

        .sa-pagination button.active {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            border-color: transparent;
            color: white;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4);
        }

        .sa-pagination button:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* Empty state styling */
        .sa-empty-state {
            text-align: center;
            padding: 40px 20px;
            color: rgba(100, 116, 139, 1);
        }

        .dark .sa-empty-state {
            color: rgba(148, 163, 184, 1);
        }

        .sa-empty-state-icon {
            font-size: 32px;
            margin-bottom: 8px;
            opacity: 0.5;
        }

        .sa-status-text {
            font-weight: 800;
            font-size: 12px;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .sa-status-text.is-running {
            color: #22c55e;
        }

        .sa-status-text.is-stopped {
            color: #ef4444;
        }

        .sa-status-text.is-unknown {
            color: rgba(100, 116, 139, 1);
        }

        .dark .sa-status-text.is-unknown {
            color: rgba(148, 163, 184, 1);
        }

        .sa-binance-logo {
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            overflow: hidden;
            background: transparent;
        }

        .sa-qc-logo {
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            overflow: hidden;
            background: transparent;
        }

        .sa-logo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .sa-pagination {
            display: flex;
            justify-content: flex-end;
            gap: 6px;
            flex-wrap: wrap;
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

        .sa-modal-formatted {
            max-height: 70vh;
            overflow: auto;
        }

        .sa-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        @media (max-width: 768px) {
            .sa-detail-grid {
                grid-template-columns: 1fr;
            }
        }

        .sa-detail-card {
            background: rgba(2, 6, 23, 0.04);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 12px;
            padding: 12px;
        }

        .dark .sa-detail-card {
            background: rgba(2, 6, 23, 0.35);
        }

        .sa-detail-card-header {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .sa-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            font-size: 13px;
        }

        .sa-detail-row:not(:last-child) {
            border-bottom: 1px solid rgba(148, 163, 184, 0.08);
        }

        .sa-detail-label {
            color: rgba(100, 116, 139, 1);
        }

        .dark .sa-detail-label {
            color: rgba(148, 163, 184, 1);
        }

        .sa-detail-value {
            font-weight: 600;
            text-align: right;
        }

        .sa-detail-value.positive {
            color: #22c55e;
        }

        .sa-detail-value.negative {
            color: #ef4444;
        }

        .sa-detail-message {
            background: rgba(2, 6, 23, 0.04);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 12px;
            padding: 12px;
            margin-top: 12px;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 13px;
            max-height: 200px;
            overflow: auto;
            line-height: 1.5;
        }

        .dark .sa-detail-message {
            background: rgba(2, 6, 23, 0.35);
        }

        .sa-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .sa-badge.entry { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
        .sa-badge.exit { background: rgba(168, 85, 247, 0.15); color: #a855f7; }
        .sa-badge.buy, .sa-badge.long { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        .sa-badge.sell, .sa-badge.short { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

        /* Mobile modal improvements */
        @media (max-width: 576px) {
            .sa-modal__panel {
                margin: 16px;
                max-height: calc(100vh - 32px);
            }
            
            .sa-detail-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .sa-detail-card {
                padding: 10px;
            }
            
            .sa-detail-message {
                font-size: 12px;
                padding: 10px;
                max-height: 150px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-column h-100 gap-2">
        <div class="derivatives-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <h1 class="mb-0">Signal & Analytics</h1>
                </div>
                <div class="small text-secondary">
                    Status: <span id="sa-health" class="fw-semibold">-</span>
                    <span id="sa-health-meta" class="ms-2"></span>
                </div>
            </div>
        </div>

        <div class="df-panel p-2">
            <div class="visually-hidden">
                <code id="sa-api-base">-</code>
                <a id="sa-open-docs" href="#" target="_blank" rel="noopener">Docs</a>
            </div>

            <div class="sa-card sa-method-bar">
                <div class="sa-method-left">
                    <div class="small text-secondary">Metode</div>
                    <select id="sa-method-select" class="form-select form-select-sm">
                        <option value="">Loading...</option>
                    </select>
                </div>
                <div id="sa-method-meta" class="sa-method-meta small text-secondary">
                    Pair: - | TF: - | Exchange: - | Creator: -
                </div>
                <div class="sa-method-right">
                    <div class="small text-secondary">Status</div>
                    <div class="d-flex align-items-center gap-2">
                        <span id="sa-method-running" class="sa-status-text is-unknown">UNKNOWN</span>
                        <span id="sa-method-status" class="small text-secondary"></span>
                        <a id="sa-method-backtest" href="#" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm" style="display:none;">Preview</a>
                    </div>
                </div>
            </div>

            <div class="sa-overview-grid mt-2">
                <div class="sa-card d-flex flex-column h-100">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                        <div class="d-flex align-items-start gap-2">
                            <div class="sa-qc-logo" title="QuantConnect" aria-hidden="true">
                                <img class="sa-logo-img" src="{{ asset('images/qclogo.png') }}" alt="QuantConnect" />
                            </div>
                            <div class="d-flex flex-column">
                                <div class="fw-semibold">QuantConnect KPI</div>
                            </div>
                        </div>
                        <button id="sa-qc-detail" class="btn btn-outline-primary btn-sm" type="button">Detail</button>
                    </div>
                    <div id="sa-kpi-grid" class="sa-kpi-grid"></div>
                </div>

                <div class="sa-card d-flex flex-column h-100">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                        <div class="d-flex align-items-start gap-2">
                            <div class="sa-binance-logo" title="Binance" aria-hidden="true">
                                <img class="sa-logo-img" src="{{ asset('images/binancelogo.png') }}" alt="Binance" />
                            </div>
                            <div class="d-flex flex-column">
                                <div class="d-flex align-items-center gap-2">
                                    <div id="sa-binance-label" class="fw-semibold">Binance</div>
                                    <span id="sa-binance-live" class="badge text-bg-success">Live</span>
                                </div>
                            </div>
                        </div>
                        <button id="sa-binance-detail" class="btn btn-outline-primary btn-sm" type="button">Detail</button>
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
                </div>
            </div>
        </div>

        <div id="sa-detail" class="df-panel p-2" style="display:none;">
            <div id="sa-detail-qc" style="display:none;">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button class="btn btn-outline-secondary btn-sm sa-tab" data-tab="positions" type="button">Positions <span id="sa-count-positions" class="ms-1 text-secondary"></span></button>
                        <button class="btn btn-outline-secondary btn-sm sa-tab" data-tab="orders" type="button">Order History <span id="sa-count-orders" class="ms-1 text-secondary"></span></button>
                        <button class="btn btn-outline-secondary btn-sm sa-tab" data-tab="signals" type="button">Signals <span id="sa-count-signals" class="ms-1 text-secondary"></span></button>
                        <button class="btn btn-outline-secondary btn-sm sa-tab" data-tab="reminders" type="button">Reminders <span id="sa-count-reminders" class="ms-1 text-secondary"></span></button>
                        <button class="btn btn-outline-secondary btn-sm sa-tab is-active" data-tab="logs" type="button">Logs <span id="sa-count-logs" class="ms-1 text-secondary"></span></button>
                    </div>
                    <div class="small text-secondary">Klik row untuk lihat detail (GET by id).</div>
                </div>

                <div class="mt-3">
                <div id="sa-panel-positions" class="sa-panel" style="display:none;">
                    <div class="d-flex align-items-center justify-content-between flex-wrap mb-2">
                        <div class="small text-secondary">Position yang sedang terbuka.</div>
                        <div id="sa-positions-status" class="small text-secondary"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm sa-data-table align-middle mb-0">
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
                        <table class="table table-sm sa-data-table align-middle mb-0">
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
                                    <th style="min-width: 260px;">Message</th>
                                </tr>
                            </thead>
                            <tbody id="sa-orders-body" class="small"></tbody>
                        </table>
                    </div>
                    <div id="sa-orders-pagination" class="sa-pagination mt-2"></div>
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
                        <table class="table table-sm sa-data-table align-middle mb-0">
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
                                    <th style="min-width: 260px;">Message</th>
                                </tr>
                            </thead>
                            <tbody id="sa-signals-body" class="small"></tbody>
                        </table>
                    </div>
                    <div id="sa-signals-pagination" class="sa-pagination mt-2"></div>
                </div>

                <div id="sa-panel-reminders" class="sa-panel" style="display:none;">
                    <div class="d-flex align-items-end gap-2 flex-wrap mb-2">
                        <div id="sa-reminders-status" class="small text-secondary"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm sa-data-table align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th style="min-width: 170px;">Date Time</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody id="sa-reminders-body" class="small"></tbody>
                        </table>
                    </div>
                    <div id="sa-reminders-pagination" class="sa-pagination mt-2"></div>
                </div>

                <div id="sa-panel-logs" class="sa-panel">
                    <div class="d-flex align-items-end gap-2 flex-wrap mb-2">
                        <div id="sa-logs-status" class="small text-secondary"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm sa-data-table align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th style="min-width: 170px;">Date Time</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody id="sa-logs-body" class="small"></tbody>
                        </table>
                    </div>
                    <div id="sa-logs-pagination" class="sa-pagination mt-2"></div>
                </div>
            </div>
            </div>

            <div id="sa-detail-binance" style="display:none;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button class="btn btn-outline-secondary btn-sm sa-binance-tab is-active" data-tab="assets" type="button">Positions / Assets <span id="sa-count-binance-assets" class="ms-1 text-secondary"></span></button>
                    <button class="btn btn-outline-secondary btn-sm sa-binance-tab" data-tab="open-orders" type="button">Open Orders <span id="sa-count-binance-open-orders" class="ms-1 text-secondary"></span></button>
                    <button class="btn btn-outline-secondary btn-sm sa-binance-tab" data-tab="orders" type="button">Order History <span id="sa-count-binance-orders" class="ms-1 text-secondary"></span></button>
                    <button class="btn btn-outline-secondary btn-sm sa-binance-tab" data-tab="trades" type="button">Trades <span id="sa-count-binance-trades" class="ms-1 text-secondary"></span></button>
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
                        <table class="table table-sm sa-data-table align-middle mb-0">
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
                        <table class="table table-sm sa-data-table align-middle mb-0">
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
                        <table class="table table-sm sa-data-table align-middle mb-0">
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
                        <table class="table table-sm sa-data-table align-middle mb-0">
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
                    <div id="sa-modal-formatted" class="sa-modal-formatted"></div>
                    <pre id="sa-modal-pre" class="sa-pre" style="display:none;">Loading...</pre>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    @php
        $saDashboardJsPath = public_path('js/signal-analytics/dashboard.js');
        $saDashboardJsVersion = file_exists($saDashboardJsPath) ? filemtime($saDashboardJsPath) : null;
        $saDashboardJsSrc = asset('js/signal-analytics/dashboard.js') . ($saDashboardJsVersion ? ('?v=' . $saDashboardJsVersion) : '');
    @endphp
    <script src="{{ $saDashboardJsSrc }}" defer></script>
@endsection
