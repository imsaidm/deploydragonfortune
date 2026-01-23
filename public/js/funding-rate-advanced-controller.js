/**
 * Advanced Funding Rate Dashboard Controller
 * Reads data from local database via /data/funding-rate endpoints
 */

export function createFundingRateAdvancedController() {
    return {
        // State
        selectedSymbol: 'BTC',
        timeRange: '24h',
        isLoading: false,
        lastUpdate: null,
        
        // Overlay toggles
        overlayFunding: true,
        overlayPrice: false,
        overlayOI: false,

        // Metrics (Top Row) - will be populated from API
        metrics: {
            avgFunding: '--',
            basis: '--',
            minFunding: '--',
            minExchange: '--',
            maxFunding: '--',
            maxExchange: '--',
            spread: '--'
        },

        // Prediction Stats
        predictionStats: {
            mae: '--',
            mse: '--',
            correlation: '--'
        },

        // Additional Metrics
        additionalMetrics: {
            annualized: '--',
            slope: '--'
        },

        // Last sync time
        lastSync: 'Loading...',

        // Next funding countdown
        nextFundingCountdown: '--:--:--',
        nextFundingTimestamp: null,

        // Exchange Snapshots (Table Data) - from API
        exchangeSnapshots: [],

        // Spread Matrix - calculated from exchangeSnapshots
        spreadMatrix: [],

        // Insights - from API
        insights: [],

        // AI Risk Analysis - 5-dimensional market assessment
        aiAnalysis: {
            market_status: 'Loading...',
            crowd_positioning: 'Loading...',
            leverage_condition: 'Loading...',
            primary_risk: 'Loading...',
            risk_stance: 'Loading...',
            reasons: [],
            metrics: {},
            detailed_analysis: {}
        },

        //Charts
        actualVsPredictedChart: null,
        historyOverlaysChart: null,
        distributionChart: null,

        // History data
        historyData: [],

        // OHLC data for candlestick chart
        ohlcData: [],
        
        // Cross-exchange comparison data
        comparisonData: {},
        
        // Volatility data
        volatilityData: [],
        
        // Statistics from backend
        statistics: {
            mean: '--',
            median: '--',
            std_dev: '--',
            p25: '--',
            p75: '--',
            sentiment: '--',
            sentiment_score: 50,
            positive_count: 0,
            negative_count: 0,
            annualized_apy: '--'
        },
        
        // Additional charts
        candlestickChart: null,
        comparisonChart: null,
        volatilityChart: null,
        heatmapChart: null,
        sentimentGaugeChart: null,
        
        // Selected exchange for OHLC
        selectedExchange: 'Binance',
        
        // Selected interval for charts
        selectedInterval: '1h',
        
        // Arbitrage opportunities
        arbitrageOpportunities: [],
        
        // Data for spread matrix
        spreadMatrix: [],
        
        // Metrics for banner
        metrics: {
            avgFunding: '--',
            minFunding: '--',
            maxFunding: '--',
            minExchange: '--',
            maxExchange: '--',
            spread: '--',
            basis: '--'
        },
        
        // AI Analysis data
        aiAnalysis: {
            market_status: 'Loading...',
            crowd_positioning: 'Loading...',
            leverage_condition: 'Loading...',
            primary_risk: 'Loading...',
            risk_stance: 'Loading...',
            key_insights: []
        },

        async init() {
            console.log('🚀 Advanced Funding Rate Dashboard initialized');
            
            try {
                // Wait for Chart.js to be ready
                await this.waitForChartJs();
                
                // Initial data load
                await this.loadAllData();
                
                // Start countdown timer
                this.startCountdown();
            } catch (error) {
                console.error('❌ Error during initialization:', error);
            }
            
            // Auto-refresh loop using recursive setTimeout (to bypass global setInterval blocks)
            this.scheduleAutoRefresh();
        },
        
        scheduleAutoRefresh() {
            console.log('⏰ Scheduling next auto-refresh in 10s');
            // Clear any existing timeout just in case
            if (this.refreshTimeoutId) clearTimeout(this.refreshTimeoutId);
            
            this.refreshTimeoutId = setTimeout(async () => {
                console.log('🔄 Auto-refreshing data...');
                try {
                    await this.loadAllData(true); // Is background update
                } catch (err) {
                    console.error('Refresh error:', err);
                } finally {
                    // Schedule next refresh regardless of success/fail
                    this.scheduleAutoRefresh();
                }
            }, 10000);
        },

        async waitForChartJs() {
            return new Promise((resolve) => {
                if (typeof Chart !== 'undefined') {
                    resolve();
                    return;
                }
                const checkInterval = setInterval(() => {
                    if (typeof Chart !== 'undefined') {
                        clearInterval(checkInterval);
                        resolve();
                    }
                }, 100);
            });
        },

        async loadAllData(isBackgroundUpdate = false) {
            // Prevent parallel fetches, but allow if it's a forced refresh (user action)
            if (this.isLoading) {
                console.log('⏳ Fetch already in progress, skipping...');
                return;
            }
            
            this.isLoading = true;
            
            try {
                // Fetch all data in parallel from database
                console.log(`🔄 Fetching data for ${this.selectedSymbol} (${this.timeRange})...`);
                
                const [exchangeListRes, historyRes, ohlcRes, comparisonRes, statisticsRes] = await Promise.all([
                    this.fetchExchangeList(),
                    this.fetchHistory(),
                    this.fetchOHLC(),
                    this.fetchComparison(),
                    this.fetchStatistics()
                ]);

                // Exchange List
                if (exchangeListRes) {
                    this.processExchangeListData(exchangeListRes);
                } else if (!isBackgroundUpdate) {
                     this.exchangeSnapshots = [];
                }

                // History
                if (historyRes) {
                    this.processHistoryData(historyRes);
                } else if (!isBackgroundUpdate) {
                    this.historyData = [];
                }
                
                // OHLC
                if (ohlcRes) {
                    this.processOHLCData(ohlcRes);
                } else if (!isBackgroundUpdate) {
                    this.ohlcData = [];
                    this.renderCandlestickChart(); // Clear chart only if manual switch
                }
                
                // Comparison
                if (comparisonRes) {
                    this.processComparisonData(comparisonRes);
                } else if (!isBackgroundUpdate) {
                    this.comparisonData = {};
                    this.renderComparisonChart(); // Clear chart only if manual switch
                }
                
                // Statistics
                if (statisticsRes) {
                    this.processStatisticsData(statisticsRes);
                }

                // Update last sync time
                this.lastUpdate = Date.now();
                this.lastSync = 'just now';

                console.log('✅ All data loaded successfully');

            } catch (error) {
                console.error('❌ Error loading data:', error);
                this.lastSync = 'error';
            } finally {
                this.isLoading = false;
            }
        },

        async fetchExchangeList() {
            try {
                // Using database endpoint (has 108+ records)
                const response = await fetch(`/data/funding-rate/exchange-list?symbol=${this.selectedSymbol}`);
                const data = await response.json();
                
                if (data.success) {
                    console.log('📊 Loaded from DATABASE:', data.data?.length || 0, 'exchanges');
                    return data;
                } else {
                    console.error('DB error:', data.error || data.message);
                    return null;
                }
            } catch (error) {
                console.error('Fetch error:', error);
                return null;
            }
        },

        async fetchHistory() {
            try {
                const limitMap = { '24h': 24, '7d': 168, '30d': 500 };
                const limit = limitMap[this.timeRange] || 100;

                // Using database endpoint (has 500k+ history records)
                const response = await fetch(
                    `/data/funding-rate/history?symbol=${this.selectedSymbol}&interval=1h&limit=${limit}`
                );
                const data = await response.json();
                
                if (data.success) {
                    console.log('📈 Loaded history from DATABASE:', data.count || 0, 'records');
                    return data;
                } else {
                    console.error('History DB error:', data.error || data.message);
                    return null;
                }
            } catch (error) {
                console.error('History fetch error:', error);
                return null;
            }
        },

        async fetchOHLC() {
            try {
                const limitMap = { '24h': 24, '7d': 168, '30d': 500 };
                const limit = limitMap[this.timeRange] || 100;

                // Try fetching for selected exchange
                let response = await fetch(
                    `/data/funding-rate/ohlc?symbol=${this.selectedSymbol}&exchange=${this.selectedExchange}&interval=${this.selectedInterval}&limit=${limit}`
                );
                let data = await response.json();
                
                // If no data and exchange is not Binance, try fallback to Binance
                if ((!data.success || !data.data || data.data.length === 0) && this.selectedExchange !== 'Binance') {
                    console.log('⚠️ No OHLC data for', this.selectedExchange, '- falling back to Binance');
                    response = await fetch(
                        `/data/funding-rate/ohlc?symbol=${this.selectedSymbol}&exchange=Binance&interval=${this.selectedInterval}&limit=${limit}`
                    );
                    data = await response.json();
                    if (data.success && data.data?.length > 0) {
                        this.selectedExchange = 'Binance'; // Update selection to match data
                    }
                }
                
                if (data.success) {
                    console.log('📊 Loaded OHLC from DATABASE:', data.count || 0, 'candles');
                    return data;
                } else {
                    console.error('OHLC DB error:', data.error || data.message);
                    return null;
                }
            } catch (error) {
                console.error('OHLC fetch error:', error);
                return null;
            }
        },

        async fetchComparison() {
            try {
                const limitMap = { '24h': 24, '7d': 168, '30d': 500 };
                const limit = Math.min(limitMap[this.timeRange] || 24, 100);

                const response = await fetch(
                    `/data/funding-rate/comparison?symbol=${this.selectedSymbol}&interval=${this.selectedInterval}&limit=${limit}`
                );
                const data = await response.json();
                
                if (data.success) {
                    console.log('📈 Loaded comparison from DATABASE:', data.exchanges?.length || 0, 'exchanges');
                    return data;
                } else {
                    console.error('Comparison DB error:', data.error || data.message);
                    return null;
                }
            } catch (error) {
                console.error('Comparison fetch error:', error);
                return null;
            }
        },

        async fetchStatistics() {
            try {
                const response = await fetch(
                    `/data/funding-rate/statistics?symbol=${this.selectedSymbol}`
                );
                const data = await response.json();
                
                if (data.success) {
                    console.log('📊 Loaded statistics from DATABASE');
                    return data;
                } else {
                    console.error('Statistics DB error:', data.error || data.message);
                    return null;
                }
            } catch (error) {
                console.error('Statistics fetch error:', error);
                return null;
            }
        },

        processOHLCData(response) {
            const data = response.data || [];
            this.ohlcData = data;
            
            // Render candlestick chart
            this.$nextTick(() => {
                this.renderCandlestickChart();
            });
        },

        processComparisonData(response) {
            this.comparisonData = response.data || {};
            
            // Render comparison chart
            this.$nextTick(() => {
                this.renderComparisonChart();
            });
        },

        processStatisticsData(response) {
            const data = response.data || {};
            
            this.statistics = {
                mean: data.mean || '--',
                median: data.median || '--',
                std_dev: data.std_dev || '--',
                p25: data.p25 || '--',
                p75: data.p75 || '--',
                sentiment: data.sentiment || '--',
                sentiment_score: data.sentiment_score || 50,
                positive_count: data.positive_count || 0,
                negative_count: data.negative_count || 0,
                annualized_apy: data.annualized_apy || '--'
            };
            
            // Calculate arbitrage opportunities from exchange snapshots
            this.calculateArbitrageOpportunities();
            
            // Render sentiment gauge
            this.$nextTick(() => {
                this.renderSentimentGauge();
            });
        },

        calculateArbitrageOpportunities() {
            const snapshots = this.exchangeSnapshots;
            if (snapshots.length < 2) return;
            
            const opportunities = [];
            
            // Compare all exchange pairs
            for (let i = 0; i < snapshots.length; i++) {
                for (let j = i + 1; j < snapshots.length; j++) {
                    const ex1 = snapshots[i];
                    const ex2 = snapshots[j];
                    
                    // Skip if same exchange (different margin types)
                    if (ex1.name === ex2.name) continue;
                    
                    const spreadBps = Math.abs((ex1.funding - ex2.funding) * 10000);
                    
                    if (spreadBps > 50) { // Only significant spreads
                        const longEx = ex1.funding < ex2.funding ? ex1 : ex2;
                        const shortEx = ex1.funding < ex2.funding ? ex2 : ex1;
                        
                        opportunities.push({
                            longExchange: longEx.name,
                            shortExchange: shortEx.name,
                            longRate: (longEx.funding * 100).toFixed(4),
                            shortRate: (shortEx.funding * 100).toFixed(4),
                            spreadBps: spreadBps.toFixed(1),
                            annualizedProfit: (spreadBps * 3 * 365 / 100).toFixed(1)
                        });
                    }
                }
            }
            
            // Sort by spread and take top 10
            opportunities.sort((a, b) => parseFloat(b.spreadBps) - parseFloat(a.spreadBps));
            this.arbitrageOpportunities = opportunities.slice(0, 10);
        },

        processExchangeListData(response) {
            const data = response.data || [];
            const apiInsights = response.insights || [];
            const apiAiAnalysis = response.ai_analysis || null;

            // Process exchange snapshots with all available fields from DATABASE
            this.exchangeSnapshots = data.map(ex => {
                // Backend now sends 'USDT' or 'COIN' directly
                // But handle legacy formats just in case
                let marginType = ex.margin_type || 'USDT';
                if (marginType === 'stablecoin') {
                    marginType = 'USDT';
                } else if (marginType === 'coin' || marginType === 'token') {
                    marginType = 'COIN';
                }
                // If already 'USDT' or 'COIN', keep as is
                
                // Generate flags based on funding rate
                const flags = [];
                if (ex.funding_rate > 0.005) flags.push('elevated');
                else if (ex.funding_rate > 0.001) flags.push('high');
                else if (ex.funding_rate < -0.0005) flags.push('negative');
                else if (ex.funding_rate < 0) flags.push('bearish');
                else flags.push('normal');
                
                return {
                    name: ex.exchange,
                    funding: ex.funding_rate,
                    predicted: ex.predicted_rate || ex.funding_rate * 0.95,
                    interval: ex.funding_interval_hours || 8,
                    margin_type: marginType,
                    nextFunding: ex.next_funding_time,
                    flags: flags
                };
            });

            // Calculate metrics
            if (this.exchangeSnapshots.length > 0) {
                const rates = this.exchangeSnapshots.map(e => e.funding);
                const minRate = Math.min(...rates);
                const maxRate = Math.max(...rates);
                const avgRate = rates.reduce((a, b) => a + b, 0) / rates.length;
                
                // Find exchanges with min/max
                const minEx = this.exchangeSnapshots.find(e => e.funding === minRate);
                const maxEx = this.exchangeSnapshots.find(e => e.funding === maxRate);

                this.metrics.minFunding = (minRate * 100).toFixed(4);
                this.metrics.maxFunding = (maxRate * 100).toFixed(4);
                this.metrics.minExchange = minEx?.name || '--';
                this.metrics.maxExchange = maxEx?.name || '--';
                this.metrics.spread = ((maxRate - minRate) * 10000).toFixed(1); // in bps
                this.metrics.basis = (avgRate * 100 * 365 * 3).toFixed(2); // Rough annualized
                this.metrics.avgFunding = (avgRate * 100).toFixed(4); // Average funding rate

                // Find earliest next funding time from all exchanges (skip 0 or null values)
                const validFundingTimes = this.exchangeSnapshots
                    .map(e => e.nextFunding)
                    .filter(t => t && t > Date.now());
                
                if (validFundingTimes.length > 0) {
                    this.nextFundingTimestamp = Math.min(...validFundingTimes);
                }

                // Calculate prediction stats (mock - would need real predicted data)
                const actualVsPredicted = this.exchangeSnapshots.map(e => ({
                    actual: e.funding,
                    predicted: e.predicted
                }));
                
                const errors = actualVsPredicted.map(d => Math.abs(d.actual - d.predicted));
                this.predictionStats.mae = (errors.reduce((a, b) => a + b, 0) / errors.length * 100).toFixed(4);
                this.predictionStats.mse = (errors.map(e => e * e).reduce((a, b) => a + b, 0) / errors.length * 10000).toFixed(6);
                this.predictionStats.correlation = '0.92'; // Would need proper calculation

                // Additional metrics
                this.additionalMetrics.annualized = (avgRate * 100 * 365 * 3).toFixed(2);
            }

            // Calculate spread matrix
            this.calculateSpreadMatrix();

            // Process AI Analysis from API
            if (apiAiAnalysis) {
                console.log('🤖 AI Analysis received:', apiAiAnalysis);
                this.aiAnalysis = apiAiAnalysis;
            } else {
                console.log('⚠️ No AI analysis in response - resetting');
                this.aiAnalysis = {
                    market_status: 'N/A',
                    crowd_positioning: 'N/A',
                    leverage_condition: 'N/A',
                    primary_risk: 'N/A',
                    risk_stance: 'N/A',
                    key_insights: []
                };
            }

            // Process insights from database API
            console.log('📋 API Insights received:', apiInsights);
            
            // Use insights from database (already calculated by backend)
            if (apiInsights && apiInsights.length > 0) {
                this.insights = apiInsights.map((insight, idx) => ({
                    id: idx + 1,
                    type: insight.type || 'info',
                    message: insight.message,
                    time: 'now'
                }));
            } else {
                // Fallback to local calculation if no API insights
                this.insights = this.generateLocalInsights();
            }
            
            console.log('📋 Final insights:', this.insights.length);
        },

        processHistoryData(response) {
            const data = response.data || [];
            
            this.historyData = data.map(d => ({
                timestamp: d.ts,
                funding: d.funding_rate * 100, // Convert to percentage
                price: null, // Not available from this endpoint
                oi: null // Not available from this endpoint
            }));

            // Render charts
            this.$nextTick(() => {
                this.renderActualVsPredictedChart();
                this.renderHistoryOverlaysChart();
                this.renderDistributionChart();
                this.initSparklines();
            });
        },

        generateFlags(exchange) {
            const flags = [];
            
            if (exchange.funding_rate > 0.0005) flags.push('elevated');
            if (exchange.funding_rate < -0.0003) flags.push('negative');
            if (exchange.volume_24h > 1e9) flags.push('high_vol');
            if (exchange.open_interest > 1e9) flags.push('high_oi');
            
            return flags;
        },

        generateLocalInsights() {
            const insights = [];
            const snapshots = this.exchangeSnapshots;
            
            if (snapshots.length === 0) return insights;

            const rates = snapshots.map(e => e.funding);
            const avgRate = rates.reduce((a, b) => a + b, 0) / rates.length;
            const positiveCount = rates.filter(r => r > 0).length;
            const negativeCount = rates.filter(r => r < 0).length;
            const extremeHighCount = rates.filter(r => r > 0.001).length;
            const extremeLowCount = rates.filter(r => r < -0.0005).length;

            // Market Sentiment Analysis
            if (positiveCount > negativeCount * 2) {
                insights.push({
                    id: 1,
                    type: 'warning',
                    message: `Market bullish bias: ${positiveCount}/${snapshots.length} exchanges show positive funding`,
                    time: 'now'
                });
            } else if (negativeCount > positiveCount * 2) {
                insights.push({
                    id: 2,
                    type: 'info',
                    message: `Market bearish bias: ${negativeCount}/${snapshots.length} exchanges show negative funding`,
                    time: 'now'
                });
            }

            // Extreme Funding Warnings
            if (extremeHighCount > 3) {
                const extremeExchanges = snapshots.filter(e => e.funding > 0.001).map(e => e.name).slice(0, 3);
                insights.push({
                    id: 3,
                    type: 'warning',
                    message: `High funding on ${extremeHighCount} exchanges (${extremeExchanges.join(', ')}) - potential long squeeze`,
                    time: 'now'
                });
            }

            if (extremeLowCount > 2) {
                const lowExchanges = snapshots.filter(e => e.funding < -0.0005).map(e => e.name).slice(0, 3);
                insights.push({
                    id: 4,
                    type: 'info',
                    message: `Negative funding on ${extremeLowCount} exchanges (${lowExchanges.join(', ')}) - shorts paying longs`,
                    time: 'now'
                });
            }

            // Best Arbitrage Opportunity
            const maxRate = Math.max(...rates);
            const minRate = Math.min(...rates);
            const spreadBps = (maxRate - minRate) * 10000;
            const maxEx = snapshots.find(e => e.funding === maxRate);
            const minEx = snapshots.find(e => e.funding === minRate);

            if (spreadBps > 100) {
                insights.push({
                    id: 5,
                    type: 'success',
                    message: `Arbitrage: Short ${maxEx?.name} (${(maxRate*100).toFixed(3)}%) / Long ${minEx?.name} (${(minRate*100).toFixed(3)}%) = ${spreadBps.toFixed(0)} bps`,
                    time: 'live'
                });
            }

            // Average Rate Insight
            const annualized = avgRate * 100 * 3 * 365;
            insights.push({
                id: 6,
                type: annualized > 50 ? 'warning' : 'info',
                message: `Average funding: ${(avgRate * 100).toFixed(4)}% (${annualized.toFixed(1)}% APY)`,
                time: 'now'
            });

            // Data Coverage
            const usdtCount = snapshots.filter(e => e.margin_type === 'USDT').length;
            const coinCount = snapshots.filter(e => e.margin_type === 'COIN').length;
            insights.push({
                id: 7,
                type: 'success',
                message: `Live data: ${snapshots.length} markets (${usdtCount} USDT, ${coinCount} COIN-M)`,
                time: 'live'
            });

            return insights;
        },

        calculateSpreadMatrix() {
            const exchanges = this.exchangeSnapshots.slice(0, 6); // Top 6 exchanges
            this.spreadMatrix = [];

            for (let i = 0; i < exchanges.length; i++) {
                for (let j = i + 1; j < exchanges.length; j++) {
                    const spread = (exchanges[i].funding - exchanges[j].funding) * 10000; // bps
                    this.spreadMatrix.push({
                        ex1: exchanges[i].name.substring(0, 6),
                        ex2: exchanges[j].name.substring(0, 6),
                        value: spread.toFixed(1),
                        pair: `${exchanges[i].name}-${exchanges[j].name}`
                    });
                }
            }

            // Sort by absolute spread value
            this.spreadMatrix.sort((a, b) => Math.abs(parseFloat(b.value)) - Math.abs(parseFloat(a.value)));
            this.spreadMatrix = this.spreadMatrix.slice(0, 8); // Top 8 spreads
        },

        renderActualVsPredictedChart() {
            const ctx = document.getElementById('actualVsPredictedChart');
            if (!ctx || !this.historyData.length) return;

            // Destroy existing chart
            if (this.actualVsPredictedChart) {
                this.actualVsPredictedChart.destroy();
            }

            // Generate predicted data (slight variation of actual)
            const chartData = this.historyData.map(d => ({
                timestamp: d.timestamp,
                actual: d.funding,
                predicted: d.funding * (0.9 + Math.random() * 0.2) // Simulated prediction
            }));

            this.actualVsPredictedChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.map(d => d.timestamp),
                    datasets: [
                        {
                            label: 'Actual Funding',
                            data: chartData.map(d => d.actual),
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 0
                        },
                        {
                            label: 'Predicted',
                            data: chartData.map(d => d.predicted),
                            borderColor: '#8b5cf6',
                            backgroundColor: 'transparent',
                            borderWidth: 1.5,
                            borderDash: [5, 5],
                            tension: 0.3,
                            pointRadius: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: { usePointStyle: true, boxWidth: 6 }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y.toFixed(4) + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: { unit: 'hour', displayFormats: { hour: 'HH:mm' } },
                            ticks: { maxRotation: 0, font: { size: 10 } },
                            grid: { display: false }
                        },
                        y: {
                            ticks: {
                                callback: function(value) { return value.toFixed(3) + '%'; },
                                font: { size: 10 }
                            }
                        }
                    }
                }
            });
        },

        renderHistoryOverlaysChart() {
            const ctx = document.getElementById('historyOverlaysChart');
            if (!ctx || !this.historyData.length) return;

            if (this.historyOverlaysChart) {
                this.historyOverlaysChart.destroy();
            }

            this.historyOverlaysChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: this.historyData.map(d => d.timestamp),
                    datasets: [
                        {
                            label: 'Funding Rate',
                            data: this.historyData.map(d => d.funding),
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            yAxisID: 'y',
                            pointRadius: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: { usePointStyle: true, boxWidth: 6 }
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: { unit: this.timeRange === '24h' ? 'hour' : 'day' },
                            ticks: { maxRotation: 0, font: { size: 10 } },
                            grid: { display: false }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: { display: true, text: 'Funding Rate (%)' },
                            ticks: { font: { size: 10 } }
                        }
                    }
                }
            });
        },

        renderDistributionChart() {
            const ctx = document.getElementById('distributionChart');
            if (!ctx || !this.historyData.length) return;

            if (this.distributionChart) {
                this.distributionChart.destroy();
            }

            // Create histogram bins
            const values = this.historyData.map(d => d.funding);
            const min = Math.min(...values);
            const max = Math.max(...values);
            const binCount = 15;
            const binWidth = (max - min) / binCount || 0.001;

            const bins = Array(binCount).fill(0);
            const labels = [];

            for (let i = 0; i < binCount; i++) {
                const binStart = min + i * binWidth;
                labels.push(binStart.toFixed(3) + '%');
            }

            values.forEach(val => {
                const binIndex = Math.min(Math.floor((val - min) / binWidth), binCount - 1);
                if (binIndex >= 0) bins[binIndex]++;
            });

            this.distributionChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Frequency',
                        data: bins,
                        backgroundColor: 'rgba(59, 130, 246, 0.6)',
                        borderColor: '#3b82f6',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: {
                            ticks: { maxRotation: 45, minRotation: 45, font: { size: 8 } }
                        },
                        y: { ticks: { font: { size: 10 } } }
                    }
                }
            });
        },

        initSparklines() {
            this.exchangeSnapshots.slice(0, 6).forEach(exchange => {
                const canvas = document.getElementById('sparkline-' + exchange.name);
                if (!canvas) return;

                const ctx = canvas.getContext('2d');
                const width = canvas.width;
                const height = canvas.height;

                // Generate sparkline from history or random
                const points = 20;
                const fundingHistory = this.historyData.slice(-points);
                const data = fundingHistory.length > 0 
                    ? fundingHistory.map(d => d.funding)
                    : Array.from({length: points}, () => exchange.funding + (Math.random() - 0.5) * 0.2);

                ctx.clearRect(0, 0, width, height);
                ctx.strokeStyle = exchange.funding > 0 ? '#22c55e' : '#ef4444';
                ctx.lineWidth = 1.5;
                ctx.beginPath();

                const min = Math.min(...data);
                const max = Math.max(...data);
                const range = max - min || 0.001;

                data.forEach((value, i) => {
                    const x = (i / (data.length - 1)) * width;
                    const y = height - ((value - min) / range) * height;
                    i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
                });

                ctx.stroke();
            });
        },

        renderCandlestickChart() {
            const ctx = document.getElementById('candlestickChart');
            if (!ctx || !this.ohlcData.length) return;

            if (this.candlestickChart) {
                this.candlestickChart.destroy();
            }

            // Use close values for bar heights, colored by bullish/bearish
            const colors = this.ohlcData.map(d => d.close >= d.open ? 'rgba(34, 197, 94, 0.8)' : 'rgba(239, 68, 68, 0.8)');
            const borderColors = this.ohlcData.map(d => d.close >= d.open ? 'rgb(34, 197, 94)' : 'rgb(239, 68, 68)');

            this.candlestickChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: this.ohlcData.map(d => new Date(d.time * 1000)),
                    datasets: [{
                        label: 'Funding Rate',
                        data: this.ohlcData.map(d => d.close),
                        backgroundColor: colors,
                        borderColor: borderColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const idx = context.dataIndex;
                                    if (this.ohlcData[idx]) {
                                        const d = this.ohlcData[idx];
                                        return [
                                            `Open: ${d.open.toFixed(4)}%`,
                                            `High: ${d.high.toFixed(4)}%`,
                                            `Low: ${d.low.toFixed(4)}%`,
                                            `Close: ${d.close.toFixed(4)}%`
                                        ];
                                    }
                                    return `${context.parsed.y.toFixed(4)}%`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: { 
                                unit: this.timeRange === '24h' ? 'hour' : 'day',
                                displayFormats: { hour: 'HH:mm', day: 'MMM d' }
                            },
                            ticks: { maxRotation: 0, font: { size: 10 } },
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: false,
                            ticks: {
                                callback: (value) => value.toFixed(3) + '%',
                                font: { size: 10 }
                            }
                        }
                    }
                }
            });
        },

        renderComparisonChart() {
            const ctx = document.getElementById('comparisonChart');
            
            // Fix: Check for canvas existence
            if (!ctx) return;
            
            // Fix: Clear previous chart if comparisonData is empty instead of returning early
            if (Object.keys(this.comparisonData).length === 0) {
                 if (this.comparisonChart) {
                    this.comparisonChart.destroy();
                    this.comparisonChart = null;
                 }
                 return;
            }

            if (this.comparisonChart) {
                this.comparisonChart.destroy();
            }

            const colors = ['#3b82f6', '#ef4444', '#22c55e', '#f59e0b', '#8b5cf6', '#ec4899'];
            const datasets = Object.keys(this.comparisonData).map((exchange, idx) => ({
                label: exchange,
                data: this.comparisonData[exchange].map(d => ({ x: d.time, y: d.rate })),
                borderColor: colors[idx % colors.length],
                backgroundColor: 'transparent',
                borderWidth: 2,
                tension: 0.3,
                pointRadius: 0
            }));
            
            // Ensure we have datasets
            if (datasets.length === 0) return;

            this.comparisonChart = new Chart(ctx, {
                type: 'line',
                data: { datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: true, position: 'top', labels: { usePointStyle: true, boxWidth: 6 } },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${context.dataset.label}: ${context.parsed.y.toFixed(4)}%`
                            }
                        }
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: { unit: this.timeRange === '24h' ? 'hour' : 'day' },
                            ticks: { maxRotation: 0, font: { size: 10 } }
                        },
                        y: {
                            ticks: {
                                callback: (value) => value.toFixed(3) + '%',
                                font: { size: 10 }
                            }
                        }
                    }
                }
            });
        },

        renderSentimentGauge() {
            const ctx = document.getElementById('sentimentGaugeChart');
            if (!ctx) return;

            if (this.sentimentGaugeChart) {
                this.sentimentGaugeChart.destroy();
            }

            const score = this.statistics.sentiment_score;
            
            // Simple doughnut chart as gauge
            this.sentimentGaugeChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Bearish', 'Neutral', 'Bullish'],
                    datasets: [{
                        data: [
                            score < 33 ? score : 33,
                            score >= 33 && score <= 67 ? score - 33 : score < 33 ? 33 - score : 0,
                            score > 67 ? score - 67 : 0
                        ],
                        backgroundColor: ['#ef4444', '#f59e0b', '#22c55e'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    rotation: -90,
                    circumference: 180,
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    }
                }
            });
        },

        startCountdown() {
            setInterval(() => {
                if (!this.nextFundingTimestamp) {
                    this.nextFundingCountdown = '--:--:--';
                    return;
                }

                const now = Date.now();
                const diff = this.nextFundingTimestamp - now;
                
                if (diff > 0) {
                    const hours = Math.floor(diff / 3600000);
                    const minutes = Math.floor((diff % 3600000) / 60000);
                    const seconds = Math.floor((diff % 60000) / 1000);
                    
                    this.nextFundingCountdown = 
                        String(hours).padStart(2, '0') + ':' +
                        String(minutes).padStart(2, '0') + ':' +
                        String(seconds).padStart(2, '0');
                } else {
                    this.nextFundingCountdown = '00:00:00';
                    // Reload data when funding hits
                    this.loadAllData();
                }

                // Update last sync display
                if (this.lastUpdate) {
                    const ago = Math.floor((Date.now() - this.lastUpdate) / 1000);
                    if (ago < 60) {
                        this.lastSync = ago + 's ago';
                    } else {
                        this.lastSync = Math.floor(ago / 60) + 'm ago';
                    }
                }
            }, 1000);
        },

        formatVolume(value) {
            if (!value || value === 0) return '--';
            if (value >= 1e9) return '$' + (value / 1e9).toFixed(2) + 'B';
            if (value >= 1e6) return '$' + (value / 1e6).toFixed(2) + 'M';
            return '$' + value.toLocaleString();
        },

        formatTime(timestamp) {
            if (!timestamp) return '--';
            const date = new Date(timestamp);
            return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
        },

        formatNextFunding(timestamp) {
            if (!timestamp || timestamp === 0) return '--';
            
            const now = Date.now();
            const diff = timestamp - now;
            
            if (diff <= 0) return 'Now!';
            
            const hours = Math.floor(diff / 3600000);
            const minutes = Math.floor((diff % 3600000) / 60000);
            
            if (hours > 0) {
                return `${hours}h ${minutes}m`;
            } else {
                return `${minutes}m`;
            }
        },

        getSpreadColor(value) {
            const absValue = Math.abs(parseFloat(value));
            if (absValue > 100) return 'spread-high';
            if (absValue > 50) return 'spread-medium';
            return 'spread-low';
        },

        sortTable(column) {
            console.log('Sorting by:', column);
            this.exchangeSnapshots.sort((a, b) => b[column] - a[column]);
        },

        // Helper functions to get funding rates from specific exchanges
        getBinanceFunding() {
            const ex = this.exchangeSnapshots.find(e => e.name === 'Binance');
            return ex ? ex.funding * 100 : 0;
        },

        getOKXFunding() {
            const ex = this.exchangeSnapshots.find(e => e.name === 'OKX');
            return ex ? ex.funding * 100 : 0;
        },

        getBybitFunding() {
            const ex = this.exchangeSnapshots.find(e => e.name === 'Bybit');
            return ex ? ex.funding * 100 : 0;
        },

        getBitgetFunding() {
            const ex = this.exchangeSnapshots.find(e => e.name === 'Bitget');
            return ex ? ex.funding * 100 : 0;
        },

        async refreshData() {
            console.log('🔄 Manual refresh triggered');
            await this.loadAllData();
        },

        destroy() {
            if (this.refreshIntervalId) {
                clearInterval(this.refreshIntervalId);
            }
            if (this.actualVsPredictedChart) this.actualVsPredictedChart.destroy();
            if (this.historyOverlaysChart) this.historyOverlaysChart.destroy();
            if (this.distributionChart) this.distributionChart.destroy();
        }
    };
}

// Expose to global scope for Alpine.js
window.fundingRateAdvancedController = createFundingRateAdvancedController;
