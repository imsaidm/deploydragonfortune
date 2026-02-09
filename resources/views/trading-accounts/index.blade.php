@extends('layouts.app')

@push('head')
<!-- DataTables & FinTech Style -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<style>
    :root {
        --fin-primary: #2563eb;
        --fin-success: #10b981;
        --fin-danger: #ef4444;
        --fin-border: #e2e8f0;
        --fin-bg-soft: #f8fafc;
        --fin-text-dark: #0f172a;
        --fin-text-muted: #64748b;
    }

    body {
        background-color: #f8fafc;
        color: var(--fin-text-dark);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    .fin-card {
        background: #ffffff;
        border: 1px solid var(--fin-border);
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        border-radius: 8px;
    }

    .table thead th {
        background-color: var(--fin-bg-soft);
        color: var(--fin-text-muted);
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 12px 16px;
        border-bottom: 1px solid var(--fin-border);
    }

    .table tbody td {
        padding: 12px 16px;
        vertical-align: middle;
        border-bottom: 1px solid var(--fin-border);
        font-size: 0.85rem;
    }

    .balance-badge {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 2px 6px;
        border-radius: 4px;
        background: var(--fin-bg-soft);
        color: var(--fin-text-muted);
        display: inline-block;
        min-width: 50px;
    }

    .btn-action {
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        border: 1px solid var(--fin-border);
        background: white;
        color: var(--fin-text-muted);
        transition: all 0.2s;
    }

    .btn-action:hover {
        background: var(--fin-bg-soft);
        color: var(--fin-text-dark);
    }

    .modal-content {
        border-radius: 12px;
        border: none;
        box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25);
    }

    .form-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--fin-text-muted);
        text-transform: uppercase;
        margin-bottom: 0.4rem;
    }

    .strategy-item {
        padding: 10px;
        border: 1px solid var(--fin-border);
        border-radius: 8px;
        margin-bottom: 8px;
        transition: all 0.2s;
        cursor: pointer;
    }

    .strategy-item:hover {
        background: var(--fin-bg-soft);
    }

    .strategy-item.active {
        border-color: var(--fin-primary);
        background: #eff6ff;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-4" x-data="tradingAccountsData()" x-init="init()">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 fw-bold mb-1">Trading Wallets</h1>
            <p class="text-muted small mb-0">Multi-account balance synchronization and execution management.</p>
        </div>
        <button class="btn btn-primary fw-bold px-4 d-flex align-items-center gap-2" 
                @click="openModal('create')"
                style="height: 42px; border-radius: 8px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Connect Wallet
        </button>
    </div>

    <!-- Stats Bar -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="fin-card p-3">
                <div class="form-label">Total Accounts</div>
                <div class="h4 fw-bold mb-0 text-dark">{{ count($accounts) }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="fin-card p-3">
                <div class="form-label">Active Keys</div>
                <div class="h4 fw-bold mb-0 text-success">{{ $accounts->where('is_active', true)->count() }}</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="fin-card p-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="form-label mb-0">Total Aggregated Equity</div>
                    <button class="btn btn-link p-0 text-primary small text-decoration-none fw-bold" @click="refreshAllBalances()">
                        Refresh All
                    </button>
                </div>
                <div class="h4 fw-bold mb-0 text-primary" x-text="'$' + formatPrice(totalBalance)">$0.00</div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm mb-4 small fw-bold text-success py-2 d-flex align-items-center gap-2" style="background: #ecfdf5;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Data Table -->
    <div class="fin-card overflow-hidden">
        <div class="table-responsive">
            <table id="walletsTable" class="table mb-0 w-100">
                <thead>
                    <tr>
                        <th>Account Name</th>
                        <th>API Key</th>
                        <th>Spot</th>
                        <th>Futures</th>
                        <th>Funding</th>
                        <th>Total (USDT)</th>
                        <th class="text-end px-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if($account->exchange === 'binance')
                                        <img src="/images/binancelogo.png" width="18" height="18">
                                    @elseif($account->exchange === 'bybit')
                                        <img src="/images/bybitlogo.png" width="18" height="18">
                                    @endif
                                    <span class="fw-bold">{{ $account->account_name }}</span>
                                    @if(!$account->is_active) 
                                        <span class="badge bg-danger-subtle text-danger" style="font-size: 0.6rem;">DISABLED</span>
                                    @endif
                                </div>
                            </td>
                            <td><code class="text-muted small">{{ Str::mask($account->api_key, '*', 4, 12) }}</code></td>
                            <td><span class="text-dark fw-medium" x-text="balances['{{ $account->id }}'] ? '$' + formatPrice(balances['{{ $account->id }}'].spot) : '—'">—</span></td>
                            <td><span class="text-dark fw-medium" x-text="balances['{{ $account->id }}'] ? '$' + formatPrice(balances['{{ $account->id }}'].futures) : '—'">—</span></td>
                            <td><span class="text-dark fw-medium" x-text="balances['{{ $account->id }}'] ? '$' + formatPrice(balances['{{ $account->id }}'].funding) : '—'">—</span></td>
                            <td><span class="text-primary fw-bold" x-text="balances['{{ $account->id }}'] ? '$' + formatPrice(balances['{{ $account->id }}'].total) : '—'">—</span></td>
                            <td class="text-end px-3">
                                <div class="d-flex gap-2 justify-content-end">
                                    <button class="btn-action" @click="openStrategyModal({{ $account->id }})" title="Link Strategy">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                                        </svg>
                                    </button>
                                    <button class="btn-action" @click="getBalance({{ $account->id }})" title="Sync Balance">
                                        <template x-if="!loading['{{ $account->id }}']">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.137-3.36L23 10M20.49 15a9 9 0 0 1-14.137 3.36L1 14"/>
                                            </svg>
                                        </template>
                                        <span x-show="loading['{{ $account->id }}']" class="spinner-border spinner-border-sm" style="width: 12px; height: 12px;"></span>
                                    </button>
                                    <button class="btn-action" @click="openModal('edit', {{ json_encode($account) }})">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </button>
                                    <form action="{{ route('trading-accounts.destroy', $account->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus wallet?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-action text-danger">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Management Modal -->
    <div class="modal fade" id="accountModal" tabindex="-1" aria-hidden="true" x-ref="accountModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header border-bottom py-3">
                    <h5 class="fw-bold mb-0" x-text="modalMode === 'create' ? 'Connect New Account' : 'Edit Account'"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form :action="modalMode === 'create' ? '{{ route('trading-accounts.store') }}' : '{{ url('trading-accounts') }}/' + form.id" 
                      method="POST">
                    @csrf
                    <template x-if="modalMode === 'edit'">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nickname</label>
                            <input type="text" name="account_name" class="form-control" x-model="form.account_name" required placeholder="e.g. Trading Bot 01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Exchange</label>
                            <select name="exchange" class="form-select" x-model="form.exchange"  required>
                                <option value="binance">Binance</option>
                                <option value="bybit">Bybit</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">API Key</label>
                            <input type="text" name="api_key" class="form-control" x-model="form.api_key" required placeholder="Paste API Key">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Secret Key</label>
                            <input type="password" name="secret_key" class="form-control" x-model="form.secret_key" :required="modalMode === 'create'" placeholder="••••••••••••••••">
                            <template x-if="modalMode === 'edit'">
                                <small class="text-primary mt-1 d-block opacity-75">Leave blank to keep current secret.</small>
                            </template>
                        </div>
                        
                        <div class="p-3 bg-light rounded d-flex align-items-center justify-content-between border">
                            <div>
                                <div class="fw-bold small">Execution Enabled</div>
                                <div class="text-muted" style="font-size: 0.65rem;">Allow engine to place trades with this key.</div>
                            </div>
                            <div class="form-check form-switch p-0 m-0">
                                <input class="form-check-input ms-0" type="checkbox" name="is_active" value="1" 
                                       x-model="form.is_active" style="transform: scale(1.2); cursor: pointer;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3">
                        <button type="button" class="btn btn-outline-secondary px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" x-text="modalMode === 'create' ? 'Connect' : 'Save'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Strategy Linking Modal -->
    <div class="modal fade" id="strategyModal" tabindex="-1" aria-hidden="true" x-ref="strategyModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header border-bottom py-3" style="background: var(--fin-bg-soft);">
                    <div>
                        <h5 class="fw-bold mb-0">Strategy Alignment</h5>
                        <p class="text-muted small mb-0" x-text="'Configuring ' + currentAccountName"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <div class="form-label mb-2">Select Following Strategies</div>
                        <div class="overflow-auto" style="max-height: 400px;">
                            <template x-for="strategy in availableStrategies" :key="strategy.id">
                                <div class="strategy-item d-flex align-items-center gap-3" 
                                     :class="selectedStrategyIds.includes(strategy.id) ? 'active' : ''"
                                     @click="toggleStrategy(strategy.id)">
                                    <div class="form-check m-0 p-0" style="pointer-events: none;">
                                        <input class="form-check-input ms-0" type="checkbox" :checked="selectedStrategyIds.includes(strategy.id)">
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-dark" x-text="strategy.nama_metode"></div>
                                        <div class="text-muted small" x-text="strategy.pair + ' • ' + (strategy.onactive ? 'Active' : 'Global Inactive')"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-outline-secondary px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary px-4 fw-bold shadow-sm d-flex align-items-center gap-2" 
                            @click="saveStrategies()" :disabled="savingStrategies">
                        <span x-show="savingStrategies" class="spinner-border spinner-border-sm"></span>
                        Apply Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
function tradingAccountsData() {
    return {
        modalMode: 'create',
        form: { id: null, account_name: '', exchange: 'binance', api_key: '', secret_key: '', is_active: true },
        balances: {},
        loading: {},
        modal: null,
        strategyModal: null,
        totalBalance: 0,
        table: null,

        // Strategy Linking
        currentAccountId: null,
        currentAccountName: '',
        availableStrategies: [],
        selectedStrategyIds: [],
        savingStrategies: false,

        init() {
            this.initUI();
            this.$nextTick(() => {
                this.refreshAllBalances();
            });
        },

        initUI() {
            if (typeof $ !== 'undefined' && $.fn.DataTable) {
                if ($.fn.DataTable.isDataTable('#walletsTable')) {
                    $('#walletsTable').DataTable().destroy();
                }

                this.table = $('#walletsTable').DataTable({
                    pageLength: 20,
                    lengthMenu: [[10, 20, 50, -1], [10, 20, 50, "All"]],
                    language: { 
                        search: "", 
                        searchPlaceholder: "Filter wallets...",
                        emptyTable: "No wallets connected yet"
                    },
                    columnDefs: [
                        { orderable: false, targets: [2, 7] }
                    ],
                });
            }

            if (typeof bootstrap !== 'undefined') {
                if (this.$refs.accountModal) this.modal = new bootstrap.Modal(this.$refs.accountModal);
                if (this.$refs.strategyModal) this.strategyModal = new bootstrap.Modal(this.$refs.strategyModal);
            } else {
                setTimeout(() => this.initUI(), 500);
            }
        },

        openModal(mode, account = null) {
            this.modalMode = mode;
            if (mode === 'edit' && account) {
                this.form = { 
                    id: account.id, 
                    account_name: account.account_name, 
                    exchange: account.exchange || 'binance',
                    api_key: account.api_key, 
                    secret_key: '', 
                    is_active: !!account.is_active 
                };
            } else {
                this.form = { id: null, account_name: '', exchange: 'binance', api_key: '', secret_key: '', is_active: true };
            }
            if (this.modal) this.modal.show();
        },

        async openStrategyModal(accountId) {
            this.currentAccountId = accountId;
            const account = @json($accounts).find(a => a.id === accountId);
            this.currentAccountName = account ? account.account_name : 'Wallet';
            
            try {
                const response = await fetch(`{{ url('trading-accounts') }}/${accountId}/strategies`);
                const data = await response.json();
                if (data.success) {
                    this.availableStrategies = data.strategies;
                    this.selectedStrategyIds = data.linked_ids.map(Number);
                    if (this.strategyModal) this.strategyModal.show();
                }
            } catch (e) {
                console.error('Failed to load strategies:', e);
            }
        },

        toggleStrategy(id) {
            const index = this.selectedStrategyIds.indexOf(id);
            if (index === -1) this.selectedStrategyIds.push(id);
            else this.selectedStrategyIds.splice(index, 1);
        },

        async saveStrategies() {
            this.savingStrategies = true;
            try {
                const response = await fetch(`{{ url('trading-accounts') }}/${this.currentAccountId}/strategies`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ strategy_ids: this.selectedStrategyIds })
                });
                const data = await response.json();
                if (data.success) {
                    if (this.strategyModal) this.strategyModal.hide();
                    window.location.reload(); // Quick refresh to show active count
                }
            } catch (e) {
                console.error('Failed to save strategies:', e);
            } finally {
                this.savingStrategies = false;
            }
        },

        async getBalance(accountId) {
            if (this.loading[accountId]) return;
            this.loading[accountId] = true;
            try {
                const response = await fetch(`{{ url('trading-accounts') }}/${accountId}/balance`);
                const data = await response.json();
                if (data.success) {
                    this.balances[accountId] = data.balance;
                    this.calculateTotal();
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.loading[accountId] = false;
            }
        },

        refreshAllBalances() {
            @foreach($accounts as $account)
                if (!this.balances['{{ $account->id }}']) {
                    this.balances['{{ $account->id }}'] = { spot: 0, futures: 0, funding: 0, total: 0 };
                }
                this.getBalance({{ $account->id }});
            @endforeach
        },

        calculateTotal() {
            this.totalBalance = Object.values(this.balances).reduce((sum, b) => sum + (parseFloat(b.total) || 0), 0);
        },

        formatPrice(v) {
            return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }
}
</script>
@endsection
@endsection
