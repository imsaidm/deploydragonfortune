/**
 * Open Interest Controller (Coinglass)
 * Date-range based queries only (no limit)
 */

import { OpenInterestAPIService } from './api-service.js';
import { ChartManager } from './chart-manager.js';
import { OpenInterestUtils } from './utils.js';

export function createOpenInterestController() {
    return {
        initialized: false,
        apiService: null,
        chartManager: null,

        // State
        selectedSymbol: 'BTC',
        selectedUnit: 'usd',
        selectedInterval: '1h',
        selectedTimeRange: '1d', // Default 1 day

        // Supported symbols (Coinglass)
        supportedSymbols: ['BTC', 'ETH', 'SOL', 'XRP', 'HYPE', 'BNB', 'DOGE'],

        // Time ranges with start_time/end_time approach
        timeRanges: [
            { label: '1D', value: '1d', days: 1 },
            { label: '1W', value: '1w', days: 7 },
            { label: '1M', value: '1m', days: 30 },
            { label: '3M', value: '3m', days: 90 },
            { label: '1Y', value: '1y', days: 365 },
            { label: 'ALL', value: 'all', days: 1095 } // ~3 years
        ],

        // Chart intervals (API compliant)
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

        // Data (OHLC-based)
        rawData: [],
        currentOI: null,    // Latest close value
        minOI: null,        // Minimum low across all periods
        maxOI: null,        // Maximum high across all periods
        avgOI: null,        // Average of close values
        oiChange: null,     // Change from first to last close
        oiVolatility: null, // Average (high-low)/close ratio
        momentum: null,     // Trend momentum percentage

        async init() {
            if (this.initialized) return;
            this.initialized = true;

            console.log('🚀 Open Interest (Coinglass) initialized');

            this.apiService = new OpenInterestAPIService();
            this.chartManager = new ChartManager('openInterestMainChart');

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

                console.log('[OI:LOAD]', {
                    symbol: this.selectedSymbol,
                    unit: this.selectedUnit,
                    interval: this.selectedInterval,
                    range: this.selectedTimeRange,
                    start: new Date(start_time).toISOString(),
                    end: new Date(end_time).toISOString()
                });

                const fetchStart = performance.now();

                const effectiveInterval = this.getEffectiveInterval();

                // Aggregated Open Interest OHLC History only
                const data = await this.apiService.fetchHistory({
                    symbol: this.selectedSymbol,
                    interval: effectiveInterval,
                    start_time,
                    end_time,
                    unit: this.selectedUnit,
                    preferFresh: !isAutoRefresh
                });

                const fetchEnd = performance.now();
                const fetchTime = Math.round(fetchEnd - fetchStart);

                if (data && data.length > 0) {
                    this.rawData = data;
                    this.calculateMetrics();

                    // ⚡ SIMPLIFIED: Always use full render for reliability
                    this.renderChart();

                    // Reset error count on successful load
                    this.errorCount = 0;

                    const totalTime = Math.round(performance.now() - startTime);
                    console.log(`[OI:OK] ${data.length} points (fetch: ${fetchTime}ms, total: ${totalTime}ms)`);
                } else {
                    console.warn('[OI:EMPTY]');
                }
            } catch (error) {
                console.error('[OI:ERROR]', error);

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

        getEffectiveInterval() {
            if (!this.useAdaptiveInterval) return this.selectedInterval;
            const range = this.timeRanges.find(r => r.value === this.selectedTimeRange);
            const days = range ? range.days : 30;
            if (days <= 2) return '1m';
            if (days <= 7) return '5m';
            if (days <= 30) return '15m';
            if (days <= 90) return '1h';
            if (days <= 180) return '4h';
            if (days <= 365) return '8h';
            return '1d';
        },

        calculateMetrics() {
            if (this.rawData.length === 0) return;

            // ⚡ FIXED: Safe batch update to prevent Alpine reactivity loops
            const metrics = this.computeMetrics(this.rawData);

            // Update properties one by one to avoid potential circular references
            this.currentOI = metrics.currentOI;
            this.minOI = metrics.minOI;
            this.maxOI = metrics.maxOI;
            this.avgOI = metrics.avgOI;
            this.oiChange = metrics.oiChange;
            this.oiVolatility = metrics.oiVolatility;
            this.momentum = metrics.momentum;
        },

        computeMetrics(rawData) {
            if (rawData.length === 0) return {};

            // ⚡ FIXED: Compute all metrics in one go to prevent reactivity loops

            const values = rawData.map(d => parseFloat(d.value || d.close || 0));
            const closes = values;
            const highs = values;
            const lows = values;

            // Compute all metrics at once
            const currentOI = closes[closes.length - 1];
            const minOI = Math.min(...lows);
            const maxOI = Math.max(...highs);
            const avgOI = closes.reduce((a, b) => a + b, 0) / closes.length;

            let oiChange = null;
            if (closes.length > 1) {
                oiChange = ((currentOI - closes[0]) / closes[0]) * 100;
            }

            let oiVolatility = 0;
            if (closes.length > 1) {
                const variance = closes.reduce((acc, val) => acc + Math.pow(val - avgOI, 2), 0) / closes.length;
                oiVolatility = Math.sqrt(variance) / avgOI * 100;
            }

            let momentum = 0;
            if (closes.length >= 10) {
                const recentAvg = closes.slice(-5).reduce((a, b) => a + b, 0) / 5;
                momentum = ((recentAvg - avgOI) / avgOI) * 100;
            }

            return {
                currentOI,
                minOI,
                maxOI,
                avgOI,
                oiChange,
                oiVolatility,
                momentum
            };
        },

        renderChart() {
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

        updateUnit(value) {
            console.log('🎯 updateUnit called with:', value);
            if (value && value !== this.selectedUnit) {
                console.log('🎯 Unit changed to:', value);
                this.selectedUnit = value;

                // ⚡ FIXED: Always trigger load for filter changes
                console.log('🚀 Filter changed, triggering instant load');
                this.instantLoadData();
            }
        },

        // ⚡ ADDED: Method for time range updates (might be missing)
        updateTimeRange(value) {
            console.log('🎯 updateTimeRange called with:', value);
            this.setTimeRange(value);
        },



        formatOI(value) {
            return OpenInterestUtils.formatOI(value);
        },

        formatChange(value) {
            if (value === null || value === undefined) return '';
            const sign = value >= 0 ? '+' : '';
            return `${sign}${value.toFixed(2)}%`;
        },

        formatPercentage(value) {
            if (value === null || value === undefined) return '';
            return `${value.toFixed(2)}%`;
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
