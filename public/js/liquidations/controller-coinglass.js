/**
 * Liquidations Heatmap Controller (Coinglass)
 * Blueprint: Open Interest Controller (proven stable)
 * 
 * Displays liquidation heatmap (Model 3)
 */

import { LiquidationsAPIService } from './api-service.js';
import { ChartManager } from './chart-manager.js';
import { LiquidationsUtils } from './utils.js';

export function createLiquidationsController() {
    return {
        initialized: false,
        apiService: null,
        chartManager: null,

        // State
        selectedSymbol: 'BTC',
        selectedRange: '3d', // Default 3 days

        // Supported symbols (same as Open Interest)
        supportedSymbols: ['BTC', 'ETH', 'SOL', 'BNB', 'XRP', 'ADA', 'DOGE', 'AVAX', 'TON', 'SUI'],

        // Time ranges for heatmap
        timeRanges: [
            { label: '12H', value: '12h' },
            { label: '24H', value: '24h' },
            { label: '3D', value: '3d' },
            { label: '7D', value: '7d' },
            { label: '30D', value: '30d' },
            { label: '90D', value: '90d' },
            { label: '180D', value: '180d' },
            { label: '1Y', value: '1y' }
        ],

        // Loading state
        isLoading: false,

        // Auto-refresh
        refreshInterval: null,
        refreshEnabled: true,
        errorCount: 0,
        maxErrors: 3,

        // Data
        rawData: null,
        stats: {
            totalLiquidations: 0,
            maxLiquidation: 0,
            avgLiquidation: 0,
            count: 0
        },

        // Interactive features
        liquidityThreshold: 0.2, // Default threshold (0-1) - lower shows more colors
        zoomLevel: 1,
        panX: 0,
        panY: 0,
        thresholdDebounceTimer: null, // For smooth slider performance

        async init() {
            if (this.initialized) return;
            this.initialized = true;

            console.log('🚀 Liquidations Heatmap (Coinglass) initialized');

            this.apiService = new LiquidationsAPIService();
            this.chartManager = new ChartManager('liquidationsHeatmapChart');
            
            // Set initial threshold to match controller default (0.2)
            this.chartManager.threshold = this.liquidityThreshold;

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
                console.log('[LIQUIDATIONS:LOAD]', {
                    symbol: this.selectedSymbol,
                    range: this.selectedRange
                });

                const fetchStart = performance.now();

                const data = await this.apiService.fetchHeatmap({
                    symbol: this.selectedSymbol,
                    range: this.selectedRange,
                    preferFresh: !isAutoRefresh
                });

                const fetchEnd = performance.now();
                const fetchTime = Math.round(fetchEnd - fetchStart);

                if (data && data.success) {
                    this.rawData = data;
                    this.calculateStats();
                    this.renderChart();

                    // Reset error count on successful load
                    this.errorCount = 0;

                    const totalTime = Math.round(performance.now() - startTime);
                    console.log(`[LIQUIDATIONS:OK] (fetch: ${fetchTime}ms, total: ${totalTime}ms)`);
                } else {
                    console.warn('[LIQUIDATIONS:EMPTY]');
                }

            } catch (error) {
                console.error('[LIQUIDATIONS:ERROR]', error);

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

        calculateStats() {
            if (!this.rawData) return;

            const parsed = LiquidationsUtils.parseHeatmapData(this.rawData);
            if (!parsed) return;

            this.stats = LiquidationsUtils.calculateStats(parsed.heatmapData);
        },

        renderChart() {
            // Simplified render
            if (!this.chartManager || !this.rawData) return;
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
            console.log('🎯 setTimeRange called with:', value, 'current:', this.selectedRange);
            if (this.selectedRange === value) {
                console.log('⚠️ Same time range, skipping');
                return;
            }
            console.log('🎯 Time range changed to:', value);
            this.selectedRange = value;

            // Always trigger load for filter changes
            console.log('🚀 Filter changed, triggering instant load');
            this.instantLoadData();
        },

        // Alpine expects these names from the blade template
        updateRange(value) {
            console.log('🎯 updateRange called with:', value);
            this.setTimeRange(value);
        },

        updateSymbol(value) {
            console.log('🎯 updateSymbol called with:', value);
            if (value && value !== this.selectedSymbol) {
                console.log('🎯 Symbol changed to:', value);
                this.selectedSymbol = value;

                // Always trigger load for filter changes
                console.log('🚀 Filter changed, triggering instant load');
                this.instantLoadData();
            }
        },

        // Format methods (must be at controller level for Alpine.js)
        formatValue(value) {
            return LiquidationsUtils.formatValue(value);
        },

        formatChange(value) {
            return LiquidationsUtils.formatChange(value);
        },

        formatPercentage(value) {
            return LiquidationsUtils.formatPercentage(value);
        },

        // Interactive controls with debouncing for smooth performance
        updateThreshold(value) {
            const newThreshold = parseFloat(value);
            this.liquidityThreshold = newThreshold; // Update immediately for UI
            
            // Debounce chart re-render for smooth slider movement
            if (this.thresholdDebounceTimer) {
                clearTimeout(this.thresholdDebounceTimer);
            }
            
            this.thresholdDebounceTimer = setTimeout(() => {
                if (this.chartManager && this.rawData) {
                    this.chartManager.updateThreshold(newThreshold);
                }
            }, 150); // 150ms debounce - smooth but responsive
        },

        zoomIn() {
            this.zoomLevel = Math.min(this.zoomLevel * 1.2, 5);
            this.updateZoom();
        },

        zoomOut() {
            this.zoomLevel = Math.max(this.zoomLevel / 1.2, 0.5);
            this.updateZoom();
        },

        resetZoom() {
            this.zoomLevel = 1;
            this.panX = 0;
            this.panY = 0;
            this.updateZoom();
        },

        updateZoom() {
            if (this.chartManager && this.rawData) {
                this.chartManager.updateZoom(this.zoomLevel, this.panX, this.panY);
            }
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
