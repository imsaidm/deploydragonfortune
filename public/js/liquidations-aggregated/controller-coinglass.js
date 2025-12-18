/**
 * Liquidations Aggregated Controller (Coinglass)
 * Blueprint: Open Interest Controller
 */

import { LiquidationsAggregatedAPIService } from './api-service.js';
import { ChartManager } from './chart-manager.js';
import { LiquidationsAggregatedUtils } from './utils.js';

export function createLiquidationsAggregatedController() {
    return {
        initialized: false,
        apiService: null,
        chartManager: null,

        // State
        selectedSymbol: 'BTC',
        selectedExchanges: 'Binance,OKX,Bybit', // Default exchanges
        selectedInterval: '1d',
        selectedTimeRange: '1w', // Default 1 week

        // Supported symbols
        supportedSymbols: ['BTC', 'ETH', 'SOL', 'BNB', 'XRP', 'ADA', 'DOGE', 'AVAX', 'TON', 'SUI'],

        // Time ranges
        timeRanges: [
            { label: '1D', value: '1d', days: 1 },
            { label: '1W', value: '1w', days: 7 },
            { label: '1M', value: '1m', days: 30 },
            { label: '3M', value: '3m', days: 90 },
            { label: '1Y', value: '1y', days: 365 }
        ],

        // Chart intervals (all supported by Coinglass API)
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

        // Data
        rawData: [],
        stats: {
            totalLong: 0,
            totalShort: 0,
            total: 0,
            avgLong: 0,
            avgShort: 0,
            maxLong: 0,
            maxShort: 0,
            longShortRatio: 0,
            count: 0
        },

        async init() {
            if (this.initialized) return;
            this.initialized = true;

            console.log('🚀 Liquidations Aggregated (Coinglass) initialized');

            this.apiService = new LiquidationsAggregatedAPIService();
            this.chartManager = new ChartManager('liquidationsAggregatedChart');

            await this.loadData();

            // Start auto-refresh
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

                console.log('[LIQUIDATIONS:LOAD]', {
                    symbol: this.selectedSymbol,
                    exchanges: this.selectedExchanges,
                    interval: this.selectedInterval,
                    range: this.selectedTimeRange
                });

                const response = await this.apiService.fetchAggregatedHistory({
                    exchange_list: this.selectedExchanges,
                    symbol: this.selectedSymbol,
                    interval: this.selectedInterval,
                    start_time,
                    end_time
                });

                if (!response.success) {
                    throw new Error(response.error?.message || 'Failed to fetch data');
                }

                this.rawData = response.data || [];

                // Calculate statistics
                this.stats = LiquidationsAggregatedUtils.calculateStats(this.rawData);

                // Update chart
                if (this.chartManager && this.rawData.length > 0) {
                    this.chartManager.updateChart(this.rawData);
                }

                const duration = performance.now() - startTime;
                console.log(`[LIQUIDATIONS:OK] (fetch: ${duration.toFixed(0)}ms)`);

            } catch (error) {
                console.error('❌ Load error:', error);
            } finally {
                this.isLoading = false;
            }
        },

        getDateRange() {
            const range = this.timeRanges.find(r => r.value === this.selectedTimeRange);
            const days = range?.days || 7;

            const end_time = Date.now();
            const start_time = end_time - (days * 24 * 60 * 60 * 1000);

            return { start_time, end_time };
        },

        // Control methods
        setTimeRange(value) {
            if (this.selectedTimeRange !== value) {
                this.selectedTimeRange = value;
                this.instantLoadData();
            }
        },

        setInterval(value) {
            if (this.selectedInterval !== value) {
                this.selectedInterval = value;
                this.instantLoadData();
            }
        },

        setSymbol(value) {
            if (this.selectedSymbol !== value) {
                this.selectedSymbol = value;
                this.instantLoadData();
            }
        },

        async instantLoadData() {
            this.stopAutoRefresh();
            await this.loadData();
            this.startAutoRefresh();
        },

        // Blade templates expect `refreshAll()` for manual refresh buttons.
        async refreshAll() {
            console.log('🔄 Manual refresh triggered');
            await this.instantLoadData();
        },

        // Auto-refresh
        startAutoRefresh() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
            }

            this.refreshInterval = setInterval(() => {
                if (this.refreshEnabled) {
                    console.log('🔄 Auto-refresh: Silent update (15s)');
                    this.loadData(true);
                }
            }, 15000); // 15 seconds

            console.log('✅ Auto-refresh started (15s interval)');
        },

        stopAutoRefresh() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
                console.log('⏸️ Auto-refresh stopped');
            }
        },

        toggleRefresh() {
            this.refreshEnabled = !this.refreshEnabled;
            console.log('🔄 Auto-refresh:', this.refreshEnabled ? 'ON' : 'OFF');
        },

        // Alpine expects these names from blade template
        updateRange(value) {
            this.setTimeRange(value);
        },

        updateInterval(value) {
            this.setInterval(value);
        },

        updateSymbol(value) {
            this.setSymbol(value);
        },

        // Format methods
        formatValue(value) {
            return LiquidationsAggregatedUtils.formatValue(value);
        },

        formatChange(value) {
            return LiquidationsAggregatedUtils.formatChange(value);
        },

        formatPercentage(value) {
            return LiquidationsAggregatedUtils.formatPercentage(value);
        }
    };
}
