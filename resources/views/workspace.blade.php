@extends('layouts.app')

@section('content')
    <div class="d-flex flex-column h-100 gap-3" x-data="dashboardData()" x-init="init()">
        <!-- Role Info Banner (Hidden for SuperAdmin/Admin to maintain 'shadow' feel, shown for others) -->
        @if(auth()->user()->isInvestor())
            <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-2 mb-0" style="background: rgba(var(--primary-rgb), 0.1);">
                <i class="bi bi-info-circle"></i>
                <div class="small">Welcome, <strong>{{ auth()->user()->name }}</strong>. You are currently in <strong>Investor Mode</strong> (View-Only).</div>
            </div>
        @endif
        <!-- Macro Snapshot (API-powered) -->
        <!-- <div class="row g-3">
            <div class="col-lg-3 col-md-6">
                <div class="df-panel p-3 h-100">
                    <div class="small" style="color: var(--muted-foreground);">Risk Appetite</div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="h5 mb-0" x-text="macro?.analytics?.market_sentiment?.risk_appetite || 'N/A'">N/A</span>
                        <span class="badge" :class="getSentimentBadge(macro?.analytics?.market_sentiment?.risk_appetite)">
                            <span x-text="macro?.analytics?.market_sentiment?.dollar_strengthening === true ? 'USD Strong' : macro?.analytics?.market_sentiment?.dollar_strengthening === false ? 'USD Weak' : '—'">—</span>
                        </span>
                    </div>
                    <div class="small text-secondary mt-1" x-text="macro.loading ? 'Loading…' : ''"></div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="df-panel p-3 h-100">
                    <div class="small" style="color: var(--muted-foreground);">Fed Stance</div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="h5 mb-0" x-text="macro?.analytics?.monetary_policy?.fed_stance || 'N/A'">N/A</span>
                        <span class="badge" :class="getFedStanceBadge(macro?.analytics?.monetary_policy?.fed_stance)">•</span>
                    </div>
                    <div class="small text-secondary">Liquidity: <span x-text="macro?.analytics?.monetary_policy?.liquidity_conditions || 'N/A'">N/A</span></div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="df-panel p-3 h-100">
                    <div class="small" style="color: var(--muted-foreground);">DXY Level</div>
                    <div class="h5 mb-0" x-text="formatNumber(macro?.analytics?.market_sentiment?.details?.dxy_level)">N/A</div>
                    <div class="small" :class="(macro?.analytics?.market_sentiment?.dollar_strengthening ? 'text-danger' : 'text-success')" x-text="macro?.analytics?.market_sentiment?.dollar_strengthening === true ? 'USD Strengthening' : macro?.analytics?.market_sentiment?.dollar_strengthening === false ? 'USD Weakening' : '—'">—</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="df-panel p-3 h-100">
                    <div class="small" style="color: var(--muted-foreground);">10Y Yield</div>
                    <div class="h5 mb-0"><span x-text="formatNumber(macro?.analytics?.market_sentiment?.details?.yield_10y_level)">N/A</span><span class="small text-secondary">%</span></div>
                    <div class="small text-secondary">Trend: <span x-text="macro?.analytics?.trends?.yield_trend || 'N/A'">N/A</span></div>
                </div>
            </div>
        </div> -->
        <!-- Market Snapshot + Regime Indicator -->
        <div class="row g-3">
            <div class="col-lg-8">
                <section class="df-panel p-3 h-100">
                    <div class="row g-3 align-items-end">
                        <div class="col-6 col-lg-3">
                            <div class="small" style="color: var(--muted-foreground);">BTCUSDT · Last</div>
                            <div class="h3 fw-bold mb-0 d-flex align-items-center gap-2">
                                <span x-text="formatPrice(btc.last)">$65,420.00</span>
                                <span class="pulse-dot" :class="btc.chgPct >= 0 ? 'pulse-success' : 'pulse-danger'"></span>
                            </div>
                            <div class="small d-flex align-items-center gap-1" :class="btc.chgPct >= 0 ? 'text-success' : 'text-danger'">
                                <svg width="12" height="12" :class="btc.chgPct >= 0 ? '' : 'rotate-180'">
                                    <path d="M6 2 L10 8 L2 8 Z" :fill="btc.chgPct >= 0 ? 'currentColor' : 'currentColor'" />
                                </svg>
                                <span x-text="signed(btc.chg) + ' (' + signed(btc.chgPct) + '%)'">+1,250.00 (+1.95%)</span>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="small" style="color: var(--muted-foreground);">24h Range</div>
                            <div class="fw-semibold">
                                <span x-text="formatPrice(btc.low)">$64,200.00</span>
                                <span class="text-secondary"> → </span>
                                <span x-text="formatPrice(btc.high)">$66,800.00</span>
                            </div>
                            <div class="progress mt-1" style="height: 4px;">
                                <div class="progress-bar bg-primary" role="progressbar" :style="'width: ' + ((btc.last - btc.low) / (btc.high - btc.low) * 100) + '%'"></div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="small" style="color: var(--muted-foreground);">24h Volume</div>
                            <div class="fw-semibold" x-text="formatVolume(btc.volume)">28.5B</div>
                            <div class="sparkline-mini" x-html="renderSparkline(volumeHistory)"></div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="small" style="color: var(--muted-foreground);">Dominance</div>
                            <div class="fw-semibold">
                                <span x-text="btc.dominance.toFixed(1) + '%'">54.2%</span>
                            </div>
                            <div class="small text-secondary" x-text="'Updated ' + lastUpdate">2s ago</div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="col-lg-4">
                <!-- Market Regime Gauge -->
                <section class="df-panel p-3 h-100 d-flex flex-column justify-content-center"
                         x-data="{ gauge: 65 }"
                         :style="'background: linear-gradient(135deg, ' + getRegimeGradient(marketRegime) + ')'">
                    <div class="text-center">
                        <div class="small text-white-50 mb-1">MARKET REGIME</div>
                        <div class="h2 fw-bold text-white mb-2" x-text="marketRegime">Risk-On</div>
                        <div class="d-flex justify-content-center align-items-center gap-2 mb-2">
                            <div class="regime-gauge" style="width: 120px; height: 120px; position: relative;">
                                <svg viewBox="0 0 100 100" style="transform: rotate(-90deg);">
                                    <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="8"/>
                                    <circle cx="50" cy="50" r="45" fill="none" stroke="white" stroke-width="8"
                                            :stroke-dasharray="(regimeScore / 100 * 283) + ' 283'"
                                            stroke-linecap="round"/>
                                </svg>
                                <div class="position-absolute top-50 start-50 translate-middle text-white">
                                    <div class="h4 fw-bold mb-0" x-text="regimeScore">65</div>
                                    <div class="small">Score</div>
                                </div>
                            </div>
                        </div>
                        <div class="small text-white" x-text="regimeInsight">
                            Bullish bias • High leverage • Watch funding
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- Derivatives + Options + On‑Chain + ETF Tiles -->
        <section class="row g-3">
            <div class="col-lg-3 col-md-6">
                <a href="/derivatives/funding-rate" class="text-decoration-none metric-card-hover" style="color: inherit;" x-data="{}">
                    <div class="df-panel p-3 h-100 position-relative overflow-hidden">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="small" style="color: var(--muted-foreground);">Funding Rate (perp)</div>
                            <div class="metric-badge" :class="getFundingBadge(funding)">
                                <svg width="12" height="12" :class="fundingTrend >= 0 ? '' : 'rotate-180'">
                                    <path d="M6 2 L10 8 L2 8 Z" fill="currentColor" />
                                </svg>
                            </div>
                        </div>
                        <div class="d-flex align-items-baseline gap-2 mb-2">
                            <div class="h4 mb-0" :class="funding >= 0 ? 'text-success' : 'text-danger'" x-text="macro?.funding?.current != null ? signed(macro.funding.current * 100) + '%' : signed(funding) + '%'">+0.02%</div>
                            <small class="text-secondary" x-text="macro?.funding?.window || '24h avg'">24h avg</small>
                        </div>
                        <div class="sparkline-container mb-2" x-html="renderSparkline(macro?.funding?.history || fundingHistory, (macro?.funding?.current ?? funding) >= 0 ? '#22c55e' : '#ef4444')"></div>
                        <div class="small" :class="fundingTrend >= 0 ? 'text-success' : 'text-danger'">
                            <span x-text="trendText(fundingTrend)">Rising</span> last 4h
                        </div>
                        <div class="metric-tooltip">
                            <template x-if="funding > 0.05">
                                <span>⚠️ High funding → Longs overheated → Potential short squeeze</span>
                            </template>
                            <template x-if="funding < -0.05">
                                <span>⚠️ Negative funding → Shorts overheated → Potential long squeeze</span>
                            </template>
                            <template x-if="Math.abs(funding) <= 0.05">
                                <span>✓ Neutral funding → Balanced market</span>
                            </template>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="/derivatives/open-interest" class="text-decoration-none metric-card-hover" style="color: inherit;">
                    <div class="df-panel p-3 h-100 position-relative overflow-hidden">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="small" style="color: var(--muted-foreground);">Open Interest Δ 24h</div>
                            <div class="metric-badge" :class="(macro?.oi?.change ?? oiChange) >= 0 ? 'badge-success' : 'badge-danger'">
                                <svg width="12" height="12" :class="oiChange >= 0 ? '' : 'rotate-180'">
                                    <path d="M6 2 L10 8 L2 8 Z" fill="currentColor" />
                                </svg>
                            </div>
                        </div>
                        <div class="h4 mb-0" :class="(macro?.oi?.change ?? oiChange) >= 0 ? 'text-success' : 'text-danger'" x-text="(macro?.oi?.change != null ? signed(macro.oi.change) : signed(oiChange)) + 'B'">+0.8B</div>
                        <div class="sparkline-container mb-2" x-html="renderSparkline(macro?.oi?.history || oiHistory, (macro?.oi?.change ?? oiChange) >= 0 ? '#22c55e' : '#ef4444')"></div>
                        <div class="small text-secondary">Across major venues</div>
                        <div class="metric-tooltip">
                            <template x-if="oiChange > 0 && btc.chgPct > 0">
                                <span>✓ OI + Price UP → Strong bullish trend</span>
                            </template>
                            <template x-if="oiChange > 0 && btc.chgPct < 0">
                                <span>⚠️ OI UP but Price DOWN → Potential short buildup</span>
                            </template>
                            <template x-if="oiChange < 0">
                                <span>📉 OI declining → Position unwinding</span>
                            </template>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="/derivatives/liquidations" class="text-decoration-none metric-card-hover" style="color: inherit;">
                    <div class="df-panel p-3 h-100 position-relative overflow-hidden">
                        <div class="small mb-2" style="color: var(--muted-foreground);">Liquidations (24h)</div>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="flex-fill">
                                <div class="small text-secondary">Long</div>
                                <div class="fw-semibold text-danger" x-text="'$' + formatCompact((macro?.liq?.long ?? liq.long))">$120M</div>
                                <div class="progress mt-1" style="height: 3px;">
                                    <div class="progress-bar bg-danger" :style="'width: ' + (((macro?.liq?.long ?? liq.long) / ((macro?.liq?.long ?? liq.long) + (macro?.liq?.short ?? liq.short))) * 100) + '%'"
                                    ></div>
                                </div>
                            </div>
                            <div class="flex-fill">
                                <div class="small text-secondary">Short</div>
                                <div class="fw-semibold text-success" x-text="'$' + formatCompact((macro?.liq?.short ?? liq.short))">$98M</div>
                                <div class="progress mt-1" style="height: 3px;">
                                    <div class="progress-bar bg-success" :style="'width: ' + (((macro?.liq?.short ?? liq.short) / ((macro?.liq?.long ?? liq.long) + (macro?.liq?.short ?? liq.short))) * 100) + '%'"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div class="small" :class="((macro?.liq?.long ?? liq.long) > (macro?.liq?.short ?? liq.short)) ? 'text-danger' : 'text-success'" x-text="((macro?.liq?.long ?? liq.long) > (macro?.liq?.short ?? liq.short)) ? 'Long squeeze active' : 'Short squeeze active'">
                            Long squeeze active
                        </div>
                        <div class="metric-tooltip">
                            <span x-text="'Ratio: ' + (liq.long / liq.short).toFixed(2) + 'x • ' + (liq.long > liq.short * 1.5 ? 'Heavy long liquidations' : 'Balanced')">
                                Ratio: 1.22x • Balanced
                            </span>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="/etf-basis/perp-basis" class="text-decoration-none metric-card-hover" style="color: inherit;">
                    <div class="df-panel p-3 h-100 position-relative overflow-hidden">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="small" style="color: var(--muted-foreground);">Perp Basis vs Spot</div>
                            <div class="metric-badge" :class="basis >= 0 ? 'badge-success' : 'badge-danger'">
                                <span x-text="Math.abs(basis * 100).toFixed(0) + 'bp'">35bp</span>
                            </div>
                        </div>
                        <div class="h4 mb-0" :class="basis >= 0 ? 'text-success' : 'text-danger'" x-text="signed(basis) + '%'">+0.35%</div>
                        <div class="sparkline-container mb-2" x-html="renderSparkline(basisHistory, basis >= 0 ? '#22c55e' : '#ef4444')"></div>
                        <div class="small text-secondary">Indicative carry</div>
                        <div class="metric-tooltip">
                            <template x-if="basis > 0.5">
                                <span>💰 High premium → Strong demand for longs</span>
                            </template>
                            <template x-if="basis < -0.5">
                                <span>⚠️ Discount → Bearish sentiment on perps</span>
                            </template>
                            <template x-if="Math.abs(basis) <= 0.5">
                                <span>⚖️ Fair value → Aligned spot & perp</span>
                            </template>
                        </div>
                    </div>
                </a>
            </div>
        </section>

        <!-- Watchlist Heat (Top movers) -->
        <section class="df-panel p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Watchlist · Top Movers</h6>
                <a href="#" class="small" style="color: var(--muted-foreground);">Manage</a>
            </div>
            <div class="row g-3">
                <template x-for="asset in watchlist" :key="asset.symbol">
                    <div class="col-6 col-lg-3">
                        <div class="p-3 rounded-3 border" :class="asset.chgPct >= 0 ? 'border-success-subtle' : 'border-danger-subtle'">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div class="fw-semibold" x-text="asset.symbol">BTC</div>
                                <div class="small" :class="asset.chgPct >= 0 ? 'text-success' : 'text-danger'" x-text="signed(asset.chgPct) + '%'">+2.1%</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <div class="small text-secondary">Last</div>
                                <div class="small" x-text="formatPrice(asset.last)">$65,420</div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <!-- On‑Chain + ETF Netflow + Risk -->
        <section class="row g-3">
            <div class="col-lg-3 col-md-6">
                <a href="/onchain-metrics/exchange-netflow" class="text-decoration-none" style="color: inherit;">
                    <div class="df-panel p-3 h-100">
                        <div class="small" style="color: var(--muted-foreground);">Exchange Netflow (BTC)</div>
                        <div class="h4 mb-0" :class="netflow >= 0 ? 'text-danger' : 'text-success'" x-text="signed(netflow) + ' BTC'">-3.2k BTC</div>
                        <div class="small text-secondary">Outflow suggests accumulation</div>
                    </div>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="/etf-basis/spot-etf-netflow" class="text-decoration-none" style="color: inherit;">
                    <div class="df-panel p-3 h-100">
                        <div class="small" style="color: var(--muted-foreground);">Spot BTC ETF Netflow (today)</div>
                        <div class="h4 mb-0" :class="etfNetflow >= 0 ? 'text-success' : 'text-danger'" x-text="'$' + formatCompact(etfNetflow)">$185M</div>
                        <div class="small text-secondary">US ETFs aggregated</div>
                    </div>
                </a>
                </div>
            </div>

            @if(auth()->user()->isCreator())
                <div class="col-lg-3 col-md-6">
                    <div class="df-panel p-3 h-100 border-primary-subtle" style="border: 1px dashed var(--primary);">
                        <div class="small mb-1" style="color: var(--primary);">Creator Tools</div>
                        <div class="h5 mb-2">Strategy Lab</div>
                        <a href="/backtest-result" class="btn btn-sm btn-primary w-100">Manage Methods</a>
                    </div>
                </div>
            @endif

            @if(auth()->user()->isAdmin())
                <div class="col-lg-3 col-md-6">
                    <div class="df-panel p-3 h-100" style="background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.05), transparent);">
                        <div class="small mb-1" style="color: var(--muted-foreground);">Administration</div>
                        <div class="h5 mb-2">System Control</div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-secondary flex-fill">Users</button>
                            <button class="btn btn-sm btn-outline-secondary flex-fill">API Status</button>
                        </div>
                    </div>
                </div>
            @endif

            @if(auth()->user()->isSuperAdmin())
                <div class="col-lg-3 col-md-6">
                    <div class="df-panel p-3 h-100 bg-dark text-white shadow-lg border-0" style="background: #000 !important;">
                        <div class="small mb-1 text-warning">SuperAdmin Terminal</div>
                        <div class="h5 mb-2">Shadow Control</div>
                        <div class="small text-white-50 mb-2">Direct DB Access & Logs</div>
                        <button class="btn btn-sm btn-warning w-100 text-dark fw-bold">Enter Ghost Mode</button>
                    </div>
                </div>
            @endif
        </section>

        <!-- Risk + Positioning -->
        <section class="row g-3">
            <div class="col-lg-6">
                <a href="/volatility-regime/detector" class="text-decoration-none" style="color: inherit;">
                    <div class="df-panel p-3 h-100 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="small" style="color: var(--muted-foreground);">Volatility Regime</div>
                            <div class="h4 mb-0" x-text="volRegime">Expanding</div>
                            <div class="small text-secondary">σ pendek vs σ panjang</div>
                </div>
                        <div>
                            <span class="badge text-bg-primary" x-text="regimeBadge">Risk‑on</span>
                </div>
                </div>
                </a>
            </div>
            <div class="col-lg-6">
                <a href="/atr/detector" class="text-decoration-none" style="color: inherit;">
                    <div class="df-panel p-3 h-100">
                        <div class="small" style="color: var(--muted-foreground);">ATR‑based Sizing Hint (BTC)</div>
                        <div class="d-flex align-items-baseline gap-2">
                            <div class="h4 mb-0" x-text="(atrMultiple).toFixed(2) + 'x ATR'">1.75x ATR</div>
                            <small class="text-secondary">use as stop distance</small>
                        </div>
                        <div class="small" x-text="'Suggested risk per trade: ' + riskPerTrade + '%'">Suggested risk per trade: 0.75%</div>
                    </div>
                </a>
        </div>
        </section>
    </div>

    <script type="text/javascript">
        function dashboardData() {
            const state = {
                macro: {
                    loading: false,
                    analytics: null,
                    funding: null, // { current, window, history[] }
                    oi: null,      // { change, history[] }
                    liq: null      // { long, short }
                },
                btc: { last: 65420, chg: 1250, chgPct: 1.95, low: 64200, high: 66800, volume: 28.5e9, dominance: 54.2 },
                fgIndex: 'Neutral (53)',
                funding: 0.02,
                fundingTrend: 1,
                oiChange: 0.8,
                liq: { long: 120e6, short: 98e6 },
                basis: 0.35,
                iv30: 52.3,
                ivTrend: 1,
                rr25: -3.1,
                netflow: -3200,
                etfNetflow: 185e6,
                volRegime: 'Expanding',
                regimeBadge: 'Risk‑on',
                atrMultiple: 1.75,
                riskPerTrade: 0.75,
                lastUpdate: '2s ago',
                marketRegime: 'Risk-On',
                regimeScore: 65,
                regimeInsight: 'Bullish bias • High leverage • Watch funding',
                watchlist: [
                    { symbol: 'BTC', last: 65420, chgPct: 2.1 },
                    { symbol: 'ETH', last: 3420, chgPct: -1.3 },
                    { symbol: 'SOL', last: 185.2, chgPct: 3.8 },
                    { symbol: 'DOGE', last: 0.18, chgPct: -4.2 }
                ],
                // Mock historical data for sparklines (24 data points = 1 hour intervals)
                volumeHistory: Array.from({length: 24}, () => 25e9 + Math.random() * 8e9),
                fundingHistory: Array.from({length: 24}, (_, i) => 0.01 + Math.sin(i/4) * 0.03),
                oiHistory: Array.from({length: 24}, (_, i) => 0.5 + Math.sin(i/3) * 0.5),
                basisHistory: Array.from({length: 24}, (_, i) => 0.25 + Math.sin(i/5) * 0.2)
            };

            // Helpers
            state.formatNumber = (v) => {
                const n = Number(v);
                if (isNaN(n)) return 'N/A';
                return n.toLocaleString('en-US', { maximumFractionDigits: 2 });
            };
            state.formatPrice = (v) => '$' + Number(v).toLocaleString('en-US', { maximumFractionDigits: 2 });
            state.formatVolume = (v) => {
                if (v >= 1e9) return (v / 1e9).toFixed(1) + 'B';
                if (v >= 1e6) return (v / 1e6).toFixed(1) + 'M';
                return Number(v).toLocaleString('en-US');
            };
            state.formatCompact = (v) => {
                const abs = Math.abs(v);
                if (abs >= 1e9) return (v / 1e9).toFixed(0) + 'B';
                if (abs >= 1e6) return (v / 1e6).toFixed(0) + 'M';
                if (abs >= 1e3) return (v / 1e3).toFixed(0) + 'K';
                return String(v);
            };
            state.signed = (v) => (v >= 0 ? '+' : '') + Number(v).toFixed(typeof v === 'number' && Math.abs(v) < 10 ? 2 : 2);
            state.trendText = (v) => v > 0 ? 'Rising' : v < 0 ? 'Falling' : 'Flat';

            // Sparkline renderer (SVG-based for performance)
            state.renderSparkline = (data, color = '#3b82f6') => {
                if (!data || data.length === 0) return '';
                const width = 100;
                const height = 24;
                const min = Math.min(...data);
                const max = Math.max(...data);
                const range = max - min || 1;

                const points = data.map((v, i) => {
                    const x = (i / (data.length - 1)) * width;
                    const y = height - ((v - min) / range) * height;
                    return `${x},${y}`;
                }).join(' ');

                return `
                    <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" style="display: block;">
                        <polyline points="${points}"
                                  fill="none"
                                  stroke="${color}"
                                  stroke-width="1.5"
                                  stroke-linecap="round"
                                  stroke-linejoin="round"
                                  opacity="0.8"/>
                    </svg>
                `;
            };

            // Market regime color gradient
            state.getRegimeGradient = (regime) => {
                const gradients = {
                    'Risk-On': 'rgba(34, 197, 94, 0.8), rgba(34, 197, 94, 0.4)',
                    'Risk-Off': 'rgba(239, 68, 68, 0.8), rgba(239, 68, 68, 0.4)',
                    'Neutral': 'rgba(100, 116, 139, 0.8), rgba(100, 116, 139, 0.4)',
                    'Expanding': 'rgba(59, 130, 246, 0.8), rgba(59, 130, 246, 0.4)'
                };
                return gradients[regime] || gradients['Neutral'];
            };

            // Funding badge helper
            state.getFundingBadge = (funding) => {
                if (Math.abs(funding) < 0.02) return 'badge-neutral';
                return funding > 0 ? 'badge-success' : 'badge-danger';
            };

            // Macro badges
            state.getSentimentBadge = (risk) => {
                const map = {
                    'High': 'text-bg-danger',
                    'Moderate': 'text-bg-warning',
                    'Low': 'text-bg-success',
                    'N/A': 'text-bg-secondary'
                };
                return map[risk] || 'text-bg-secondary';
            };
            state.getFedStanceBadge = (stance) => {
                const map = {
                    'Tightening': 'text-bg-danger',
                    'Neutral': 'text-bg-secondary',
                    'Easing': 'text-bg-success'
                };
                return map[stance] || 'text-bg-secondary';
            };

            // Calculate market regime based on multiple factors
            state.calculateRegime = () => {
                // Aggregate: funding + OI + liquidations + basis
                let score = 50; // baseline neutral

                // Funding contribution (±15 points)
                if (state.funding > 0.05) score += 15;
                else if (state.funding > 0.02) score += 8;
                else if (state.funding < -0.05) score -= 15;
                else if (state.funding < -0.02) score -= 8;

                // OI contribution (±10 points)
                if (state.oiChange > 1) score += 10;
                else if (state.oiChange < -1) score -= 10;

                // Liquidations (±10 points)
                const liqRatio = state.liq.long / state.liq.short;
                if (liqRatio > 1.5) score -= 10; // heavy long liqs = bearish
                else if (liqRatio < 0.67) score += 10; // heavy short liqs = bullish

                // Basis (±10 points)
                if (state.basis > 0.5) score += 10;
                else if (state.basis < -0.5) score -= 10;

                // Price momentum (±5 points)
                if (state.btc.chgPct > 2) score += 5;
                else if (state.btc.chgPct < -2) score -= 5;

                state.regimeScore = Math.max(0, Math.min(100, score));

                // Determine regime label
                if (state.regimeScore >= 70) {
                    state.marketRegime = 'Risk-On';
                    state.regimeInsight = 'Strong bullish bias • High leverage • Watch funding spikes';
                } else if (state.regimeScore >= 55) {
                    state.marketRegime = 'Neutral';
                    state.regimeInsight = 'Balanced market • Mixed signals • Wait for confirmation';
                } else if (state.regimeScore >= 30) {
                    state.marketRegime = 'Risk-Off';
                    state.regimeInsight = 'Bearish pressure • Reduce exposure • Tight stops';
                } else {
                    state.marketRegime = 'Risk-Off';
                    state.regimeInsight = 'High risk • Heavy selling • Consider sidelines';
                }
            };

            // Update last updated timestamp
            let secondsAgo = 0;
            setInterval(() => {
                secondsAgo++;
                if (secondsAgo < 60) state.lastUpdate = secondsAgo + 's ago';
                else if (secondsAgo < 3600) state.lastUpdate = Math.floor(secondsAgo / 60) + 'm ago';
                else state.lastUpdate = Math.floor(secondsAgo / 3600) + 'h ago';
            }, 1000);

            // Simulate light real‑time updates
            setInterval(() => {
                const drift = (Math.random() - 0.5) * 150;
                state.btc.last = Math.max(1000, state.btc.last + drift);
                state.btc.chg = drift;
                state.btc.chgPct = (drift / (state.btc.last - drift)) * 100;

                // Update funding slightly
                state.funding += (Math.random() - 0.5) * 0.005;
                state.fundingTrend = Math.random() > 0.5 ? 1 : -1;

                // Shift sparkline data (simulate real-time)
                state.fundingHistory.shift();
                state.fundingHistory.push(state.funding);
                state.oiHistory.shift();
                state.oiHistory.push(state.oiChange);

                // Recalculate regime
                state.calculateRegime();

                secondsAgo = 0; // reset update timer
            }, 5000);

            // Initial calculation
            state.calculateRegime();

            // API base util
            const getApiBase = () => {
                const meta = document.querySelector('meta[name="api-base-url"]');
                return (meta?.content || '').trim();
            };

            // Load Macro Overlay quick data
            state.loadMacroQuick = async () => {
                state.macro.loading = true;
                try {
                    const base = getApiBase();
                    const baseUrl = base ? base.replace(/\/$/, '') : '';

                    // 1) Analytics (provides sentiment, policy, trends)
                    const analyticsUrl = `${baseUrl}/api/macro-overlay/analytics?limit=100`;
                    const analyticsRes = await fetch(analyticsUrl);
                    state.macro.analytics = await analyticsRes.json();

                    // 2) Funding rate proxy (use funding-rate analytics current)
                    // Optional: guarded if endpoint not reachable
                    try {
                        const frUrl = `${baseUrl}/api/funding-rate/analytics?symbol=BTCUSDT&interval=4h&limit=60`;
                        const frRes = await fetch(frUrl);
                        const fr = await frRes.json();
                        const hist = (fr?.history || []).map(x => x?.funding_rate || 0).slice(-24);
                        state.macro.funding = {
                            current: fr?.summary?.current ?? null,
                            window: '24h avg',
                            history: hist
                        };
                    } catch (_) {}

                    // 3) Liquidations quick (use liquidations analytics)
                    try {
                        const lqUrl = `${baseUrl}/api/liquidations/analytics?symbol=BTCUSDT&interval=1h&limit=24`;
                        const lqRes = await fetch(lqUrl);
                        const lq = await lqRes.json();
                        state.macro.liq = {
                            long: lq?.summary?.total_long_usd ?? 0,
                            short: lq?.summary?.total_short_usd ?? 0
                        };
                    } catch (_) {}

                    // 4) OI quick (use funding-rate aggregate or oi endpoint if exists)
                    try {
                        const oiUrl = `${baseUrl}/api/funding-rate/aggregate?symbol=BTCUSDT&window=24h`;
                        const oiRes = await fetch(oiUrl);
                        const oi = await oiRes.json();
                        const series = (oi?.exchanges || []).map(e => Number(e?.avg_rate || 0));
                        const change = series.reduce((a, b) => a + b, 0) / Math.max(1, series.length);
                        state.macro.oi = { change, history: series.slice(-24) };
                    } catch (_) {}

                } catch (e) {
                    console.warn('Macro quick load failed:', e);
                } finally {
                    state.macro.loading = false;
                }
            };

            state.init = () => {
                state.loadMacroQuick();
            };

            return state;
        }
    </script>
@endsection
