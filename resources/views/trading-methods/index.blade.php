@extends('layouts.app')

@section('title', 'Trading Methods')

@section('styles')
<!-- Styles for DataTables (loaded via Vite JS import actually handles CSS mostly, but Bootstrap Icons needed) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<!-- Vite handles DataTables CSS via app.js now -->

<style>
    .badge-spot {
        background-color: #10b981;
    }
    .badge-futures {
        background-color: #3b82f6;
    }
    .kpi-card {
        border-left: 4px solid;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    .kpi-good { border-color: #10b981; }
    .kpi-medium { border-color: #f59e0b; }
    .kpi-bad { border-color: #ef4444; }
    .nav-tabs .nav-link {
        color: #6b7280;
    }
    .nav-tabs .nav-link.active {
        color: #1f2937;
        font-weight: 600;
    }
</style>
@endsection

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">Trading Methods</h2>
                    <p class="text-muted mb-0">Manage QuantConnect trading strategies</p>
                </div>
                <div>
                    <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="bi bi-upload"></i> Import
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#methodModal" onclick="openCreateModal()">
                        <i class="bi bi-plus-lg"></i> Create Method
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Methods Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table id="methodsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Method Name</th>
                                <th>Market</th>
                                <th>Pair</th>
                                <th>TF</th>
                                <th>CAGR</th>
                                <th>Winrate</th>
                                <th>Drawdown</th>
                                <th>Active</th>
                                <th>Auto-Trade</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="methodModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Create Trading Method</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="methodForm">
                    <input type="hidden" id="methodId">
                    
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#basicTab">Basic Info</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#performanceTab">Performance</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#qcTab">QuantConnect</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#binanceTab">Binance</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#riskTab">Risk Settings</a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Basic Info Tab -->
                        <div class="tab-pane fade show active" id="basicTab">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Method Name *</label>
                                        <input type="text" class="form-control" id="nama_metode" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Market Type *</label>
                                        <select class="form-select" id="market_type" required>
                                            <option value="">Select...</option>
                                            <option value="SPOT">SPOT</option>
                                            <option value="FUTURES">FUTURES</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Trading Pair *</label>
                                        <input type="text" class="form-control" id="pair" placeholder="BTCUSDT" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Timeframe *</label>
                                        <input type="text" class="form-control" id="tf" placeholder="1h" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Exchange *</label>
                                        <input type="text" class="form-control" id="exchange" value="BINANCE" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Performance Tab -->
                        <div class="tab-pane fade" id="performanceTab">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">CAGR (%)</label>
                                        <input type="number" step="0.01" class="form-control" id="cagr">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Drawdown (%)</label>
                                        <input type="number" step="0.01" class="form-control" id="drawdown">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Winrate (%)</label>
                                        <input type="number" step="0.01" class="form-control" id="winrate">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Lossrate (%)</label>
                                        <input type="number" step="0.01" class="form-control" id="lossrate">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Prob SR</label>
                                        <input type="number" step="0.01" class="form-control" id="prob_sr">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Sharpe Ratio</label>
                                        <input type="number" step="0.01" class="form-control" id="sharpen_ratio">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Sortino Ratio</label>
                                        <input type="number" step="0.01" class="form-control" id="sortino_ratio">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Information Ratio</label>
                                        <input type="number" step="0.01" class="form-control" id="information_ratio">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Turnover</label>
                                        <input type="number" step="0.01" class="form-control" id="turnover">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Total Orders</label>
                                        <input type="number" step="0.01" class="form-control" id="total_orders">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label">Additional KPI (JSON)</label>
                                        <textarea class="form-control" id="kpi_extra" rows="3" placeholder='{"metric": "value"}'></textarea>
                                        <small class="text-muted">Optional: Additional metrics in JSON format</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- QuantConnect Tab -->
                        <div class="tab-pane fade" id="qcTab">
                            <div class="row">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label">QuantConnect Backtest URL *</label>
                                        <input type="url" class="form-control" id="qc_url" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Project ID</label>
                                        <input type="text" class="form-control" id="qc_project_id">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Webhook Token</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="webhook_token">
                                            <button class="btn btn-outline-secondary" type="button" onclick="generateToken()">
                                                Generate
                                            </button>
                                        </div>
                                        <small class="text-muted">Leave empty to auto-generate</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Binance Tab -->
                        <div class="tab-pane fade" id="binanceTab">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> <strong>Master Exchange Required:</strong> All Binance trading methods must use a Master Exchange account for API credentials.
                                <a href="/master-exchanges" target="_blank" class="alert-link">Manage Exchanges</a>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label">Master Exchange Account <span class="text-danger">*</span></label>
                                        <select class="form-select" id="master_exchange_id" required>
                                            <option value="">-- Select Master Exchange --</option>
                                        </select>
                                        <small class="text-muted">Select an exchange account with API credentials</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Risk Settings Tab -->
                        <div class="tab-pane fade" id="riskTab">
                            <div class="mb-3">
                                <label class="form-label">Risk Management Settings (JSON)</label>
                                <textarea class="form-control" id="risk_settings" rows="10" placeholder='{"position_size": 0.01, "max_drawdown": 20}'></textarea>
                                <small class="text-muted">Configure position sizing, stop loss, take profit, etc.</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveMethod()">Save Method</button>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Trading Methods</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="importForm">
                    <div class="mb-3">
                        <label class="form-label">Select JSON File</label>
                        <input type="file" class="form-control" id="importFile" accept=".json" required>
                    </div>
                    <div class="alert alert-info">
                        <small>
                            <strong>Note:</strong> Duplicate method names will be skipped.
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="importMethods()">Import</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/trading-methods/index.js') }}"></script>
@endsection
