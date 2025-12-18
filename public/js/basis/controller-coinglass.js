/**
 * Basis & Term Structure Controller (Coinglass)
 * 
 * Blueprint: Open Interest Controller (proven stable)
 * Date-range based queries only (no limit)
 */

import { BasisAPIService } from './api-service.js';
import { ChartManager } from './chart-manager.js';
import { BasisUtils } from './utils.js';

export function createBasisController() {
    return {
        initialized: false,
        apiService: null,
        chartManager: null,

        // State
        selectedSymbol: 'BTC',
        selectedExchange: 'Binance',
        selectedInterval: '1h',
        selectedTimeRange: '1w', // Default 1 week

        // Supported symbols (Verified with data available - 11 symbols)
        supportedSymbols: ['BTC', 'ETH', 'SOL', 'DOGE', 'XRP', 'ONDO', 'BNB', 'ADA', 'AVAX', 'TON', 'SUI'],

        // Supported exchanges (Only exchanges with confirmed data)
        supportedExchanges: ['Binance', 'Bybit'],

        // Time ranges with start_time/end_time approach
        timeRanges: [
            { label: '1D', value: '1d', days: 1 },
            { label: '1W', value: '1w', days: 7 },
            { label: '1M', value: '1m', days: 30 },
            { label: '3M', value: '3m', days: 90 },
            { label: '1Y', value: '1y', days: 365 },
            { label: 'ALL', value: 'all', days: 1095 } // ~3 years
        ],

        // Chart intervals (API compliant) - SAME AS OPEN INTEREST
        chartIntervals: [
            { label: '1M', value: '1m' },
            { label: '3M', value: '3m' },
            { label: '5M', value: '5m' },
            { label: '15M', value: '15m' },
            { label: '30M', value: '30m' },
            { label: '1H', value: '1h' },
            { label: '4H', value: '4h' },
            { label: '6H', value: '6h' },
            { label: '8H', value: '8h' },
            { label: '12H', value: '12h' },
            { label: '1D', value: '1d' },
            { label: '1W', value: '1w' }
        ],

        // Loading state
        isLoading: false,

        // Auto-refresh
        refreshInterval: null,
        refreshEnabled: true,
        errorCount: 0,
        maxErrors: 3,

        // Data
        rawData: [],
        currentBasis: null,
        minBasis: null,
        maxBasis: null,
        avgBasis: null,
        basisChange: null,

        async init() {
            if (this.initialized) return;
            this.initialized = true;

            console.log('🚀 Basis & Term Structure (Coinglass) initialized');

            this.apiService = new BasisAPIService();
            this.chartManager = new ChartManager('basisMainChart');

            await this.loadData();

            // Start auto-refresh for real-time updates
            this.startAutoRefresh();
        },

        async loadData(isAutoRefresh = false) {
            if (this.isLoading && !isAutoRefresh) {
                console.warn('⚠️ Load already in progress, skipping');
                return;
            }

            const startTime = performance.now();

            // ⚡ FIXED: Always set loading to prevent concurrent calls
            this.isLoading = true;

            try {
                const { start_time, end_time } = this.getDateRange();

                console.log('[BASIS:LOAD]', {
                    symbol: this.selectedSymbol,
                    exchange: this.selectedExchange,
                    interval: this.selectedInterval,
                    range: this.selectedTimeRange,
                    start: new Date(start_time).toISOString(),
                    end: new Date(end_time).toISOString()
                });

                const fetchStart = performance.now();

                const data = await this.apiService.fetchHistory({
                    symbol: this.selectedSymbol,
                    exchange: this.selectedExchange,
                    interval: this.selectedInterval,
                    start_time,
                    end_time,
                    preferFresh: !isAutoRefresh
                });

                const fetchEnd = performance.now();
                const fetchTime = Math.round(fetchEnd - fetchStart);

                if (data && data.length > 0) {
                    this.rawData = data;
                    this.calculateMetrics();
                    this.renderChart();

                    // Reset error count on successful load
                    this.errorCount = 0;

                    const totalTime = Math.round(performance.now() - startTime);
                    console.log(`[BASIS:OK] ${data.length} points (fetch: ${fetchTime}ms, total: ${totalTime}ms)`);
                } else {
                    console.warn('[BASIS:EMPTY]');
                }

            } catch (error) {
                console.error('[BASIS:ERROR]', error);

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

        getDateRange() {
            // ⚡ SIMPLIFIED: Use current time for fresh data
            const now = Date.now();
            const range = this.timeRanges.find(r => r.value === this.selectedTimeRange);
            const days = range ? range.days : 30;
            const start_time = now - (days * 24 * 60 * 60 * 1000);
            return { start_time, end_time: now };
        },

        calculateMetrics() {
            if (this.rawData.length === 0) return;

            const metrics = this.computeMetrics(this.rawData);

            this.currentBasis = metrics.currentBasis;
            this.minBasis = metrics.minBasis;
            this.maxBasis = metrics.maxBasis;
            this.avgBasis = metrics.avgBasis;
            this.basisChange = metrics.basisChange;
        },

        computeMetrics(rawData) {
            if (rawData.length === 0) return {};

            const values = rawData.map(d => parseFloat(d.close_basis || 0));

            const currentBasis = values[values.length - 1];
            const minBasis = Math.min(...values);
            const maxBasis = Math.max(...values);
            const avgBasis = values.reduce((a, b) => a + b, 0) / values.length;

            let basisChange = null;
            if (values.length > 1) {
                basisChange = ((currentBasis - values[0]) / Math.abs(values[0] || 0.0001)) * 100;
            }

            return {
                currentBasis,
                minBasis,
                maxBasis,
                avgBasis,
                basisChange
            };
        },

        renderChart() {
            // ⚡ FIXED: Simplified render (SAME AS OPEN INTEREST)
            if (!this.chartManager || this.rawData.length === 0) return;
            this.chartManager.renderChart(this.rawData);
        },

        // Direct load for user interactions
        instantLoadData() {
            console.log('⚡ Instant load triggered');

            // Force load even if currently loading (user interaction priority)
            if (this.isLoading) {
                console.log('⚡ Force loading for user interaction (overriding current load)');
                this.isLoading = false; // Reset flag to allow new load
            }

            this.loadData(); // Load immediately
        },

        // Blade templates expect `refreshAll()` for manual refresh buttons.
        refreshAll() {
            console.log('🔄 Manual refresh triggered');
            this.errorCount = 0;
            this.instantLoadData();
        },

        setTimeRange(value) {
            console.log('🎯 setTimeRange called with:', value, 'current:', this.selectedTimeRange);
            if (this.selectedTimeRange === value) {
                console.log('⚠️ Same time range, skipping');
                return;
            }
            console.log('🎯 Time range changed to:', value);
            this.selectedTimeRange = value;

            // ⚡ FIXED: Always trigger load for filter changes
            console.log('🚀 Filter changed, triggering instant load');
            this.instantLoadData();
        },

        setChartInterval(value) {
            console.log('🎯 setChartInterval called with:', value, 'current:', this.selectedInterval);
            if (this.selectedInterval === value) {
                console.log('⚠️ Same interval, skipping');
                return;
            }
            console.log('🎯 Interval changed to:', value);
            this.selectedInterval = value;

            // ⚡ FIXED: Always trigger load for filter changes
            console.log('🚀 Filter changed, triggering instant load');
            this.instantLoadData();
        },

        // Alpine expects these names from the blade template
        updateInterval(value) {
            console.log('🎯 updateInterval called with:', value);
            this.setChartInterval(value);
        },

        updateSymbol(value) {
            console.log('🎯 updateSymbol called with:', value);
            if (value && value !== this.selectedSymbol) {
                console.log('🎯 Symbol changed to:', value);
                this.selectedSymbol = value;

                // ⚡ FIXED: Always trigger load for filter changes
                console.log('🚀 Filter changed, triggering instant load');
                this.instantLoadData();
            }
        },

        updateExchange(value) {
            console.log('🎯 updateExchange called with:', value);
            if (value && value !== this.selectedExchange) {
                console.log('🎯 Exchange changed to:', value);
                this.selectedExchange = value;

                // ⚡ FIXED: Always trigger load for filter changes
                console.log('🚀 Filter changed, triggering instant load');
                this.instantLoadData();
            }
        },

        // ⚡ ADDED: Method for time range updates
        updateTimeRange(value) {
            console.log('🎯 updateTimeRange called with:', value);
            this.setTimeRange(value);
        },

        formatBasis(value) {
            return BasisUtils.formatBasis(value);
        },

        formatChange(value) {
            return BasisUtils.formatChange(value);
        },

        getMarketStructure(value) {
            return BasisUtils.getMarketStructure(value);
        },

        getStructureBadge(value) {
            return BasisUtils.getStructureBadge(value);
        },

        // Auto-refresh functionality - SAME AS OPEN INTEREST
        startAutoRefresh() {
            this.stopAutoRefresh(); // Clear any existing interval

            if (!this.refreshEnabled) return;

            // 15 second interval for faster real-time updates
            this.refreshInterval = setInterval(() => {
                // Skip if page is hidden (tab not active)
                if (document.hidden) return;

                // Skip if currently loading to prevent race conditions
                if (this.isLoading) return;

                // Skip if too many errors
                if (this.errorCount >= this.maxErrors) {
                    console.warn('🚨 Auto-refresh disabled due to errors');
                    this.stopAutoRefresh();
                    return;
                }

                console.log('🔄 Auto-refresh: Silent update (15s)');
                this.loadData(true); // Silent background update

            }, 15000); // 15 seconds - Faster real-time updates

            // Handle page visibility changes
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && this.refreshEnabled) {
                    // Page became visible - trigger immediate update
                    console.log('👁️ Page visible: Triggering refresh');
                    if (!this.isLoading) {
                        this.loadData(true);
                    }
                }
            });

            console.log('✅ Auto-refresh started (15s interval)');
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
            if (this.apiService) this.apiService.cancelRequest();
        }
    };
}
