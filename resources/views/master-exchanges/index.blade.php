@extends('layouts.app')

@section('title', 'Master Exchange Accounts')

@section('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    .page-header {
        margin-bottom: 2rem;
    }
    
    .page-title {
        font-size: 1.75rem;
        font-weight: 600;
        color: var(--foreground);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .page-title i {
        color: var(--primary);
    }
    
    .page-subtitle {
        color: var(--muted-foreground);
        margin-top: 0.25rem;
        font-size: 0.875rem;
    }
    
    .card {
        border: 1px solid var(--border);
        border-radius: 0.5rem;
        box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
        background: var(--card);
    }
    
    .badge-binance {
        background-color: #F3BA2F;
        color: #000;
        font-weight: 600;
    }
    
    .badge-bybit {
        background-color: #F7A600;
        color: #fff;
        font-weight: 600;
    }
    
    .badge-okx {
        background-color: #000;
        color: #fff;
        font-weight: 600;
    }
    
    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }
    
    .status-active {
        background-color: #22c55e;
    }
    
    .status-inactive {
        background-color: #ef4444;
    }
    
    .table thead th {
        background-color: var(--muted);
        color: var(--foreground);
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        border-bottom: 2px solid var(--border);
    }
    
    .table tbody tr {
        border-bottom: 1px solid var(--border);
    }
    
    .table tbody tr:hover {
        background-color: var(--muted);
    }
    
    .btn-action-group {
        display: flex;
        gap: 0.25rem;
    }
    
    .modal-header {
        background-color: var(--muted);
        border-bottom: 1px solid var(--border);
    }
    
    .form-label {
        font-weight: 500;
        color: var(--foreground);
        margin-bottom: 0.5rem;
    }
    
    .alert-info {
        background-color: #dbeafe;
        border-color: #3b82f6;
        color: #1e40af;
    }
    
    .alert-warning {
        background-color: #fef3c7;
        border-color: #f59e0b;
        color: #92400e;
    }
</style>
@endsection

@section('content')
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="page-title">
                    <i class="bi bi-bank2"></i>
                    Master Exchange Accounts
                </h1>
                <p class="page-subtitle">Centralized API key management for trading methods</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exchangeModal" onclick="openCreateModal()">
                <i class="bi bi-plus-circle me-1"></i> Add Exchange Account
            </button>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="exchangesTable" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Exchange</th>
                            <th>Market</th>
                            <th>Type</th>
                            <th class="text-center">Methods Using</th>
                            <th>Last Validated</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
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

<!-- Exchange Modal -->
<div class="modal fade" id="exchangeModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">
                    <i class="bi bi-bank2 me-2"></i>Create Exchange Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exchangeForm">
                    <input type="hidden" id="exchangeId">
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="name" class="form-label">Account Name *</label>
                            <input type="text" class="form-control" id="name" placeholder="e.g., Binance Main Account" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="exchange_type" class="form-label">Exchange *</label>
                            <select class="form-select" id="exchange_type" required>
                                <option value="BINANCE">Binance</option>
                                <option value="BYBIT">Bybit</option>
                                <option value="OKX">OKX</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="market_type" class="form-label">Market Type *</label>
                            <select class="form-select" id="market_type" required>
                                <option value="FUTURES">Futures</option>
                                <option value="SPOT">Spot</option>
                            </select>
                            <small class="text-muted">Different API endpoints for SPOT vs FUTURES</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="api_key" class="form-label">API Key *</label>
                            <input type="text" class="form-control" id="api_key" placeholder="Your API Key" required>
                            <small class="text-muted"><i class="bi bi-shield-lock me-1"></i>Will be encrypted before storage</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="secret_key" class="form-label">Secret Key *</label>
                            <input type="password" class="form-control" id="secret_key" placeholder="Your Secret Key" required>
                            <small class="text-muted"><i class="bi bi-shield-lock me-1"></i>Will be encrypted before storage</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="testnet">
                                <label class="form-check-label" for="testnet">
                                    <i class="bi bi-bug me-1"></i>Testnet Account
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" rows="2" placeholder="Optional notes about this account"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
                <button type="button" class="btn btn-primary" onclick="saveExchange()">
                    <i class="bi bi-check-circle me-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Balance Modal -->
<div class="modal fade" id="balanceModal" tabindex="-1" aria-labelledby="balanceModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="balanceModalTitle">
                    <i class="bi bi-wallet2 me-2"></i>Wallet Balance
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="balanceContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2">Fetching balance data...</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/master-exchanges/index.js') }}"></script>
@endsection
