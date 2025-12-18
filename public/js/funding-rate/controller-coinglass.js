/**
 * Funding Rate Controller (Coinglass)
 * Date-range based queries only (no limit)
 */

import { FundingRateAPIService } from './api-service.js';
import { ChartManager } from './chart-manager.js';
import { FundingRateUtils } from './utils.js';

export function createFundingRateController() {
    return {
        initialized: false,
        apiService: null,
        chartManager: null,

        // State
        selectedSymbol: 'BTC',
        selectedInterval: '8h', // Default 8H (funding rate payment interval)
        selectedTimeRange: '1w', // Default 1 week

        // Supported symbols (Verified with data available - 20 symbols)
        supportedSymbols: ['BTC', 'ETH', 'SOL', 'XRP', 'HYPE', 'DOGE', 'BNB', 'ZEC', 'SUI', 'ADA', 'LINK', 'ASTER', 'AVAX', 'ENA', 'LTC', 'PUMP', 'XPL', 'BCH', 'AAVE', 'TRUMP'],

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

        // Data (Funding Rate based)
        rawData: [],
        currentFundingRate: null,    // Latest funding rate value
        minFundingRate: null,        // Minimum funding rate
        maxFundingRate: null,        // Maximum funding rate
        avgFundingRate: null,        // Average funding rate
        fundingChange: null,         // Change from first to last
        fundingVolatility: null,     // Volatility measure
        momentum: null,              // Trend momentum percentage

        async init() {
            if (this.initialized) return;
            this.initialized = true;

            console.log('🚀 Funding Rate (Coinglass) initialized');

            this.apiService = new FundingRateAPIService();
            this.chartManager = new ChartManager('fundingRateMainChart');

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

            // Always set loading to prevent concurrent calls
            this.isLoading = true;

            try {
                const { start_time, end_time } = this.getDateRange();

                console.log('[FR:LOAD]', {
                    symbol: this.selectedSymbol,
                    interval: this.selectedInterval,
                    range: this.selectedTimeRange,
                    start: new Date(start_time).toISOString(),
                    end: new Date(end_time).toISOString()
                });

                const fetchStart = performance.now();

                const effectiveInterval = this.getEffectiveInterval();

                // Aggregated Funding Rate History
                const data = await this.apiService.fetchHistory({
                    symbol: this.selectedSymbol,
                    interval: effectiveInterval,
                    start_time,
                    end_time,
                    preferFresh: !isAutoRefresh
                });

                const fetchEnd = performance.now();
                const fetchTime = Math.round(fetchEnd - fetchStart);

                if (data && data.length > 0) {
                    this.rawData = data;
                    this.calculateMetrics();

                    // Render chart
                    this.renderChart();

                    // Reset error count on successful load
                    this.errorCount = 0;

                    const totalTime = Math.round(performance.now() - startTime);
                    console.log(`[FR:OK] ${data.length} points (fetch: ${fetchTime}ms, total: ${totalTime}ms)`);
                } else {
                    console.warn('[FR:EMPTY]');
                }
            } catch (error) {
                console.error('[FR:ERROR]', error);

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
            // Use current time for fresh data
            const now = Date.now();
            const range = this.timeRanges.find(r => r.value === this.selectedTimeRange);
            const days = range ? range.days : 30;
            const start_time = now - (days * 24 * 60 * 60 * 1000);
            return { start_time, end_time: now };
        },

        getEffectiveInterval() {
            return this.selectedInterval;
        },

        calculateMetrics() {
            if (this.rawData.length === 0) return;

            // Safe batch update to prevent Alpine reactivity loops
            const metrics = this.computeMetrics(this.rawData);

            // Update properties one by one to avoid potential circular references
            this.currentFundingRate = metrics.currentFundingRate;
            this.minFundingRate = metrics.minFundingRate;
            this.maxFundingRate = metrics.maxFundingRate;
            this.avgFundingRate = metrics.avgFundingRate;
            this.fundingChange = metrics.fundingChange;
            this.fundingVolatility = metrics.fundingVolatility;
            this.momentum = metrics.momentum;
        },

        computeMetrics(rawData) {
            if (rawData.length === 0) return {};

            // Compute all metrics in one go to prevent reactivity loops
            const values = rawData.map(d => parseFloat(d.value || 0));

            // Compute all metrics at once
            const currentFundingRate = values[values.length - 1];
            const minFundingRate = Math.min(...values);
            const maxFundingRate = Math.max(...values);
            const avgFundingRate = values.reduce((a, b) => a + b, 0) / values.length;

            let fundingChange = null;
            if (values.length > 1) {
                fundingChange = ((currentFundingRate - values[0]) / Math.abs(values[0])) * 100;
            }

            let fundingVolatility = 0;
            if (values.length > 1) {
                const variance = values.reduce((acc, val) => acc + Math.pow(val - avgFundingRate, 2), 0) / values.length;
                fundingVolatility = Math.sqrt(variance);
            }

            let momentum = 0;
            if (values.length >= 10) {
                const recentAvg = values.slice(-5).reduce((a, b) => a + b, 0) / 5;
                momentum = ((recentAvg - avgFundingRate) / Math.abs(avgFundingRate)) * 100;
            }

            return {
                currentFundingRate,
                minFundingRate,
                maxFundingRate,
                avgFundingRate,
                fundingChange,
                fundingVolatility,
                momentum
            };
        },

        renderChart() {
            // ⚡ FIXED: Simplified render (SAME AS OPEN INTEREST)
            // No debouncing needed - chart-manager handles all timing and validation
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

            // Always trigger load for filter changes
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

            // Always trigger load for filter changes
            console.log('🚀 Filter changed, triggering instant load');
            this.instantLoadData();
        },

        // Alpine expects these names from the blade template
        updateInterval(value) {
            console.log('🎯 updateInterval called with:', value, 'current selectedInterval:', this.selectedInterval);
            if (value && value !== this.selectedInterval) {
                console.log('🎯 Headline interval changed to:', value);
                this.selectedInterval = value;

                // Always trigger load for filter changes
                console.log('🚀 Headline filter changed, triggering instant load');
                this.instantLoadData();
            } else {
                console.log('⚠️ Same interval or invalid value, skipping');
            }
        },

        updateSymbol(value) {
            console.log('🎯 updateSymbol called with:', value, 'current selectedSymbol:', this.selectedSymbol);
            if (value && value !== this.selectedSymbol) {
                console.log('🎯 Headline symbol changed to:', value);
                this.selectedSymbol = value;

                // Always trigger load for filter changes
                console.log('🚀 Headline filter changed, triggering instant load');
                this.instantLoadData();
            } else {
                console.log('⚠️ Same symbol or invalid value, skipping');
            }
        },

        // Method for time range updates
        updateTimeRange(value) {
            console.log('🎯 updateTimeRange called with:', value, 'current selectedTimeRange:', this.selectedTimeRange);
            if (value && value !== this.selectedTimeRange) {
                console.log('🎯 Headline time range changed to:', value);
                this.selectedTimeRange = value;

                // Always trigger load for filter changes
                console.log('🚀 Headline filter changed, triggering instant load');
                this.instantLoadData();
            } else {
                console.log('⚠️ Same time range or invalid value, skipping');
            }
        },

        formatFundingRate(value) {
            return FundingRateUtils.formatFundingRate(value);
        },

        formatChange(value) {
            if (value === null || value === undefined) return '';
            const sign = value >= 0 ? '+' : '';
            return `${sign}${value.toFixed(4)}%`;
        },

        formatPercentage(value) {
            if (value === null || value === undefined) return '';
            return `${value.toFixed(4)}%`;
        },

        // Auto-refresh functionality
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
