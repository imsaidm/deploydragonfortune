const OpenInterestNewController = {
    chart: null,
    stablecoinChart: null,
    compChart: null,
    domChart: null,
    volChart: null,
    cachedAggData: null,
    cachedStableData: null,
    currentPrice: 0,
    symbol: 'BTC',
    interval: '1h',

    init: async function() {
        console.log('Open Interest Controller Initialized (Chart.js)');
        this.bindEvents();
        this.startClock();
        await this.loadSymbols();
        await this.loadData();
    },

    startClock: function() {
        // Update immediately
        const updateTime = () => {
             document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();
        };
        updateTime();
        // Update every second
        setInterval(updateTime, 1000);
    },

    bindEvents: function() {
        document.getElementById('symbol-select').addEventListener('change', (e) => {
            this.symbol = e.target.value;
            this.loadData();
        });
    },
    
    setFrame: function(frame) {
        console.log("Switching timeframe to", frame);
        // this.interval = frame; 
        // this.loadData();
    },

    loadSymbols: async function() {
        try {
            const res = await fetch('/data/open-interest/symbols');
            const data = await res.json();
            if (data.success && data.data && data.data.length > 0) {
                const select = document.getElementById('symbol-select');
                const currentVal = select.value;
                
                // Keep 'BTC' if it was default, otherwise clear
                select.innerHTML = '';
                
                // Prioritize current selection being in list
                let foundCurrent = false;

                data.data.forEach(sym => {
                    const opt = document.createElement('option');
                    opt.value = sym;
                    opt.textContent = sym;
                    if (sym === currentVal) {
                        opt.selected = true;
                        foundCurrent = true;
                    }
                    select.appendChild(opt);
                });
                
                // If current wasn't found (and we have data), select first
                if (!foundCurrent && data.data.length > 0) {
                     // But wait, if we just loaded page, currentVal is BTC (hardcoded). 
                     // If BTC is in list, we good. If not, pick first.
                     if (currentVal === 'BTC' && !data.data.includes('BTC')) {
                         select.value = data.data[0];
                         this.symbol = data.data[0];
                     }
                }
            }
        } catch (e) {
            console.error('Failed to load symbol list', e);
        }
    },

    initMainChart: function(priceData, oiData, labels) {
        const ctx = document.getElementById('chart-container').getContext('2d');
        
        if (this.chart) {
            this.chart.destroy();
        }

        // OI Gradient
        const gradientOI = ctx.createLinearGradient(0, 0, 0, 400);
        gradientOI.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
        gradientOI.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

        this.chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Open Interest',
                        data: oiData,
                        borderColor: '#6366f1',
                        backgroundColor: gradientOI,
                        borderWidth: 2,
                        fill: true,
                        yAxisID: 'y',
                        tension: 0.4,
                        pointRadius: 0
                    },
                    {
                        label: 'Price',
                        data: priceData,
                        borderColor: '#fbbf24',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        yAxisID: 'y1',
                        tension: 0.1,
                        pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: '#9ca3af' }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#6b7280' }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { 
                            color: '#6366f1',
                            callback: function(value) { return '$' + (value/1e9).toFixed(1) + 'B'; }
                        },
                        title: { display: true, text: 'Open Interest', color: '#6366f1' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: { color: '#fbbf24' },
                         title: { display: true, text: 'Price', color: '#fbbf24' }
                    }
                }
            }
        });
    },

    initStablecoinChart: function(data, labels) {
        const ctx = document.getElementById('stablecoin-mini-chart').getContext('2d');
        
        if (this.stablecoinChart) {
            this.stablecoinChart.destroy();
        }

        const gradient = ctx.createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, 'rgba(16, 185, 129, 0.4)');
        gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

        this.stablecoinChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Stablecoin OI',
                    data: data,
                    borderColor: '#10b981',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: false },
                    y: { display: false }
                },
                layout: { padding: 0 }
            }
        });
    },

    loadData: async function() {
        console.log("Loading data for", this.symbol);
        
        try {
            // 1. Live Price (Binance) for Conversion
            let currentPrice = 0;
            try {
                const priceRes = await fetch(`https://api.binance.com/api/v3/ticker/price?symbol=${this.symbol}USDT`);
                const priceData = await priceRes.json();
                if (priceData.price) currentPrice = parseFloat(priceData.price);
            } catch (e) {
                console.warn('Failed to fetch live price', e);
            }
            this.currentPrice = currentPrice;

            // 2. Aggregated Data
            const aggRes = await fetch(`/data/open-interest/aggregated?symbol=${this.symbol}&limit=100`);
            const aggData = await aggRes.json();
            
            if (aggData.success && aggData.data && aggData.data.length > 0) {
                const sortedData = aggData.data.sort((a,b) => a.time - b.time);
                this.cachedAggData = sortedData; 

                const labels = sortedData.map(d => new Date(d.time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
                // Use live price if history is 0 ? No, mixing is weird. Keep 0 history.
                const priceData = sortedData.map(d => d.price);
                const oiData = sortedData.map(d => d.value);
                
                this.initMainChart(priceData, oiData, labels);
                this.updateStats(aggData.data);
            } else {
                 this.cachedAggData = null; // Signal fallback
                 console.warn("Aggregated history empty, falling back to Stablecoin data.");
            }

            // 3. Stablecoin Data
            const stableRes = await fetch(`/data/open-interest/stablecoin?symbol=${this.symbol}&limit=50`);
            const stableData = await stableRes.json();
             if (stableData.success && stableData.data && stableData.data.length > 0) {
                const sortedS = stableData.data.sort((a,b) => a.time - b.time);
                this.cachedStableData = sortedS;

                const sData = sortedS.map(d => d.value);
                const sLabels = sortedS.map(d => d.time);
                this.initStablecoinChart(sData, sLabels);
                
                // Fallback: Use Stablecoin data for Main Chart if Agg is empty
                if (!this.cachedAggData) {
                    const labels = sortedS.map(d => new Date(d.time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
                    
                    const p = this.currentPrice || 0;
                    // Convert Units -> USD if we have price, else keep as Units (better than 0)
                    // If p is 0, arguably we should show 0 USD, but user wants to see *something*. 
                    // Let's assume if p=0 we show units but label might be wrong ($). 
                    // Actually if p=0, showing units as $ is misleading. 
                    // But for BNB, we expect p > 0 from Binance API.
                    
                    const oiDataUSD = sortedS.map(d => d.value * (p > 0 ? p : 1));
                    const priceDataZero = sortedS.map(d => 0); 
                    
                    this.initMainChart(priceDataZero, oiDataUSD, labels);
                    
                    const mockAggData = sortedS.map(d => ({
                        time: d.time,
                        value: d.value * (p > 0 ? p : 1), // USD est
                        high: d.value * (p > 0 ? p : 1) * 1.01, // dummy volatility
                        low: d.value * (p > 0 ? p : 1) * 0.99,
                        price: 0
                    }));
                    
                    this.updateStats(mockAggData);
                    this.cachedAggData = mockAggData.map(d => ({...d, isFallback: true}));
                }
             }

             // Render Advanced Metrics (using live price)
             this.renderAdvancedMetrics();

            // 3. AI Analysis
            const aiRes = await fetch(`/data/open-interest/analysis?symbol=${this.symbol}`);
            const aiData = await aiRes.json();
            
            if (aiData.success) {
                this.renderAnalysis(aiData);
            }

        } catch (e) {
            console.error("Failed to load data", e);
        }
        
        // document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();
    },

    updateStats: function(data) {
        if (!data || data.length < 2) return;
        
        const latest = data[data.length - 1];
        const prev = data[data.length - 2];
        
        // Total OI
        document.getElementById('stat-total-oi').textContent = this.formatCurrency(latest.value);
        
        // Change
        const change = ((latest.value - prev.value) / prev.value) * 100;
        const changeEl = document.getElementById('stat-oi-change');
        changeEl.textContent = `${change > 0 ? '+' : ''}${change.toFixed(2)}%`;
        changeEl.className = `small fw-medium ${change >= 0 ? 'text-success' : 'text-danger'}`;
        
        // Average 24h Open Interest
        if (data.length > 0) {
            const sumOI = data.reduce((acc, curr) => acc + curr.value, 0);
            const avgOI = sumOI / data.length;
            const avgOiEl = document.getElementById('stat-avg-oi');
            if (avgOiEl) {
                avgOiEl.textContent = this.formatCurrency(avgOI);
            }
        }
    },

    renderAnalysis: function(data) {
        const analysis = data.analysis;
        const text = data.text;
        
        // Update Stats based on Analysis
        document.getElementById('stat-regime').textContent = analysis.sentiment || 'Neutral';
        const riskEl = document.getElementById('stat-risk');
        riskEl.textContent = analysis.primary_risk || 'Low';
        riskEl.className = `stat-value ${analysis.primary_risk === 'Low' || analysis.primary_risk === 'None' ? 'text-success' : 'text-danger'}`;

        // Text Content
        const container = document.getElementById('ai-analysis-content');
        container.innerHTML = '';
        
        if (text) {
             const lines = text.split('\n\n');
             lines.forEach(line => {
                 const p = document.createElement('div');
                 p.className = 'ai-insight-card text-secondary small mb-2';
                 p.textContent = line;
                 container.appendChild(p);
             });
        }
    },
    
    renderAdvancedMetrics: function() {
        if (!this.cachedAggData || !this.cachedStableData) return;

        // Get Latest Snapshots
        const latestAgg = this.cachedAggData[this.cachedAggData.length - 1];
        const latestStable = this.cachedStableData[this.cachedStableData.length - 1];
        
        if (!latestAgg || !latestStable) return;

        const totalOI = latestAgg.value; // USD
        let price = this.currentPrice || 0; 
        
        // Fallback Price from data if live failed (unlikely if Price=0 in DB)
        if (price === 0 && latestAgg.price > 0) price = latestAgg.price;

        // Stablecoin data seems to be in Coin Units (e.g. BTC), so convert to USD
        let stableOI_USD = latestStable.value;
        
        // Heuristic: If stable value is tiny compared to Total (< 1%), it is likely in Coin Units.
        // Multiply by Price to get USD.
        if (price > 0 && stableOI_USD < totalOI * 0.01) {
             stableOI_USD = latestStable.value * price;
        }

        const coinMOI = Math.max(0, totalOI - stableOI_USD);
        
        // 1. Snapshot: Margin Composition (Pie)
        const ctxComp = document.getElementById('chart-composition').getContext('2d');
        if (this.compChart) this.compChart.destroy();
        
        this.compChart = new Chart(ctxComp, {
            type: 'doughnut',
            data: {
                labels: ['Coin-Margined', 'Stablecoin-Margined'],
                datasets: [{
                    data: [coinMOI, stableOI_USD],
                    backgroundColor: ['#f59e0b', '#0ea5e9'], // Orange, Sky Blue
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                         callbacks: {
                            label: function(context) {
                                let val = context.raw;
                                return ' $' + (val/1e9).toFixed(2) + 'B';
                            }
                        }
                    }
                }
            }
        });

        // 2. Snapshot: Dominance (Semi Circle)
        const dominanceParams = (stableOI_USD / totalOI) * 100;
        document.getElementById('stat-dominance-val').textContent = dominanceParams.toFixed(2) + '%';
        
        const ctxDom = document.getElementById('chart-dominance').getContext('2d');
        if (this.domChart) this.domChart.destroy();
        
        this.domChart = new Chart(ctxDom, {
            type: 'doughnut',
            data: {
                labels: ['Stablecoin', 'Coin'],
                datasets: [{
                    data: [dominanceParams, 100 - dominanceParams],
                    backgroundColor: ['#0ea5e9', '#334155'],
                    borderWidth: 0,
                    circumference: 180,
                    rotation: 270,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '80%',
                plugins: { legend: { display: false }, tooltip: { enabled: false } }
            }
        });

        // 3. Series: Volatility (High - Low)
        const labels = this.cachedAggData.map(d => new Date(d.time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
        const volatilityData = this.cachedAggData.map(d => (d.high - d.low));
        const latestVol = volatilityData[volatilityData.length - 1];
        
        document.getElementById('stat-vol-val').textContent = '$' + (latestVol/1e6).toFixed(2) + 'M';

        const ctxVol = document.getElementById('chart-volatility').getContext('2d');
        if (this.volChart) this.volChart.destroy();

        this.volChart = new Chart(ctxVol, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Volatility (Hi-Lo)',
                    data: volatilityData,
                    borderColor: '#ef4444',
                    borderWidth: 1,
                    pointRadius: 0,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { display: false }, y: { display: false } }
            }
        });
    },

    formatCurrency: function(value) {
        if (value >= 1e9) {
            return '$' + (value / 1e9).toFixed(2) + 'B';
        } else if (value >= 1e6) {
             return '$' + (value / 1e6).toFixed(2) + 'M';
        }
        return '$' + value.toLocaleString();
    }
};

document.addEventListener('DOMContentLoaded', () => {
    OpenInterestNewController.init();
    // Re-run icons if needed
    if (typeof feather !== 'undefined') feather.replace();
});
