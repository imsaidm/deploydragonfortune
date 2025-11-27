@extends('layouts.app')

@section('title', 'ETF Flows | DragonFortune')

@push('head')
    <!-- Resource Hints for Faster API Loading -->
    <link rel="dns-prefetch" href="{{ config('app.api_urls.internal') }}">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="{{ config('app.api_urls.internal') }}" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="{{ asset('js/etf-flows/controller-coinglass.js') }}?v={{ time() }}" as="script" crossorigin="anonymous">
@endpush

@section('content')
    {{--
        Bitcoin ETF Flows Dashboard
        Track daily inflows and outflows of Bitcoin ETFs
        
        Data Source: Coinglass ETF Flow History API
        - Daily aggregated flows across all major Bitcoin ETFs
        - Individual ETF breakdown available
        - Institutional sentiment indicator
    --}}

    <div class="d-flex flex-column h-100 gap-3" x-data="etfFlowsController()">
        <!-- Page Header -->
        <div class="derivatives-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h1 class="mb-0">Bitcoin ETF Flows</h1>
                    <span class="spinner-border spinner-border-sm text-primary" style="width: 16px; height: 16px;" x-show="rawData.length === 0" x-cloak></span>
                </div>
                <p class="mb-0 text-secondary">
                    Track daily inflows and outflows of Bitcoin ETFs to gauge institutional sentiment. 
                </p>
            </div>

            <!-- Global Controls -->
            <div class="d-flex gap-2 align-items-center flex-wrap">
                    <!-- Time Range Selector -->
                    <select class="form-select" style="width: 120px;" :value="selectedTimeRange" @change="updateTimeRange($event.target.value)">
                        <template x-for="range in timeRanges" :key="range.value">
                            <option :value="range.value" x-text="range.label"></option>
                        </template>
                    </select>

                    <!-- Manual Refresh -->
                    <button class="btn btn-outline-primary d-flex align-items-center gap-2" type="button"
                        @click="loadData(); loadCmeOpenInterest();">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"/>
                            <polyline points="1 20 1 14 7 14"/>
                            <path d="M3.51 9a9 9 0 0 1 14.137-3.36L23 10"/>
                            <path d="M20.49 15a9 9 0 0 1-14.137 3.36L1 14"/>
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary Cards Row -->
        <div class="row g-3">
            <!-- Current Flow -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Latest Flow</span>
                        <span class="badge text-bg-primary" x-show="currentFlow !== null">Latest</span>
                        <span class="badge text-bg-secondary" x-show="currentFlow === null">Loading...</span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="currentFlow !== null" x-text="formatFlow(currentFlow)" :class="currentFlow >= 0 ? 'text-success' : 'text-danger'"></div>
                        <div class="h3 mb-1 text-secondary" x-show="currentFlow === null">...</div>
                        <small class="text-muted">
                            <span x-show="currentFlow > 0" class="text-success">📈 Inflow</span>
                            <span x-show="currentFlow < 0" class="text-danger">📉 Outflow</span>
                            <span x-show="currentFlow === 0" class="text-muted">➡️ Neutral</span>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Total Inflows -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Total Inflows</span>
                        <span class="badge text-bg-success">Positive</span>
                    </div>
                    <div>
                        <div class="h3 mb-1 text-success" x-show="totalInflow !== null" x-text="formatFlow(totalInflow)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="totalInflow === null">...</div>
                        <small class="text-muted">
                            Cumulative inflows
                        </small>
                    </div>
                </div>
            </div>

            <!-- Total Outflows -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Total Outflows</span>
                        <span class="badge text-bg-danger">Negative</span>
                    </div>
                    <div>
                        <div class="h3 mb-1 text-danger" x-show="totalOutflow !== null" x-text="formatFlow(totalOutflow)"></div>
                        <div class="h3 mb-1 text-secondary" x-show="totalOutflow === null">...</div>
                        <small class="text-muted">
                            Cumulative outflows
                        </small>
                    </div>
                </div>
            </div>

            <!-- Net Flow -->
            <div class="col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-secondary">Net Flow</span>
                        <span class="badge" :class="netFlow > 0 ? 'text-bg-success' : netFlow < 0 ? 'text-bg-danger' : 'text-bg-secondary'">
                            <span x-show="netFlow > 0">📈 Bullish</span>
                            <span x-show="netFlow < 0">📉 Bearish</span>
                            <span x-show="netFlow === 0">➡️ Neutral</span>
                        </span>
                    </div>
                    <div>
                        <div class="h3 mb-1" x-show="netFlow !== null" x-text="formatFlow(netFlow)" :class="netFlow >= 0 ? 'text-success' : 'text-danger'"></div>
                        <div class="h3 mb-1 text-secondary" x-show="netFlow === null">...</div>
                        <small class="text-muted">
                            Inflows - Outflows
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 1: DAILY FLOWS -->
        <div class="row g-3">
            <div class="col-12">
                <div class="tradingview-chart-container">
                    <div class="chart-header">
                        <div class="d-flex align-items-center gap-3">
                            <h5 class="mb-0">Daily ETF Flows</h5>
                            <!-- Legend -->
                            <div class="d-flex align-items-center gap-3 ms-3">
                                <div class="d-flex align-items-center gap-1">
                                    <div style="width: 16px; height: 16px; background: rgba(34, 197, 94, 0.8); border-radius: 3px;"></div>
                                    <small class="text-muted" style="font-size: 0.85rem;">Inflow</small>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <div style="width: 16px; height: 16px; background: rgba(239, 68, 68, 0.8); border-radius: 3px;"></div>
                                    <small class="text-muted" style="font-size: 0.85rem;">Outflow</small>
                                </div>
                            </div>
                        </div>
                        <div class="chart-controls">
                            <!-- Time Range Buttons -->
                            <div class="time-range-selector me-3">
                                <template x-for="range in timeRanges" :key="range.value">
                                    <button type="button" 
                                            class="btn btn-sm time-range-btn"
                                            :class="selectedTimeRange === range.value ? 'btn-primary' : 'btn-outline-secondary'"
                                            @click="updateTimeRange(range.value)"
                                            x-text="range.label">
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="chart-body" style="position: relative;">
                        <canvas id="etfFlowsMainChart"></canvas>
                    </div>
                    <div class="chart-footer">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <small class="chart-footer-text">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-right: 4px;">
                                    <circle cx="6" cy="6" r="5" fill="none" stroke="currentColor" stroke-width="1"/>
                                    <path d="M6 3v3l2 2" stroke="currentColor" stroke-width="1" fill="none"/>
                                </svg>
                                <strong>Bar height</strong> = Flow magnitude | <strong>Green bars</strong> = Institutional buying (inflow) | <strong>Red bars</strong> = Profit-taking (outflow)
                            </small>
                            <small class="text-muted">
                                <span class="badge text-bg-success">Coinglass API</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: ETF RANKINGS -->
        <div class="row g-3 mt-4">
            <div class="col-12">
                <div class="df-panel p-4">
                            <!-- Table Header with Search -->
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h5 class="mb-1">Bitcoin ETF Rankings</h5>
                                    <small class="text-muted">
                                        Compare performance, holdings, and premiums across all Bitcoin ETFs
                                    </small>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="text" 
                                           class="form-control form-control-sm" 
                                           placeholder="Search ticker or name..."
                                           style="width: 250px;"
                                           x-model="etfTableFilter"
                                           @input="filterEtfTable">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            @click="refreshEtfList"
                                            :disabled="etfListLoading">
                                        <i class="fas fa-sync-alt" :class="{ 'fa-spin': etfListLoading }"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Loading State -->
                            <div x-show="etfListLoading && etfList.length === 0" 
                                 class="text-center py-5">
                                <div class="spinner-border text-primary mb-3"></div>
                                <p class="text-muted">Loading ETF data...</p>
                            </div>

                            <!-- Table -->
                            <div x-show="!etfListLoading || etfList.length > 0" 
                                 class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th @click="sortEtfTable('rank')" style="cursor: pointer;">
                                                <span x-html="getSortIcon('rank')"></span> Rank
                                            </th>
                                            <th @click="sortEtfTable('ticker')" style="cursor: pointer;">
                                                <span x-html="getSortIcon('ticker')"></span> Ticker
                                            </th>
                                            <th @click="sortEtfTable('aum_usd')" style="cursor: pointer;">
                                                <span x-html="getSortIcon('aum_usd')"></span> AUM
                                            </th>
                                            <th @click="sortEtfTable('holding_quantity')" style="cursor: pointer;">
                                                <span x-html="getSortIcon('holding_quantity')"></span> Holdings
                                            </th>
                                            <th @click="sortEtfTable('change_percent_24h')" style="cursor: pointer;">
                                                <span x-html="getSortIcon('change_percent_24h')"></span> 24h Change
                                            </th>
                                            <th @click="sortEtfTable('premium_discount_bps')" style="cursor: pointer;">
                                                <span x-html="getSortIcon('premium_discount_bps')"></span> P/D
                                            </th>
                                            <th @click="sortEtfTable('management_fee_percent')" style="cursor: pointer;">
                                                <span x-html="getSortIcon('management_fee_percent')"></span> Fee
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(etf, index) in getFilteredEtfList()" :key="etf.ticker">
                                            <tr>
                                                <td>
                                                    <span class="badge bg-light text-dark" x-text="getRankBadge(index)"></span>
                                                </td>
                                                <td>
                                                    <div class="fw-bold" x-text="etf.ticker"></div>
                                                    <small class="text-muted" x-text="etf.fund_name"></small>
                                                </td>
                                                <td>
                                                    <span class="fw-semibold" x-text="formatCurrency(etf.aum_usd)"></span>
                                                </td>
                                                <td>
                                                    <span x-text="formatBTC(etf.holding_quantity)"></span>
                                                </td>
                                                <td>
                                                    <span :class="getChangeClass(etf.change_percent_24h)" 
                                                          x-text="formatPercent(etf.change_percent_24h)">
                                                    </span>
                                                </td>
                                                <td>
                                                    <span :class="getPDClass(etf.premium_discount_bps)" 
                                                          x-text="formatBPS(etf.premium_discount_bps)">
                                                    </span>
                                                </td>
                                                <td>
                                                    <span x-text="formatPercent(etf.management_fee_percent)"></span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                        </div>

                            <!-- Summary Stats -->
                            <div class="row g-3 mt-4 pt-4 border-top">
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <div class="text-muted small mb-1">Total AUM</div>
                                        <div class="h4 mb-0" x-text="formatCurrency(getTotalAUM())"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <div class="text-muted small mb-1">Total BTC Holdings</div>
                                        <div class="h4 mb-0" x-text="formatBTC(getTotalHoldings())"></div>
                                    </div>
                                </div>
                        <div class="col-md-4">
                                    <div class="text-center">
                                        <div class="text-muted small mb-1">Average Fee</div>
                                        <div class="h4 mb-0" x-text="formatPercent(getAverageFee())"></div>
                                    </div>
                                </div>
                            </div>
                                </div>
                            </div>
                        </div>

        <!-- SECTION 3: PREMIUM/DISCOUNT TRACKER -->
        <div class="row g-3 mt-4">
            <div class="col-12">
                        <div class="tradingview-chart-container">
                            <div class="chart-header">
                                <div class="d-flex align-items-center gap-3">
                                    <h5 class="mb-0">Premium/Discount Tracker</h5>
                                    <small class="text-muted">Compare ETF premium/discount trends</small>
                                </div>
                                <div class="chart-controls">
                                    <!-- ETF Selector (Multi-select) -->
                                    <div class="d-flex gap-2 align-items-center flex-wrap">
                                        <template x-for="ticker in availableTickers" :key="ticker">
                                            <button type="button"
                                                    class="btn btn-sm"
                                                    :class="selectedTickers.includes(ticker) ? 'btn-primary' : 'btn-outline-secondary'"
                                                    @click="toggleTicker(ticker)"
                                                    x-text="ticker">
                                            </button>
                                        </template>
                                        <button class="btn btn-sm btn-outline-primary ms-2" 
                                                @click="refreshPremiumDiscount"
                                                :disabled="pdLoading">
                                            <i class="fas fa-sync-alt" :class="{ 'fa-spin': pdLoading }"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="chart-body" style="position: relative;">
                                <!-- Loading -->
                                <div x-show="pdLoading" 
                                     class="position-absolute top-50 start-50 translate-middle">
                                    <div class="spinner-border text-primary"></div>
                                </div>
                                <!-- Chart Canvas -->
                                <canvas id="premiumDiscountChart"></canvas>
                            </div>
                            <div class="chart-footer">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <small class="text-muted">
                                        <strong>Negative (Green)</strong> = Discount (buying opportunity) | 
                                        <strong>Positive (Red)</strong> = Premium | 
                                        <strong>0 BPS</strong> = Trading at NAV
                                    </small>
                                    <small class="text-muted">
                                        <span class="badge text-bg-success">Coinglass API</span>
                                    </small>
                        </div>
                                </div>
                            </div>
                        </div>
                    </div>

        <!-- SECTION 4: CME FUTURES OPEN INTEREST -->
        <div class="row g-3 mt-4">
            <div class="col-12">
                <div class="tradingview-chart-container">
                    <div class="chart-header">
                        <div class="d-flex align-items-center gap-3">
                            <h5 class="mb-0">CME Futures Open Interest</h5>
                            <small class="text-muted">Track institutional exposure through CME Bitcoin Futures</small>
                        </div>
                        <div class="chart-controls d-flex gap-3 align-items-center flex-wrap">
                            <!-- Current OI Badge (in contracts) -->
                            <div class="badge text-bg-info" x-show="cmeOiLatest > 0">
                                <strong x-text="formatCurrency(cmeOiLatest) + ' contracts'"></strong>
                            </div>
                            <!-- Change Badge -->
                            <div class="badge" x-show="cmeOiChange !== 0" 
                                 :class="cmeOiChange > 0 ? 'text-bg-success' : 'text-bg-danger'">
                                <span x-text="(cmeOiChange > 0 ? '↑' : '↓') + ' ' + Math.abs(cmeOiChangePercent).toFixed(2) + '%'"></span>
                            </div>
                            
                            <!-- Time Range Selector (SAME AS FUNDING RATE) -->
                            <div class="time-range-selector">
                                <template x-for="range in cmeTimeRanges" :key="range.value">
                                    <button type="button" 
                                            class="time-range-btn"
                                            :class="selectedCmeTimeRange === range.value ? 'btn-primary' : ''"
                                            @click="updateCmeTimeRange(range.value)"
                                            x-text="range.label">
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="chart-body">
                        <!-- Loading Overlay (like Premium/Discount chart) -->
                        <div x-show="cmeLoading && cmeData.length === 0" 
                             class="position-absolute top-50 start-50 translate-middle"
                             style="z-index: 10;">
                            <div class="spinner-border text-primary"></div>
                        </div>
                        <!-- Chart Canvas (always visible like other charts) -->
                        <canvas id="cmeOiChart"></canvas>
                    </div>
                    <div class="chart-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="chart-footer-text">
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" style="margin-right: 4px;">
                                    <circle cx="6" cy="6" r="5" fill="none" stroke="currentColor" stroke-width="1"/>
                                    <path d="M6 3v3l2 2" stroke="currentColor" stroke-width="1" fill="none"/>
                                </svg>
                                CME Open Interest data from Coinglass API
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
    <!-- Chart.js with Date Adapter -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js" defer></script>

    <!-- Initialize Chart.js ready promise -->
    <script>
        window.chartJsReady = new Promise((resolve) => {
            if (typeof Chart !== 'undefined') {
                console.log('✅ Chart.js already loaded');
                resolve();
                return;
            }
            
            let checkCount = 0;
            const checkInterval = setInterval(() => {
                checkCount++;
                if (typeof Chart !== 'undefined') {
                    console.log('✅ Chart.js loaded (after', checkCount * 50, 'ms)');
                    clearInterval(checkInterval);
                    resolve();
                } else if (checkCount > 40) {
                    console.warn('⚠️ Chart.js load timeout, resolving anyway');
                    clearInterval(checkInterval);
                    resolve();
                }
            }, 50);
        });
    </script>

    <!-- ETF Flows Controller - Load before Alpine initializes -->
    <script type="module">
        // Import and register controller before Alpine initializes
        import { EtfFlowsAPIService } from '{{ asset('js/etf-flows/api-service.js') }}';
        import { ChartManager } from '{{ asset('js/etf-flows/chart-manager.js') }}';
        import { EtfFlowsUtils } from '{{ asset('js/etf-flows/utils.js') }}';

        // Alpine.js controller function
        function etfFlowsController() {
            return {
                initialized: false,
                apiService: null,
                chartManager: null,

                // State - ETF Flows is Bitcoin only, no symbol selection needed
                selectedTimeRange: '1m', // Default 1 month

                // Time ranges for filtering display (ETF data is daily)
                timeRanges: [
                    { label: '1W', value: '1w', days: 7 },
                    { label: '1M', value: '1m', days: 30 },
                    { label: '3M', value: '3m', days: 90 },
                    { label: '6M', value: '6m', days: 180 },
                    { label: '1Y', value: '1y', days: 365 },
                    { label: 'ALL', value: 'all', days: 1095 }
                ],

                // Loading state
                isLoading: false,

                // Auto-refresh
                refreshInterval: null,
                refreshEnabled: true,
                errorCount: 0,
                maxErrors: 3,

                // Data (Flow-based)
                rawData: [],
                currentFlow: null,    // Latest flow value
                totalInflow: null,    // Total positive flows
                totalOutflow: null,   // Total negative flows
                netFlow: null,        // Net flow (inflow - outflow)
                avgDailyFlow: null,   // Average daily flow
                flowTrend: null,      // Recent trend (positive/negative)

                // CME Open Interest State
                cmeData: [],
                cmeLoading: false,
                cmeIsRendering: false, // ⚡ Race condition guard (from Funding Rate)
                cmeOiLatest: 0,
                cmeOiChange: 0,
                cmeOiChangePercent: 0,
                
                // Time ranges (SAME AS FUNDING RATE)
                cmeTimeRanges: [
                    { label: '1D', value: '1d', days: 1 },
                    { label: '1W', value: '1w', days: 7 },
                    { label: '1M', value: '1m', days: 30 },
                    { label: '3M', value: '3m', days: 90 },
                    { label: '1Y', value: '1y', days: 365 },
                    { label: 'ALL', value: 'all', days: 1095 } // ~3 years
                ],
                selectedCmeTimeRange: '1m', // Default 1 month
                
                // Fixed interval: Always use daily (1d) data
                selectedCmeInterval: '1d', // Fixed to daily only
                
                cmeChartInstance: null,

                async init() {
                    if (this.initialized) return;
                    this.initialized = true;

                    console.log('🚀 ETF Flows (Coinglass) initialized');

                    this.apiService = new EtfFlowsAPIService();
                    this.chartManager = new ChartManager('etfFlowsMainChart');

                    // Load all 4 sections in parallel
                    await Promise.all([
                        this.loadData(),               // Section 1: Daily Flows
                        this.loadEtfList(),            // Section 2: ETF Rankings
                        this.loadPremiumDiscount(),    // Section 3: Premium/Discount
                        this.loadCmeOpenInterest()     // Section 4: CME Futures OI
                    ]);

                    // Start auto-refresh for real-time updates
                    this.startAutoRefresh();
                },

                async loadData(isAutoRefresh = false) {
                    if (this.isLoading && !isAutoRefresh) {
                        console.warn('⚠️ Load already in progress, skipping');
                        return;
                    }

                    const startTime = performance.now();
                    this.isLoading = true;

                    try {
                        const { start_time, end_time } = this.getDateRange();

                        console.log('[ETF:LOAD]', {
                            range: this.selectedTimeRange
                        });

                        const fetchStart = performance.now();

                        // ETF Flow History (Bitcoin only)
                        const data = await this.apiService.fetchFlowHistory({
                            preferFresh: !isAutoRefresh
                        });

                        const fetchEnd = performance.now();
                        const fetchTime = Math.round(fetchEnd - fetchStart);

                        console.log('[ETF:DEBUG] Raw API response:', data);
                        
                        if (data && data.success && data.data && data.data.length > 0) {
                            // Filter data based on selected time range
                            this.rawData = this.filterDataByTimeRange(data.data);
                            this.calculateMetrics();

                            // Update UI
                            this.renderChart();
                            this.updateStatsCards();
                            this.updateLastRefreshTime();

                            // Reset error count on successful load
                            this.errorCount = 0;

                            const totalTime = Math.round(performance.now() - startTime);
                            console.log(`[ETF:OK] ${this.rawData.length} points (fetch: ${fetchTime}ms, total: ${totalTime}ms)`);
                        } else {
                            console.warn('[ETF:EMPTY] No data received:', data);
                        }
                    } catch (error) {
                        console.error('[ETF:ERROR]', error);

                        // Circuit breaker: Prevent infinite error loops
                        this.errorCount++;
                        if (this.errorCount >= this.maxErrors) {
                            console.error('🚨 Circuit breaker tripped! Too many errors, stopping auto-refresh');
                            this.stopAutoRefresh();

                            // Reset after 5 minutes
                            setTimeout(() => {
                                console.log('🔄 Circuit breaker reset, resuming auto-refresh');
                                this.errorCount = 0;
                                this.startAutoRefresh();
                            }, 300000); // 5 minutes
                        }
                    } finally {
                        this.isLoading = false;
                    }
                },

                filterDataByTimeRange(data) {
                    if (this.selectedTimeRange === 'all') return data;
                    
                    const range = this.timeRanges.find(r => r.value === this.selectedTimeRange);
                    if (!range) return data;
                    
                    const cutoffDate = Date.now() - (range.days * 24 * 60 * 60 * 1000);
                    return data.filter(item => item.ts >= cutoffDate);
                },

                getDateRange() {
                    const now = Date.now();
                    const range = this.timeRanges.find(r => r.value === this.selectedTimeRange);
                    const days = range ? range.days : 30;
                    const start_time = now - (days * 24 * 60 * 60 * 1000);
                    return { start_time, end_time: now };
                },

                calculateMetrics() {
                    if (this.rawData.length === 0) return;

                    const metrics = this.computeFlowMetrics(this.rawData);

                    // Update properties
                    this.currentFlow = metrics.currentFlow;
                    this.totalInflow = metrics.totalInflow;
                    this.totalOutflow = metrics.totalOutflow;
                    this.netFlow = metrics.netFlow;
                    this.avgDailyFlow = metrics.avgDailyFlow;
                    this.flowTrend = metrics.flowTrend;
                },

                computeFlowMetrics(rawData) {
                    if (rawData.length === 0) return {};

                    const flows = rawData.map(d => parseFloat(d.flow_usd || 0));
                    
                    // Current flow (latest)
                    const currentFlow = flows[flows.length - 1];
                    
                    // Separate inflows and outflows
                    const inflows = flows.filter(f => f > 0);
                    const outflows = flows.filter(f => f < 0);
                    
                    // Calculate totals
                    const totalInflow = inflows.reduce((a, b) => a + b, 0);
                    const totalOutflow = Math.abs(outflows.reduce((a, b) => a + b, 0));
                    const netFlow = totalInflow - totalOutflow;
                    const avgDailyFlow = flows.reduce((a, b) => a + b, 0) / flows.length;
                    
                    // Calculate trend (last 7 days vs previous 7 days)
                    let flowTrend = 0;
                    if (flows.length >= 14) {
                        const recent7 = flows.slice(-7).reduce((a, b) => a + b, 0) / 7;
                        const previous7 = flows.slice(-14, -7).reduce((a, b) => a + b, 0) / 7;
                        if (previous7 !== 0) {
                            flowTrend = ((recent7 - previous7) / Math.abs(previous7)) * 100;
                        }
                    }

                    return {
                        currentFlow,
                        totalInflow,
                        totalOutflow,
                        netFlow,
                        avgDailyFlow,
                        flowTrend
                    };
                },

                renderChart() {
                    if (!this.chartManager || this.rawData.length === 0) return;
                    this.chartManager.renderChart(this.rawData);
                },

                instantLoadData() {
                    console.log('⚡ Instant load triggered');

                    if (this.isLoading) {
                        console.log('⚡ Force loading for user interaction (overriding current load)');
                        this.isLoading = false;
                    }

                    this.loadData();
                },

                setTimeRange(value) {
                    console.log('🎯 setTimeRange called with:', value, 'current:', this.selectedTimeRange);
                    if (this.selectedTimeRange === value) {
                        console.log('⚠️ Same time range, skipping');
                        return;
                    }
                    console.log('🎯 Time range changed to:', value);
                    this.selectedTimeRange = value;
                    console.log('🚀 Filter changed, triggering instant load');
                    this.instantLoadData();
                },

                updateTimeRange(value) {
                    console.log('🎯 updateTimeRange called with:', value);
                    this.setTimeRange(value);
                },

                startAutoRefresh() {
                    this.stopAutoRefresh();

                    if (!this.refreshEnabled) return;

                    // 5 second interval for all sections
                    this.refreshInterval = setInterval(() => {
                        if (document.hidden) return;
                        if (this.isLoading || this.etfListLoading || this.pdLoading || this.cmeLoading) return;
                        if (this.errorCount >= this.maxErrors) {
                            console.warn('🚨 Auto-refresh disabled due to errors');
                            this.stopAutoRefresh();
                            return;
                        }

                        console.log('🔄 Auto-refresh: Silent update (5s) - ALL 4 sections');
                        
                        // Refresh ALL 4 sections in parallel
                        Promise.all([
                            this.loadData(true),               // Section 1: Daily Flows
                            this.refreshEtfList(),             // Section 2: ETF Rankings
                            this.refreshPremiumDiscount(),     // Section 3: Premium/Discount
                            this.loadCmeOpenInterest()         // Section 4: CME Futures OI
                        ]).catch(error => {
                            console.error('Auto-refresh error:', error);
                            this.errorCount++;
                        });

                    }, 5000); // 5 seconds

                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden && this.refreshEnabled) {
                            console.log('👁️ Page visible: Triggering refresh');
                            if (!this.isLoading) {
                                this.loadData(true);
                            }
                        }
                    });

                    console.log('✅ Auto-refresh started (5s interval) for ALL 4 sections');
                },

                stopAutoRefresh() {
                    if (this.refreshInterval) {
                        clearInterval(this.refreshInterval);
                        this.refreshInterval = null;
                        console.log('⏹️ Auto-refresh stopped');
                    }
                },

                cleanup() {
                    this.stopAutoRefresh();
                    if (this.chartManager) this.chartManager.destroy();
                },

                onTimeRangeChange(range) {
                    if (this.selectedTimeRange !== range) {
                        this.selectedTimeRange = range;
                        this.loadData(false);
                    }
                },

                updateStatsCards() {
                    // Update current flow
                    const currentFlowElement = document.getElementById('currentFlow');
                    if (currentFlowElement && this.currentFlow !== null) {
                        const color = EtfFlowsUtils.getFlowColor(this.currentFlow);
                        currentFlowElement.style.color = color;
                        currentFlowElement.textContent = EtfFlowsUtils.formatFlow(this.currentFlow);
                    }

                    // Update total inflow
                    const totalInflowElement = document.getElementById('totalInflow');
                    if (totalInflowElement && this.totalInflow !== null) {
                        totalInflowElement.textContent = EtfFlowsUtils.formatFlow(this.totalInflow);
                    }

                    // Update total outflow
                    const totalOutflowElement = document.getElementById('totalOutflow');
                    if (totalOutflowElement && this.totalOutflow !== null) {
                        totalOutflowElement.textContent = EtfFlowsUtils.formatFlow(this.totalOutflow);
                    }

                    // Update net flow
                    const netFlowElement = document.getElementById('netFlow');
                    if (netFlowElement && this.netFlow !== null) {
                        const color = EtfFlowsUtils.getFlowColor(this.netFlow);
                        netFlowElement.style.color = color;
                        netFlowElement.textContent = EtfFlowsUtils.formatFlow(this.netFlow);
                    }
                },

                updateLastRefreshTime() {
                    const lastRefreshElement = document.getElementById('lastRefresh');
                    if (lastRefreshElement) {
                        const now = new Date();
                        lastRefreshElement.textContent = now.toLocaleTimeString('en-US');
                    }
                },

                formatFlow(value) {
                    return EtfFlowsUtils.formatFlow(value);
                },

                formatPercentage(value) {
                    return EtfFlowsUtils.formatPercentage(value);
                },

                refresh() {
                    console.log('🔄 Manual refresh triggered');
                    this.apiService.clearCache();
                    this.loadData(false);
                },

                // =================================================================
                // SECTION 2: ETF RANKINGS
                // =================================================================
                
                etfList: [],
                etfListLoading: false,
                etfTableFilter: '',
                etfTableSortField: 'aum_usd',
                etfTableSortDirection: 'desc',

                async loadEtfList() {
                    if (this.etfListLoading) return;

                    this.etfListLoading = true;
                    try {
                        console.log('📊 Loading ETF List...');
                        const response = await this.apiService.fetchEtfList();
                        
                        if (response.success && response.data) {
                            this.etfList = response.data;
                            console.log(`✅ Loaded ${this.etfList.length} ETFs`);
                        }
                    } catch (error) {
                        console.error('❌ ETF List load error:', error);
                    } finally {
                        this.etfListLoading = false;
                    }
                },

                async refreshEtfList() {
                    console.log('🔄 Refreshing ETF List...');
                    this.apiService.clearCache(); // Clear cache for fresh data
                    await this.loadEtfList();
                },

                getFilteredEtfList() {
                    let filtered = [...this.etfList];

                    // Apply filter
                    if (this.etfTableFilter) {
                        const search = this.etfTableFilter.toLowerCase();
                        filtered = filtered.filter(etf => 
                            etf.ticker.toLowerCase().includes(search) ||
                            etf.fund_name.toLowerCase().includes(search)
                        );
                    }

                    // Apply sort
                    filtered.sort((a, b) => {
                        let aVal = a[this.etfTableSortField];
                        let bVal = b[this.etfTableSortField];

                        if (aVal === null || aVal === undefined) return 1;
                        if (bVal === null || bVal === undefined) return -1;

                        if (this.etfTableSortDirection === 'asc') {
                            return aVal > bVal ? 1 : -1;
                        } else {
                            return aVal < bVal ? 1 : -1;
                        }
                    });

                    return filtered;
                },

                sortEtfTable(field) {
                    if (this.etfTableSortField === field) {
                        this.etfTableSortDirection = this.etfTableSortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.etfTableSortField = field;
                        this.etfTableSortDirection = 'desc';
                    }
                    console.log(`🔄 Sort: ${field} (${this.etfTableSortDirection})`);
                },

                filterEtfTable() {
                    // Trigger reactivity
                    this.etfList = [...this.etfList];
                },

                getSortIcon(field) {
                    if (this.etfTableSortField !== field) {
                        return '<i class="fas fa-sort text-muted"></i>';
                    }
                    if (this.etfTableSortDirection === 'asc') {
                        return '<i class="fas fa-sort-up text-primary"></i>';
                    } else {
                        return '<i class="fas fa-sort-down text-primary"></i>';
                    }
                },

                getRankBadge(index) {
                    if (index === 0) return '🥇';
                    if (index === 1) return '🥈';
                    if (index === 2) return '🥉';
                    return `#${index + 1}`;
                },

                // Table formatting helpers
                formatCurrency(value) {
                    if (!value) return 'N/A';
                    const absValue = Math.abs(value);
                    if (absValue >= 1e9) return `$${(value / 1e9).toFixed(2)}B`;
                    if (absValue >= 1e6) return `$${(value / 1e6).toFixed(2)}M`;
                    return `$${value.toFixed(2)}`;
                },

                formatBTC(value) {
                    if (!value) return 'N/A';
                    return `${value.toLocaleString('en-US', { maximumFractionDigits: 0 })} BTC`;
                },

                formatPercent(value) {
                    if (value === null || value === undefined) return 'N/A';
                    const sign = value >= 0 ? '+' : '';
                    return `${sign}${value.toFixed(2)}%`;
                },

                formatBPS(value) {
                    if (value === null || value === undefined) return 'N/A';
                    const sign = value >= 0 ? '+' : '';
                    return `${sign}${value.toFixed(0)} bps`;
                },

                getPDClass(value) {
                    if (value > 0) return 'text-danger';
                    if (value < 0) return 'text-success';
                    return 'text-muted';
                },

                getChangeClass(value) {
                    if (value > 0) return 'text-success';
                    if (value < 0) return 'text-danger';
                    return 'text-muted';
                },

                getTotalAUM() {
                    return this.etfList.reduce((sum, etf) => sum + (etf.aum_usd || 0), 0);
                },

                getTotalHoldings() {
                    return this.etfList.reduce((sum, etf) => sum + (etf.holding_quantity || 0), 0);
                },

                getAverageFee() {
                    const fees = this.etfList.map(e => e.management_fee_percent).filter(f => f > 0);
                    if (fees.length === 0) return 0;
                    return fees.reduce((sum, f) => sum + f, 0) / fees.length;
                },

                // =================================================================
                // SECTION 3: PREMIUM/DISCOUNT TRACKER
                // =================================================================
                
                pdChartManager: null,
                availableTickers: ['IBIT', 'GBTC', 'FBTC', 'ARKB', 'BITB', 'HODL'],
                selectedTickers: ['IBIT', 'GBTC', 'FBTC'], // Default selection
                pdData: {},
                pdLoading: false,

                async loadPremiumDiscount() {
                    if (this.pdLoading) return;

                    // Initialize chart manager if not exists
                    if (!this.pdChartManager) {
                        const { PremiumDiscountChartManager } = await import('{{ asset('js/etf-flows/premium-discount-chart.js') }}');
                        this.pdChartManager = new PremiumDiscountChartManager('premiumDiscountChart');
                    }

                    this.pdLoading = true;
                    try {
                        console.log('📈 Loading Premium/Discount data...');
                        
                        // Fetch data for selected tickers
                        const promises = this.selectedTickers.map(ticker => 
                            this.apiService.fetchPremiumDiscount(ticker)
                        );
                        
                        const responses = await Promise.all(promises);
                        
                        // Store data
                        this.selectedTickers.forEach((ticker, index) => {
                            if (responses[index].success) {
                                this.pdData[ticker] = responses[index].data;
                            }
                        });

                        // Render chart
                        this.renderPDChart();
                        
                        console.log('✅ Premium/Discount data loaded');
                    } catch (error) {
                        console.error('❌ Premium/Discount load error:', error);
                    } finally {
                        this.pdLoading = false;
                    }
                },

                async toggleTicker(ticker) {
                    const index = this.selectedTickers.indexOf(ticker);
                    
                    if (index > -1) {
                        // Remove ticker
                        if (this.selectedTickers.length === 1) {
                            alert('At least one ETF must be selected');
                            return;
                        }
                        this.selectedTickers.splice(index, 1);
                    } else {
                        // Add ticker
                        if (this.selectedTickers.length >= 6) {
                            alert('Maximum 6 ETFs can be selected');
                            return;
                        }
                        this.selectedTickers.push(ticker);
                        
                        // Fetch data if not exists
                        if (!this.pdData[ticker]) {
                            try {
                                const response = await this.apiService.fetchPremiumDiscount(ticker);
                                if (response.success) {
                                    this.pdData[ticker] = response.data;
                                }
                            } catch (error) {
                                console.error(`❌ Failed to load ${ticker}:`, error);
                            }
                        }
                    }

                    // Render chart with new selection
                    this.renderPDChart();
                },

                renderPDChart() {
                    if (!this.pdChartManager) return;

                    const datasets = this.selectedTickers
                        .filter(ticker => this.pdData[ticker])
                        .map(ticker => ({
                            ticker: ticker,
                            data: this.pdData[ticker]
                        }));

                    this.pdChartManager.renderChart(datasets);
                },

                async refreshPremiumDiscount() {
                    console.log('🔄 Refreshing Premium/Discount...');
                    this.pdData = {}; // Clear data for fresh fetch
                    await this.loadPremiumDiscount();
                },

                // =================================================================
                // SECTION 4: CME FUTURES OPEN INTEREST
                // =================================================================

                async loadCmeOpenInterest() {
                    if (this.cmeLoading) return;
                    
                    this.cmeLoading = true;
                    console.log('🏛️ Loading CME Open Interest...', {
                        interval: '1d', // Fixed to daily
                        timeRange: this.selectedCmeTimeRange
                    });

                    try {
                        // Calculate limit for daily data
                        // Coinglass API max limit is ~1000 data points
                        const range = this.cmeTimeRanges.find(r => r.value === this.selectedCmeTimeRange);
                        const limit = Math.min(range ? range.days : 30, 1000); // Simple: 1 day = 1 point
                        
                        console.log('📊 Calculated limit:', limit, 'days for daily data');
                        
                        const response = await fetch(`/api/coinglass/etf-flows/cme-oi?symbol=BTC&interval=1d&limit=${limit}`);
                        const result = await response.json();

                        if (result.success && result.data) {
                            this.cmeData = result.data;
                            
                            // Update summary metrics
                            if (result.summary) {
                                this.cmeOiLatest = result.summary.latest_oi;
                                this.cmeOiChange = result.summary.change;
                                this.cmeOiChangePercent = result.summary.change_percent;
                            }

                            // Render chart
                            this.renderCmeChart();
                            console.log('✅ CME OI loaded:', this.cmeData.length, 'points');
                        } else {
                            console.error('❌ CME OI API error:', result.error);
                        }
                    } catch (error) {
                        console.error('❌ Failed to load CME OI:', error);
                    } finally {
                        this.cmeLoading = false;
                    }
                },

                updateCmeTimeRange(value) {
                    if (this.selectedCmeTimeRange === value) return;
                    
                    console.log('📊 Updating CME time range to:', value);
                    this.selectedCmeTimeRange = value;
                    this.loadCmeOpenInterest();
                },

                async renderCmeChart() {
                    // ⚡ FIX 1: Prevent concurrent renders (from Funding Rate)
                    if (this.cmeIsRendering) {
                        console.warn('⚠️ CME chart rendering already in progress, skipping');
                        return;
                    }

                    this.cmeIsRendering = true;

                    try {
                        // ⚡ FIX 2: Always destroy existing chart FIRST (from Funding Rate)
                        this.destroyCmeChart();

                        // ⚡ FIX 3: Wait for Chart.js to be ready
                        if (typeof Chart === 'undefined') {
                            if (window.chartJsReady) {
                                await window.chartJsReady;
                            } else {
                                console.warn('⚠️ Chart.js not loaded, aborting CME render');
                                this.cmeIsRendering = false;
                                return;
                            }
                        }

                        // ⚡ FIX 4: Enhanced canvas validation - check isConnected (from Funding Rate)
                        const canvas = document.getElementById('cmeOiChart');
                        if (!canvas || !canvas.isConnected) {
                            console.warn('⚠️ CME canvas not available or not connected to DOM');
                            this.cmeIsRendering = false;
                            return;
                        }

                        // ⚡ FIX 5: Validate context (from Funding Rate)
                        const ctx = canvas.getContext('2d');
                        if (!ctx) {
                            console.warn('⚠️ Cannot get 2D context for CME chart');
                            this.cmeIsRendering = false;
                            return;
                        }

                        // ⚡ FIX 6: Clear canvas before rendering (from Funding Rate)
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        
                        // Prepare data
                        const labels = this.cmeData.map(d => new Date(d.ts));
                        const values = this.cmeData.map(d => d.close);
                        
                        console.log('📊 CME Chart Data:', {
                            labelsCount: labels.length,
                            valuesCount: values.length,
                            firstValue: values[0],
                            lastValue: values[values.length - 1]
                        });

                        // Create chart
                        this.cmeChartInstance = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'CME Open Interest',
                                    data: values,
                                    borderColor: '#f59e0b',
                                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4,
                                    pointRadius: 0,
                                    pointHoverRadius: 5,
                                    pointHoverBackgroundColor: '#f59e0b',
                                    pointHoverBorderColor: '#fff',
                                    pointHoverBorderWidth: 2
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: false, // ⚡ FIX 7: CRITICAL - Disable animations (from Funding Rate)
                                interaction: {
                                    mode: 'index',
                                    intersect: false
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                        padding: 12,
                                        titleFont: {
                                            size: 13,
                                            weight: '600'
                                        },
                                        bodyFont: {
                                            size: 13
                                        },
                                        displayColors: false,
                                        callbacks: {
                                            title: (tooltipItems) => {
                                                const date = new Date(tooltipItems[0].parsed.x);
                                                return date.toLocaleDateString('en-US', {
                                                    month: 'short',
                                                    day: 'numeric',
                                                    year: 'numeric'
                                                });
                                            },
                                            label: (context) => {
                                                const value = context.parsed.y;
                                                return `OI: ${this.formatCurrency(value)}`;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        type: 'time',
                                        time: {
                                            unit: 'day', // Fixed to daily
                                            tooltipFormat: 'MMM d, yyyy',
                                            displayFormats: {
                                                day: 'MMM d',
                                                week: 'MMM d',
                                                month: 'MMM yyyy'
                                            }
                                        },
                                        grid: {
                                            display: false
                                        },
                                        ticks: {
                                            font: {
                                                size: 11
                                            }
                                        }
                                    },
                                    y: {
                                        beginAtZero: false,
                                        grid: {
                                            color: 'rgba(0, 0, 0, 0.05)'
                                        },
                                        ticks: {
                                            font: {
                                                size: 11
                                            },
                                            callback: (value) => {
                                                return this.formatCurrencyShort(value);
                                            }
                                        }
                                    }
                                }
                            }
                        });

                        console.log('📊 CME chart rendered successfully');
                        console.log('📊 CME chart instance:', this.cmeChartInstance);

                    } catch (error) {
                        console.error('❌ Error rendering CME chart:', error);
                        this.cmeChartInstance = null;
                    } finally {
                        // ⚡ FIX 8: Always reset rendering flag (from Funding Rate)
                        this.cmeIsRendering = false;
                    }
                },

                // ⚡ FIX 9: Robust destroy method (from Funding Rate)
                destroyCmeChart() {
                    if (this.cmeChartInstance) {
                        try {
                            // Stop all animations before destroying
                            if (this.cmeChartInstance.options && this.cmeChartInstance.options.animation) {
                                this.cmeChartInstance.options.animation = false;
                            }
                            
                            // Stop chart updates
                            this.cmeChartInstance.stop();
                            
                            // Destroy chart
                            this.cmeChartInstance.destroy();
                            console.log('🗑️ CME chart destroyed');
                        } catch (error) {
                            console.warn('⚠️ CME chart destroy error:', error);
                        }
                        this.cmeChartInstance = null;
                    }
                },

                formatCurrency(value) {
                    if (value === null || value === undefined) return '0';
                    // CME OI is in number of contracts (e.g., 140878.7 contracts)
                    // Display as thousands with K suffix
                    if (value >= 1000) {
                        return (value / 1000).toFixed(1) + 'K';
                    }
                    return value.toFixed(0);
                },

                formatCurrencyShort(value) {
                    if (value === null || value === undefined) return '0';
                    // CME OI is in number of contracts
                    if (value >= 1000) {
                        return (value / 1000).toFixed(0) + 'K';
                    }
                    return value.toFixed(0);
                }
            };
        }

        // Make controller available globally for Alpine.js
        window.etfFlowsController = etfFlowsController;
        console.log('✅ ETF Flows controller registered');
    </script>

    <style>
        [x-cloak] { display: none !important; }
        
        /* Copy exact styling from open-interest */
        .tradingview-chart-container {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: none;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            background: rgba(59, 130, 246, 0.03);
        }

        .chart-header h5 {
            color: #1e293b;
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }

        .chart-controls {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .time-range-selector {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            background: linear-gradient(135deg, 
                rgba(241, 245, 249, 0.8) 0%, 
                rgba(226, 232, 240, 0.8) 100%);
            border: 1px solid rgba(59, 130, 246, 0.15);
            border-radius: 8px;
            padding: 0.25rem;
        }

        .time-range-btn {
            padding: 6px 14px;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.2s;
            white-space: nowrap;
            color: #64748b;
            background: transparent;
            border: none;
        }

        .time-range-btn:hover {
            color: #1e293b;
            background: rgba(241, 245, 249, 1);
        }

        .time-range-btn.btn-primary {
            background: #3b82f6 !important;
            color: white !important;
        }

        .chart-body {
            padding: 20px;
            height: 500px;
            position: relative;
            background: #ffffff;
        }

        .chart-footer {
            padding: 12px 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
            background: rgba(59, 130, 246, 0.02);
        }

        .chart-footer small {
            color: #64748b;
            display: flex;
            align-items: center;
        }

        /* Pulse animation */
        .pulse-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }

        .pulse-success {
            background-color: #22c55e;
            box-shadow: 0 0 0 rgba(34, 197, 94, 0.7);
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
            }
            50% {
                box-shadow: 0 0 0 8px rgba(34, 197, 94, 0);
            }
        }

        /* Enhanced Summary Cards */
        .df-panel {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
            border: 1px solid rgba(59, 130, 246, 0.1);
            transition: all 0.3s ease;
        }

        .df-panel:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.3);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .derivatives-header h1 {
                font-size: 1.5rem;
            }
            
            .chart-body {
                height: 350px;
                padding: 12px;
            }
            
            .chart-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            
            .chart-header > div:first-child {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px !important;
            }
            
            .chart-controls {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
                gap: 0.75rem;
            }

            .time-range-selector {
                justify-content: center;
                flex-wrap: wrap;
            }

            .time-range-btn {
                flex: 1;
                min-width: 35px;
            }
            
            .chart-footer-text {
                font-size: 0.75rem;
            }
        }
    </style>
@endsection
