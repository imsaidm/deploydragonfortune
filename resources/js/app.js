import "./bootstrap";
import "bootstrap/dist/js/bootstrap.bundle.min.js";

import jQuery from "jquery";
window.$ = window.jQuery = jQuery;

// Global kill-switch: disable all setInterval-based auto refresh across pages
if (typeof window !== "undefined" && !window.__AUTO_REFRESH_DISABLED__) {
    const originalSetInterval = window.setInterval.bind(window);
    const originalClearInterval = window.clearInterval.bind(window);

    window.__AUTO_REFRESH_DISABLED__ = true;
    window.__ORIGINAL_SET_INTERVAL__ = originalSetInterval;
    window.__ORIGINAL_CLEAR_INTERVAL__ = originalClearInterval;

    window.setInterval = (...args) => {
        // Allow setInterval in specific contexts (like Signal Analytics polling)
        const stackTrace = new Error().stack;
        if (stackTrace && (stackTrace.includes('signalAnalytics') || stackTrace.includes('quantConnect'))) {
            console.log('Allowing setInterval for Signal Analytics/QuantConnect');
            return originalSetInterval(...args);
        }
        console.warn("Auto refresh blocked (setInterval disabled)", { args });
        return -1;
    };
    window.clearInterval = (id) => {
        if (id && id !== -1) {
            return originalClearInterval(id);
        }
    };

    // Strip auto-refresh labels/toggles globally to avoid stale UI hints
    const hideEl = (el) => {
        if (!el) return;
        el.style.setProperty("display", "none", "important");
    };

    const matchesAutoRefreshText = (text) =>
        typeof text === "string" &&
        /auto[\s-]?\s*refresh/i.test(text.trim());

    const scrubElement = (el) => {
        if (!el || el.nodeType !== 1) return;

        // Hide if the element or its title mentions auto-refresh
        if (
            matchesAutoRefreshText(el.textContent || "") ||
            matchesAutoRefreshText(el.getAttribute?.("title") || "")
        ) {
            hideEl(el.closest("button, .badge, .form-check, .form-switch") || el);
            el.removeAttribute?.("title");
        }

        // Hide known classes/attrs
        if (
            el.matches(
                "[data-auto-refresh], .auto-refresh, .auto-refresh-toggle, [x-model='refreshEnabled'], [x-show*='refreshEnabled'], .pulse-dot, .badge"
            )
        ) {
            hideEl(el);
        }
    };

    const removeAutoRefreshArtifacts = (root = document.body) => {
        if (!root) return;
        // Scrub existing DOM
        root.querySelectorAll("*").forEach(scrubElement);
    };

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 3 && matchesAutoRefreshText(node.textContent)) {
                    const parent =
                        node.parentElement?.closest(
                            "button, .badge, .form-check, .form-switch"
                        ) || node.parentElement;
                    hideEl(parent);
                } else if (node.nodeType === 1) {
                    scrubElement(node);
                    if (node.querySelectorAll) {
                        node.querySelectorAll("*").forEach(scrubElement);
                    }
                }
            });
        });
    });

    if (document.readyState === "complete" || document.readyState === "interactive") {
        removeAutoRefreshArtifacts();
        observer.observe(document.body, { childList: true, subtree: true });
    } else {
        document.addEventListener(
            "DOMContentLoaded",
            () => {
                removeAutoRefreshArtifacts();
                observer.observe(document.body, { childList: true, subtree: true });
            },
            { once: true }
        );
    }
}

import { 
    Chart, 
    CategoryScale,
    LinearScale,
    LogarithmicScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    Filler,
    registerables 
} from "chart.js";
import { MatrixController, MatrixElement } from "chartjs-chart-matrix";

