(function () {
    const cfg = window.strategyDashboard || {};
    const chartEl = document.getElementById('marketChart');
    if (!chartEl || !window.LightweightCharts || !cfg.strategy) return;

    const colors = {
        text: getCss('--sd-text', '#0f172a'),
        muted: getCss('--sd-muted', '#64748b'),
        grid: document.documentElement.classList.contains('dark') ? 'rgba(148,163,184,.12)' : 'rgba(15,23,42,.08)',
        surface: getCss('--sd-surface', '#ffffff'),
        green: '#16a34a',
        red: '#e11d48',
        blue: '#2563eb',
        amber: '#f59e0b',
    };

    const state = {
        tf: cfg.defaultTf || '1h',
        candles: [],
        socket: null,
        priceLineSeries: null,
        tpSeries: null,
        slSeries: null,
        entrySeries: null,
        selectedTrade: null,
    };

    const chart = LightweightCharts.createChart(chartEl, {
        autoSize: true,
        layout: {
            background: { color: 'transparent' },
            textColor: colors.muted,
            fontFamily: 'Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif',
        },
        grid: {
            vertLines: { color: colors.grid },
            horzLines: { color: colors.grid },
        },
        crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
        rightPriceScale: { borderColor: colors.grid },
        leftPriceScale: { visible: true, borderColor: colors.grid },
        timeScale: {
            borderColor: colors.grid,
            timeVisible: true,
            secondsVisible: false,
        },
        handleScale: true,
        handleScroll: true,
    });

    const candleSeries = chart.addCandlestickSeries({
        upColor: '#16a34a',
        downColor: '#e11d48',
        borderUpColor: '#16a34a',
        borderDownColor: '#e11d48',
        wickUpColor: '#16a34a',
        wickDownColor: '#e11d48',
        priceFormat: { type: 'price', precision: pricePrecision(cfg.strategy.symbol), minMove: minMove(cfg.strategy.symbol) },
    });

    const volumeSeries = chart.addHistogramSeries({
        priceFormat: { type: 'volume' },
        priceScaleId: '',
        lastValueVisible: false,
        priceLineVisible: false,
    });

    volumeSeries.priceScale().applyOptions({
        scaleMargins: { top: 0.82, bottom: 0 },
    });

    const equitySeries = chart.addLineSeries({
        color: '#22c55e',
        lineWidth: 2,
        priceScaleId: 'left',
        lastValueVisible: true,
        priceLineVisible: false,
        title: 'Equity',
    });

    equitySeries.priceScale().applyOptions({
        scaleMargins: { top: 0.08, bottom: 0.72 },
    });

    if (Array.isArray(cfg.equity) && cfg.equity.length) {
        equitySeries.setData(cfg.equity);
    }

    wireTimeframeButtons();
    wireTableRows();
    chart.subscribeClick(handleChartClick);
    loadChart(state.tf);

    async function loadChart(tf, explicitRange = null, options = {}) {
        state.tf = tf;
        setDataSource('Candles: loading');
        clearTradeLevels();
        closeStream();

        try {
            const range = explicitRange || buildRange(tf);
            const { candles, source } = await requestCandles(tf, range);
            renderCandles(candles);
            const last = state.candles[state.candles.length - 1];
            if (last) updateLivePrice(last.close);
            setDataSource(`Candles: ${source} (${state.candles.length})`);
            chart.timeScale().fitContent();
            if (options.trade) {
                drawTradeLevels(options.trade);
                setLiveStatus('Historical trade window');
            } else {
                openStream();
            }
        } catch (error) {
            console.error('[Strategy chart] candle load failed', error);
            setDataSource('Candles: unavailable');
            setLiveStatus('Price stream waiting');
        }
    }

    async function requestCandles(tf, range) {
        const url = new URL(cfg.candleEndpoint, window.location.origin);
        url.searchParams.set('interval', tf);
        url.searchParams.set('start', String(range.start));
        url.searchParams.set('end', String(range.end));
        url.searchParams.set('limit', String(range.limit));

        const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
        const payload = await response.json();
        let candles = Array.isArray(payload.candles) ? payload.candles : [];
        let source = payload.source || 'database';

        if (!candles.length) {
            candles = await fetchBrowserCandles(tf, range);
            source = candles.length ? `${cfg.strategy.exchange}_browser_api` : source;
        }

        return { candles, source };
    }

    function renderCandles(candles) {
        state.candles = normalizeCandles(candles);

        candleSeries.setData(state.candles);
        volumeSeries.setData(state.candles.map((c) => ({
            time: c.time,
            value: c.volume || 0,
            color: c.close >= c.open ? 'rgba(22,163,74,.24)' : 'rgba(225,29,72,.24)',
        })));

        candleSeries.setMarkers((cfg.markers || []).map((marker) => ({
            time: marker.time,
            position: marker.position,
            color: marker.color,
            shape: marker.shape,
            text: marker.text,
            id: String(marker.trade_id),
        })));
    }

    function buildRange(tf) {
        const end = Date.now();
        const seconds = intervalSeconds(tf);
        const limit = {
            '1m': 900,
            '5m': 1400,
            '10m': 1400,
            '30m': 2200,
            '1h': 3600,
            '4h': 2400,
            '1d': 1000,
            '1w': 700,
        }[tf] || 1200;
        let start = end - seconds * limit * 1000;

        const firstSignal = ['30m', '1h', '4h', '1d', '1w'].includes(tf)
            ? (cfg.trades || []).reduce((min, trade) => {
                if (!trade.entry_time) return min;
                return min === null ? trade.entry_time : Math.min(min, trade.entry_time);
            }, null)
            : null;

        if (firstSignal) {
            start = Math.min(start, (firstSignal - seconds * 20) * 1000);
        }

        return { start, end, limit };
    }

    function openStream() {
        const exchange = (cfg.strategy.exchange || 'binance').toLowerCase();
        const market = (cfg.strategy.market_type || 'future').toLowerCase();
        const symbol = (cfg.strategy.symbol || 'BTCUSDT').toLowerCase();

        try {
            if (exchange === 'bybit') {
                const channelType = market === 'spot' ? 'spot' : 'linear';
                const socket = new WebSocket(`wss://stream.bybit.com/v5/public/${channelType}`);
                state.socket = socket;
                socket.addEventListener('open', () => {
                    setLiveStatus('Live stream connected');
                    socket.send(JSON.stringify({ op: 'subscribe', args: [`publicTrade.${symbol.toUpperCase()}`] }));
                });
                socket.addEventListener('message', (event) => {
                    const payload = safeJson(event.data);
                    const tick = Array.isArray(payload.data) ? payload.data[0] : null;
                    const price = tick ? parseFloat(tick.p) : NaN;
                    if (Number.isFinite(price)) applyTick(price, Date.now());
                });
            } else {
                const host = market === 'future' ? 'wss://fstream.binance.com/ws' : 'wss://stream.binance.com:9443/ws';
                const socket = new WebSocket(`${host}/${symbol}@trade`);
                state.socket = socket;
                socket.addEventListener('open', () => setLiveStatus('Live stream connected'));
                socket.addEventListener('message', (event) => {
                    const tick = safeJson(event.data);
                    const price = parseFloat(tick.p);
                    const time = Number.isFinite(tick.T) ? tick.T : Date.now();
                    if (Number.isFinite(price)) applyTick(price, time);
                });
            }

            const activeSocket = state.socket;
            activeSocket.addEventListener('close', () => {
                if (state.socket === activeSocket) setLiveStatus('Live stream closed');
            });
            activeSocket.addEventListener('error', () => {
                if (state.socket === activeSocket) setLiveStatus('Live stream error');
            });
        } catch (error) {
            console.error('[Strategy chart] stream failed', error);
            setLiveStatus('Live stream unavailable');
        }
    }

    async function fetchBrowserCandles(tf, range) {
        const exchange = (cfg.strategy.exchange || 'binance').toLowerCase();
        const market = (cfg.strategy.market_type || 'future').toLowerCase();
        const symbol = (cfg.strategy.symbol || 'BTCUSDT').toUpperCase();
        const requestTf = tf === '10m' ? '1m' : tf;

        try {
            if (exchange === 'bybit') {
                const category = market === 'spot' ? 'spot' : 'linear';
                const url = new URL('https://api.bybit.com/v5/market/kline');
                url.searchParams.set('category', category);
                url.searchParams.set('symbol', symbol);
                url.searchParams.set('interval', bybitInterval(requestTf));
                url.searchParams.set('start', String(range.start));
                url.searchParams.set('end', String(range.end));
                url.searchParams.set('limit', String(Math.min(tf === '10m' ? 1000 : range.limit, 1000)));

                const response = await fetch(url);
                const payload = await response.json();
                const rows = payload && payload.result && Array.isArray(payload.result.list) ? payload.result.list : [];
                const candles = rows.map((row) => ({
                    time: Math.floor(Number(row[0]) / 1000),
                    open: Number(row[1]),
                    high: Number(row[2]),
                    low: Number(row[3]),
                    close: Number(row[4]),
                    volume: Number(row[5] || 0),
                })).sort((a, b) => a.time - b.time);

                return tf === '10m' ? aggregateTenMinute(candles) : candles;
            }

            const base = market === 'future' ? 'https://fapi.binance.com/fapi/v1/klines' : 'https://api.binance.com/api/v3/klines';
            const url = new URL(base);
            url.searchParams.set('symbol', symbol);
            url.searchParams.set('interval', requestTf);
            url.searchParams.set('startTime', String(range.start));
            url.searchParams.set('endTime', String(range.end));
            url.searchParams.set('limit', String(Math.min(tf === '10m' ? 1000 : range.limit, 1500)));

            const response = await fetch(url);
            const rows = await response.json();
            if (!Array.isArray(rows)) return [];

            const candles = rows.map((row) => ({
                time: Math.floor(Number(row[0]) / 1000),
                open: Number(row[1]),
                high: Number(row[2]),
                low: Number(row[3]),
                close: Number(row[4]),
                volume: Number(row[5] || 0),
            }));

            return tf === '10m' ? aggregateTenMinute(candles) : candles;
        } catch (error) {
            console.warn('[Strategy chart] browser candle fallback failed', error);
            return [];
        }
    }

    function aggregateTenMinute(candles) {
        const buckets = new Map();
        candles.forEach((candle) => {
            const bucket = Math.floor(candle.time / 600) * 600;
            if (!buckets.has(bucket)) {
                buckets.set(bucket, {
                    time: bucket,
                    open: candle.open,
                    high: candle.high,
                    low: candle.low,
                    close: candle.close,
                    volume: candle.volume || 0,
                });
                return;
            }

            const current = buckets.get(bucket);
            current.high = Math.max(current.high, candle.high);
            current.low = Math.min(current.low, candle.low);
            current.close = candle.close;
            current.volume += candle.volume || 0;
        });

        return Array.from(buckets.values()).sort((a, b) => a.time - b.time);
    }

    function closeStream() {
        if (state.socket) {
            state.socket.close();
            state.socket = null;
        }
    }

    function applyTick(price, eventMs) {
        const bucket = Math.floor(eventMs / 1000 / intervalSeconds(state.tf)) * intervalSeconds(state.tf);
        const last = state.candles[state.candles.length - 1];

        if (!last || bucket > last.time) {
            const open = last ? last.close : price;
            const next = { time: bucket, open, high: Math.max(open, price), low: Math.min(open, price), close: price, volume: 0 };
            state.candles.push(next);
            candleSeries.update(next);
            if (state.candles.length === 1) {
                setDataSource('Candles: live stream (building)');
            }
        } else {
            last.high = Math.max(last.high, price);
            last.low = Math.min(last.low, price);
            last.close = price;
            candleSeries.update(last);
        }

        updateLivePrice(price);
    }

    function handleChartClick(param) {
        if (!param || !param.time) return;

        let trade = null;
        if (param.hoveredObjectId) {
            trade = findTrade(param.hoveredObjectId);
        }

        if (!trade) {
            const margin = Math.max(intervalSeconds(state.tf) * 2, 1800);
            trade = (cfg.trades || []).find((candidate) => {
                const entryHit = Math.abs(candidate.entry_time - param.time) <= margin;
                const exitHit = candidate.exit_time && Math.abs(candidate.exit_time - param.time) <= margin;
                return entryHit || exitHit;
            });
        }

        if (trade) inspectTrade(trade);
    }

    async function inspectTrade(trade) {
        state.selectedTrade = trade;
        renderInspector(trade);
        if (!tradeIsInLoadedRange(trade)) {
            await loadChart(state.tf, buildTradeRange(trade, state.tf), { trade });
            return;
        }
        drawTradeLevels(trade);
    }

    function drawTradeLevels(trade) {
        clearTradeLevels();
        if (!state.candles.length) return;

        const first = state.candles[0].time;
        const last = state.candles[state.candles.length - 1].time;
        const makeLine = (color, title, value, style) => {
            const series = chart.addLineSeries({
                color,
                title,
                lineWidth: 1,
                lineStyle: style,
                lastValueVisible: true,
                priceLineVisible: false,
            });
            series.setData([{ time: first, value }, { time: last, value }]);
            return series;
        };

        state.entrySeries = makeLine(colors.blue, 'Entry', Number(trade.entry_price), LightweightCharts.LineStyle.Dotted);
        if (Number(trade.target_tp) > 0) {
            state.tpSeries = makeLine('#22c55e', 'TP', Number(trade.target_tp), LightweightCharts.LineStyle.Dashed);
        }
        if (Number(trade.target_sl) > 0) {
            state.slSeries = makeLine('#f43f5e', 'SL', Number(trade.target_sl), LightweightCharts.LineStyle.Dashed);
        }

        const from = Math.max(first, trade.entry_time - intervalSeconds(state.tf) * 8);
        const to = Math.min(last, (trade.exit_time || trade.entry_time + intervalSeconds(state.tf) * 80) + intervalSeconds(state.tf) * 8);
        chart.timeScale().setVisibleRange({ from, to });
    }

    function clearTradeLevels() {
        ['entrySeries', 'tpSeries', 'slSeries'].forEach((key) => {
            if (state[key]) {
                chart.removeSeries(state[key]);
                state[key] = null;
            }
        });
    }

    function renderInspector(trade) {
        const target = document.getElementById('tradeInspector');
        if (!target) return;

        const isLong = /long|buy/i.test(trade.side || '');
        const resultClass = trade.is_exited ? (trade.is_profit ? 'metric-positive' : 'metric-negative') : '';
        const pnl = trade.is_exited ? pnlPct(trade) : null;

        target.innerHTML = `
            <div class="inspector-row">
                <div class="inspector-label">Trade</div>
                <div class="inspector-value">${cfg.strategy.symbol} ${isLong ? 'LONG' : 'SHORT'}</div>
            </div>
            <div class="inspector-row">
                <div class="inspector-label">Entry</div>
                <div class="inspector-value">$${formatNumber(trade.entry_price)} at ${formatDate(trade.entry_time)}</div>
            </div>
            <div class="inspector-row">
                <div class="inspector-label">Targets</div>
                <div class="inspector-value"><span class="metric-positive">TP $${formatNumber(trade.target_tp)}</span> / <span class="metric-negative">SL $${formatNumber(trade.target_sl)}</span></div>
            </div>
            <div class="inspector-row">
                <div class="inspector-label">Exit</div>
                <div class="inspector-value">${trade.is_exited ? `$${formatNumber(trade.exit_price)} at ${formatDate(trade.exit_time)}` : 'Still active'}</div>
            </div>
            <div class="inspector-row">
                <div class="inspector-label">Result</div>
                <div class="inspector-value ${resultClass}">${trade.is_exited ? `${trade.is_profit ? 'Profit' : 'Loss'} ${pnl.toFixed(2)}%` : 'Active signal'}</div>
            </div>
            <div class="inspector-row">
                <div class="inspector-label">Leverage</div>
                <div class="inspector-value">${trade.leverage || 1}x</div>
            </div>
        `;
    }

    function wireTimeframeButtons() {
        document.querySelectorAll('.tf-button').forEach((button) => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.tf-button').forEach((item) => item.classList.remove('active'));
                button.classList.add('active');
                if (state.selectedTrade) {
                    loadChart(button.dataset.tf, buildTradeRange(state.selectedTrade, button.dataset.tf), { trade: state.selectedTrade });
                    return;
                }
                loadChart(button.dataset.tf);
            });
        });
    }

    function wireTableRows() {
        document.querySelectorAll('[data-trade-id]').forEach((row) => {
            row.addEventListener('click', () => {
                const trade = findTrade(row.dataset.tradeId);
                if (trade) inspectTrade(trade);
            });
        });
    }

    function findTrade(id) {
        return (cfg.trades || []).find((trade) => String(trade.id) === String(id));
    }

    function tradeIsInLoadedRange(trade) {
        if (!state.candles.length || !trade.entry_time) return false;
        const first = state.candles[0].time;
        const last = state.candles[state.candles.length - 1].time;
        const exitTime = trade.exit_time || trade.entry_time;

        return trade.entry_time >= first && exitTime <= last;
    }

    function buildTradeRange(trade, tf) {
        const seconds = intervalSeconds(tf);
        const entry = Number(trade.entry_time);
        const exit = Number(trade.exit_time || 0);
        const padding = Math.max(seconds * 80, 3600 * 6);
        let endSeconds = exit || entry + Math.max(seconds * 220, 86400);

        if (!exit) {
            endSeconds = Math.min(Math.floor(Date.now() / 1000), endSeconds);
        }

        const start = Math.max(0, (entry - padding) * 1000);
        const end = (endSeconds + padding) * 1000;
        const span = Math.max(1, Math.ceil((end - start) / 1000 / seconds));

        return {
            start,
            end,
            limit: Math.min(Math.max(span + 20, 300), 5000),
        };
    }

    function normalizeCandles(candles) {
        return candles
            .map((c) => ({
                time: Number(c.time),
                open: Number(c.open),
                high: Number(c.high),
                low: Number(c.low),
                close: Number(c.close),
                volume: Number(c.volume || 0),
            }))
            .filter((c) => Number.isFinite(c.time) && Number.isFinite(c.close))
            .sort((a, b) => a.time - b.time);
    }

    function intervalSeconds(tf) {
        return {
            '1m': 60,
            '5m': 300,
            '10m': 600,
            '30m': 1800,
            '1h': 3600,
            '4h': 14400,
            '1d': 86400,
            '1w': 604800,
        }[tf] || 3600;
    }

    function bybitInterval(tf) {
        return {
            '1m': '1',
            '5m': '5',
            '30m': '30',
            '1h': '60',
            '4h': '240',
            '1d': 'D',
            '1w': 'W',
        }[tf] || '60';
    }

    function pnlPct(trade) {
        const entry = Number(trade.entry_price);
        const exit = Number(trade.exit_price);
        if (!entry || !exit) return 0;
        const isLong = /long|buy/i.test(trade.side || '');
        const raw = isLong ? exit - entry : entry - exit;
        return (raw / entry) * 100 * (Number(trade.leverage) || 1);
    }

    function updateLivePrice(price) {
        const livePrice = document.getElementById('livePrice');
        if (livePrice) livePrice.textContent = `$${formatNumber(price)}`;
    }

    function setLiveStatus(text) {
        const liveStatus = document.getElementById('liveStatus');
        const streamLabel = document.getElementById('streamLabel');
        if (liveStatus) liveStatus.textContent = text;
        if (streamLabel) streamLabel.textContent = text;
    }

    function setDataSource(text) {
        const source = document.getElementById('dataSource');
        if (source) source.textContent = text;
    }

    function formatNumber(value) {
        const numeric = Number(value);
        if (!Number.isFinite(numeric)) return '-';
        return numeric.toLocaleString(undefined, {
            minimumFractionDigits: numeric >= 100 ? 2 : 4,
            maximumFractionDigits: numeric >= 100 ? 2 : 6,
        });
    }

    function formatDate(seconds) {
        if (!seconds) return '-';
        return new Date(seconds * 1000).toLocaleString(undefined, {
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function pricePrecision(symbol) {
        return /BTC/i.test(symbol || '') ? 1 : 2;
    }

    function minMove(symbol) {
        return /BTC/i.test(symbol || '') ? 0.1 : 0.01;
    }

    function safeJson(raw) {
        try {
            return JSON.parse(raw);
        } catch (_) {
            return {};
        }
    }

    function getCss(name, fallback) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
    }
})();
