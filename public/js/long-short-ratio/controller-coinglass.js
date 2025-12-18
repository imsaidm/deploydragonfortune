/**
 * Long-Short Ratio Controller (Coinglass)
 * Handles TWO charts: Global Account & Top Account
 * 
 * Blueprint: Open Interest Controller (proven stable)
 * Date-range based queries only (no limit)
 */

import { LongShortRatioAPIService } from './api-service.js';
import { ChartManager } from './chart-manager.js';
import { LongShortRatioUtils } from './utils.js';

export function createLongShortRatioController() {
    return {
        initialized: false,
        apiService: null,
        globalChartManager: null,
        topChartManager: null,

        // State
        selectedSymbol: 'BTC',
        selectedExchange: 'Binance',
        selectedInterval: '1h',
        selectedTimeRange: '1w', // Default 1 week

        // Supported symbols (Verified with data available)
        supportedSymbols: ['BTC', 'ETH', 'SOL', 'BNB', 'XRP', 'ADA', 'DOGE', 'AVAX', 'TON', 'SUI'],

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

        // Data - Global Account
        globalRawData: [],
        globalCurrentRatio: null,
        globalMinRatio: null,
        globalMaxRatio: null,
        globalAvgRatio: null,
        globalChange: null,

        // Data - Top Account
        topRawData: [],
        topCurrentRatio: null,
        topMinRatio: null,
        topMaxRatio: null,
        topAvgRatio: null,
        topChange: null,

        async init() {
            if (this.initialized) return;
            this.initialized = true;

            console.log('🚀 Long-Short Ratio (Coinglass) initialized');

            this.apiService = new LongShortRatioAPIService();
            this.globalChartManager = new ChartManager('globalAccountChart');
            this.topChartManager = new ChartManager('topAccountChart');

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

                console.log('[LSR:LOAD]', {
                    symbol: this.selectedSymbol,
                    exchange: this.selectedExchange,
                    interval: this.selectedInterval,
                    range: this.selectedTimeRange,
                    start: new Date(start_time).toISOString(),
                    end: new Date(end_time).toISOString()
                });

                const fetchStart = performance.now();

                // Fetch both Global and Top Account data in parallel
                const [globalData, topData] = await Promise.all([
                    this.apiService.fetchHistory({
                        symbol: this.selectedSymbol,
                        exchange: this.selectedExchange,
                        interval: this.selectedInterval,
                        start_time,
                        end_time,
                        type: 'global',
                        preferFresh: !isAutoRefresh
                    }),
                    this.apiService.fetchHistory({
                        symbol: this.selectedSymbol,
                        exchange: this.selectedExchange,
                        interval: this.selectedInterval,
                        start_time,
                        end_time,
                        type: 'top',
                        preferFresh: !isAutoRefresh
                    })
                ]);

                const fetchEnd = performance.now();
                const fetchTime = Math.round(fetchEnd - fetchStart);

                // Process Global Account data
                if (globalData && globalData.length > 0) {
                    this.globalRawData = globalData;
                    this.calculateGlobalMetrics();
                    this.renderGlobalChart();
                }

                // Process Top Account data
                if (topData && topData.length > 0) {
                    this.topRawData = topData;
                    this.calculateTopMetrics();
                    this.renderTopChart();
                }

                // Reset error count on successful load
                this.errorCount = 0;

                const totalTime = Math.round(performance.now() - startTime);
                console.log(`[LSR:OK] Global: ${globalData.length}, Top: ${topData.length} points (fetch: ${fetchTime}ms, total: ${totalTime}ms)`);

            } catch (error) {
                console.error('[LSR:ERROR]', error);

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

        calculateGlobalMetrics() {
            if (this.globalRawData.length === 0) return;

            const metrics = this.computeMetrics(this.globalRawData);

            this.globalCurrentRatio = metrics.currentRatio;
            this.globalMinRatio = metrics.minRatio;
            this.globalMaxRatio = metrics.maxRatio;
            this.globalAvgRatio = metrics.avgRatio;
            this.globalChange = metrics.change;
        },

        calculateTopMetrics() {
            if (this.topRawData.length === 0) return;

            const metrics = this.computeMetrics(this.topRawData);

            this.topCurrentRatio = metrics.currentRatio;
            this.topMinRatio = metrics.minRatio;
            this.topMaxRatio = metrics.maxRatio;
            this.topAvgRatio = metrics.avgRatio;
            this.topChange = metrics.change;
        },

        computeMetrics(rawData) {
            if (rawData.length === 0) return {};

            const values = rawData.map(d => parseFloat(d.ratio || 0));

            const currentRatio = values[values.length - 1];
            const minRatio = Math.min(...values);
            const maxRatio = Math.max(...values);
            const avgRatio = values.reduce((a, b) => a + b, 0) / values.length;

            let change = null;
            if (values.length > 1) {
                change = ((currentRatio - values[0]) / values[0]) * 100;
            }

            return {
                currentRatio,
                minRatio,
                maxRatio,
                avgRatio,
                change
            };
        },

        renderGlobalChart() {
            // ⚡ FIXED: Simplified render (SAME AS OPEN INTEREST)
            if (!this.globalChartManager || this.globalRawData.length === 0) return;
            this.globalChartManager.renderChart(this.globalRawData);
        },

        renderTopChart() {
            // ⚡ FIXED: Simplified render (SAME AS OPEN INTEREST)
            if (!this.topChartManager || this.topRawData.length === 0) return;
            this.topChartManager.renderChart(this.topRawData);
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

        formatRatio(value) {
            return LongShortRatioUtils.formatRatio(value);
        },

        formatPercent(value) {
            return LongShortRatioUtils.formatPercent(value);
        },

        formatChange(value) {
            return LongShortRatioUtils.formatChange(value);
        },

        getSentiment(value) {
            return LongShortRatioUtils.getSentiment(value);
        },

        getSentimentBadge(value) {
            return LongShortRatioUtils.getSentimentBadge(value);
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
            if (this.globalChartManager) this.globalChartManager.destroy();
            if (this.topChartManager) this.topChartManager.destroy();
            if (this.apiService) this.apiService.cancelRequest();
        }
    };
}