// Register Chart.js components
Chart.register(
    ...registerables, 
    MatrixController, 
    MatrixElement,
    CategoryScale,
    LinearScale,
    LogarithmicScale,
    PointElement,
    LineElement,
    BarElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

// Disable dataset clipping globally to avoid undefined metadata from custom controllers
Chart.defaults.dataset = Chart.defaults.dataset || {};
Chart.defaults.dataset.clip = false;

// Make Chart.js available globally
window.Chart = Chart;

// Alpine.js data and components
// Note: Livewire already loads Alpine on window; reuse that singleton.
const registerAlpineModules = () => {
    const Alpine = window.Alpine;
    if (!Alpine) {
        console.error(
            "Alpine.js is not available on window.Alpine; dashboard modules cannot be registered."
        );
        return;
    }
    const themePalette = {
        bullish: "#22c55e",
        bearish: "#ef4444",
        neutral: "#3b82f6",
        background: "#f9fafb",
        slate: "#1f2937",
    };

    const apiBaseMeta = document.querySelector('meta[name="api-base-url"]');
    const apiBaseUrl = (apiBaseMeta?.content || "").replace(/\/+$/, "");

    const buildApiUrl = (path) => {
        if (!path.startsWith("/")) {
            path = `/${path}`;
        }
        return `${apiBaseUrl}${path}`;
    };

    const fetchJson = async (path, params = {}, { signal } = {}) => {
        const url = new URL(buildApiUrl(path), window.location.origin);
        url.search = new URLSearchParams(
            Object.entries(params).reduce((acc, [key, value]) => {
                if (value === undefined || value === null || value === "") {
                    return acc;
                }
                acc[key] = value;
                return acc;
            }, {})
        ).toString();

        const response = await fetch(url.toString(), {
            headers: { Accept: "application/json" },
            signal,
        });
        if (!response.ok) {
            const message = await response
                .json()
                .catch(() => ({ error: response.statusText }));
            throw new Error(
                message?.error || `Request failed with status ${response.status}`
            );
        }
        return response.json();
    };

    const assetProfiles = {
        BTC: {
            label: "Bitcoin",
            totalSupply: 19.6,
            mvrv: { base: 2.15, amplitude: 0.55, noise: 0.25, min: 0.7 },
            zScore: { base: 1.05, amplitude: 1.4, noise: 0.55, min: -1.8 },
            reserveRisk: {
                base: 0.42,
                amplitude: 0.22,
                noise: 0.08,
                min: 0.04,
            },
            sopr: { base: 1.02, amplitude: 0.14, noise: 0.05, min: 0.82 },
            dormancy: { base: 86, amplitude: 24, noise: 12, min: 35 },
            cdd: { base: 145, amplitude: 48, noise: 24, min: 60 },
            lthBase: 0.68,
            realizedCap: { base: 465, amplitude: 34, noise: 12 },
            hodlBands: [14, 16, 17, 18, 20, 15],
            minerReserve: { base: 1.82, amplitude: 0.17, noise: 0.08 },
            puell: { base: 1.55, amplitude: 0.75, noise: 0.3, min: 0.4 },
            whaleHoldings: {
                "1k-10k": 3.4,
                "10k+": 2.1,
                Exchanges: 2.8,
            },
            flowIntensity: { inflow: 48, outflow: 52, volatility: 7 },
        },
        ETH: {
            label: "Ethereum",
            totalSupply: 120,
            mvrv: { base: 1.72, amplitude: 0.45, noise: 0.22, min: 0.6 },
            zScore: { base: 0.68, amplitude: 1.05, noise: 0.45, min: -2.1 },
            reserveRisk: {
                base: 0.36,
                amplitude: 0.18,
                noise: 0.07,
                min: 0.05,
            },
            sopr: { base: 1.01, amplitude: 0.11, noise: 0.05, min: 0.82 },
            dormancy: { base: 72, amplitude: 22, noise: 10, min: 28 },
            cdd: { base: 112, amplitude: 42, noise: 20, min: 45 },
            lthBase: 0.58,
            realizedCap: { base: 220, amplitude: 24, noise: 9 },
            hodlBands: [18, 21, 19, 17, 15, 10],
            minerReserve: { base: 2.95, amplitude: 0.22, noise: 0.09 },
            puell: { base: 1.32, amplitude: 0.58, noise: 0.26, min: 0.35 },
            whaleHoldings: {
                "1k-10k": 6.2,
                "10k+": 4.1,
                Exchanges: 10.4,
            },
            flowIntensity: { inflow: 54, outflow: 46, volatility: 9 },
        },
        SOL: {
            label: "Solana",
            totalSupply: 440,
            mvrv: { base: 1.95, amplitude: 0.72, noise: 0.32, min: 0.5 },
            zScore: { base: 0.85, amplitude: 1.65, noise: 0.55, min: -2.6 },
            reserveRisk: { base: 0.51, amplitude: 0.28, noise: 0.1, min: 0.08 },
            sopr: { base: 1.05, amplitude: 0.18, noise: 0.07, min: 0.75 },
            dormancy: { base: 58, amplitude: 28, noise: 12, min: 18 },
            cdd: { base: 96, amplitude: 54, noise: 25, min: 30 },
            lthBase: 0.44,
            realizedCap: { base: 92, amplitude: 18, noise: 7 },
            hodlBands: [26, 22, 18, 14, 12, 8],
            minerReserve: { base: 0.42, amplitude: 0.08, noise: 0.04 },
            puell: { base: 1.95, amplitude: 0.85, noise: 0.33, min: 0.5 },
            whaleHoldings: {
                "1k-10k": 11.6,
                "10k+": 5.4,
                Exchanges: 48,
            },
            flowIntensity: { inflow: 58, outflow: 42, volatility: 15 },
        },
        STABLECOINS: {
            label: "Stablecoins",
            totalSupply: 1400,
            mvrv: { base: 1, amplitude: 0.06, noise: 0.02, min: 0.92 },
            zScore: { base: 0.2, amplitude: 0.35, noise: 0.12, min: -0.6 },
            reserveRisk: {
                base: 0.26,
                amplitude: 0.12,
                noise: 0.05,
                min: 0.02,
            },
            sopr: { base: 1, amplitude: 0.04, noise: 0.01, min: 0.94 },
            dormancy: { base: 44, amplitude: 11, noise: 5, min: 20 },
            cdd: { base: 62, amplitude: 18, noise: 8, min: 25 },
            lthBase: 0.38,
            realizedCap: { base: 140, amplitude: 12, noise: 5 },
            hodlBands: [34, 22, 18, 12, 8, 6],
            minerReserve: { base: 0.18, amplitude: 0.03, noise: 0.01 },
            puell: { base: 0.85, amplitude: 0.22, noise: 0.08, min: 0.5 },
            whaleHoldings: {
                "1k-10k": 40,
                "10k+": 220,
                Exchanges: 320,
            },
            flowIntensity: { inflow: 61, outflow: 39, volatility: 12 },
        },
    };

    const cohortBands = ["< 1M", "1-3M", "3-6M", "6-12M", "1-2Y", "2Y+"];
    const hodlPalette = [
        "#0f172a",
        "#1e3a8a",
        "#2563eb",
        "#3b82f6",
        "#60a5fa",
        "#93c5fd",
    ];
    const whalePalette = ["#2563eb", "#9333ea", "#f97316"];
    const exchangeVenues = [
        "Binance",
        "Coinbase",
        "Kraken",
        "OKX",
        "Bybit",
        "Bitfinex",
    ];

    const rangeToDays = {
        "7D": 7,
        "30D": 30,
        "90D": 90,
        "180D": 180,
    };

    const clamp = (value, min = -Infinity, max = Infinity) =>
        Math.min(Math.max(value, min), max);

    const generateDateRange = (range) => {
        const days = rangeToDays[range] ?? 30;
        const today = new Date();
        const dates = [];
        for (let offset = days - 1; offset >= 0; offset -= 1) {
            const point = new Date(today);
            point.setDate(today.getDate() - offset);
            dates.push(point.toISOString().split("T")[0]);
        }
        return dates;
    };

    const generateSeries = (
        length,
        base,
        amplitude,
        noise = amplitude / 3,
        minimum = null
    ) => {
        return Array.from({ length }, (_, idx) => {
            const wave = Math.sin(
                (idx / Math.max(1, length - 1)) * Math.PI * 2
            );
            const variance = (Math.random() - 0.5) * noise * 2;
            const next = base + wave * amplitude + variance;
            return Number(
                clamp(next, minimum ?? -Infinity, Infinity).toFixed(2)
            );
        });
    };

    const createDistributionSeries = (length, baseDistribution) => {
        return Array.from({ length }, () => {
            const adjusted = baseDistribution.map((value) => {
                const variation = (Math.random() - 0.5) * 2.4;
                return Math.max(0.5, value + variation);
            });
            const sum = adjusted.reduce((acc, value) => acc + value, 0);
            return adjusted.map((value) =>
                Number(((value / sum) * 100).toFixed(2))
            );
        });
    };

    const buildHeatmapDataset = (dates, assetProfile) => {
        const data = [];
        dates.forEach((date, xIndex) => {
            exchangeVenues.forEach((venue, yIndex) => {
                const base =
                    (assetProfile.flowIntensity.volatility / 2) *
                    Math.sin((xIndex + yIndex) / 2);
                const directionalBias =
                    yIndex % 2 === 0
                        ? assetProfile.flowIntensity.outflow
                        : assetProfile.flowIntensity.inflow;
                const net =
                    (Math.random() - 0.5) *
                        assetProfile.flowIntensity.volatility +
                    (directionalBias - 50) * 1.1 +
                    base;
                data.push({
                    x: date,
                    y: venue,
                    v: Number(net.toFixed(2)),
                });
            });
        });
        return data;
    };

    const formatCompact = (value, decimals = 1) => {
        if (Math.abs(value) >= 1_000_000_000) {
            return `${(value / 1_000_000_000).toFixed(decimals)}B`;
        }
        if (Math.abs(value) >= 1_000_000) {
            return `${(value / 1_000_000).toFixed(decimals)}M`;
        }
        if (Math.abs(value) >= 1_000) {
            return `${(value / 1_000).toFixed(decimals)}K`;
        }
        return value.toFixed(decimals);
    };

    const hexToRgba = (hex, alpha = 1) => {
        let sanitized = hex.replace("#", "");
        if (sanitized.length === 3) {
            sanitized = sanitized
                .split("")
                .map((char) => char + char)
                .join("");
        }
        const bigint = parseInt(sanitized, 16);
        const r = (bigint >> 16) & 255;
        const g = (bigint >> 8) & 255;
        const b = bigint & 255;
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    };

    const createGradientFill = (ctx, color, alpha = 0.18) => {
        if (!ctx || !ctx.canvas) {
            return hexToRgba(color, alpha);
        }
        const gradient = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);
        gradient.addColorStop(0, hexToRgba(color, alpha));
        gradient.addColorStop(1, hexToRgba(color, 0));
        return gradient;
    };

    const getCanvasContext = (canvas, type = "2d") => {
        if (!canvas || typeof canvas.getContext !== "function") {
            return null;
        }
        return canvas.getContext(type);
    };

    Alpine.store("onchainMetrics", {
        assets: ["BTC"],
        ranges: ["7D", "30D", "90D", "180D"],
        theme: themePalette,
        selectedAsset: "BTC",
        selectedRange: "30D",
        loading: false,
        refreshTick: 0,

        setAsset(asset) {
            if (!asset || this.selectedAsset === asset || !this.assets.includes(asset)) {
                return;
            }
            this.selectedAsset = asset;
            this.triggerRefresh();
        },

        setRange(range) {
            if (this.selectedRange === range || !this.ranges.includes(range)) {
                return;
            }
            this.selectedRange = range;
            this.triggerRefresh();
        },

        triggerRefresh() {
            this.refreshTick += 1;
        },

        refresh() {
            if (this.loading) {
                return;
            }
            this.loading = true;
            setTimeout(() => {
                this.loading = false;
                this.triggerRefresh();
            }, 420);
        },

        assetLabel() {
            return this.selectedAsset;
        },

        assetSlug(asset = this.selectedAsset) {
            return (asset || "").toLowerCase();
        },

        rangeLimit(range = this.selectedRange) {
            return Math.min(rangeToDays[range] ?? 30, 365);
        },

        async fetchOnchainSeries(
            metric,
            { asset = this.selectedAsset, limit, valueKey } = {}
        ) {
            const params = {
                asset: this.assetSlug(asset),
                metric_type: metric,
                limit: limit ?? this.rangeLimit(),
            };
            try {
                const response = await fetchJson("/api/onchain/metrics", params);
                const rows = Array.isArray(response?.data) ? response.data : [];
                const key = valueKey || metric.replace(/-/g, "_");
                const byDate = new Map();
                rows.forEach((row) => {
                    const date = row?.date;
                    const values = row?.values || {};
                    const rawValue = values[key];
                    const value =
                        typeof rawValue === "number" ? rawValue : Number(rawValue);
                    if (!date || Number.isNaN(value)) {
                        return;
                    }
                    if (!byDate.has(date)) {
                        byDate.set(date, {
                            date,
                            value,
                            values,
                            raw: row,
                        });
                    }
                });
                return Array.from(byDate.values()).sort((a, b) =>
                    a.date.localeCompare(b.date)
                );
            } catch (error) {
                console.error("Failed to fetch on-chain metric", metric, error);
                return [];
            }
        },

        async fetchMarketSeries(
            metric,
            { asset = this.selectedAsset, limit } = {}
        ) {
            const params = {
                asset: this.assetSlug(asset),
                metric_type: metric,
                limit: limit ?? this.rangeLimit(),
            };
            try {
                const response = await fetchJson("/api/onchain/market-data", params);
                const rows = Array.isArray(response?.data) ? response.data : [];
                const aggregates = new Map();
                rows.forEach((row) => {
                    const date = row?.date;
                    const values = row?.values || {};
                    if (!date) {
                        return;
                    }
                    const bucket = aggregates.get(date) || {
                        date,
                        totals: {},
                        counts: {},
                    };
                    Object.entries(values).forEach(([field, raw]) => {
                        let numeric = raw;
                        if (typeof numeric !== "number") {
                            numeric = Number(numeric);
                        }
                        if (!Number.isFinite(numeric)) {
                            return;
                        }
                        bucket.totals[field] = (bucket.totals[field] || 0) + numeric;
                        bucket.counts[field] = (bucket.counts[field] || 0) + 1;
                    });
                    aggregates.set(date, bucket);
                });
                return Array.from(aggregates.values())
                    .map((entry) => {
                        const averaged = {};
                        Object.entries(entry.totals).forEach(([field, total]) => {
                            const count = entry.counts[field] || 1;
                            averaged[field] = total / count;
                        });
                        return {
                            date: entry.date,
                            values: averaged,
                        };
                    })
                    .sort((a, b) => a.date.localeCompare(b.date));
            } catch (error) {
                console.error("Failed to fetch market data", metric, error);
                return [];
            }
        },

        async fetchExchangeFlowSeries(
            metric,
            { asset = this.selectedAsset, limit, valueKey } = {}
        ) {
            const params = {
                asset: this.assetSlug(asset),
                metric_type: metric,
                limit: limit ?? this.rangeLimit(),
            };
            try {
                const response = await fetchJson(
                    "/api/onchain/exchange-flows",
                    params
                );
                const rows = Array.isArray(response?.data) ? response.data : [];
                const key =
                    valueKey ||
                    (metric.includes("flow")
                        ? `${metric.replace(/-/g, "_")}_total`
                        : `${metric}_total`);
                const dedup = new Map();
                rows.forEach((row) => {
                    const date = row?.date;
                    const exchange = row?.exchange || "unknown";
                    const values = row?.values || {};
                    let value = values[key];
                    if (typeof value !== "number") {
                        value = Number(value);
                    }
                    if (!date || Number.isNaN(value)) {
                        return;
                    }
                    const uniqueKey = `${exchange}:${date}`;
                    if (!dedup.has(uniqueKey)) {
                        dedup.set(uniqueKey, {
                            date,
                            exchange,
                            value,
                            values,
                            raw: row,
                        });
                    }
                });
                return Array.from(dedup.values()).sort((a, b) => {
                    if (a.date === b.date) {
                        return a.exchange.localeCompare(b.exchange);
                    }
                    return a.date.localeCompare(b.date);
                });
            } catch (error) {
                console.error("Failed to fetch exchange flow", metric, error);
                return [];
            }
        },

        async fetchStablecoinFlows({ limit, exchange } = {}) {
            const params = {
                limit: limit ?? this.rangeLimit(),
            };
            if (exchange) {
                params.exchange = exchange;
            }
            try {
                const response = await fetchJson(
                    "/api/onchain/stablecoin-flows",
                    params
                );
                const rows = Array.isArray(response?.data) ? response.data : [];
                return rows
                    .map((row) => {
                        const date = row?.date;
                        if (!date) {
                            return null;
                        }
                        return {
                            date,
                            exchange: row?.exchange || "unknown",
                            inflow: Number(row?.inflow_usd ?? 0),
                            outflow: Number(row?.outflow_usd ?? 0),
                            netflow: Number(row?.netflow_usd ?? 0),
                        };
                    })
                    .filter(Boolean);
            } catch (error) {
                console.error("Failed to fetch stablecoin flows", error);
                return [];
            }
        },

        formatNumber(value, decimals = 2) {
            if (value === null || value === undefined || Number.isNaN(value)) {
                return "--";
            }
            return Number(value).toLocaleString("en-US", {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            });
        },

        formatPercent(value, decimals = 2) {
            if (value === null || value === undefined || Number.isNaN(value)) {
                return "--";
            }
            return `${Number(value).toFixed(decimals)}%`;
        },

        formatCompact,
    });
    Alpine.data("valuationModule", () => ({
        store: Alpine.store("onchainMetrics"),
        charts: {
            mvrv: null,
            sopr: null,
            fundFlow: null,
        },
        series: {
            mvrv: [],
            sopr: [],
            fundFlow: [],
        },
        metrics: {
            mvrv: "--",
            mvrvDelta: "",
            sopr: "--",
            soprDelta: "",
            fundFlow: "--",
            fundFlowDelta: "",
        },
        insights: {
            mvrv: "",
            sopr: "",
            fundFlow: "",
        },
        state: {
            loading: false,
            error: null,
        },
        requestId: 0,
        init() {
            queueMicrotask(() => this.load());
            this.$watch(
                () => [this.store.selectedAsset, this.store.selectedRange],
                () => this.load()
            );
            this.$watch(
                () => this.store.refreshTick,
                () => this.load()
            );
        },
        async load() {
            const current = ++this.requestId;
            this.state.loading = true;
            this.state.error = null;
            try {
                const limit = this.store.rangeLimit();
                const [mvrv, sopr, fundFlow] = await Promise.all([
                    this.store.fetchOnchainSeries("mvrv", { limit }),
                    this.store.fetchOnchainSeries("sopr", {
                        limit,
                        valueKey: "sopr",
                    }),
                    this.store.fetchOnchainSeries("fund-flow-ratio", {
                        limit,
                        valueKey: "fund_flow_ratio",
                    }),
                ]);
                if (this.requestId !== current) {
                    return;
                }
                this.series = { mvrv, sopr, fundFlow };
                this.renderOrUpdateCharts();
                this.updateMetrics();
            } catch (error) {
                if (this.requestId !== current) {
                    return;
                }
                console.error("Failed to load valuation metrics", error);
                this.state.error = error.message || "Gagal memuat data.";
            } finally {
                if (this.requestId === current) {
                    this.state.loading = false;
                }
            }
        },
        renderOrUpdateCharts() {
            this.renderMvrvChart();
            this.renderSoprChart();
            this.renderFundFlowChart();
        },
        renderMvrvChart() {
            const canvas = this.$refs.mvrvChart;
            if (!canvas) {
                return;
            }
            const ctx = getCanvasContext(canvas);
            if (!ctx) {
                console.warn(
                    "MVRV chart canvas context is unavailable; skipping render."
                );
                return;
            }
            if (!this.charts.mvrv) {
                const zonePlugin = {
                    id: "mvrvZones",
                    beforeDraw: (chart) => {
                        const context = chart?.ctx ?? null;
                        const chartArea = chart?.chartArea ?? null;
                        const axis = chart?.scales?.mvrv ?? null;
                        if (!context || typeof context.save !== "function" || !chartArea || !axis) {
                            return;
                        }
                        if (typeof axis.getPixelForValue !== "function") {
                            return;
                        }
                        const sections = [
                            {
                                limit: Math.min(1, axis.max ?? 1),
                                color: hexToRgba(themePalette.bullish, 0.12),
                            },
                            {
                                limit: Math.min(3, axis.max ?? 3),
                                color: hexToRgba(themePalette.neutral, 0.08),
                            },
                            {
                                limit: axis.max ?? 4,
                                color: hexToRgba(themePalette.bearish, 0.08),
                            },
                        ];
                        let start = axis.getPixelForValue(axis.min ?? 0);
                        sections.forEach((section) => {
                            const top = axis.getPixelForValue(section.limit);
                            context.save();
                            context.fillStyle = section.color;
                            context.fillRect(
                                chartArea.left,
                                Math.min(start, top),
                                chartArea.right - chartArea.left,
                                Math.abs(start - top)
                            );
                            context.restore();
                            start = top;
                        });
                    },
                };
                this.charts.mvrv = new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: "MVRV Ratio",
                                data: [],
                                borderColor: themePalette.bullish,
                                backgroundColor: createGradientFill(
                                    ctx,
                                    themePalette.bullish,
                                    0.22
                                ),
                                borderWidth: 2,
                                tension: 0.35,
                                fill: true,
                                pointRadius: 0,
                                yAxisID: "mvrv",
                            },
                        ],
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        interaction: { mode: "index", intersect: false },
                        animation: { duration: 520, easing: "easeOutQuart" },
                        plugins: {
                            legend: {
                                display: true,
                                align: "start",
                                labels: { usePointStyle: true, boxWidth: 10 },
                            },
                            tooltip: {
                                callbacks: {
                                    label: (context) => {
                                        const label = context.dataset.label ?? "";
                                        return `${label}: ${context.parsed.y.toFixed(2)}`;
                                    },
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                            },
                            mvrv: {
                                position: "left",
                                title: { display: true, text: "MVRV" },
                                grid: {
                                    color: hexToRgba(themePalette.slate, 0.08),
                                },
                            },
                        },
                    },
                    plugins: [zonePlugin],
                });
            }
            const labels = this.series.mvrv.map((item) => item.date);
            this.charts.mvrv.data.labels = labels;
            this.charts.mvrv.data.datasets[0].data = this.series.mvrv.map(
                (item) => item.value
            );
            this.charts.mvrv.update();
        },
        renderSoprChart() {
            const canvas = this.$refs.reserveChart;
            if (!canvas) {
                return;
            }
            const ctx = getCanvasContext(canvas);
            if (!ctx) {
                console.warn(
                    "SOPR chart canvas context is unavailable; skipping render."
                );
                return;
            }
            if (!this.charts.sopr) {
                this.charts.sopr = new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: "SOPR",
                                data: [],
                                borderColor: themePalette.bearish,
                                borderWidth: 2,
                                tension: 0.3,
                                fill: false,
                                pointRadius: 0,
                            },
                        ],
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        interaction: { mode: "index", intersect: false },
                        animation: { duration: 520, easing: "easeOutQuart" },
                        plugins: {
                            legend: { display: true, align: "start" },
                            tooltip: {
                                callbacks: {
                                    label: (context) =>
                                        `SOPR: ${context.parsed.y.toFixed(3)}`,
                                },
                            },
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                title: { display: true, text: "SOPR" },
                                grid: {
                                    color: hexToRgba(themePalette.slate, 0.08),
                                },
                            },
                        },
                    },
                });
            }
            const labels = this.series.sopr.map((item) => item.date);
            this.charts.sopr.data.labels = labels;
            this.charts.sopr.data.datasets[0].data = this.series.sopr.map(
                (item) => item.value
            );
            this.charts.sopr.update();
        },
        renderFundFlowChart() {
            const canvas = this.$refs.cddChart;
            if (!canvas) {
                return;
            }
            const ctx = getCanvasContext(canvas);
            if (!ctx) {
                console.warn(
                    "Fund Flow chart canvas context is unavailable; skipping render."
                );
                return;
            }
            if (!this.charts.fundFlow) {
                this.charts.fundFlow = new Chart(ctx, {
                    type: "bar",
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: "Fund Flow Ratio",
                                data: [],
                                backgroundColor: hexToRgba(
                                    themePalette.neutral,
                                    0.5
                                ),
                                borderColor: themePalette.neutral,
                                borderWidth: 1,
                            },
                        ],
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (context) =>
                                        `Fund Flow: ${context.parsed.y.toFixed(2)}`,
                                },
                            },
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: hexToRgba(themePalette.slate, 0.08),
                                },
                            },
                        },
                    },
                });
            }
            const labels = this.series.fundFlow.map((item) => item.date);
            this.charts.fundFlow.data.labels = labels;
            this.charts.fundFlow.data.datasets[0].data =
                this.series.fundFlow.map((item) => item.value);
            this.charts.fundFlow.update();
        },
        computeChange(series) {
            if (!Array.isArray(series) || series.length === 0) {
                return {
                    latest: null,
                    previous: null,
                    change: null,
                    changePct: null,
                };
            }
            const latest = series[series.length - 1]?.value ?? null;
            const previous =
                series.length > 1 ? series[series.length - 2]?.value ?? null : null;
            const change =
                latest !== null && previous !== null ? latest - previous : null;
            const changePct =
                change !== null && previous !== 0 && previous !== null
                    ? (change / previous) * 100
                    : null;
            return { latest, previous, change, changePct };
        },
        updateMetrics() {
            const mvrvStats = this.computeChange(this.series.mvrv);
            const soprStats = this.computeChange(this.series.sopr);
            const fundFlowStats = this.computeChange(this.series.fundFlow);

            this.metrics.mvrv = this.store.formatNumber(mvrvStats.latest, 2);
            this.metrics.mvrvDelta =
                mvrvStats.change !== null
                    ? `${mvrvStats.change >= 0 ? "+" : ""}${mvrvStats.change.toFixed(3)}`
                    : "--";

            this.metrics.sopr = this.store.formatNumber(soprStats.latest, 3);
            this.metrics.soprDelta =
                soprStats.changePct !== null
                    ? `${soprStats.changePct >= 0 ? "+" : ""}${soprStats.changePct.toFixed(2)}%`
                    : "--";

            this.metrics.fundFlow = this.store.formatNumber(
                fundFlowStats.latest,
                2
            );
            this.metrics.fundFlowDelta =
                fundFlowStats.changePct !== null
                    ? `${fundFlowStats.changePct >= 0 ? "+" : ""}${fundFlowStats.changePct.toFixed(2)}%`
                    : "--";

            this.insights.mvrv =
                mvrvStats.latest === null
                    ? "Data belum tersedia."
                    : mvrvStats.latest >= 3
                    ? "MVRV berada di zona panas. Waspadai risiko koreksi."
                    : mvrvStats.latest <= 1
                    ? "MVRV mendekati undervalued historis. Perhatikan peluang akumulasi."
                    : "MVRV berada di zona netral.";

            this.insights.sopr =
                soprStats.latest === null
                    ? "Menunggu data SOPR terbaru."
                    : soprStats.latest > 1
                    ? "SOPR > 1 menunjukkan dominasi take-profit."
                    : "SOPR < 1 biasanya menandakan fase kapitulasai dan potensi akumulasi.";

            this.insights.fundFlow =
                fundFlowStats.latest === null
                    ? "Fund flow ratio belum tersedia."
                    : fundFlowStats.latest > 1
                    ? "Fund flow ratio menunjukkan tekanan inflow ke bursa."
                    : "Fund flow ratio rendah, tekanan jual relatif ringan.";
        },
    }));


    Alpine.data("supplyModule", () => ({
        store: Alpine.store("onchainMetrics"),
        charts: {
            market: null,
            secondary: null,
            thermo: null,
        },
        series: [],
        metrics: {
            marketCap: "--",
            marketCapChange: "",
            realizedCap: "--",
            realizedCapChange: "",
            thermoCap: "--",
        },
        state: {
            loading: false,
            error: null,
        },
        requestId: 0,
        init() {
            queueMicrotask(() => this.load());
            this.$watch(
                () => [this.store.selectedAsset, this.store.selectedRange],
                () => this.load()
            );
            this.$watch(
                () => this.store.refreshTick,
                () => this.load()
            );
        },
        async load() {
            const current = ++this.requestId;
            this.state.loading = true;
            this.state.error = null;
            try {
                const limit = this.store.rangeLimit();
                const series = await this.store.fetchMarketSeries(
                    "capitalization",
                    { limit }
                );
                if (this.requestId !== current) {
                    return;
                }
                this.series = series;
                this.renderMarketChart();
                this.renderSecondaryChart();
                this.renderThermoChart();
                this.updateMetrics();
            } catch (error) {
                if (this.requestId !== current) {
                    return;
                }
                console.error("Failed to load market capitalization data", error);
                this.state.error = error.message || "Gagal memuat data.";
            } finally {
                if (this.requestId === current) {
                    this.state.loading = false;
                }
            }
        },
        numericSeries(valueKey) {
            return this.series
                .map(({ date, values }) => {
                    const raw = values?.[valueKey];
                    if (typeof raw !== "number" || Number.isNaN(raw)) {
                        return null;
                    }
                    return { date, value: raw };
                })
                .filter(Boolean);
        },
        renderMarketChart() {
            const canvas = this.$refs.supplyChart;
            if (!canvas) {
                return;
            }
            const ctx = getCanvasContext(canvas);
            if (!ctx) {
                console.warn(
                    "Market cap chart canvas context is unavailable; skipping render."
                );
                return;
            }
            if (!this.charts.market) {
                this.charts.market = new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: "Market Cap (Bn USD)",
                                data: [],
                                borderColor: themePalette.neutral,
                                backgroundColor: createGradientFill(
                                    ctx,
                                    themePalette.neutral,
                                    0.18
                                ),
                                tension: 0.35,
                                borderWidth: 2,
                                fill: true,
                                pointRadius: 0,
                            },
                            {
                                label: "Realized Cap (Bn USD)",
                                data: [],
                                borderColor: themePalette.bullish,
                                borderWidth: 2,
                                tension: 0.35,
                                fill: false,
                                pointRadius: 0,
                            },
                        ],
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        interaction: { mode: "index", intersect: false },
                        plugins: {
                            legend: { display: true, align: "start" },
                            tooltip: {
                                callbacks: {
                                    label: (context) =>
                                        `${context.dataset.label}: $${context.parsed.y.toFixed(2)}B`,
                                },
                            },
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                title: { display: true, text: "USD (Billions)" },
                                grid: { color: hexToRgba(themePalette.slate, 0.08) },
                            },
                        },
                    },
                });
            }
            const market = this.numericSeries("market_cap");
            const realized = this.numericSeries("realized_cap");
            const labels = market.map((item) => item.date);
            const toBn = (value) => value / 1_000_000_000;
            this.charts.market.data.labels = labels;
            this.charts.market.data.datasets[0].data = market.map((item) =>
                toBn(item.value)
            );
            this.charts.market.data.datasets[1].data = realized.map((item) =>
                toBn(item.value)
            );
            this.charts.market.update();
        },
        renderSecondaryChart() {
            const canvas = this.$refs.realizedCapChart;
            if (!canvas) {
                return;
            }
            const ctx = getCanvasContext(canvas);
            if (!ctx) {
                console.warn(
                    "Secondary market chart canvas context is unavailable; skipping render."
                );
                return;
            }
            if (!this.charts.secondary) {
                this.charts.secondary = new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: "Delta Cap (Bn USD)",
                                data: [],
                                borderColor: themePalette.bearish,
                                borderWidth: 2,
                                tension: 0.35,
                                fill: false,
                                pointRadius: 0,
                            },
                            {
                                label: "Average Cap (Bn USD)",
                                data: [],
                                borderColor: "#8b5cf6",
                                borderWidth: 2,
                                borderDash: [6, 4],
                                tension: 0.35,
                                fill: false,
                                pointRadius: 0,
                            },
                        ],
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        interaction: { mode: "index", intersect: false },
                        plugins: {
                            legend: { display: true, align: "start" },
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                title: { display: true, text: "USD (Billions)" },
                                grid: { color: hexToRgba(themePalette.slate, 0.08) },
                            },
                        },
                    },
                });
            }
            const delta = this.numericSeries("delta_cap");
            const average = this.numericSeries("average_cap");
            const labels = delta.map((item) => item.date);
            const toBn = (value) => value / 1_000_000_000;
            this.charts.secondary.data.labels = labels;
            this.charts.secondary.data.datasets[0].data = delta.map((item) =>
                toBn(item.value)
            );
            this.charts.secondary.data.datasets[1].data = average.map((item) =>
                toBn(item.value)
            );
            this.charts.secondary.update();
        },
        renderThermoChart() {
            const canvas = this.$refs.hodlChart;
            if (!canvas) {
                return;
            }
            const ctx = getCanvasContext(canvas);
            if (!ctx) {
                console.warn(
                    "Thermo cap chart canvas context is unavailable; skipping render."
                );
                return;
            }
            if (!this.charts.thermo) {
                this.charts.thermo = new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: "Thermo Cap (Bn USD)",
                                data: [],
                                borderColor: "#f97316",
                                borderWidth: 2,
                                tension: 0.35,
                                fill: false,
                                pointRadius: 0,
                            },
                        ],
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        interaction: { mode: "index", intersect: false },
                        plugins: {
                            legend: { display: true, align: "start" },
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                title: { display: true, text: "USD (Billions)" },
                                grid: { color: hexToRgba(themePalette.slate, 0.08) },
                            },
                        },
                    },
                });
            }
            const thermo = this.numericSeries("thermo_cap");
            const toBn = (value) => value / 1_000_000_000;
            this.charts.thermo.data.labels = thermo.map((item) => item.date);
            this.charts.thermo.data.datasets[0].data = thermo.map((item) =>
                toBn(item.value)
            );
            this.charts.thermo.update();
        },
        computeChange(series) {
            if (!series.length) {
                return { latest: null, change: null, changePct: null };
            }
            const latest = series[series.length - 1].value;
            const previous =
                series.length > 1 ? series[series.length - 2].value : null;
            const change =
                previous !== null ? latest - previous : null;
            const changePct =
                change !== null && previous
                    ? (change / previous) * 100
                    : null;
            return { latest, change, changePct };
        },
        updateMetrics() {
            const marketStats = this.computeChange(
                this.numericSeries("market_cap")
            );
            const realizedStats = this.computeChange(
                this.numericSeries("realized_cap")
            );
            const thermoStats = this.computeChange(
                this.numericSeries("thermo_cap")
            );
            const toBn = (value) =>
                value === null ? null : value / 1_000_000_000;

            this.metrics.marketCap =
                marketStats.latest === null
                    ? "--"
                    : `$${toBn(marketStats.latest).toFixed(2)}B`;
            this.metrics.marketCapChange =
                marketStats.changePct === null
                    ? ""
                    : `${marketStats.changePct >= 0 ? "+" : ""}${marketStats.changePct.toFixed(2)}%`; 

            this.metrics.realizedCap =
                realizedStats.latest === null
                    ? "--"
                    : `$${toBn(realizedStats.latest).toFixed(2)}B`;
            this.metrics.realizedCapChange =
                realizedStats.changePct === null
                    ? ""
                    : `${realizedStats.changePct >= 0 ? "+" : ""}${realizedStats.changePct.toFixed(2)}%`;

            this.metrics.thermoCap =
                thermoStats.latest === null
                    ? "--"
                    : `$${toBn(thermoStats.latest).toFixed(2)}B`;
        },
    }));


    Alpine.data("flowsModule", () => ({
        store: Alpine.store("onchainMetrics"),
        charts: {
            netflow: null,
            flowSplit: null,
            exchangeBar: null,
        },
        series: {
            netflow: [],
            inflow: [],
            outflow: [],
            exchanges: { dates: [], datasets: [] },
            stablecoin: {
                inflow: [],
                outflow: [],
                netflow: [],
            },
        },
        exchangeRows: [],
        metrics: {
            btcNetflow: "--",
            btcNetflowTone: "",
            stablecoinNet: "--",
            stablecoinTone: "",
            dominantVenue: "--",
        },
        state: {
            loading: false,
            error: null,
        },
        requestId: 0,
        init() {
            queueMicrotask(() => this.load());
            this.$watch(
                () => [this.store.selectedAsset, this.store.selectedRange],
                () => this.load()
            );
            this.$watch(
                () => this.store.refreshTick,
                () => this.load()
            );
        },
        async load() {
            const current = ++this.requestId;
            this.state.loading = true;
            this.state.error = null;
            try {
                const limit = this.store.rangeLimit();
                const [netflowRaw, inflowRaw, outflowRaw, stablecoinRaw] = await Promise.all([
                    this.store.fetchExchangeFlowSeries("netflow", {
                        limit,
                        valueKey: "netflow_total",
                    }),
                    this.store.fetchExchangeFlowSeries("inflow", {
                        limit,
                        valueKey: "inflow_total",
                    }),
                    this.store.fetchExchangeFlowSeries("outflow", {
                        limit,
                        valueKey: "outflow_total",
                    }),
                    this.store.fetchStablecoinFlows({
                        limit,
                    }),
                ]);
                if (this.requestId !== current) {
                    return;
                }
                this.series.netflow = this.aggregateByDate(netflowRaw);
                this.series.inflow = this.aggregateByDate(inflowRaw);
                this.series.outflow = this.aggregateByDate(outflowRaw);
                this.series.exchanges = this.buildExchangeDatasets(netflowRaw);
                this.series.stablecoin = this.aggregateStablecoinFlows(stablecoinRaw);
                this.exchangeRows = this.buildExchangeRows(netflowRaw);
                this.renderNetflowChart();
                this.renderFlowSplitChart();
                this.renderExchangeBarChart();
                this.updateMetrics();
            } catch (error) {
                if (this.requestId !== current) {
                    return;
                }
                console.error("Failed to load exchange flow data", error);
                this.state.error = error.message || "Gagal memuat data.";
            } finally {
                if (this.requestId === current) {
                    this.state.loading = false;
                }
            }
        },
        aggregateByDate(records) {
            const totals = new Map();
            records.forEach(({ date, value }) => {
                if (!date || typeof value !== "number" || Number.isNaN(value)) {
                    return;
                }
                totals.set(date, (totals.get(date) || 0) + value);
            });
            return Array.from(totals.entries())
                .sort((a, b) => a[0].localeCompare(b[0]))
                .map(([date, value]) => ({ date, value }));
        },

        aggregateStablecoinFlows(records) {
            const totals = new Map();
            records.forEach(({ date, inflow, outflow, netflow }) => {
                if (!date) {
                    return;
                }
                const bucket =
                    totals.get(date) || { inflow: 0, outflow: 0, netflow: 0 };
                if (typeof inflow === "number" && !Number.isNaN(inflow)) {
                    bucket.inflow += inflow;
                }
                if (typeof outflow === "number" && !Number.isNaN(outflow)) {
                    bucket.outflow += outflow;
                }
                if (typeof netflow === "number" && !Number.isNaN(netflow)) {
                    bucket.netflow += netflow;
                } else {
                    bucket.netflow +=
                        (typeof inflow === "number" ? inflow : 0) -
                        (typeof outflow === "number" ? outflow : 0);
                }
                totals.set(date, bucket);
            });
            const ordered = Array.from(totals.entries()).sort((a, b) =>
                a[0].localeCompare(b[0])
            );
            return {
                inflow: ordered.map(([date, values]) => ({
                    date,
                    value: values.inflow,
                })),
                outflow: ordered.map(([date, values]) => ({
                    date,
                    value: values.outflow,
                })),
                netflow: ordered.map(([date, values]) => ({
                    date,
                    value: values.netflow,
                })),
            };
        },

        formatUsd(value, decimals = 2) {
            if (value === null || value === undefined || Number.isNaN(value)) {
                return "--";
            }
            return `$${this.store.formatCompact(value, decimals)}`;
        },
        buildExchangeDatasets(records) {
            const exchangeMap = new Map();
            const totals = new Map();
            const dateSet = new Set();
            records.forEach(({ date, exchange, value }) => {
                if (!date || !exchange || typeof value !== "number") {
                    return;
                }
                dateSet.add(date);
                const perDate = exchangeMap.get(exchange) || new Map();
                perDate.set(date, (perDate.get(date) || 0) + value);
                exchangeMap.set(exchange, perDate);
                totals.set(
                    exchange,
                    (totals.get(exchange) || 0) + Math.abs(value)
                );
            });
            const dates = Array.from(dateSet).sort((a, b) => a.localeCompare(b));
            const topExchanges = Array.from(totals.entries())
                .sort((a, b) => b[1] - a[1])
                .slice(0, 5)
                .map(([name]) => name);
            const palette = [
                "#22c55e",
                "#ef4444",
                "#3b82f6",
                "#f97316",
                "#8b5cf6",
            ];
            const datasets = topExchanges.map((exchange, idx) => {
                const perDate = exchangeMap.get(exchange) || new Map();
                return {
                    label: exchange,
                    data: dates.map((date) => perDate.get(date) || 0),
                    backgroundColor: hexToRgba(palette[idx % palette.length], 0.7),
                };
            });
            return { dates, datasets };
        },
        buildExchangeRows(records) {
            if (!records.length) {
                return [];
            }
            const latestDate = records.reduce((acc, cur) =>
                acc > cur.date ? acc : cur.date
            , records[0].date);
            const perExchange = new Map();
            records.forEach(({ date, exchange, value }) => {
                if (date !== latestDate || !exchange || typeof value !== "number") {
                    return;
                }
                perExchange.set(exchange, (perExchange.get(exchange) || 0) + value);
            });
            return Array.from(perExchange.entries())
                .map(([venue, netflow]) => ({
                    venue,
                    netflow,
                    balance: Math.abs(netflow),
                }))
                .sort((a, b) => Math.abs(b.netflow) - Math.abs(a.netflow));
        },
        renderNetflowChart() {
            const canvas = this.$refs.netflowChart;
            if (!canvas) {
                return;
            }
            const ctx = getCanvasContext(canvas);
            if (!ctx) {
                console.warn(
                    "Netflow chart canvas context is unavailable; skipping render."
                );
                return;
            }
            if (!this.charts.netflow) {
                this.charts.netflow = new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: "Aggregated Netflow",
                                data: [],
                                borderColor: themePalette.bearish,
                                backgroundColor: createGradientFill(
                                    ctx,
                                    themePalette.bearish,
                                    0.18
                                ),
                                borderWidth: 2,
                                tension: 0.35,
                                fill: true,
                                pointRadius: 0,
                            },
                        ],
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        interaction: { mode: "index", intersect: false },
                        plugins: {
                            legend: { display: true, align: "start" },
                            tooltip: {
                                callbacks: {
                                    label: (context) =>
                                        `Netflow: ${context.parsed.y.toFixed(2)} BTC`,
                                },
                            },
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                title: { display: true, text: "BTC" },
                                grid: {
                                    color: hexToRgba(themePalette.slate, 0.08),
                                },
                            },
                        },
                    },
                });
            }
            this.charts.netflow.data.labels = this.series.netflow.map(
                (item) => item.date
            );
            this.charts.netflow.data.datasets[0].data = this.series.netflow.map(
                (item) => item.value
            );
            this.charts.netflow.update();
        },
        renderFlowSplitChart() {
            const canvas = this.$refs.stablecoinChart;
            if (!canvas) {
                return;
            }
            const ctx = getCanvasContext(canvas);
            if (!ctx) {
                console.warn(
                    "Stablecoin flow chart canvas context is unavailable; skipping render."
                );
                return;
            }
            if (!this.charts.flowSplit) {
                this.charts.flowSplit = new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: "Stablecoin Inflow (USD)",
                                data: [],
                                borderColor: themePalette.bearish,
                                tension: 0.3,
                                fill: false,
                                pointRadius: 0,
                            },
                            {
                                label: "Stablecoin Outflow (USD)",
                                data: [],
                                borderColor: themePalette.bullish,
                                tension: 0.3,
                                fill: false,
                                pointRadius: 0,
                            },
                        ],
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        interaction: { mode: "index", intersect: false },
                        plugins: {
                            legend: { display: true, align: "start" },
                            tooltip: {
                                callbacks: {
                                    label: (context) => {
                                        const value = context.parsed.y;
                                        return `${context.dataset.label}: $${value.toLocaleString("en-US", {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2,
                                        })}`;
                                    },
                                },
                            },
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                title: { display: true, text: "USD" },
                                ticks: {
                                    callback: (value) =>
                                        `$${(value / 1_000_000_000).toFixed(2)}B`,
                                },
                                grid: {
                                    color: hexToRgba(themePalette.slate, 0.08),
                                },
                            },
                        },
                    },
                });
            }
            const stablecoin = this.series.stablecoin;
            const labels = stablecoin.inflow.map((item) => item.date);
            this.charts.flowSplit.data.labels = labels;
            this.charts.flowSplit.data.datasets[0].data = stablecoin.inflow.map(
                (item) => item.value
            );
            this.charts.flowSplit.data.datasets[1].data = stablecoin.outflow.map(
                (item) => item.value
            );
            this.charts.flowSplit.update();
        },
        renderExchangeBarChart() {
            const canvas = this.$refs.heatmapChart;
            if (!canvas) {
                return;
            }
            const ctx = getCanvasContext(canvas);
            if (!ctx) {
                console.warn(
                    "Exchange comparison chart canvas context is unavailable; skipping render."
                );
                return;
            }
            if (!this.charts.exchangeBar) {
                this.charts.exchangeBar = new Chart(ctx, {
                    type: "bar",
                    data: {
                        labels: [],
                        datasets: [],
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        scales: {
                            x: {
                                stacked: true,
                                grid: { display: false },
                            },
                            y: {
                                stacked: true,
                                title: { display: true, text: "Netflow (BTC)" },
                                grid: {
                                    color: hexToRgba(themePalette.slate, 0.08),
                                },
                            },
                        },
                        plugins: {
                            legend: { display: true, align: "start" },
                        },
                    },
                });
            }
            this.charts.exchangeBar.data.labels = this.series.exchanges.dates;
            this.charts.exchangeBar.data.datasets = this.series.exchanges.datasets;
            this.charts.exchangeBar.update();
        },
        updateMetrics() {
            const btcSeries = this.series.netflow;
            if (!btcSeries.length) {
                this.metrics.btcNetflow = "--";
                this.metrics.btcNetflowTone = "Belum ada data netflow.";
            } else {
                const latest = btcSeries.at(-1);
                const previous =
                    btcSeries.length > 1 ? btcSeries.at(-2) : null;
                this.metrics.btcNetflow = `${latest.value >= 0 ? "+" : ""}${latest.value.toFixed(2)} BTC`;
                const change = previous ? latest.value - previous.value : null;
                this.metrics.btcNetflowTone =
                    change === null
                        ? ""
                        : change > 0
                        ? "Tekanan inflow meningkat dibanding periode sebelumnya."
                        : change < 0
                        ? "Inflow mereda dibanding periode sebelumnya."
                        : "Netflow relatif stabil.";
            }

            this.metrics.dominantVenue = this.exchangeRows[0]?.venue ?? "--";

            const stablecoinSeries = this.series.stablecoin?.netflow ?? [];
            if (!stablecoinSeries.length) {
                this.metrics.stablecoinNet = "--";
                this.metrics.stablecoinTone = "Data stablecoin belum tersedia.";
                return;
            }

            const latestStable = stablecoinSeries.at(-1)?.value ?? null;
            const previousStable =
                stablecoinSeries.length > 1
                    ? stablecoinSeries.at(-2)?.value ?? null
                    : null;

            this.metrics.stablecoinNet = this.formatUsd(latestStable, 2);
            if (previousStable === null) {
                this.metrics.stablecoinTone = "";
                return;
            }

            const delta = latestStable - previousStable;
            this.metrics.stablecoinTone =
                delta > 0
                    ? "Stablecoin inflow ke exchange meningkat."
                    : delta < 0
                    ? "Stablecoin cenderung keluar dari exchange."
                    : "Stablecoin netflow relatif stabil.";
        },
    }));


    Alpine.data("minersWhalesModule", () => ({
        store: Alpine.store("onchainMetrics"),
        charts: {
            puell: null,
        },
        series: [],
        metrics: {
            puellMultiple: "--",
            puellChange: "",
        },
        state: {
            loading: false,
            error: null,
        },
        requestId: 0,
        init() {
            queueMicrotask(() => this.load());
            this.$watch(
                () => [this.store.selectedAsset, this.store.selectedRange],
                () => this.load()
            );
            this.$watch(
                () => this.store.refreshTick,
                () => this.load()
            );
        },
        async load() {
            const current = ++this.requestId;
            this.state.loading = true;
            this.state.error = null;
            try {
                const limit = this.store.rangeLimit();
                const series = await this.store.fetchOnchainSeries(
                    "puell-multiple",
                    { limit, valueKey: "puell_multiple" }
                );
                if (this.requestId !== current) {
                    return;
                }
                this.series = series;
                this.renderPuellChart();
                this.updateMetrics();
            } catch (error) {
                if (this.requestId !== current) {
                    return;
                }
                console.error("Failed to load miner metrics", error);
                this.state.error = error.message || "Gagal memuat data.";
            } finally {
                if (this.requestId === current) {
                    this.state.loading = false;
                }
            }
        },
        renderPuellChart() {
            const canvas = this.$refs.minerReserveChart;
            if (!canvas) {
                return;
            }
            const ctx = getCanvasContext(canvas);
            if (!ctx) {
                console.warn(
                    "Puell Multiple chart canvas context is unavailable; skipping render."
                );
                return;
            }
            if (!this.charts.puell) {
                this.charts.puell = new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: "Puell Multiple",
                                data: [],
                                borderColor: themePalette.bullish,
                                borderWidth: 2,
                                tension: 0.35,
                                fill: false,
                                pointRadius: 0,
                            },
                        ],
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        interaction: { mode: "index", intersect: false },
                        plugins: {
                            legend: { display: true, align: "start" },
                            tooltip: {
                                callbacks: {
                                    label: (context) =>
                                        `Puell Multiple: ${context.parsed.y.toFixed(3)}`,
                                },
                            },
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                title: { display: true, text: "Multiple" },
                                grid: { color: hexToRgba(themePalette.slate, 0.08) },
                            },
                        },
                    },
                });
            }
            this.charts.puell.data.labels = this.series.map((item) => item.date);
            this.charts.puell.data.datasets[0].data = this.series.map(
                (item) => item.value
            );
            this.charts.puell.update();
        },
        updateMetrics() {
            if (!this.series.length) {
                this.metrics.puellMultiple = "--";
                this.metrics.puellChange = "Data Puell Multiple belum tersedia.";
                return;
            }
            const latest = this.series.at(-1).value;
            const previous = this.series.length > 1 ? this.series.at(-2).value : null;
            this.metrics.puellMultiple = latest.toFixed(3);
            if (previous !== null) {
                const change = latest - previous;
                this.metrics.puellChange = `${change >= 0 ? "+" : ""}${change.toFixed(3)}`;
            } else {
                this.metrics.puellChange = "";
            }
        },
    }));


    Alpine.data("sidebar", () => ({
        open: true,
        collapsed: false,
        openSubmenus: {},
        profileDropdownOpen: false,

        toggle() {
            this.open = !this.open;
        },

        toggleCollapse() {
            this.collapsed = !this.collapsed;
            // Close all submenus when collapsing
            this.openSubmenus = {};
            // Close profile dropdown when collapsing
            this.profileDropdownOpen = false;
        },

        toggleSubmenu(menuId) {
            this.openSubmenus[menuId] = !this.openSubmenus[menuId];
        },

        toggleProfileDropdown() {
            this.profileDropdownOpen = !this.profileDropdownOpen;
        },

        closeProfileDropdown() {
            this.profileDropdownOpen = false;
        },
    }));

    Alpine.data("theme", () => ({
        dark: false,

        init() {
            // Check for saved theme preference or default to light
            this.dark =
                localStorage.getItem("theme") === "dark" ||
                (!localStorage.getItem("theme") &&
                    window.matchMedia("(prefers-color-scheme: dark)").matches);
            this.applyTheme();
        },

        toggle() {
            this.dark = !this.dark;
            this.applyTheme();
            localStorage.setItem("theme", this.dark ? "dark" : "light");

            // Update TradingView theme if widget exists
            this.updateTradingViewTheme();
        },

        applyTheme() {
            if (this.dark) {
                document.documentElement.classList.add("dark");
            } else {
                document.documentElement.classList.remove("dark");
            }
        },

        updateTradingViewTheme() {
            // Check if TradingView widget exists and update its theme
            if (window.TradingView && document.getElementById("tradingChart")) {
                // Remove existing widget
                const container = document.getElementById("tradingChart");
                container.innerHTML = "";

                // Create new widget with updated theme
                new TradingView.widget({
                    autosize: true,
                    symbol: "BINANCE:BTCUSDT",
                    interval: "D",
                    timezone: "Etc/UTC",
                    theme: this.dark ? "dark" : "light",
                    style: "1",
                    locale: "en",
                    toolbar_bg: this.dark ? "#1e293b" : "#ffffff",
                    enable_publishing: false,
                    withdateranges: true,
                    range: "1M",
                    hide_side_toolbar: false,
                    allow_symbol_change: true,
                    details: true,
                    hotlist: true,
                    calendar: false,
                    studies: ["RSI@tv-basicstudies", "MACD@tv-basicstudies"],
                    container_id: "tradingChart",
                });
            }
        },
    }));

    Alpine.data("tradingChart", () => ({
        symbol: "BTCUSDT",
        price: 65420.0,
        change: 1250.0,
        changePercent: 1.95,
        volume: 28500000000,
        high24h: 66800.0,
        low24h: 64200.0,

        init() {
            this.startPriceUpdates();
        },

        startPriceUpdates() {
            setInterval(() => {
                this.updatePrice();
            }, 2000);
        },

        updatePrice() {
            const basePrice = 65420;
            const change = (Math.random() - 0.5) * 2000;
            this.price = basePrice + change;
            this.change = change;
            this.changePercent = (change / basePrice) * 100;

            // Update volume randomly
            this.volume = Math.floor(Math.random() * 10000000000) + 20000000000;
        },

        formatPrice(price) {
            return (
                "$" +
                price.toLocaleString("en-US", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                })
            );
        },

        formatVolume(volume) {
            if (volume >= 1000000000) {
                return (volume / 1000000000).toFixed(1) + "B BTC";
            } else if (volume >= 1000000) {
                return (volume / 1000000).toFixed(1) + "M BTC";
            } else {
                return volume.toLocaleString() + " BTC";
            }
        },
    }));

