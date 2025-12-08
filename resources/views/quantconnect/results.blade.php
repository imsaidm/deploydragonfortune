<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>QuantConnect Results | DragonFortune</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        :root {
            --bg-dark: #0b1220;
            --bg-card: #111a2e;
            --bg-soft: #17233d;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-green: #22c55e;
            --accent-red: #ef4444;
            --accent-blue: #3b82f6;
            --accent-yellow: #eab308;
            --accent-purple: #a855f7;
        }
        body {
            background: radial-gradient(circle at 20% 20%, rgba(168, 85, 247, 0.08), transparent 25%), 
                        radial-gradient(circle at 80% 0%, rgba(59, 130, 246, 0.08), transparent 20%), 
                        var(--bg-dark);
            color: var(--text-primary);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .df-card {
            background: var(--bg-card);
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.06);
            padding: 1.5rem;
            box-shadow: 0 14px 40px rgba(0, 0, 0, 0.25);
        }
        .metric-value { font-size: 2rem; font-weight: 800; }
        .metric-label { color: var(--text-secondary); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.06em; }
        .text-success { color: var(--accent-green) !important; }
        .text-danger { color: var(--accent-red) !important; }
        .text-info { color: var(--accent-blue) !important; }
        .text-warning { color: var(--accent-yellow) !important; }
        .text-secondary { color: var(--text-secondary) !important; }
        .btn-primary { background: var(--accent-blue); border-color: var(--accent-blue); }
        .btn-outline-primary { color: var(--accent-blue); border-color: var(--accent-blue); }
        .btn-outline-secondary { color: var(--text-secondary); border-color: rgba(255,255,255,0.2); }
        .table { color: var(--text-primary); }
        .table th { color: var(--text-secondary); font-weight: 500; }
        .form-select, .form-control { 
            background: var(--bg-soft); 
            border-color: rgba(255,255,255,0.1); 
            color: var(--text-primary);
        }
        .form-select:focus, .form-control:focus {
            background: var(--bg-soft);
            border-color: var(--accent-blue);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25);
        }
        .badge { font-weight: 500; }
        .import-zone {
            border: 2px dashed rgba(255,255,255,0.2);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .import-zone:hover, .import-zone.dragover {
            border-color: var(--accent-blue);
            background: rgba(59, 130, 246, 0.1);
        }
        .modal-content {
            background: var(--bg-card);
            color: var(--text-primary);
        }
        .modal-header { border-color: rgba(255,255,255,0.1); }
        .modal-footer { border-color: rgba(255,255,255,0.1); }
        .btn-close { filter: invert(1); }
    </style>
</head>
<body>
    <div class="container-fluid py-4" x-data="quantConnectDashboard()" x-init="init()">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <a href="/" class="text-decoration-none text-secondary small mb-2 d-inline-block">&larr; Back to Dashboard</a>
                <h1 class="mb-1">QuantConnect Backtest Results</h1>
                <p class="text-secondary mb-0">Import dan analisis hasil backtest dari QuantConnect</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <button class="btn btn-outline-primary" @click="showImportModal = true">
                    <svg width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                        <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                        <path d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708l3-3z"/>
                    </svg>
                    Import Backtest
                </button>
                <button class="btn btn-primary" @click="loadBacktests()" :disabled="isLoading">
                    <span x-show="isLoading" class="spinner-border spinner-border-sm me-1"></span>
                    Refresh
                </button>
            </div>
        </div>

        <!-- No backtests message -->
        <template x-if="!isLoading && backtests.length === 0">
            <div class="df-card text-center py-5">
                <div class="mb-4">
                    <svg width="64" height="64" fill="currentColor" class="text-secondary" viewBox="0 0 16 16">
                        <path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5h-2z"/>
                        <path d="M8 6.5a.5.5 0 0 1 .5.5v1.5H10a.5.5 0 0 1 0 1H8.5V11a.5.5 0 0 1-1 0V9.5H6a.5.5 0 0 1 0-1h1.5V7a.5.5 0 0 1 .5-.5z"/>
                    </svg>
                </div>
                <h4>Belum Ada Backtest</h4>
                <p class="text-secondary mb-4">Import hasil backtest dari QuantConnect untuk mulai menganalisis performance strategy kamu.</p>
                <button class="btn btn-primary btn-lg" @click="showImportModal = true">
                    Import Backtest Pertama
                </button>
            </div>
        </template>

        <!-- Strategy Selector (when backtests exist) -->
        <template x-if="backtests.length > 0">
            <div class="mb-4">
                <div class="d-flex gap-2 flex-wrap">
                    <template x-for="bt in backtests" :key="bt.id">
                        <button class="btn" 
                                :class="selectedBacktestId === bt.id ? 'btn-primary' : 'btn-outline-secondary'"
                                @click="selectBacktest(bt.id)">
                            <span x-text="bt.name"></span>
                            <span class="badge ms-2" 
                                  :class="bt.totalReturn >= 0 ? 'bg-success' : 'bg-danger'"
                                  x-text="formatPercent(bt.totalReturn)"></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        <!-- Main Content (when backtest selected) -->
        <template x-if="currentResult">
            <div>
                <!-- Overview Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-xl-3 col-lg-6">
                        <div class="df-card h-100">
                            <div class="metric-label mb-2">Total Return</div>
                            <div class="metric-value" :class="currentResult.totalReturn >= 0 ? 'text-success' : 'text-danger'" 
                                 x-text="formatPercent(currentResult.totalReturn)"></div>
                            <p class="text-secondary small mb-0">
                                <span x-text="currentResult.durationDays || '--'"></span> days
                            </p>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6">
                        <div class="df-card h-100">
                            <div class="metric-label mb-2">Sharpe Ratio</div>
                            <div class="metric-value" x-text="formatNumber(currentResult.sharpeRatio, 2)"></div>
                            <p class="text-secondary small mb-0" x-text="sharpeLabel(currentResult.sharpeRatio)"></p>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6">
                        <div class="df-card h-100">
                            <div class="metric-label mb-2">Win Rate</div>
                            <div class="metric-value" x-text="formatPercent(currentResult.winRate)"></div>
                            <p class="text-secondary small mb-0">
                                <span x-text="currentResult.totalTrades || 0"></span> trades
                            </p>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6">
                        <div class="df-card h-100">
                            <div class="metric-label mb-2">Max Drawdown</div>
                            <div class="metric-value text-danger" x-text="formatPercent(currentResult.maxDrawdown)"></div>
                            <p class="text-secondary small mb-0">
                                Recovery: <span x-text="currentResult.recoveryDays || '--'"></span> days
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-8">
                        <div class="df-card h-100">
                            <h5 class="mb-3">Equity Curve</h5>
                            <div style="height: 350px;">
                                <canvas x-ref="equityChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="df-card h-100">
                            <h5 class="mb-3">Key Metrics</h5>
                            <dl class="row mb-0 small">
                                <dt class="col-7 text-secondary">Profit Factor</dt>
                                <dd class="col-5 fw-semibold text-end" x-text="formatNumber(currentResult.profitFactor, 2)">--</dd>
                                
                                <dt class="col-7 text-secondary">CAGR</dt>
                                <dd class="col-5 fw-semibold text-end" x-text="formatPercent(currentResult.cagr)">--</dd>
                                
                                <dt class="col-7 text-secondary">Expectancy</dt>
                                <dd class="col-5 fw-semibold text-end" x-text="formatCurrency(currentResult.expectancy)">--</dd>
                                
                                <dt class="col-7 text-secondary">Avg Win</dt>
                                <dd class="col-5 fw-semibold text-end text-success" x-text="formatPercent(currentResult.avgWin)">--</dd>
                                
                                <dt class="col-7 text-secondary">Avg Loss</dt>
                                <dd class="col-5 fw-semibold text-end text-danger" x-text="formatPercent(currentResult.avgLoss)">--</dd>
                                
                                <dt class="col-7 text-secondary">Win Streak</dt>
                                <dd class="col-5 fw-semibold text-end" x-text="currentResult.longestWinStreak || '--'">--</dd>
                                
                                <dt class="col-7 text-secondary">Loss Streak</dt>
                                <dd class="col-5 fw-semibold text-end" x-text="currentResult.longestLossStreak || '--'">--</dd>
                                
                                <dt class="col-7 text-secondary">Total Fees</dt>
                                <dd class="col-5 fw-semibold text-end" x-text="formatCurrency(currentResult.totalFees)">--</dd>
                                
                                <dt class="col-7 text-secondary">Start Date</dt>
                                <dd class="col-5 fw-semibold text-end" x-text="formatDate(currentResult.startDate)">--</dd>
                                
                                <dt class="col-7 text-secondary">End Date</dt>
                                <dd class="col-5 fw-semibold text-end" x-text="formatDate(currentResult.endDate)">--</dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Monthly Returns & Drawdown -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="df-card h-100">
                            <h5 class="mb-3">Monthly Returns</h5>
                            <div style="height: 250px;">
                                <canvas x-ref="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="df-card h-100">
                            <h5 class="mb-3">Drawdown</h5>
                            <div style="height: 250px;">
                                <canvas x-ref="drawdownChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trade History -->
                <div class="df-card mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Trade History</h5>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm" :class="tradeFilter === 'all' ? 'btn-primary' : 'btn-outline-secondary'" @click="tradeFilter = 'all'">All</button>
                            <button class="btn btn-sm" :class="tradeFilter === 'wins' ? 'btn-success' : 'btn-outline-success'" @click="tradeFilter = 'wins'">Wins</button>
                            <button class="btn btn-sm" :class="tradeFilter === 'losses' ? 'btn-danger' : 'btn-outline-danger'" @click="tradeFilter = 'losses'">Losses</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Entry</th>
                                    <th>Exit</th>
                                    <th>Symbol</th>
                                    <th>Direction</th>
                                    <th>Entry Price</th>
                                    <th>Exit Price</th>
                                    <th>P&L</th>
                                    <th>Return</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="trade in filteredTrades()" :key="trade.id">
                                    <tr>
                                        <td class="small" x-text="formatDateTime(trade.entryTime)"></td>
                                        <td class="small" x-text="formatDateTime(trade.exitTime)"></td>
                                        <td><span class="badge bg-secondary" x-text="trade.symbol"></span></td>
                                        <td>
                                            <span class="badge" :class="trade.direction === 'LONG' ? 'bg-success' : 'bg-danger'" x-text="trade.direction"></span>
                                        </td>
                                        <td x-text="formatCurrency(trade.entryPrice)"></td>
                                        <td x-text="formatCurrency(trade.exitPrice)"></td>
                                        <td :class="trade.pnl >= 0 ? 'text-success' : 'text-danger'" x-text="formatCurrency(trade.pnl)"></td>
                                        <td :class="trade.returnPct >= 0 ? 'text-success' : 'text-danger'">
                                            <strong x-text="formatPercent(trade.returnPct)"></strong>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="filteredTrades().length === 0">
                                    <td colspan="8" class="text-center text-secondary py-4">No trades</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Strategy Info & Delete -->
                <div class="df-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-3">Strategy Details</h5>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="text-secondary small">Strategy Name</div>
                                    <div x-text="currentResult.name || 'N/A'"></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-secondary small">Type</div>
                                    <div x-text="currentResult.strategyType || 'N/A'"></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-secondary small">Import Source</div>
                                    <div x-text="currentResult.importSource || 'manual'"></div>
                                </div>
                                <div class="col-12" x-show="currentResult.description">
                                    <div class="text-secondary small">Description</div>
                                    <div x-text="currentResult.description"></div>
                                </div>
                                <div class="col-12" x-show="currentResult.parameters && Object.keys(currentResult.parameters).length > 0">
                                    <div class="text-secondary small mb-2">Parameters</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <template x-for="(value, key) in currentResult.parameters" :key="key">
                                            <span class="badge bg-secondary">
                                                <span x-text="key"></span>: <strong x-text="value"></strong>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-outline-danger btn-sm" @click="deleteBacktest(currentResult.id)">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Import Modal -->
        <div class="modal fade" :class="{'show d-block': showImportModal}" tabindex="-1" x-show="showImportModal" @click.self="showImportModal = false">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Import QuantConnect Backtest</h5>
                        <button type="button" class="btn-close" @click="showImportModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Tabs -->
                        <ul class="nav nav-pills mb-4">
                            <li class="nav-item">
                                <a class="nav-link" :class="{'active': importTab === 'file'}" href="#" @click.prevent="importTab = 'file'">Upload JSON File</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" :class="{'active': importTab === 'paste'}" href="#" @click.prevent="importTab = 'paste'">Paste JSON</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" :class="{'active': importTab === 'api'}" href="#" @click.prevent="importTab = 'api'">API Import</a>
                            </li>
                        </ul>

                        <!-- File Upload -->
                        <div x-show="importTab === 'file'">
                            <div class="import-zone mb-3" 
                                 @dragover.prevent="$el.classList.add('dragover')"
                                 @dragleave.prevent="$el.classList.remove('dragover')"
                                 @drop.prevent="handleFileDrop($event)"
                                 @click="$refs.fileInput.click()">
                                <input type="file" x-ref="fileInput" accept=".json" class="d-none" @change="handleFileSelect($event)">
                                <div class="text-secondary mb-2">
                                    <svg width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                        <path d="M7.646 1.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 2.707V11.5a.5.5 0 0 1-1 0V2.707L5.354 4.854a.5.5 0 1 1-.708-.708l3-3z"/>
                                    </svg>
                                </div>
                                <p class="mb-1">Drag & drop backtest JSON file here</p>
                                <p class="small text-secondary mb-0">or click to browse</p>
                            </div>
                            <div class="alert alert-info small">
                                <strong>How to export from QuantConnect:</strong><br>
                                1. Go to your backtest results page<br>
                                2. Click "..." menu → "Download Results"<br>
                                3. Select JSON format and download<br>
                                4. Upload the file here
                            </div>
                        </div>

                        <!-- Paste JSON -->
                        <div x-show="importTab === 'paste'">
                            <textarea class="form-control mb-3" rows="10" placeholder="Paste QuantConnect JSON result here..." x-model="pasteJson"></textarea>
                        </div>

                        <!-- API Import -->
                        <div x-show="importTab === 'api'">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">User ID</label>
                                    <input type="text" class="form-control" placeholder="Your QuantConnect User ID" x-model="apiCredentials.userId">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">API Token</label>
                                    <input type="password" class="form-control" placeholder="Your API Token" x-model="apiCredentials.token">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Project ID</label>
                                    <input type="text" class="form-control" placeholder="Project ID" x-model="apiCredentials.projectId">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Backtest ID</label>
                                    <input type="text" class="form-control" placeholder="Backtest ID" x-model="apiCredentials.backtestId">
                                </div>
                            </div>
                            <div class="alert alert-info small mt-3">
                                Find your credentials at: <a href="https://www.quantconnect.com/account" target="_blank" class="text-info">quantconnect.com/account</a>
                            </div>
                        </div>

                        <!-- Error message -->
                        <div class="alert alert-danger mt-3" x-show="importError" x-text="importError"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" @click="showImportModal = false">Cancel</button>
                        <button type="button" class="btn btn-primary" @click="doImport()" :disabled="isImporting">
                            <span x-show="isImporting" class="spinner-border spinner-border-sm me-1"></span>
                            Import
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show" x-show="showImportModal"></div>

        <!-- Webhook Info -->
        <div class="df-card mt-4">
            <h6 class="mb-3">🔗 Webhook URL untuk Auto-Import</h6>
            <p class="text-secondary small mb-2">Tambahkan webhook ini di QuantConnect untuk otomatis kirim hasil backtest:</p>
            <div class="input-group">
                <input type="text" class="form-control" readonly :value="webhookUrl">
                <button class="btn btn-outline-primary" @click="copyWebhook()">Copy</button>
            </div>
            <p class="text-secondary small mt-2 mb-0">
                POST JSON result ke URL ini setelah backtest selesai.
            </p>
        </div>
    </div>

    <script>
        function quantConnectDashboard() {
            return {
                isLoading: false,
                backtests: [],
                selectedBacktestId: null,
                currentResult: null,
                tradeFilter: 'all',
                
                // Import state
                showImportModal: false,
                importTab: 'file',
                pasteJson: '',
                isImporting: false,
                importError: '',
                apiCredentials: {
                    userId: '',
                    token: '',
                    projectId: '',
                    backtestId: '',
                },
                
                // Charts
                equityChart: null,
                monthlyChart: null,
                drawdownChart: null,
                
                webhookUrl: window.location.origin + '/api/quantconnect/webhook',

                init() {
                    this.loadBacktests();
                },

                async loadBacktests() {
                    this.isLoading = true;
                    try {
                        const response = await fetch('/api/quantconnect/backtests');
                        const data = await response.json();
                        if (data.success) {
                            this.backtests = data.backtests;
                            if (this.backtests.length > 0 && !this.selectedBacktestId) {
                                this.selectBacktest(this.backtests[0].id);
                            }
                        }
                    } catch (error) {
                        console.error('Failed to load backtests:', error);
                    } finally {
                        this.isLoading = false;
                    }
                },

                async selectBacktest(id) {
                    this.selectedBacktestId = id;
                    this.isLoading = true;
                    try {
                        const response = await fetch(`/api/quantconnect/backtests/${id}`);
                        const data = await response.json();
                        if (data.success) {
                            this.currentResult = data.backtest;
                            this.$nextTick(() => this.renderCharts());
                        }
                    } catch (error) {
                        console.error('Failed to load backtest:', error);
                    } finally {
                        this.isLoading = false;
                    }
                },

                handleFileSelect(event) {
                    const file = event.target.files[0];
                    if (file) this.importFile(file);
                },

                handleFileDrop(event) {
                    event.target.classList.remove('dragover');
                    const file = event.dataTransfer.files[0];
                    if (file) this.importFile(file);
                },

                async importFile(file) {
                    this.isImporting = true;
                    this.importError = '';
                    
                    const formData = new FormData();
                    formData.append('file', file);
                    
                    try {
                        const response = await fetch('/api/quantconnect/backtests/import', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: formData,
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.showImportModal = false;
                            await this.loadBacktests();
                            this.selectBacktest(data.backtest.id);
                        } else {
                            this.importError = data.error || 'Import failed';
                        }
                    } catch (error) {
                        this.importError = error.message;
                    } finally {
                        this.isImporting = false;
                    }
                },

                async doImport() {
                    this.importError = '';
                    
                    if (this.importTab === 'paste') {
                        if (!this.pasteJson.trim()) {
                            this.importError = 'Please paste JSON data';
                            return;
                        }
                        
                        let jsonData;
                        try {
                            jsonData = JSON.parse(this.pasteJson);
                        } catch (e) {
                            this.importError = 'Invalid JSON format';
                            return;
                        }
                        
                        this.isImporting = true;
                        try {
                            const response = await fetch('/api/quantconnect/backtests/import', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                                body: JSON.stringify({ data: jsonData }),
                            });
                            const data = await response.json();
                            if (data.success) {
                                this.showImportModal = false;
                                this.pasteJson = '';
                                await this.loadBacktests();
                                this.selectBacktest(data.backtest.id);
                            } else {
                                this.importError = data.error || 'Import failed';
                            }
                        } catch (error) {
                            this.importError = error.message;
                        } finally {
                            this.isImporting = false;
                        }
                    } else if (this.importTab === 'api') {
                        if (!this.apiCredentials.userId || !this.apiCredentials.token || 
                            !this.apiCredentials.projectId || !this.apiCredentials.backtestId) {
                            this.importError = 'Please fill all API credentials';
                            return;
                        }
                        
                        this.isImporting = true;
                        try {
                            const response = await fetch('/api/quantconnect/backtests/import-api', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                                body: JSON.stringify({
                                    user_id: this.apiCredentials.userId,
                                    api_token: this.apiCredentials.token,
                                    project_id: this.apiCredentials.projectId,
                                    backtest_id: this.apiCredentials.backtestId,
                                }),
                            });
                            const data = await response.json();
                            if (data.success) {
                                this.showImportModal = false;
                                await this.loadBacktests();
                                this.selectBacktest(data.backtest.id);
                            } else {
                                this.importError = data.error || 'Import failed';
                            }
                        } catch (error) {
                            this.importError = error.message;
                        } finally {
                            this.isImporting = false;
                        }
                    }
                },

                async deleteBacktest(id) {
                    if (!confirm('Delete this backtest?')) return;
                    
                    try {
                        const response = await fetch(`/api/quantconnect/backtests/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.currentResult = null;
                            this.selectedBacktestId = null;
                            await this.loadBacktests();
                        }
                    } catch (error) {
                        console.error('Failed to delete:', error);
                    }
                },

                copyWebhook() {
                    navigator.clipboard.writeText(this.webhookUrl);
                    alert('Webhook URL copied!');
                },

                filteredTrades() {
                    const trades = this.currentResult?.trades || [];
                    if (this.tradeFilter === 'wins') return trades.filter(t => (t.pnl || 0) > 0);
                    if (this.tradeFilter === 'losses') return trades.filter(t => (t.pnl || 0) < 0);
                    return trades;
                },

                renderCharts() {
                    this.renderEquityChart();
                    this.renderMonthlyChart();
                    this.renderDrawdownChart();
                },

                renderEquityChart() {
                    const canvas = this.$refs.equityChart;
                    if (!canvas || !this.currentResult?.equityCurve?.length) return;
                    
                    if (this.equityChart) this.equityChart.destroy();
                    
                    const data = this.currentResult.equityCurve;
                    this.equityChart = new Chart(canvas, {
                        type: 'line',
                        data: {
                            labels: data.map(d => d.date),
                            datasets: [{
                                label: 'Equity',
                                data: data.map(d => d.equity),
                                borderColor: '#22c55e',
                                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                fill: true,
                                tension: 0.4,
                                pointRadius: 0,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { display: true, grid: { display: false }, ticks: { color: '#94a3b8' } },
                                y: { display: true, ticks: { callback: v => '$' + (v/1000).toFixed(0) + 'K', color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                            }
                        }
                    });
                },

                renderMonthlyChart() {
                    const canvas = this.$refs.monthlyChart;
                    if (!canvas || !this.currentResult?.monthlyReturns?.length) return;
                    
                    if (this.monthlyChart) this.monthlyChart.destroy();
                    
                    const data = this.currentResult.monthlyReturns;
                    this.monthlyChart = new Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: data.map(d => d.month),
                            datasets: [{
                                data: data.map(d => d.return),
                                backgroundColor: data.map(d => d.return >= 0 ? 'rgba(34, 197, 94, 0.7)' : 'rgba(239, 68, 68, 0.7)'),
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { display: false }, ticks: { color: '#94a3b8' } },
                                y: { ticks: { callback: v => v + '%', color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                            }
                        }
                    });
                },

                renderDrawdownChart() {
                    const canvas = this.$refs.drawdownChart;
                    if (!canvas || !this.currentResult?.equityCurve?.length) return;
                    
                    if (this.drawdownChart) this.drawdownChart.destroy();
                    
                    const equity = this.currentResult.equityCurve.map(d => d.equity);
                    const drawdown = [];
                    let peak = equity[0];
                    for (let e of equity) {
                        peak = Math.max(peak, e);
                        drawdown.push(((e - peak) / peak) * 100);
                    }
                    
                    this.drawdownChart = new Chart(canvas, {
                        type: 'line',
                        data: {
                            labels: this.currentResult.equityCurve.map(d => d.date),
                            datasets: [{
                                data: drawdown,
                                borderColor: '#ef4444',
                                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                                fill: true,
                                tension: 0.4,
                                pointRadius: 0,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { display: true, grid: { display: false }, ticks: { color: '#94a3b8' } },
                                y: { ticks: { callback: v => v + '%', color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                            }
                        }
                    });
                },

                formatPercent(v) {
                    if (v === null || v === undefined) return '--';
                    return v.toFixed(2) + '%';
                },
                formatNumber(v, d = 2) {
                    if (v === null || v === undefined) return '--';
                    return Number(v).toFixed(d);
                },
                formatCurrency(v) {
                    if (v === null || v === undefined) return '--';
                    return '$' + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                formatDate(v) {
                    if (!v) return '--';
                    return new Date(v).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
                },
                formatDateTime(v) {
                    if (!v) return '--';
                    return new Date(v).toLocaleString('en-US', { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' });
                },
                sharpeLabel(v) {
                    if (!v) return 'N/A';
                    if (v >= 2) return 'Excellent';
                    if (v >= 1.5) return 'Very Good';
                    if (v >= 1) return 'Good';
                    return 'Fair';
                }
            };
        }
    </script>
</body>
</html>
