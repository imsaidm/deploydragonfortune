@extends('layouts.app')

@section('title', 'Signal Manager | DragonFortune')

@push('head')
    <style>
        .derivatives-header {
            margin-bottom: 0 !important;
            padding: 0.75rem 1rem !important;
        }

        .sm-overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 12px;
        }

        .sm-filters-bar {
            display: flex;
            align-items: end;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .sm-card {
            padding: 16px;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(148, 163, 184, 0.15);
        }

        .dark .sm-card {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(148, 163, 184, 0.18);
        }

        .sm-stat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 12px;
        }

        .sm-stat {
            padding: 8px 12px;
            border-radius: 8px;
            background: rgba(2, 6, 23, 0.04);
            text-align: center;
        }

        .dark .sm-stat {
            background: rgba(2, 6, 23, 0.35);
        }

        .sm-stat .label {
            font-size: 11px;
            color: rgba(100, 116, 139, 1);
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-bottom: 2px;
        }

        .dark .sm-stat .label {
            color: rgba(148, 163, 184, 1);
        }

        .sm-stat .value {
            font-weight: 700;
            font-size: 16px;
        }

        .sm-project-bar {
            display: flex;
            align-items: center;
            justify-content: between;
            gap: 12px;
            padding: 12px 16px;
            background: rgba(59, 130, 246, 0.08);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .dark .sm-project-bar {
            background: rgba(59, 130, 246, 0.12);
        }

        .sm-project-info {
            flex: 1;
            min-width: 0;
        }

        .sm-project-name {
            font-weight: 600;
            color: rgba(37, 99, 235, 1);
            margin-bottom: 2px;
        }

        .dark .sm-project-name {
            color: rgba(147, 197, 253, 1);
        }

        .sm-project-meta {
            font-size: 12px;
            color: rgba(100, 116, 139, 1);
        }

        .dark .sm-project-meta {
            color: rgba(148, 163, 184, 1);
        }

        .sm-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .sm-badge.live {
            background: rgba(34, 197, 94, 0.15);
            color: rgba(21, 128, 61, 1);
        }

        .dark .sm-badge.live {
            background: rgba(34, 197, 94, 0.2);
            color: rgba(74, 222, 128, 1);
        }

        .sm-badge.backtest {
            background: rgba(245, 158, 11, 0.15);
            color: rgba(146, 64, 14, 1);
        }

        .dark .sm-badge.backtest {
            background: rgba(245, 158, 11, 0.2);
            color: rgba(251, 191, 36, 1);
        }

        .sm-badge.active {
            background: rgba(34, 197, 94, 0.15);
            color: rgba(21, 128, 61, 1);
        }

        .dark .sm-badge.active {
            background: rgba(34, 197, 94, 0.2);
            color: rgba(74, 222, 128, 1);
        }

        .sm-badge.stopped {
            background: rgba(107, 114, 128, 0.15);
            color: rgba(55, 65, 81, 1);
        }

        .dark .sm-badge.stopped {
            background: rgba(107, 114, 128, 0.2);
            color: rgba(156, 163, 175, 1);
        }

        .sm-badge.error {
            background: rgba(239, 68, 68, 0.15);
            color: rgba(153, 27, 27, 1);
        }

        .dark .sm-badge.error {
            background: rgba(239, 68, 68, 0.2);
            color: rgba(248, 113, 113, 1);
        }

        .sm-badge.inactive {
            background: rgba(107, 114, 128, 0.15);
            color: rgba(55, 65, 81, 1);
        }

        .dark .sm-badge.inactive {
            background: rgba(107, 114, 128, 0.2);
            color: rgba(156, 163, 175, 1);
        }

        .sm-badge.idle {
            background: rgba(245, 158, 11, 0.15);
            color: rgba(146, 64, 14, 1);
        }

        .dark .sm-badge.idle {
            background: rgba(245, 158, 11, 0.2);
            color: rgba(251, 191, 36, 1);
        }

        .sm-pnl.positive {
            color: rgba(34, 197, 94, 1);
        }

        .sm-pnl.negative {
            color: rgba(239, 68, 68, 1);
        }

        .sm-pnl.neutral {
            color: rgba(107, 114, 128, 1);
        }

        .dark .sm-pnl.neutral {
            color: rgba(156, 163, 175, 1);
        }

        .sm-modal {
            position: fixed;
            inset: 0;
            display: none;
            z-index: 9999;
        }

        .sm-modal.is-open {
            display: block;
        }

        .sm-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
        }

        .sm-modal__panel {
            position: relative;
            max-width: 1200px;
            max-height: 90vh;
            margin: 24px auto;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.25);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .dark .sm-modal__panel {
            background: rgba(15, 23, 42, 0.98);
            border-color: rgba(148, 163, 184, 0.18);
        }

        .sm-modal__header {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
            flex-shrink: 0;
        }

        .sm-modal__body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }

        .sm-project-table {
            width: 100%;
        }

        .sm-project-table th,
        .sm-project-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(148, 163, 184, 0.15);
        }

        .sm-project-table th {
            background: rgba(2, 6, 23, 0.04);
            font-weight: 600;
            font-size: 12px;
            color: rgba(100, 116, 139, 1);
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .dark .sm-project-table th {
            background: rgba(2, 6, 23, 0.35);
            color: rgba(148, 163, 184, 1);
        }

        .sm-project-table tbody tr:hover {
            background: rgba(2, 6, 23, 0.02);
        }

        .dark .sm-project-table tbody tr:hover {
            background: rgba(2, 6, 23, 0.2);
        }

        .sm-pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
        }

        .sm-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: rgba(100, 116, 139, 1);
        }

        .dark .sm-loading {
            color: rgba(148, 163, 184, 1);
        }

        /* Smooth transition for status updates */
        #sm-health, #sm-health-meta, .sm-badge {
            transition: all 0.3s ease-in-out;
        }

        /* Pulse animation for syncing indicator */
        @keyframes syncPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .syncing {
            animation: syncPulse 1s ease-in-out infinite;
        }

        /* Glow effect when status changes */
        @keyframes statusGlow {
            0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
            100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }

        .status-updated {
            animation: statusGlow 0.6s ease-out;
        }

        /* Live indicator dot */
        .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }

        .live-dot.running {
            background: #22c55e;
            box-shadow: 0 0 6px #22c55e;
            animation: livePulse 2s ease-in-out infinite;
        }

        .live-dot.stopped {
            background: #6b7280;
        }

        .live-dot.error {
            background: #ef4444;
            box-shadow: 0 0 6px #ef4444;
        }

        @keyframes livePulse {
            0%, 100% { opacity: 1; box-shadow: 0 0 6px #22c55e; }
            50% { opacity: 0.7; box-shadow: 0 0 12px #22c55e; }
        }

        /* Spin animation for sync button */
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Data update fade animation */
        @keyframes dataUpdate {
            0% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        .data-updated {
            animation: dataUpdate 0.3s ease-out;
        }

        /* Cache indicator */
        .cache-indicator {
            display: inline-flex;
            align-items: center;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 6px;
        }

        .cache-indicator.cached {
            background: rgba(245, 158, 11, 0.15);
            color: rgba(146, 64, 14, 1);
        }

        .dark .cache-indicator.cached {
            background: rgba(245, 158, 11, 0.2);
            color: rgba(251, 191, 36, 1);
        }

        .cache-indicator.live {
            background: rgba(34, 197, 94, 0.15);
            color: rgba(21, 128, 61, 1);
        }

        .dark .cache-indicator.live {
            background: rgba(34, 197, 94, 0.2);
            color: rgba(74, 222, 128, 1);
        }

        /* Data Tabs Styling */
        .sm-data-tab {
            background: transparent;
            border: 1px solid transparent;
            color: rgba(100, 116, 139, 1);
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .dark .sm-data-tab {
            color: rgba(148, 163, 184, 1);
        }

        .sm-data-tab:hover {
            background: rgba(59, 130, 246, 0.08);
            color: rgba(59, 130, 246, 1);
        }

        .sm-data-tab.active {
            background: rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.3);
            color: rgba(59, 130, 246, 1);
        }

        .dark .sm-data-tab.active {
            background: rgba(59, 130, 246, 0.2);
            color: rgba(147, 197, 253, 1);
        }

        .sm-data-panel {
            min-height: 300px;
        }

        /* Log message styling */
        .sm-log-message {
            max-width: 100%;
            word-break: break-word;
            white-space: pre-wrap;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 12px;
        }

        .sm-log-trade {
            color: rgba(34, 197, 94, 1);
        }

        .sm-log-warning {
            color: rgba(245, 158, 11, 1);
        }

        .sm-log-error {
            color: rgba(239, 68, 68, 1);
        }

        .sm-log-info {
            color: rgba(59, 130, 246, 1);
        }

        /* Direction badges */
        .sm-direction-buy {
            color: #22c55e;
            font-weight: 600;
        }

        .sm-direction-sell {
            color: #ef4444;
            font-weight: 600;
        }

        /* Status badges for orders */
        .sm-status-filled {
            background: rgba(34, 197, 94, 0.15);
            color: rgba(21, 128, 61, 1);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
        }

        .dark .sm-status-filled {
            background: rgba(34, 197, 94, 0.2);
            color: rgba(74, 222, 128, 1);
        }

        .sm-status-canceled {
            background: rgba(107, 114, 128, 0.15);
            color: rgba(55, 65, 81, 1);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
        }

        .dark .sm-status-canceled {
            background: rgba(107, 114, 128, 0.2);
            color: rgba(156, 163, 175, 1);
        }

        #sm-sync-btn {
            padding: 2px 6px;
            font-size: 12px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        #sm-sync-btn:hover:not(:disabled) {
            background: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.5);
        }

        #sm-sync-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .sm-empty {
            text-align: center;
            padding: 40px;
            color: rgba(100, 116, 139, 1);
        }

        .dark .sm-empty {
            color: rgba(148, 163, 184, 1);
        }

        @media (max-width: 768px) {
            .sm-overview-grid {
                grid-template-columns: 1fr;
            }

            .sm-filters-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .sm-stat-grid {
                grid-template-columns: 1fr;
            }

            .sm-project-bar {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
        }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-column h-100 gap-2">
        <div class="derivatives-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <h1 class="mb-0">Signal Manager</h1>
                </div>
                <div class="small text-secondary d-flex align-items-center gap-2">
                    <span class="live-dot stopped" id="sm-live-dot"></span>
                    <span>Status:</span>
                    <span id="sm-health" class="fw-semibold">-</span>
                    <span id="sm-data-source" class="cache-indicator cached" style="display: none;">CACHED</span>
                    <span id="sm-health-meta" class="ms-2"></span>
                    <button id="sm-sync-btn" class="btn btn-sm btn-outline-secondary ms-2" title="Sync Status">
                        <i class="bi bi-arrow-repeat" id="sm-sync-icon"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Selected Project Bar -->
        <div id="sm-project-bar" class="sm-project-bar" style="display: none;">
            <div class="sm-project-info">
                <div id="sm-project-name" class="sm-project-name">No Project Selected</div>
                <div id="sm-project-meta" class="sm-project-meta">Select a project to monitor signals</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span id="sm-project-type" class="sm-badge backtest">Backtest</span>
                <span id="sm-project-status" class="sm-badge stopped">Stopped</span>
                <span id="sm-project-activity" class="sm-badge inactive" style="display: none;">Inactive</span>
                <button id="sm-change-project" class="btn btn-outline-primary btn-sm" type="button">
                    Change Project
                </button>
                <button id="sm-project-details" class="btn btn-outline-info btn-sm" type="button">
                    Details
                </button>
            </div>
        </div>

        <!-- QuantConnect KPI Card -->
        <div class="df-panel p-2" id="sm-kpi-panel">
            <div class="sm-card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(99, 102, 241, 0.05) 100%);">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #6366f1); color: white; font-weight: bold; font-size: 12px;">QC</div>
                        <h6 class="mb-0 fw-semibold">QuantConnect KPI</h6>
                        <span id="kpi-data-source" class="badge bg-secondary small" style="font-size: 10px; display: none;">-</span>
                    </div>
                    <button id="sm-kpi-detail" class="btn btn-primary btn-sm" type="button">
                        Detail
                    </button>
                </div>
                
                <div class="row g-2" id="sm-kpi-grid">
                    <!-- Row 1 -->
                    <div class="col-md-4 col-6">
                        <div class="sm-stat" style="background: rgba(59, 130, 246, 0.08); border-left: 3px solid #3b82f6;">
                            <div class="label">Sharpe Ratio</div>
                            <div id="kpi-sharpe" class="value text-primary">-</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="sm-stat" style="background: rgba(59, 130, 246, 0.08); border-left: 3px solid #3b82f6;">
                            <div class="label">Sortino Ratio</div>
                            <div id="kpi-sortino" class="value text-primary">-</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="sm-stat" style="background: rgba(34, 197, 94, 0.08); border-left: 3px solid #22c55e;">
                            <div class="label">CAGR</div>
                            <div id="kpi-cagr" class="value sm-pnl positive">-</div>
                        </div>
                    </div>
                    
                    <!-- Row 2 -->
                    <div class="col-md-4 col-6">
                        <div class="sm-stat" style="background: rgba(239, 68, 68, 0.08); border-left: 3px solid #ef4444;">
                            <div class="label">Drawdown</div>
                            <div id="kpi-drawdown" class="value sm-pnl negative">-</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="sm-stat" style="background: rgba(59, 130, 246, 0.08); border-left: 3px solid #3b82f6;">
                            <div class="label">Probabilistic SR</div>
                            <div id="kpi-psr" class="value text-primary">-</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="sm-stat" style="background: rgba(34, 197, 94, 0.08); border-left: 3px solid #22c55e;">
                            <div class="label">Win Rate</div>
                            <div id="kpi-winrate" class="value sm-pnl positive">-</div>
                        </div>
                    </div>
                    
                    <!-- Row 3 -->
                    <div class="col-md-4 col-6">
                        <div class="sm-stat" style="background: rgba(239, 68, 68, 0.08); border-left: 3px solid #ef4444;">
                            <div class="label">Loss Rate</div>
                            <div id="kpi-lossrate" class="value sm-pnl negative">-</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="sm-stat" style="background: rgba(107, 114, 128, 0.08); border-left: 3px solid #6b7280;">
                            <div class="label">Total Orders</div>
                            <div id="kpi-orders" class="value">-</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="sm-stat" style="background: rgba(107, 114, 128, 0.08); border-left: 3px solid #6b7280;">
                            <div class="label">Turnover</div>
                            <div id="kpi-turnover" class="value">-</div>
                        </div>
                    </div>
                </div>
                
                <div id="sm-kpi-loading" class="text-center py-3" style="display: none;">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    <span class="small text-secondary">Loading KPI from QuantConnect...</span>
                </div>
                
                <div id="sm-kpi-error" class="alert alert-warning py-2 mt-2 mb-0 small" style="display: none;">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <span id="sm-kpi-error-text">No live data available</span>
                </div>
            </div>
        </div>

        <!-- Filters and Controls -->
        <div class="df-panel p-2">
            <div class="sm-filters-bar">
                <div class="d-flex flex-column">
                    <label class="small text-secondary mb-1" for="sm-filter-symbol">Symbol</label>
                    <input id="sm-filter-symbol" class="form-control form-control-sm" placeholder="e.g. BTCUSDT" style="width: 120px;" />
                </div>
                <div class="d-flex flex-column">
                    <label class="small text-secondary mb-1" for="sm-filter-type">Signal Type</label>
                    <select id="sm-filter-type" class="form-select form-select-sm" style="width: 120px;">
                        <option value="">All Types</option>
                        <option value="entry">Entry</option>
                        <option value="exit">Exit</option>
                        <option value="update">Update</option>
                        <option value="error">Error</option>
                    </select>
                </div>
                <div class="d-flex flex-column">
                    <label class="small text-secondary mb-1" for="sm-filter-start-date">Start Date</label>
                    <input id="sm-filter-start-date" class="form-control form-control-sm" type="date" style="width: 140px;" />
                </div>
                <div class="d-flex flex-column">
                    <label class="small text-secondary mb-1" for="sm-filter-end-date">End Date</label>
                    <input id="sm-filter-end-date" class="form-control form-control-sm" type="date" style="width: 140px;" />
                </div>
                <div class="d-flex flex-column">
                    <label class="small text-secondary mb-1">&nbsp;</label>
                    <button id="sm-apply-filters" class="btn btn-outline-primary btn-sm" type="button">
                        Apply Filters
                    </button>
                </div>
                <div class="d-flex flex-column">
                    <label class="small text-secondary mb-1">&nbsp;</label>
                    <button id="sm-clear-filters" class="btn btn-outline-secondary btn-sm" type="button">
                        Clear
                    </button>
                </div>
                <div class="d-flex flex-column ms-auto">
                    <label class="small text-secondary mb-1">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button id="sm-export-json" class="btn btn-outline-success btn-sm" type="button">
                            Export JSON
                        </button>
                        <button id="sm-export-csv" class="btn btn-outline-success btn-sm" type="button">
                            Export CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Tabs Panel (Holdings, Orders, Signals, Logs) -->
        <div class="df-panel p-2 flex-fill">
            <!-- Tabs Navigation -->
            <div class="d-flex align-items-center justify-content-between mb-2 border-bottom pb-2">
                <div class="d-flex gap-1">
                    <button class="btn btn-sm sm-data-tab active" data-tab="holdings" type="button">
                        Holdings <span id="sm-count-holdings" class="text-secondary">(0)</span>
                    </button>
                    <button class="btn btn-sm sm-data-tab" data-tab="orders" type="button">
                        Order History <span id="sm-count-orders" class="text-secondary">(0)</span>
                    </button>
                    <button class="btn btn-sm sm-data-tab" data-tab="signals" type="button">
                        Signals <span id="sm-count-signals" class="text-secondary">(0)</span>
                    </button>
                    <button class="btn btn-sm sm-data-tab" data-tab="logs" type="button">
                        Logs <span id="sm-count-logs" class="text-secondary">(0)</span>
                    </button>
                </div>
                <div class="small text-secondary">
                    Klik row untuk lihat detail (GET by id).
                </div>
            </div>

            <!-- Tab: Holdings -->
            <div id="sm-panel-holdings" class="sm-data-panel" style="display: block;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="mb-0 fw-semibold">Portfolio Holdings</h6>
                        <span id="sm-holdings-cash" class="badge bg-success">Cash: -</span>
                    </div>
                    <button id="sm-refresh-holdings" class="btn btn-outline-primary btn-sm" type="button">Refresh</button>
                </div>
                <div id="sm-holdings-loading" class="sm-loading" style="display: none;">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    Loading holdings...
                </div>
                <div id="sm-holdings-empty" class="sm-empty">
                    <div class="mb-2">💼</div>
                    <div class="fw-semibold mb-1">No Holdings</div>
                    <div class="small">No active positions in portfolio</div>
                </div>
                <div id="sm-holdings-table-container" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th>Symbol</th>
                                    <th class="text-end">Quantity</th>
                                    <th class="text-end">Avg Price</th>
                                    <th class="text-end">Market Price</th>
                                    <th class="text-end">Market Value</th>
                                    <th class="text-end">Unrealized PnL</th>
                                </tr>
                            </thead>
                            <tbody id="sm-holdings-body" class="small"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Orders -->
            <div id="sm-panel-orders" class="sm-data-panel" style="display: none;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="mb-0 fw-semibold">Order History (from QuantConnect)</h6>
                    <button id="sm-refresh-orders" class="btn btn-outline-primary btn-sm" type="button">Refresh</button>
                </div>
                <div id="sm-orders-loading" class="sm-loading" style="display: none;">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    Loading orders...
                </div>
                <div id="sm-orders-empty" class="sm-empty">
                    <div class="mb-2">📦</div>
                    <div class="fw-semibold mb-1">No Orders</div>
                    <div class="small">No order history found for this project</div>
                </div>
                <div id="sm-orders-table-container" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th style="min-width: 60px;">ID</th>
                                    <th style="min-width: 150px;">Time</th>
                                    <th style="min-width: 100px;">Symbol</th>
                                    <th style="min-width: 70px;">Direction</th>
                                    <th class="text-end" style="min-width: 80px;">Quantity</th>
                                    <th class="text-end" style="min-width: 100px;">Price</th>
                                    <th class="text-end" style="min-width: 100px;">Value</th>
                                    <th class="text-end" style="min-width: 80px;">Fee</th>
                                    <th style="min-width: 80px;">Status</th>
                                    <th style="min-width: 120px;">Tag</th>
                                </tr>
                            </thead>
                            <tbody id="sm-orders-body" class="small"></tbody>
                        </table>
                    </div>
                    <div id="sm-orders-pagination" class="sm-pagination"></div>
                </div>
            </div>

            <!-- Tab: Signals -->
            <div id="sm-panel-signals" class="sm-data-panel" style="display: none;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="mb-0 fw-semibold">Trading Signals (Webhook Data)</h6>
                    <div class="d-flex align-items-center gap-2">
                        <div class="small text-secondary">
                            <span id="sm-signals-count">0</span> signals
                        </div>
                        <button id="sm-refresh-signals" class="btn btn-outline-primary btn-sm" type="button">
                            Refresh
                        </button>
                    </div>
                </div>

                <div id="sm-signals-loading" class="sm-loading" style="display: none;">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    Loading signals...
                </div>

                <div id="sm-signals-empty" class="sm-empty" style="display: none;">
                    <div class="mb-2">📊</div>
                    <div class="fw-semibold mb-1">No Signals Found</div>
                    <div class="small">Select a project and apply filters to view trading signals</div>
                </div>

                <div id="sm-signals-table-container" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th style="min-width: 160px;">Timestamp</th>
                                    <th style="min-width: 120px;">Project</th>
                                    <th style="min-width: 100px;">Symbol</th>
                                    <th style="min-width: 80px;">Type</th>
                                    <th style="min-width: 80px;">Action</th>
                                    <th style="min-width: 120px;" class="text-end">Price</th>
                                    <th style="min-width: 100px;" class="text-end">Quantity</th>
                                    <th style="min-width: 120px;" class="text-end">Target</th>
                                    <th style="min-width: 120px;" class="text-end">Stop Loss</th>
                                    <th style="min-width: 120px;" class="text-end">PnL</th>
                                    <th style="min-width: 200px;">Message</th>
                                </tr>
                            </thead>
                            <tbody id="sm-signals-body" class="small"></tbody>
                        </table>
                    </div>

                    <div id="sm-signals-pagination" class="sm-pagination"></div>
                </div>
            </div>

            <!-- Tab: Logs -->
            <div id="sm-panel-logs" class="sm-data-panel" style="display: none;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="mb-0 fw-semibold">Live Logs (from QuantConnect)</h6>
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex flex-column">
                            <input id="sm-logs-search" class="form-control form-control-sm" placeholder="Cari message atau waktu" style="width: 200px;">
                        </div>
                        <div class="d-flex flex-column">
                            <select id="sm-logs-page-size" class="form-select form-select-sm" style="width: 80px;">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50" selected>50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <span id="sm-logs-status" class="small text-secondary"></span>
                        <button id="sm-refresh-logs" class="btn btn-outline-primary btn-sm" type="button">Refresh</button>
                    </div>
                </div>
                <div id="sm-logs-loading" class="sm-loading" style="display: none;">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    Loading logs...
                </div>
                <div id="sm-logs-empty" class="sm-empty">
                    <div class="mb-2">📜</div>
                    <div class="fw-semibold mb-1">No Logs</div>
                    <div class="small">No logs found for this live algorithm</div>
                </div>
                <div id="sm-logs-table-container" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th style="min-width: 140px;">Date Time</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody id="sm-logs-body" class="small"></tbody>
                        </table>
                    </div>
                    <div id="sm-logs-pagination" class="sm-pagination"></div>
                </div>
            </div>
        </div>

        <!-- Project Selection Modal -->
        <div id="sm-project-modal" class="sm-modal" aria-hidden="true">
            <div class="sm-modal__backdrop" data-close="1"></div>
            <div class="sm-modal__panel">
                <div class="sm-modal__header">
                    <div class="fw-semibold">Select QuantConnect Project</div>
                    <button id="sm-project-modal-close" class="btn btn-outline-secondary btn-sm" type="button">
                        Close
                    </button>
                </div>
                <div class="sm-modal__body">
                    <div id="sm-projects-loading" class="sm-loading">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                        Loading projects...
                    </div>

                    <div id="sm-projects-error" class="alert alert-danger" style="display: none;">
                        <div class="fw-semibold mb-1">Failed to Load Projects</div>
                        <div id="sm-projects-error-message" class="small"></div>
                    </div>

                    <div id="sm-projects-table-container" style="display: none;">
                        <!-- Project Search and Filters -->
                        <div class="mb-3">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <input id="sm-project-search" class="form-control form-control-sm" 
                                           placeholder="Search projects by name or description..." />
                                </div>
                                <div class="col-md-3">
                                    <select id="sm-project-type-filter" class="form-select form-select-sm">
                                        <option value="">All Types</option>
                                        <option value="live">Live Trading</option>
                                        <option value="backtest">Paper/Backtest</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select id="sm-project-status-filter" class="form-select form-select-sm">
                                        <option value="">All Status</option>
                                        <option value="running">Running</option>
                                        <option value="stopped">Stopped</option>
                                        <option value="error">Runtime Error</option>
                                        <option value="not_selected">Not Selected</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="sm-project-table">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Description</th>
                                        <th>Language</th>
                                        <th>Modified</th>
                                        <th>Status</th>
                                        <th>Signals</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="sm-projects-body"></tbody>
                            </table>
                        </div>

                        <div id="sm-projects-empty" class="text-center py-4" style="display: none;">
                            <div class="text-muted">
                                <div class="mb-2">🔍</div>
                                <div>No projects found matching your criteria</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Details Modal -->
        <div id="sm-project-details-modal" class="sm-modal" aria-hidden="true">
            <div class="sm-modal__backdrop" data-close-details="1"></div>
            <div class="sm-modal__panel">
                <div class="sm-modal__header">
                    <div class="fw-semibold">Project Details</div>
                    <button id="sm-project-details-modal-close" class="btn btn-outline-secondary btn-sm" type="button">
                        Close
                    </button>
                </div>
                <div class="sm-modal__body">
                    <div id="sm-project-details-loading" class="sm-loading">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                        Loading project details...
                    </div>

                    <div id="sm-project-details-error" class="alert alert-danger" style="display: none;">
                        <div class="fw-semibold mb-1">Failed to Load Project Details</div>
                        <div id="sm-project-details-error-message" class="small"></div>
                    </div>

                    <div id="sm-project-details-content" style="display: none;">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="sm-card">
                                    <h6 class="mb-2 fw-semibold">Project Information</h6>
                                    <div class="small">
                                        <div class="mb-1"><strong>ID:</strong> <span id="detail-project-id">-</span></div>
                                        <div class="mb-1"><strong>Name:</strong> <span id="detail-project-name">-</span></div>
                                        <div class="mb-1"><strong>Type:</strong> <span id="detail-project-type">-</span></div>
                                        <div class="mb-1"><strong>Status:</strong> <span id="detail-project-status">-</span></div>
                                        <div class="mb-1"><strong>Activity:</strong> <span id="detail-project-activity">-</span></div>
                                        <div class="mb-1"><strong>Last Signal:</strong> <span id="detail-last-signal">-</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="sm-card">
                                    <h6 class="mb-2 fw-semibold">Performance Summary</h6>
                                    <div class="sm-stat-grid">
                                        <div class="sm-stat">
                                            <div class="label">Total Signals</div>
                                            <div id="detail-total-signals" class="value">-</div>
                                        </div>
                                        <div class="sm-stat">
                                            <div class="label">Total PnL</div>
                                            <div id="detail-total-pnl" class="value sm-pnl neutral">-</div>
                                        </div>
                                        <div class="sm-stat">
                                            <div class="label">Win Rate</div>
                                            <div id="detail-win-rate" class="value">-</div>
                                        </div>
                                        <div class="sm-stat">
                                            <div class="label">Profitable</div>
                                            <div id="detail-profitable" class="value sm-pnl positive">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="sm-card">
                                <h6 class="mb-2 fw-semibold">Recent Signals (Last 24h)</h6>
                                <div id="detail-recent-signals" class="small">
                                    <div class="text-muted">Loading recent signals...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Signal Manager Dashboard JavaScript
        class SignalManagerDashboard {
            constructor() {
                // Signal Manager uses LOCAL Laravel routes, not external API
                // Always use relative URL (empty = same origin)
                this.apiBaseUrl = '';
                this.selectedProject = null;
                this.currentFilters = {};
                this.currentPage = 1;
                this.signalsData = [];
                this.allProjects = []; // Store all projects for filtering
                
                // Data tabs properties
                this.currentTab = 'holdings';
                this.holdingsData = [];
                this.ordersData = [];
                this.logsData = [];
                this.logsPageSize = 50;
                this.logsSearchTerm = '';
                
                // LocalStorage keys for caching
                this.STORAGE_KEYS = {
                    SELECTED_PROJECT: 'sm_selected_project',
                    SIGNALS: 'sm_signals',
                    PROJECTS: 'sm_projects',
                    LAST_SYNC: 'sm_last_sync',
                    HOLDINGS: 'sm_holdings',
                    ORDERS: 'sm_orders',
                    LOGS: 'sm_logs'
                };
                
                this.init();
            }

            init() {
                this.bindEvents();
                
                // Set default date range (last 7 days)
                const endDate = new Date();
                const startDate = new Date();
                startDate.setDate(startDate.getDate() - 7);
                
                document.getElementById('sm-filter-start-date').value = startDate.toISOString().split('T')[0];
                document.getElementById('sm-filter-end-date').value = endDate.toISOString().split('T')[0];
                
                // STEP 1: Load cached data from localStorage FIRST (instant display)
                this.loadFromCache();
                
                // STEP 2: Then fetch fresh data from API (background update)
                this.loadSelectedProject();
                this.syncProjectStatus();
            }
            
            // ==================== LOCAL STORAGE HELPERS ====================
            
            /**
             * Save data to localStorage with timestamp
             */
            saveToCache(key, data) {
                try {
                    const cacheData = {
                        data: data,
                        timestamp: Date.now()
                    };
                    localStorage.setItem(key, JSON.stringify(cacheData));
                } catch (e) {
                    console.warn('[Cache] Failed to save:', key, e);
                }
            }
            
            /**
             * Load data from localStorage
             * @param {string} key - Storage key
             * @param {number} maxAge - Max age in milliseconds (default 5 minutes)
             */
            getFromCache(key, maxAge = 5 * 60 * 1000) {
                try {
                    const cached = localStorage.getItem(key);
                    if (!cached) return null;
                    
                    const { data, timestamp } = JSON.parse(cached);
                    const age = Date.now() - timestamp;
                    
                    // Return data even if expired (we'll refresh anyway)
                    // But log if it's stale
                    if (age > maxAge) {
                        console.log(`[Cache] ${key} is stale (${Math.round(age/1000)}s old)`);
                    }
                    
                    return data;
                } catch (e) {
                    console.warn('[Cache] Failed to load:', key, e);
                    return null;
                }
            }
            
            /**
             * Load all cached data and display immediately
             */
            loadFromCache() {
                console.log('[Cache] Loading cached data...');
                
                const dataSourceEl = document.getElementById('sm-data-source');
                let hasCachedData = false;
                
                // 1. Load cached selected project
                const cachedProject = this.getFromCache(this.STORAGE_KEYS.SELECTED_PROJECT);
                if (cachedProject) {
                    console.log('[Cache] Found cached project:', cachedProject.project_name);
                    this.selectedProject = cachedProject;
                    this.currentFilters = { project_id: cachedProject.project_id };
                    this.updateProjectBar();
                    hasCachedData = true;
                }
                
                // 2. Load cached signals
                const cachedSignals = this.getFromCache(this.STORAGE_KEYS.SIGNALS);
                if (cachedSignals && cachedSignals.data && cachedSignals.data.length > 0) {
                    console.log('[Cache] Found cached signals:', cachedSignals.data.length);
                    this.signalsData = cachedSignals.data;
                    this.renderSignals(cachedSignals.data, cachedSignals.pagination);
                    document.getElementById('sm-signals-table-container').style.display = 'block';
                    document.getElementById('sm-signals-count').textContent = cachedSignals.pagination?.total || 0;
                    document.getElementById('sm-count-signals').textContent = `(${cachedSignals.pagination?.total || 0})`;
                    hasCachedData = true;
                }
                
                // 4. Load cached KPI
                const cachedKpi = this.getFromCache('sm_kpi');
                if (cachedKpi) {
                    console.log('[Cache] Found cached KPI');
                    this.updateKpiDisplay(cachedKpi);
                    hasCachedData = true;
                }
                
                // 5. Show cache indicator if we have cached data
                if (hasCachedData && dataSourceEl) {
                    dataSourceEl.style.display = 'inline-flex';
                    dataSourceEl.className = 'cache-indicator cached';
                    dataSourceEl.textContent = 'CACHED';
                }
                
                // 6. Show last sync time
                const lastSync = this.getFromCache(this.STORAGE_KEYS.LAST_SYNC, Infinity);
                if (lastSync) {
                    const healthMetaEl = document.getElementById('sm-health-meta');
                    healthMetaEl.textContent = `Last sync: ${new Date(lastSync).toLocaleTimeString()}`;
                }
                
                // 7. Load cached Holdings
                const cachedHoldings = this.getFromCache(this.STORAGE_KEYS.HOLDINGS);
                if (cachedHoldings) {
                    console.log('[Cache] Found cached holdings');
                    this.holdingsData = cachedHoldings.holdings || [];
                    document.getElementById('sm-count-holdings').textContent = `(${this.holdingsData.length})`;
                    if (cachedHoldings.summary?.cash) {
                        const usdtCash = cachedHoldings.summary.cash.USDT?.amount || cachedHoldings.summary.cash.USD?.amount || 0;
                        document.getElementById('sm-holdings-cash').textContent = `Cash: $${Number(usdtCash).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    }
                    this.renderHoldings();
                    hasCachedData = true;
                }
                
                // 8. Load cached Orders
                const cachedOrders = this.getFromCache(this.STORAGE_KEYS.ORDERS);
                if (cachedOrders && cachedOrders.length > 0) {
                    console.log('[Cache] Found cached orders:', cachedOrders.length);
                    this.ordersData = cachedOrders;
                    document.getElementById('sm-count-orders').textContent = `(${this.ordersData.length})`;
                    this.renderOrders();
                    hasCachedData = true;
                }
                
                // 9. Load cached Logs
                const cachedLogs = this.getFromCache(this.STORAGE_KEYS.LOGS);
                if (cachedLogs && cachedLogs.length > 0) {
                    console.log('[Cache] Found cached logs:', cachedLogs.length);
                    this.logsData = cachedLogs;
                    document.getElementById('sm-count-logs').textContent = `(${this.logsData.length})`;
                    this.renderLogs();
                    hasCachedData = true;
                }
                
                // 10. Fetch fresh data in background if project selected
                if (this.selectedProject) {
                    this.loadHoldings();
                    this.loadOrders();
                    this.loadLogs();
                }
            }

            bindEvents() {
                // Project selection
                document.getElementById('sm-change-project').addEventListener('click', () => this.openProjectModal());
                
                // Manual sync button
                document.getElementById('sm-sync-btn').addEventListener('click', () => this.syncProjectStatus());
                
                // KPI Detail button
                document.getElementById('sm-kpi-detail')?.addEventListener('click', () => this.openKpiDetailModal());
                
                // Modal controls
                document.getElementById('sm-project-modal-close').addEventListener('click', () => this.closeProjectModal());
                document.querySelector('[data-close="1"]').addEventListener('click', () => this.closeProjectModal());
                
                // Project details modal
                document.getElementById('sm-project-details').addEventListener('click', () => this.openProjectDetailsModal());
                document.getElementById('sm-project-details-modal-close').addEventListener('click', () => this.closeProjectDetailsModal());
                document.querySelector('[data-close-details="1"]').addEventListener('click', () => this.closeProjectDetailsModal());
                
                // Project filtering
                document.getElementById('sm-project-search').addEventListener('input', () => this.filterProjects());
                document.getElementById('sm-project-type-filter').addEventListener('change', () => this.filterProjects());
                document.getElementById('sm-project-status-filter').addEventListener('change', () => this.filterProjects());
                
                // Filters
                document.getElementById('sm-apply-filters').addEventListener('click', () => this.applyFilters());
                document.getElementById('sm-clear-filters').addEventListener('click', () => this.clearFilters());
                
                // Export
                document.getElementById('sm-export-json').addEventListener('click', () => this.exportSignals('json'));
                document.getElementById('sm-export-csv').addEventListener('click', () => this.exportSignals('csv'));
                
                // Refresh buttons
                document.getElementById('sm-refresh-signals').addEventListener('click', () => this.loadSignals());
                document.getElementById('sm-refresh-holdings')?.addEventListener('click', () => this.loadHoldings());
                document.getElementById('sm-refresh-orders')?.addEventListener('click', () => this.loadOrders());
                document.getElementById('sm-refresh-logs')?.addEventListener('click', () => this.loadLogs());
                
                // Data Tabs
                document.querySelectorAll('.sm-data-tab').forEach(tab => {
                    tab.addEventListener('click', (e) => this.switchDataTab(e.target.dataset.tab));
                });
                
                // Logs search
                document.getElementById('sm-logs-search')?.addEventListener('input', (e) => this.filterLogs(e.target.value));
                document.getElementById('sm-logs-page-size')?.addEventListener('change', (e) => {
                    this.logsPageSize = parseInt(e.target.value) || 50;
                    this.renderLogs();
                });
                
                // Auto-refresh every 30 seconds (sync status + refresh data)
                setInterval(() => {
                    // Sync status from QuantConnect API
                    this.syncProjectStatus();
                    
                    if (this.selectedProject) {
                        this.loadSignals();
                        this.loadKpi(); // Refresh KPI
                        this.loadHoldings(); // Refresh Holdings
                        this.loadOrders(); // Refresh Orders
                        this.loadLogs(); // Refresh Logs
                        this.loadSelectedProject(); // Refresh project bar with new status
                    }
                    
                    // Refresh project list if modal is open
                    const modal = document.getElementById('sm-project-modal');
                    if (modal.classList.contains('is-open')) {
                        this.refreshProjectStatus();
                    }
                }, 30000);
            }
            
            // ==================== DATA TABS ====================
            
            switchDataTab(tabName) {
                this.currentTab = tabName;
                
                // Update tab buttons
                document.querySelectorAll('.sm-data-tab').forEach(tab => {
                    tab.classList.toggle('active', tab.dataset.tab === tabName);
                });
                
                // Update panels
                document.querySelectorAll('.sm-data-panel').forEach(panel => {
                    panel.style.display = 'none';
                });
                
                const panel = document.getElementById(`sm-panel-${tabName}`);
                if (panel) panel.style.display = 'block';
                
                // Load data for the tab if needed
                if (tabName === 'holdings' && this.holdingsData.length === 0) {
                    this.loadHoldings();
                } else if (tabName === 'orders' && this.ordersData.length === 0) {
                    this.loadOrders();
                } else if (tabName === 'logs' && this.logsData.length === 0) {
                    this.loadLogs();
                }
            }
            
            async loadHoldings() {
                if (!this.selectedProject) return;
                
                const loading = document.getElementById('sm-holdings-loading');
                const empty = document.getElementById('sm-holdings-empty');
                const container = document.getElementById('sm-holdings-table-container');
                const cashBadge = document.getElementById('sm-holdings-cash');
                
                try {
                    if (loading) loading.style.display = 'block';
                    if (empty) empty.style.display = 'none';
                    if (container) container.style.display = 'none';
                    
                    const response = await fetch(`${this.apiBaseUrl}/api/signal-manager/live/holdings?project_id=${this.selectedProject.project_id}`);
                    const result = await response.json();
                    
                    if (result.success && result.data) {
                        this.holdingsData = result.data.holdings || [];
                        const summary = result.data.summary || {};
                        
                        // Save to cache
                        this.saveToCache(this.STORAGE_KEYS.HOLDINGS, {
                            holdings: this.holdingsData,
                            summary: summary
                        });
                        
                        // Update cash badge
                        if (cashBadge && summary.cash) {
                            const usdtCash = summary.cash.USDT?.amount || summary.cash.USD?.amount || 0;
                            cashBadge.textContent = `Cash: $${Number(usdtCash).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                        }
                        
                        // Update count
                        document.getElementById('sm-count-holdings').textContent = `(${this.holdingsData.length})`;
                        
                        this.renderHoldings();
                    }
                } catch (error) {
                    console.error('[Holdings] Failed to load:', error);
                } finally {
                    if (loading) loading.style.display = 'none';
                }
            }
            
            renderHoldings() {
                const empty = document.getElementById('sm-holdings-empty');
                const container = document.getElementById('sm-holdings-table-container');
                const tbody = document.getElementById('sm-holdings-body');
                
                if (this.holdingsData.length === 0) {
                    if (empty) empty.style.display = 'block';
                    if (container) container.style.display = 'none';
                    return;
                }
                
                if (empty) empty.style.display = 'none';
                if (container) container.style.display = 'block';
                
                tbody.innerHTML = this.holdingsData.map(h => `
                    <tr>
                        <td><strong>${this.escapeHtml(h.symbol)}</strong></td>
                        <td class="text-end">${Number(h.quantity).toLocaleString()}</td>
                        <td class="text-end">$${Number(h.average_price).toFixed(2)}</td>
                        <td class="text-end">$${Number(h.market_price).toFixed(2)}</td>
                        <td class="text-end">$${Number(h.market_value).toFixed(2)}</td>
                        <td class="text-end ${Number(h.unrealized_pnl) >= 0 ? 'sm-pnl positive' : 'sm-pnl negative'}">
                            ${Number(h.unrealized_pnl) >= 0 ? '+' : ''}$${Number(h.unrealized_pnl).toFixed(2)}
                        </td>
                    </tr>
                `).join('');
            }
            
            async loadOrders() {
                if (!this.selectedProject) return;
                
                const loading = document.getElementById('sm-orders-loading');
                const empty = document.getElementById('sm-orders-empty');
                const container = document.getElementById('sm-orders-table-container');
                
                try {
                    if (loading) loading.style.display = 'block';
                    if (empty) empty.style.display = 'none';
                    if (container) container.style.display = 'none';
                    
                    const response = await fetch(`${this.apiBaseUrl}/api/signal-manager/live/orders?project_id=${this.selectedProject.project_id}&start=0&end=500`);
                    const result = await response.json();
                    
                    if (result.success) {
                        this.ordersData = result.data || [];
                        
                        // Save to cache
                        this.saveToCache(this.STORAGE_KEYS.ORDERS, this.ordersData);
                        
                        // Update count
                        document.getElementById('sm-count-orders').textContent = `(${this.ordersData.length})`;
                        
                        this.renderOrders();
                    }
                } catch (error) {
                    console.error('[Orders] Failed to load:', error);
                } finally {
                    if (loading) loading.style.display = 'none';
                }
            }
            
            renderOrders() {
                const empty = document.getElementById('sm-orders-empty');
                const container = document.getElementById('sm-orders-table-container');
                const tbody = document.getElementById('sm-orders-body');
                
                if (this.ordersData.length === 0) {
                    if (empty) empty.style.display = 'block';
                    if (container) container.style.display = 'none';
                    return;
                }
                
                if (empty) empty.style.display = 'none';
                if (container) container.style.display = 'block';
                
                // Sort by created_time descending
                const sorted = [...this.ordersData].sort((a, b) => 
                    new Date(b.created_time) - new Date(a.created_time)
                );
                
                tbody.innerHTML = sorted.map(o => {
                    const dirClass = o.direction === 'buy' ? 'sm-direction-buy' : 'sm-direction-sell';
                    const statusClass = o.status === 'filled' ? 'sm-status-filled' : 'sm-status-canceled';
                    const time = o.created_time ? new Date(o.created_time).toLocaleString() : '-';
                    
                    return `
                        <tr style="cursor: pointer;" onclick="console.log(${JSON.stringify(o._raw).replace(/"/g, '&quot;')})">
                            <td>${o.id}</td>
                            <td>${time}</td>
                            <td><strong>${this.escapeHtml(o.symbol)}</strong></td>
                            <td class="${dirClass}">${o.direction?.toUpperCase()}</td>
                            <td class="text-end">${Math.abs(Number(o.quantity)).toFixed(4)}</td>
                            <td class="text-end">$${Number(o.price).toFixed(2)}</td>
                            <td class="text-end">$${Math.abs(Number(o.value)).toFixed(2)}</td>
                            <td class="text-end">$${Number(o.fee).toFixed(4)}</td>
                            <td><span class="${statusClass}">${o.status}</span></td>
                            <td class="text-truncate" style="max-width: 120px;" title="${this.escapeHtml(o.tag)}">${this.escapeHtml(o.tag || '-')}</td>
                        </tr>
                    `;
                }).join('');
            }
            
            async loadLogs() {
                if (!this.selectedProject) return;
                
                const loading = document.getElementById('sm-logs-loading');
                const empty = document.getElementById('sm-logs-empty');
                const container = document.getElementById('sm-logs-table-container');
                const status = document.getElementById('sm-logs-status');
                
                try {
                    if (loading) loading.style.display = 'block';
                    if (empty) empty.style.display = 'none';
                    if (container) container.style.display = 'none';
                    
                    const response = await fetch(`${this.apiBaseUrl}/api/signal-manager/live/logs?project_id=${this.selectedProject.project_id}`);
                    const result = await response.json();
                    
                    if (result.success) {
                        this.logsData = result.data || [];
                        
                        // Save to cache
                        this.saveToCache(this.STORAGE_KEYS.LOGS, this.logsData);
                        
                        // Update count
                        document.getElementById('sm-count-logs').textContent = `(${this.logsData.length})`;
                        if (status) status.textContent = `Loaded ${this.logsData.length} logs.`;
                        
                        this.renderLogs();
                    } else {
                        if (status) status.textContent = result.error?.message || 'No logs';
                        if (empty) {
                            empty.style.display = 'block';
                            empty.querySelector('.small').textContent = result.error?.message || 'No logs available';
                        }
                    }
                } catch (error) {
                    console.error('[Logs] Failed to load:', error);
                    if (status) status.textContent = 'Failed to load logs';
                } finally {
                    if (loading) loading.style.display = 'none';
                }
            }
            
            filterLogs(searchTerm) {
                this.logsSearchTerm = searchTerm.toLowerCase();
                this.renderLogs();
            }
            
            renderLogs() {
                const empty = document.getElementById('sm-logs-empty');
                const container = document.getElementById('sm-logs-table-container');
                const tbody = document.getElementById('sm-logs-body');
                const pagination = document.getElementById('sm-logs-pagination');
                
                // Filter logs
                let filtered = this.logsData;
                if (this.logsSearchTerm) {
                    filtered = filtered.filter(log => {
                        const msg = (log.message || '').toLowerCase();
                        const time = (log.timestamp || '').toLowerCase();
                        return msg.includes(this.logsSearchTerm) || time.includes(this.logsSearchTerm);
                    });
                }
                
                if (filtered.length === 0) {
                    if (empty) empty.style.display = 'block';
                    if (container) container.style.display = 'none';
                    return;
                }
                
                if (empty) empty.style.display = 'none';
                if (container) container.style.display = 'block';
                
                // Paginate
                const pageSize = this.logsPageSize || 50;
                const totalPages = Math.ceil(filtered.length / pageSize);
                const currentPage = 1;
                const start = (currentPage - 1) * pageSize;
                const pageItems = filtered.slice(start, start + pageSize);
                
                tbody.innerHTML = pageItems.map(log => {
                    const msg = log.message || '';
                    let msgClass = 'sm-log-info';
                    if (msg.includes('[TRADE]') || msg.includes('Signal sent')) msgClass = 'sm-log-trade';
                    else if (msg.includes('Warning') || msg.includes('WARNING')) msgClass = 'sm-log-warning';
                    else if (msg.includes('Error') || msg.includes('ERROR')) msgClass = 'sm-log-error';
                    
                    return `
                        <tr>
                            <td class="text-nowrap">${this.escapeHtml(log.timestamp || '-')}</td>
                            <td class="sm-log-message ${msgClass}">${this.escapeHtml(msg)}</td>
                        </tr>
                    `;
                }).join('');
                
                // Simple pagination display
                if (pagination) {
                    if (totalPages > 1) {
                        pagination.innerHTML = `<span class="small text-secondary">Page 1 of ${totalPages} (${filtered.length} total)</span>`;
                    } else {
                        pagination.innerHTML = '';
                    }
                }
            }
            
            escapeHtml(text) {
                if (text === null || text === undefined) return '';
                const div = document.createElement('div');
                div.textContent = String(text);
                return div.innerHTML;
            }

            /**
             * Sync project status from QuantConnect API
             * Uses /live/list API for accurate status (Running, Stopped, RuntimeError, Liquidated)
             */
            async syncProjectStatus() {
                const healthEl = document.getElementById('sm-health');
                const healthMetaEl = document.getElementById('sm-health-meta');
                const liveDot = document.getElementById('sm-live-dot');
                const syncBtn = document.getElementById('sm-sync-btn');
                const syncIcon = document.getElementById('sm-sync-icon');
                const dataSourceEl = document.getElementById('sm-data-source');
                
                try {
                    // Show syncing state
                    if (syncBtn) {
                        syncBtn.disabled = true;
                        syncIcon.classList.add('syncing');
                        syncIcon.style.animation = 'spin 1s linear infinite';
                    }
                    healthEl.classList.add('syncing');
                    
                    const response = await fetch(`${this.apiBaseUrl}/api/signal-manager/sync-status`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            sync_all: false // Only sync selected project
                        })
                    });
                    
                    // Handle rate limiting
                    if (response.status === 429) {
                        console.warn('[Sync] Rate limited, skipping...');
                        healthMetaEl.textContent = `Rate limited - ${new Date().toLocaleTimeString()}`;
                        return;
                    }
                    
                    // Handle non-JSON responses
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        console.warn('[Sync] Non-JSON response received');
                        return;
                    }
                    
                    const result = await response.json();
                    
                    if (result.success && result.data.length > 0) {
                        console.log('[Sync] Status synced:', result.data);
                        
                        const syncedProject = result.data[0];
                        const prevStatus = healthEl.textContent;
                        
                        // Update running status with smooth transition
                        if (syncedProject.is_running) {
                            healthEl.textContent = 'Running';
                            healthEl.className = 'fw-semibold text-success';
                            liveDot.className = 'live-dot running';
                        } else if (syncedProject.qc_live_state === 'RuntimeError') {
                            healthEl.textContent = 'Error';
                            healthEl.className = 'fw-semibold text-danger';
                            liveDot.className = 'live-dot error';
                        } else {
                            healthEl.textContent = 'Stopped';
                            healthEl.className = 'fw-semibold text-secondary';
                            liveDot.className = 'live-dot stopped';
                        }
                        
                        // Add glow effect if status changed
                        if (prevStatus !== healthEl.textContent && prevStatus !== '-') {
                            healthEl.classList.add('status-updated');
                            setTimeout(() => healthEl.classList.remove('status-updated'), 600);
                        }
                        
                        // Build meta text with QC state and brokerage info
                        let metaParts = [];
                        if (syncedProject.qc_live_state) {
                            metaParts.push(syncedProject.qc_live_state);
                        }
                        if (syncedProject.brokerage) {
                            metaParts.push(syncedProject.brokerage);
                        }
                        if (syncedProject.equity !== null && syncedProject.equity !== undefined) {
                            metaParts.push(`$${Number(syncedProject.equity).toLocaleString()}`);
                        }
                        
                        const stateInfo = metaParts.length > 0 ? `(${metaParts.join(' | ')})` : '';
                        healthMetaEl.textContent = `${stateInfo} - Synced: ${new Date().toLocaleTimeString()}`;
                        
                        // Update data source indicator to LIVE
                        if (dataSourceEl) {
                            dataSourceEl.style.display = 'inline-flex';
                            dataSourceEl.className = 'cache-indicator live';
                            dataSourceEl.textContent = 'LIVE';
                            dataSourceEl.classList.add('data-updated');
                            setTimeout(() => dataSourceEl.classList.remove('data-updated'), 300);
                        }
                        
                        // Update project type badge with smooth transition
                        const projectType = document.getElementById('sm-project-type');
                        if (projectType) {
                            projectType.textContent = syncedProject.is_live ? 'Live' : 'Paper';
                            projectType.className = `sm-badge ${syncedProject.is_live ? 'live' : 'backtest'}`;
                        }
                        
                        // Update project status badge
                        const projectStatus = document.getElementById('sm-project-status');
                        if (projectStatus) {
                            projectStatus.textContent = syncedProject.is_running ? 'Running' : 'Stopped';
                            projectStatus.className = `sm-badge ${syncedProject.is_running ? 'active' : 'stopped'}`;
                        }
                    }
                } catch (error) {
                    console.error('[Sync] Failed to sync status:', error);
                    healthMetaEl.textContent = `Sync failed - ${new Date().toLocaleTimeString()}`;
                } finally {
                    // Remove syncing state
                    if (syncBtn) {
                        syncBtn.disabled = false;
                        syncIcon.classList.remove('syncing');
                        syncIcon.style.animation = '';
                    }
                    healthEl.classList.remove('syncing');
                    
                    // Save last sync time
                    this.saveToCache(this.STORAGE_KEYS.LAST_SYNC, Date.now());
                }
            }

            /**
             * Load KPI from QuantConnect Live Algorithm
             */
            async loadKpi() {
                const loading = document.getElementById('sm-kpi-loading');
                const error = document.getElementById('sm-kpi-error');
                const grid = document.getElementById('sm-kpi-grid');
                
                if (!this.selectedProject) {
                    // Show no project selected message
                    if (error) {
                        error.style.display = 'block';
                        document.getElementById('sm-kpi-error-text').textContent = 'Select a project to view KPI';
                    }
                    return;
                }
                
                try {
                    // Show loading state
                    if (loading) loading.style.display = 'block';
                    if (error) error.style.display = 'none';
                    
                    const response = await fetch(`${this.apiBaseUrl}/api/signal-manager/kpi?project_id=${this.selectedProject.project_id}`);
                    const result = await response.json();
                    
                    if (result.success && result.data) {
                        console.log('[KPI] Loaded:', result.data);
                        
                        // Save to cache
                        this.saveToCache('sm_kpi', result.data);
                        
                        // Update KPI display
                        this.updateKpiDisplay(result.data);
                        
                        if (grid) grid.style.display = 'flex';
                        if (error) error.style.display = 'none';
                    } else {
                        throw new Error(result.error?.message || 'No KPI data available');
                    }
                } catch (err) {
                    console.error('[KPI] Failed to load:', err);
                    if (error) {
                        error.style.display = 'block';
                        document.getElementById('sm-kpi-error-text').textContent = err.message || 'Failed to load KPI';
                    }
                } finally {
                    if (loading) loading.style.display = 'none';
                }
            }
            
            /**
             * Update KPI display with data
             */
            updateKpiDisplay(kpi) {
                // Format helpers
                const formatPercent = (val) => val !== null && val !== undefined ? `${parseFloat(val).toFixed(2)}%` : '-';
                const formatNumber = (val, decimals = 2) => val !== null && val !== undefined ? parseFloat(val).toFixed(decimals) : '-';
                const formatInt = (val) => val !== null && val !== undefined ? parseInt(val).toLocaleString() : '-';
                
                // Update each KPI field with animation
                const updateField = (id, value) => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.textContent = value;
                        el.classList.add('data-updated');
                        setTimeout(() => el.classList.remove('data-updated'), 300);
                    }
                };
                
                // Show data source badge
                const dataSourceEl = document.getElementById('kpi-data-source');
                if (dataSourceEl && kpi.data_source) {
                    dataSourceEl.style.display = 'inline-block';
                    if (kpi.data_source === 'live') {
                        dataSourceEl.textContent = 'LIVE';
                        dataSourceEl.className = 'badge bg-success small';
                    } else if (kpi.data_source === 'backtest') {
                        dataSourceEl.textContent = 'BACKTEST';
                        dataSourceEl.className = 'badge bg-warning text-dark small';
                    } else if (kpi.data_source === 'live+backtest') {
                        dataSourceEl.textContent = 'LIVE+BACKTEST';
                        dataSourceEl.className = 'badge bg-info small';
                    }
                    dataSourceEl.style.fontSize = '10px';
                }
                
                // Core metrics
                updateField('kpi-sharpe', formatNumber(kpi.sharpe_ratio));
                updateField('kpi-sortino', formatNumber(kpi.sortino_ratio));
                updateField('kpi-cagr', formatPercent(kpi.cagr));
                updateField('kpi-drawdown', formatPercent(kpi.drawdown));
                updateField('kpi-psr', formatPercent(kpi.probabilistic_sharpe));
                updateField('kpi-winrate', formatPercent(kpi.win_rate));
                updateField('kpi-lossrate', formatPercent(kpi.loss_rate));
                updateField('kpi-orders', formatInt(kpi.total_orders || kpi.total_trades));
                updateField('kpi-turnover', formatPercent(kpi.turnover));
                
                // Store full KPI for detail modal
                this.currentKpi = kpi;
            }
            
            /**
             * Open KPI Detail Modal (shows all raw data)
             */
            openKpiDetailModal() {
                if (!this.currentKpi) {
                    alert('No KPI data loaded. Please select a project first.');
                    return;
                }
                
                // Create modal if doesn't exist
                let modal = document.getElementById('sm-kpi-detail-modal');
                if (!modal) {
                    modal = document.createElement('div');
                    modal.id = 'sm-kpi-detail-modal';
                    modal.className = 'sm-modal';
                    modal.innerHTML = `
                        <div class="sm-modal__backdrop" onclick="document.getElementById('sm-kpi-detail-modal').classList.remove('is-open')"></div>
                        <div class="sm-modal__panel" style="max-width: 900px;">
                            <div class="sm-modal__header">
                                <div class="fw-semibold">QuantConnect KPI Details</div>
                                <button class="btn btn-outline-secondary btn-sm" type="button" 
                                        onclick="document.getElementById('sm-kpi-detail-modal').classList.remove('is-open')">
                                    Close
                                </button>
                            </div>
                            <div class="sm-modal__body" id="sm-kpi-detail-content"></div>
                        </div>
                    `;
                    document.body.appendChild(modal);
                }
                
                // Populate content
                const content = document.getElementById('sm-kpi-detail-content');
                const kpi = this.currentKpi;
                
                content.innerHTML = `
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="sm-card">
                                <h6 class="fw-semibold text-primary mb-2">Performance Ratios</h6>
                                <table class="table table-sm small mb-0">
                                    <tr><td>Sharpe Ratio</td><td class="text-end fw-semibold">${kpi.sharpe_ratio ?? '-'}</td></tr>
                                    <tr><td>Sortino Ratio</td><td class="text-end fw-semibold">${kpi.sortino_ratio ?? '-'}</td></tr>
                                    <tr><td>Probabilistic SR</td><td class="text-end fw-semibold">${kpi.probabilistic_sharpe ? kpi.probabilistic_sharpe + '%' : '-'}</td></tr>
                                    <tr><td>Information Ratio</td><td class="text-end fw-semibold">${kpi.information_ratio ?? '-'}</td></tr>
                                    <tr><td>Treynor Ratio</td><td class="text-end fw-semibold">${kpi.treynor_ratio ?? '-'}</td></tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="sm-card">
                                <h6 class="fw-semibold text-success mb-2">Returns</h6>
                                <table class="table table-sm small mb-0">
                                    <tr><td>CAGR</td><td class="text-end fw-semibold text-success">${kpi.cagr ? kpi.cagr + '%' : '-'}</td></tr>
                                    <tr><td>Total Return</td><td class="text-end fw-semibold">${kpi.total_return ?? '-'}</td></tr>
                                    <tr><td>Average Win</td><td class="text-end fw-semibold text-success">${kpi.average_win ?? '-'}</td></tr>
                                    <tr><td>Average Loss</td><td class="text-end fw-semibold text-danger">${kpi.average_loss ?? '-'}</td></tr>
                                    <tr><td>Profit/Loss Ratio</td><td class="text-end fw-semibold">${kpi.profit_loss_ratio ?? '-'}</td></tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="sm-card">
                                <h6 class="fw-semibold text-danger mb-2">Risk</h6>
                                <table class="table table-sm small mb-0">
                                    <tr><td>Drawdown</td><td class="text-end fw-semibold text-danger">${kpi.drawdown ? kpi.drawdown + '%' : '-'}</td></tr>
                                    <tr><td>Alpha</td><td class="text-end fw-semibold">${kpi.alpha ?? '-'}</td></tr>
                                    <tr><td>Beta</td><td class="text-end fw-semibold">${kpi.beta ?? '-'}</td></tr>
                                    <tr><td>Largest Win</td><td class="text-end fw-semibold text-success">${kpi.largest_win ?? '-'}</td></tr>
                                    <tr><td>Largest Loss</td><td class="text-end fw-semibold text-danger">${kpi.largest_loss ?? '-'}</td></tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="sm-card">
                                <h6 class="fw-semibold mb-2">Trade Statistics</h6>
                                <table class="table table-sm small mb-0">
                                    <tr><td>Total Orders</td><td class="text-end fw-semibold">${kpi.total_orders ?? '-'}</td></tr>
                                    <tr><td>Total Trades</td><td class="text-end fw-semibold">${kpi.total_trades ?? '-'}</td></tr>
                                    <tr><td>Win Rate</td><td class="text-end fw-semibold text-success">${kpi.win_rate ? kpi.win_rate + '%' : '-'}</td></tr>
                                    <tr><td>Loss Rate</td><td class="text-end fw-semibold text-danger">${kpi.loss_rate ? kpi.loss_rate + '%' : '-'}</td></tr>
                                    <tr><td>Winning Trades</td><td class="text-end fw-semibold text-success">${kpi.winning_trades ?? '-'}</td></tr>
                                    <tr><td>Losing Trades</td><td class="text-end fw-semibold text-danger">${kpi.losing_trades ?? '-'}</td></tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="sm-card">
                                <h6 class="fw-semibold mb-2">Portfolio</h6>
                                <table class="table table-sm small mb-0">
                                    <tr><td>Equity</td><td class="text-end fw-semibold">${kpi.equity ? '$' + parseFloat(kpi.equity).toLocaleString() : '-'}</td></tr>
                                    <tr><td>Holdings</td><td class="text-end fw-semibold">${kpi.holdings ?? '-'}</td></tr>
                                    <tr><td>Unrealized PnL</td><td class="text-end fw-semibold">${kpi.unrealized_pnl ?? '-'}</td></tr>
                                    <tr><td>Total Fees</td><td class="text-end fw-semibold">${kpi.fees ?? '-'}</td></tr>
                                    <tr><td>Turnover</td><td class="text-end fw-semibold">${kpi.turnover ? kpi.turnover + '%' : '-'}</td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="small text-secondary">
                            <strong>Started:</strong> ${kpi.started_at ? new Date(kpi.started_at).toLocaleString() : '-'} |
                            <strong>Last Update:</strong> ${kpi.last_update ? new Date(kpi.last_update).toLocaleString() : '-'}
                        </div>
                    </div>
                `;
                
                modal.classList.add('is-open');
            }

            async loadSelectedProject() {
                try {
                    const response = await fetch(`${this.apiBaseUrl}/api/signal-manager/selected-project`);
                    const result = await response.json();
                    
                    if (result.success && result.data) {
                        this.selectedProject = result.data;
                        
                        // Save to cache for instant load next time
                        this.saveToCache(this.STORAGE_KEYS.SELECTED_PROJECT, result.data);
                        
                        // Set project filter for data loading
                        this.currentFilters = { project_id: this.selectedProject.project_id };
                        
                        this.updateProjectBar();
                        
                        // Load data for the selected project
                        this.loadSignals();
                        this.loadKpi(); // Load KPI from QuantConnect
                    }
                } catch (error) {
                    console.error('Failed to load selected project:', error);
                }
            }

            updateProjectBar() {
                const projectBar = document.getElementById('sm-project-bar');
                const projectName = document.getElementById('sm-project-name');
                const projectMeta = document.getElementById('sm-project-meta');
                const projectType = document.getElementById('sm-project-type');
                const projectStatus = document.getElementById('sm-project-status');
                const projectActivity = document.getElementById('sm-project-activity');
                
                if (this.selectedProject) {
                    projectBar.style.display = 'flex';
                    projectName.textContent = this.selectedProject.project_name;
                    projectMeta.textContent = `Project ID: ${this.selectedProject.project_id} | Selected: ${new Date(this.selectedProject.selected_at).toLocaleString()}`;
                    
                    projectType.textContent = this.selectedProject.is_live ? 'Live' : 'Backtest';
                    projectType.className = `sm-badge ${this.selectedProject.is_live ? 'live' : 'backtest'}`;
                    
                    projectStatus.textContent = 'Active';
                    projectStatus.className = 'sm-badge active';
                    
                    // Show activity status if available
                    if (this.selectedProject.activity_status) {
                        projectActivity.textContent = this.selectedProject.activity_status;
                        projectActivity.className = `sm-badge ${this.selectedProject.activity_status}`;
                        projectActivity.style.display = 'inline-flex';
                    } else {
                        projectActivity.style.display = 'none';
                    }
                } else {
                    projectBar.style.display = 'none';
                }
            }

            async openProjectModal() {
                const modal = document.getElementById('sm-project-modal');
                modal.classList.add('is-open');
                
                // Clear filters when opening modal
                document.getElementById('sm-project-search').value = '';
                document.getElementById('sm-project-type-filter').value = '';
                document.getElementById('sm-project-status-filter').value = '';
                
                await this.loadProjects();
            }

            closeProjectModal() {
                const modal = document.getElementById('sm-project-modal');
                modal.classList.remove('is-open');
            }

            async loadProjects() {
                const loading = document.getElementById('sm-projects-loading');
                const error = document.getElementById('sm-projects-error');
                const container = document.getElementById('sm-projects-table-container');
                
                loading.style.display = 'block';
                error.style.display = 'none';
                container.style.display = 'none';
                
                try {
                    const response = await fetch(`${this.apiBaseUrl}/api/signal-manager/projects`);
                    const result = await response.json();
                    
                    if (result.success) {
                        this.allProjects = result.data; // Store all projects
                        this.renderProjects(result.data);
                        container.style.display = 'block';
                    } else {
                        throw new Error(result.error?.message || 'Failed to load projects');
                    }
                } catch (err) {
                    error.style.display = 'block';
                    document.getElementById('sm-projects-error-message').textContent = err.message;
                } finally {
                    loading.style.display = 'none';
                }
            }

            renderProjects(projects) {
                const tbody = document.getElementById('sm-projects-body');
                const emptyState = document.getElementById('sm-projects-empty');
                tbody.innerHTML = '';
                
                if (projects.length === 0) {
                    emptyState.style.display = 'block';
                    return;
                } else {
                    emptyState.style.display = 'none';
                }
                
                projects.forEach(project => {
                    const row = document.createElement('tr');
                    
                    const localSession = project.local_session;
                    
                    // Use REAL-TIME status from QC API (is_running, qc_status)
                    // NOT from local_session which requires select first
                    let statusBadge = '';
                    if (project.is_running) {
                        statusBadge = '<span class="sm-badge active">RUNNING</span>';
                    } else if (project.qc_status === 'RuntimeError') {
                        statusBadge = '<span class="sm-badge" style="background: #ef4444; color: white;">ERROR</span>';
                    } else if (project.qc_status === 'Stopped') {
                        statusBadge = '<span class="sm-badge stopped">STOPPED</span>';
                    } else if (localSession) {
                        statusBadge = `<span class="sm-badge ${localSession.activity_status || 'inactive'}">${(localSession.activity_status || 'INACTIVE').toUpperCase()}</span>`;
                    } else {
                        statusBadge = '<span class="sm-badge stopped">NOT SELECTED</span>';
                    }
                    
                    // Type badge: Live (real brokerage) or Backtest/Paper
                    let typeBadge = '';
                    if (project.is_live) {
                        typeBadge = '<span class="sm-badge live">LIVE</span>';
                    } else if (project.qc_brokerage === 'PaperBrokerage' || project.qc_brokerage === 'Paper Trading') {
                        typeBadge = '<span class="sm-badge backtest">PAPER</span>';
                    } else {
                        typeBadge = '<span class="sm-badge backtest">BACKTEST</span>';
                    }
                    
                    // Signals info from local session
                    const signalsInfo = localSession 
                        ? `${localSession.signals_count || 0} total, ${localSession.recent_signals_count || 0} recent`
                        : 'No signals';
                    
                    row.innerHTML = `
                        <td>
                            <div class="fw-semibold">${this.escapeHtml(project.name)}</div>
                            <div class="small text-secondary">ID: ${project.project_id}</div>
                        </td>
                        <td>
                            <div class="small" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                ${this.escapeHtml(project.description || 'No description')}
                            </div>
                        </td>
                        <td>${this.escapeHtml(project.language)}</td>
                        <td class="small">${new Date(project.modified).toLocaleDateString()}</td>
                        <td>${statusBadge} ${typeBadge}</td>
                        <td class="small">${signalsInfo}</td>
                        <td class="text-end">
                            <button class="btn btn-primary btn-sm" onclick="signalManager.selectProject(${project.project_id}, '${this.escapeHtml(project.name)}', ${project.is_live || false})">
                                Select Project
                            </button>
                        </td>
                    `;
                    
                    tbody.appendChild(row);
                });
            }

            filterProjects() {
                if (!this.allProjects || this.allProjects.length === 0) {
                    return;
                }

                const searchTerm = document.getElementById('sm-project-search').value.toLowerCase();
                const typeFilter = document.getElementById('sm-project-type-filter').value;
                const statusFilter = document.getElementById('sm-project-status-filter').value;

                const filteredProjects = this.allProjects.filter(project => {
                    // Search filter
                    const matchesSearch = !searchTerm || 
                        project.name.toLowerCase().includes(searchTerm) ||
                        (project.description && project.description.toLowerCase().includes(searchTerm));

                    // Type filter - use real-time is_live from QC API
                    const matchesType = !typeFilter || 
                        (typeFilter === 'live' && project.is_live) ||
                        (typeFilter === 'backtest' && !project.is_live);

                    // Status filter - use real-time is_running from QC API
                    let matchesStatus = true;
                    if (statusFilter) {
                        if (statusFilter === 'running') {
                            matchesStatus = project.is_running;
                        } else if (statusFilter === 'stopped') {
                            matchesStatus = !project.is_running && project.qc_status !== 'RuntimeError';
                        } else if (statusFilter === 'error') {
                            matchesStatus = project.qc_status === 'RuntimeError';
                        } else if (statusFilter === 'not_selected') {
                            matchesStatus = !project.local_session;
                        }
                    }

                    return matchesSearch && matchesType && matchesStatus;
                });

                this.renderProjects(filteredProjects);
            }

            async selectProject(projectId, projectName, isLive) {
                try {
                    const response = await fetch(`${this.apiBaseUrl}/api/signal-manager/select-project`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            project_id: projectId,
                            project_name: projectName,
                            is_live: isLive
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.selectedProject = result.data;
                        
                        // Save to cache
                        this.saveToCache(this.STORAGE_KEYS.SELECTED_PROJECT, result.data);
                        
                        // IMPORTANT: Set project filter BEFORE loading data
                        this.currentFilters = { project_id: projectId };
                        this.currentPage = 1;
                        
                        // Reset data tabs
                        this.holdingsData = [];
                        this.ordersData = [];
                        this.logsData = [];
                        
                        this.updateProjectBar();
                        this.closeProjectModal();
                        
                        // Load data filtered by selected project
                        this.loadSignals();
                        this.loadKpi(); // Load KPI from QuantConnect
                        
                        // Load data tabs (Holdings, Orders, Logs)
                        this.loadHoldings();
                        this.loadOrders();
                        this.loadLogs();
                        
                        // Sync status for this project
                        this.syncProjectStatus();
                    } else {
                        alert('Failed to select project: ' + (result.error?.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Failed to select project:', error);
                    alert('Failed to select project. Please try again.');
                }
            }

            async refreshProjectStatus() {
                try {
                    const response = await fetch(`${this.apiBaseUrl}/api/signal-manager/projects`);
                    const result = await response.json();
                    
                    if (result.success) {
                        this.allProjects = result.data;
                        this.filterProjects(); // Re-apply current filters
                    }
                } catch (error) {
                    console.error('Failed to refresh project status:', error);
                }
            }

            async openProjectDetailsModal() {
                if (!this.selectedProject) {
                    alert('No project selected');
                    return;
                }

                const modal = document.getElementById('sm-project-details-modal');
                modal.classList.add('is-open');
                
                await this.loadProjectDetails();
            }

            closeProjectDetailsModal() {
                const modal = document.getElementById('sm-project-details-modal');
                modal.classList.remove('is-open');
            }

            async loadProjectDetails() {
                const loading = document.getElementById('sm-project-details-loading');
                const error = document.getElementById('sm-project-details-error');
                const content = document.getElementById('sm-project-details-content');
                
                loading.style.display = 'block';
                error.style.display = 'none';
                content.style.display = 'none';
                
                try {
                    const response = await fetch(`${this.apiBaseUrl}/api/signal-manager/project-status?project_id=${this.selectedProject.project_id}`);
                    const result = await response.json();
                    
                    if (result.success) {
                        this.renderProjectDetails(result.data);
                        content.style.display = 'block';
                    } else {
                        throw new Error(result.error?.message || 'Failed to load project details');
                    }
                } catch (err) {
                    error.style.display = 'block';
                    document.getElementById('sm-project-details-error-message').textContent = err.message;
                } finally {
                    loading.style.display = 'none';
                }
            }

            renderProjectDetails(data) {
                const session = data.project_session;
                const performance = data.performance;
                const recentSignals = data.recent_signals;

                // Project information
                document.getElementById('detail-project-id').textContent = session.project_id;
                document.getElementById('detail-project-name').textContent = session.project_name;
                document.getElementById('detail-project-type').innerHTML = `<span class="sm-badge ${session.is_live ? 'live' : 'backtest'}">${session.is_live ? 'Live' : 'Backtest'}</span>`;
                document.getElementById('detail-project-status').innerHTML = `<span class="sm-badge ${session.status}">${session.status}</span>`;
                document.getElementById('detail-project-activity').innerHTML = `<span class="sm-badge ${session.activity_status || 'inactive'}">${session.activity_status || 'inactive'}</span>`;
                document.getElementById('detail-last-signal').textContent = session.last_signal_at 
                    ? new Date(session.last_signal_at).toLocaleString()
                    : 'No signals yet';

                // Performance metrics
                document.getElementById('detail-total-signals').textContent = performance.total_signals.toLocaleString();
                
                const pnlElement = document.getElementById('detail-total-pnl');
                const totalPnl = performance.total_realized_pnl;
                pnlElement.textContent = totalPnl ? `$${totalPnl.toLocaleString()}` : '-';
                pnlElement.className = `value sm-pnl ${totalPnl > 0 ? 'positive' : totalPnl < 0 ? 'negative' : 'neutral'}`;
                
                document.getElementById('detail-win-rate').textContent = `${performance.win_rate}%`;
                document.getElementById('detail-profitable').textContent = performance.profitable_signals.toLocaleString();

                // Recent signals
                const recentSignalsContainer = document.getElementById('detail-recent-signals');
                if (recentSignals.length === 0) {
                    recentSignalsContainer.innerHTML = '<div class="text-muted">No recent signals in the last 24 hours</div>';
                } else {
                    const signalsHtml = recentSignals.map(signal => {
                        const pnlVal = signal.realized_pnl ? parseFloat(signal.realized_pnl) : null;
                        const pnlClass = pnlVal > 0 ? 'positive' : pnlVal < 0 ? 'negative' : 'neutral';
                        const pnlText = pnlVal !== null ? `$${pnlVal.toFixed(2)}` : '-';
                        
                        return `
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                <div>
                                    <span class="fw-semibold">${signal.symbol}</span>
                                    <span class="sm-badge ${signal.signal_type}">${signal.signal_type}</span>
                                    <span class="text-uppercase small">${signal.action}</span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-semibold">$${parseFloat(signal.price).toFixed(2)}</div>
                                    <div class="small sm-pnl ${pnlClass}">${pnlText}</div>
                                </div>
                            </div>
                        `;
                    }).join('');
                    
                    recentSignalsContainer.innerHTML = signalsHtml;
                }
            }

            applyFilters() {
                this.currentFilters = {};
                
                if (this.selectedProject) {
                    this.currentFilters.project_id = this.selectedProject.project_id;
                }
                
                const symbol = document.getElementById('sm-filter-symbol').value.trim();
                if (symbol) this.currentFilters.symbol = symbol;
                
                const type = document.getElementById('sm-filter-type').value;
                if (type) this.currentFilters.signal_type = type;
                
                const startDate = document.getElementById('sm-filter-start-date').value;
                if (startDate) this.currentFilters.start_date = startDate;
                
                const endDate = document.getElementById('sm-filter-end-date').value;
                if (endDate) this.currentFilters.end_date = endDate;
                
                this.currentPage = 1;
                this.loadSignals();
            }

            clearFilters() {
                document.getElementById('sm-filter-symbol').value = '';
                document.getElementById('sm-filter-type').value = '';
                document.getElementById('sm-filter-start-date').value = '';
                document.getElementById('sm-filter-end-date').value = '';
                
                this.currentFilters = {};
                if (this.selectedProject) {
                    this.currentFilters.project_id = this.selectedProject.project_id;
                }
                
                this.currentPage = 1;
                this.loadSignals();
            }

            async loadSignals() {
                const loading = document.getElementById('sm-signals-loading');
                const empty = document.getElementById('sm-signals-empty');
                const container = document.getElementById('sm-signals-table-container');
                
                loading.style.display = 'block';
                empty.style.display = 'none';
                container.style.display = 'none';
                
                try {
                    const params = new URLSearchParams({
                        ...this.currentFilters,
                        page: this.currentPage,
                        per_page: 25
                    });
                    
                    const response = await fetch(`${this.apiBaseUrl}/api/signal-manager/signals?${params}`);
                    const result = await response.json();
                    
                    if (result.success) {
                        this.signalsData = result.data;
                        
                        // Save to cache
                        this.saveToCache(this.STORAGE_KEYS.SIGNALS, {
                            data: result.data,
                            pagination: result.pagination
                        });
                        
                        if (this.signalsData.length > 0) {
                            this.renderSignals(result.data, result.pagination);
                            container.style.display = 'block';
                        } else {
                            empty.style.display = 'block';
                        }
                        
                        document.getElementById('sm-signals-count').textContent = result.pagination?.total || 0;
                        document.getElementById('sm-count-signals').textContent = `(${result.pagination?.total || 0})`;
                    } else {
                        throw new Error(result.error?.message || 'Failed to load signals');
                    }
                } catch (error) {
                    console.error('Failed to load signals:', error);
                    empty.style.display = 'block';
                } finally {
                    loading.style.display = 'none';
                }
            }

            renderSignals(signals, pagination) {
                const tbody = document.getElementById('sm-signals-body');
                tbody.innerHTML = '';
                
                signals.forEach(signal => {
                    const row = document.createElement('tr');
                    
                    const pnlValue = signal.realized_pnl ? parseFloat(signal.realized_pnl) : null;
                    const pnlClass = pnlValue > 0 ? 'positive' : pnlValue < 0 ? 'negative' : 'neutral';
                    const pnlText = pnlValue !== null ? `$${pnlValue.toFixed(2)}` : '-';
                    
                    row.innerHTML = `
                        <td class="small">${new Date(signal.signal_timestamp).toLocaleString()}</td>
                        <td class="small">${this.escapeHtml(signal.project_name || 'Unknown')}</td>
                        <td class="fw-semibold">${this.escapeHtml(signal.symbol)}</td>
                        <td><span class="sm-badge ${signal.signal_type}">${signal.signal_type}</span></td>
                        <td class="text-uppercase small fw-semibold">${this.escapeHtml(signal.action)}</td>
                        <td class="text-end fw-semibold">$${parseFloat(signal.price).toFixed(2)}</td>
                        <td class="text-end">${signal.quantity ? parseFloat(signal.quantity).toFixed(4) : '-'}</td>
                        <td class="text-end">${signal.target_price ? '$' + parseFloat(signal.target_price).toFixed(2) : '-'}</td>
                        <td class="text-end">${signal.stop_loss ? '$' + parseFloat(signal.stop_loss).toFixed(2) : '-'}</td>
                        <td class="text-end sm-pnl ${pnlClass} fw-semibold">${pnlText}</td>
                        <td class="small" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                            ${this.escapeHtml(signal.message || '')}
                        </td>
                    `;
                    
                    tbody.appendChild(row);
                });
                
                this.renderPagination(pagination);
            }

            renderPagination(pagination) {
                const container = document.getElementById('sm-signals-pagination');
                container.innerHTML = '';
                
                if (pagination.last_page <= 1) return;
                
                // Previous button
                if (pagination.current_page > 1) {
                    const prevBtn = document.createElement('button');
                    prevBtn.className = 'btn btn-outline-secondary btn-sm';
                    prevBtn.textContent = 'Previous';
                    prevBtn.onclick = () => this.goToPage(pagination.current_page - 1);
                    container.appendChild(prevBtn);
                }
                
                // Page info
                const pageInfo = document.createElement('span');
                pageInfo.className = 'small text-secondary mx-2';
                pageInfo.textContent = `Page ${pagination.current_page} of ${pagination.last_page}`;
                container.appendChild(pageInfo);
                
                // Next button
                if (pagination.current_page < pagination.last_page) {
                    const nextBtn = document.createElement('button');
                    nextBtn.className = 'btn btn-outline-secondary btn-sm';
                    nextBtn.textContent = 'Next';
                    nextBtn.onclick = () => this.goToPage(pagination.current_page + 1);
                    container.appendChild(nextBtn);
                }
            }

            goToPage(page) {
                this.currentPage = page;
                this.loadSignals();
            }

            async exportSignals(format) {
                try {
                    const params = new URLSearchParams({
                        ...this.currentFilters,
                        format: format
                    });
                    
                    const response = await fetch(`${this.apiBaseUrl}/api/signal-manager/export?${params}`);
                    const result = await response.json();
                    
                    if (result.success) {
                        this.downloadFile(result.data, result.filename, format);
                    } else {
                        alert('Export failed: ' + (result.error?.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Export failed:', error);
                    alert('Export failed. Please try again.');
                }
            }

            downloadFile(data, filename, format) {
                let content, mimeType;
                
                if (format === 'csv') {
                    const headers = Object.keys(data[0] || {});
                    const csvContent = [
                        headers.join(','),
                        ...data.map(row => headers.map(header => `"${(row[header] || '').toString().replace(/"/g, '""')}"`).join(','))
                    ].join('\n');
                    
                    content = csvContent;
                    mimeType = 'text/csv';
                } else {
                    content = JSON.stringify(data, null, 2);
                    mimeType = 'application/json';
                }
                
                const blob = new Blob([content], { type: mimeType });
                const url = URL.createObjectURL(blob);
                
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
        }

        // Initialize dashboard when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            window.signalManager = new SignalManagerDashboard();
        });
    </script>
@endsection