// Note: Alpine.start() is already called by Livewire

// Legacy functions for backward compatibility
window.initTradingWidget = (element, options = {}) => {
    const $el = $(element);
    $el.addClass("position-relative bg-black rounded-4 overflow-hidden");
    $el.attr("data-widget-options", JSON.stringify(options));

    const info = $("<div/>", {
        class: "position-absolute top-0 end-0 m-3 text-end text-light",
    }).appendTo($el);

    const canvas = $("<div/>", {
        class: "w-100 h-100",
        css: {
            minHeight: "480px",
            background:
                "radial-gradient(circle at top, rgba(59,130,246,.2), rgba(15,23,42,1))",
        },
    }).appendTo($el);

    $el.data("df-info", info);
    $el.data("df-canvas", canvas);

    window.updateTradingWidget(options);
};

window.updateTradingWidget = (payload = {}) => {
    const { symbol = "BTCUSD", price = 0, changePercent = 0 } = payload;
    const target = $("[data-widget-options]");

    if (!target.length) {
        return;
    }

    target.each((_, element) => {
        const $el = $(element);
        const info = $el.data("df-info");
        if (!info) {
            return;
        }
        info.html(`
            <div class="fw-semibold">${symbol}</div>
            <div class="display-6 fw-bold">${Number(price).toLocaleString(
                undefined,
                { minimumFractionDigits: 2 }
            )}</div>
            <div class="small ${
                changePercent >= 0 ? "text-success" : "text-danger"
            }">
                ${changePercent >= 0 ? "+" : ""}${changePercent.toFixed(2)}%
            </div>
        `);
    });
};

// Utility functions
window.DFUtils = {
    formatCurrency: (amount, currency = "USD") => {
        return new Intl.NumberFormat("en-US", {
            style: "currency",
            currency: currency,
        }).format(amount);
    },

    formatNumber: (number, decimals = 2) => {
        return new Intl.NumberFormat("en-US", {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(number);
    },

    formatPercentage: (number, decimals = 2) => {
        return (number >= 0 ? "+" : "") + number.toFixed(decimals) + "%";
    },
};

};

if (window.Alpine) {
    registerAlpineModules();
} else {
    document.addEventListener("alpine:init", registerAlpineModules, {
        once: true,
    });
}
