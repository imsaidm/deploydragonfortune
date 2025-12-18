@extends('layouts.app')

@section('title', 'Signal & Analytics (BTC) | DragonFortune')

@section('content')
    {{--
        Signal & Analytics (Template)
        Fokus: BTC (Bitcoin)
        Catatan: halaman ini sengaja "text-only" (belum ada integrasi data).
    --}}

    <div class="d-flex flex-column h-100 gap-3">
        <!-- Page Header -->
        <div class="derivatives-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h1 class="mb-0">Signal & Analytics</h1>
                        <span class="badge text-bg-secondary">BTC Template</span>
                    </div>
                    <p class="mb-0 text-secondary">
                        Template ringkasan signal & analytics untuk BTC. Isi manual dulu, nanti tinggal sambung ke widget/data yang sudah ada.
                    </p>
                </div>
            </div>
        </div>

        <!-- Quick Snapshot -->
        <div class="row g-3">
            <div class="col-12 col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="small text-secondary mb-1">Asset</div>
                    <div class="h5 mb-0 fw-semibold">BTC — Bitcoin</div>
                    <div class="small text-muted">Pair: BTC/USDT</div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="small text-secondary mb-1">Focus Timeframe</div>
                    <div class="h5 mb-0 fw-semibold">1D · 4H · 1H</div>
                    <div class="small text-muted">Pilih sesuai style trading</div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="small text-secondary mb-1">Mode</div>
                    <div class="h5 mb-0 fw-semibold">Intraday / Swing</div>
                    <div class="small text-muted">Template (tanpa data)</div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="df-panel p-3 h-100">
                    <div class="small text-secondary mb-1">Last Update</div>
                    <div class="h5 mb-0 fw-semibold">(isi manual)</div>
                    <div class="small text-muted">Contoh: 18 Dec 2025, 21:00</div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Signals (Left) -->
            <div class="col-12 col-lg-7">
                <div class="df-panel p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Signal Summary (BTC)</h5>
                        <span class="badge text-bg-secondary">Draft</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="p-3 border rounded h-100">
                                <div class="small text-secondary mb-2">Bias & Regime</div>
                                <ul class="small mb-0">
                                    <li><strong>Market regime:</strong> Trending / Ranging / Choppy</li>
                                    <li><strong>Directional bias:</strong> Bullish / Bearish / Neutral</li>
                                    <li><strong>Confidence:</strong> Low / Medium / High</li>
                                    <li><strong>Time horizon:</strong> Scalping / Intraday / Swing</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="p-3 border rounded h-100">
                                <div class="small text-secondary mb-2">Key Levels (isi manual)</div>
                                <ul class="small mb-0">
                                    <li><strong>Support 1–2:</strong> (____) / (____)</li>
                                    <li><strong>Resistance 1–2:</strong> (____) / (____)</li>
                                    <li><strong>Pivot / Mid:</strong> (____)</li>
                                    <li><strong>Invalidation:</strong> (____)</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 border rounded">
                                <div class="small text-secondary mb-2">One-liner</div>
                                <div class="fw-semibold">
                                    “BTC bias (____) karena (____). Fokus pantau (____) dan invalid jika (____).”
                                </div>
                                <div class="small text-muted mt-2">
                                    Tip: bikin 1 kalimat biar cepat kebaca saat buka dashboard.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="df-panel p-4 mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Trade Setup Template (BTC)</h5>
                        <span class="badge text-bg-warning">Example</span>
                    </div>

                    <div class="accordion" id="btcSetupAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="btcSetupAHeading">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#btcSetupACollapse" aria-expanded="true"
                                    aria-controls="btcSetupACollapse">
                                    Scenario A — Breakout
                                </button>
                            </h2>
                            <div id="btcSetupACollapse" class="accordion-collapse collapse show"
                                aria-labelledby="btcSetupAHeading" data-bs-parent="#btcSetupAccordion">
                                <div class="accordion-body">
                                    <ul class="small mb-0">
                                        <li><strong>Trigger:</strong> Close di atas resistance utama + volume confirm</li>
                                        <li><strong>Entry:</strong> (____)</li>
                                        <li><strong>Stop:</strong> (____)</li>
                                        <li><strong>Targets:</strong> (____) / (____)</li>
                                        <li><strong>Invalidation:</strong> Breakdown balik ke bawah resistance</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="btcSetupBHeading">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#btcSetupBCollapse" aria-expanded="false"
                                    aria-controls="btcSetupBCollapse">
                                    Scenario B — Pullback / Retest
                                </button>
                            </h2>
                            <div id="btcSetupBCollapse" class="accordion-collapse collapse"
                                aria-labelledby="btcSetupBHeading" data-bs-parent="#btcSetupAccordion">
                                <div class="accordion-body">
                                    <ul class="small mb-0">
                                        <li><strong>Trigger:</strong> Retest support/pivot + rejection candle</li>
                                        <li><strong>Entry:</strong> (____)</li>
                                        <li><strong>Stop:</strong> (____)</li>
                                        <li><strong>Targets:</strong> (____)</li>
                                        <li><strong>Invalidation:</strong> Close di bawah support</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="btcSetupCHeading">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#btcSetupCCollapse" aria-expanded="false"
                                    aria-controls="btcSetupCCollapse">
                                    Scenario C — Breakdown / Trend Continuation Short
                                </button>
                            </h2>
                            <div id="btcSetupCCollapse" class="accordion-collapse collapse"
                                aria-labelledby="btcSetupCHeading" data-bs-parent="#btcSetupAccordion">
                                <div class="accordion-body">
                                    <ul class="small mb-0">
                                        <li><strong>Trigger:</strong> Breakdown support + OI/liq confirm (jika ada)</li>
                                        <li><strong>Entry:</strong> (____)</li>
                                        <li><strong>Stop:</strong> (____)</li>
                                        <li><strong>Targets:</strong> (____)</li>
                                        <li><strong>Invalidation:</strong> Reclaim support</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="small text-muted mt-3">
                        Disclaimer: ini template/jurnal internal (bukan saran finansial).
                    </div>
                </div>
            </div>

            <!-- Analytics (Right) -->
            <div class="col-12 col-lg-5">
                <div class="df-panel p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Analytics Checklist (BTC)</h5>
                        <span class="badge text-bg-info">What to show</span>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        <div class="p-3 border rounded">
                            <div class="fw-semibold mb-1">Price Action</div>
                            <ul class="small mb-0">
                                <li>Structure: HH/HL vs LH/LL</li>
                                <li>Trend filter: MA20/MA50 atau EMA ribbon</li>
                                <li>Volatility: ATR / Bollinger squeeze</li>
                                <li>Volume: confirmation breakout / distribution</li>
                            </ul>
                        </div>

                        <div class="p-3 border rounded">
                            <div class="fw-semibold mb-1">Derivatives</div>
                            <ul class="small mb-0">
                                <li>Funding rate: normal vs extreme (crowding)</li>
                                <li>Open interest: build-up vs flush</li>
                                <li>Long/Short ratio: positioning crowd</li>
                                <li>Liquidations: squeeze zones / cascade risk</li>
                                <li>Basis: contango vs backwardation</li>
                            </ul>
                        </div>

                        <div class="p-3 border rounded">
                            <div class="fw-semibold mb-1">Options (opsional)</div>
                            <ul class="small mb-0">
                                <li>IV level: low/normal/high</li>
                                <li>Skew: demand put vs call</li>
                                <li>Put/Call ratio: sentiment hedging</li>
                                <li>Event risk: expiry / data macro</li>
                            </ul>
                        </div>

                        <div class="p-3 border rounded">
                            <div class="fw-semibold mb-1">On-chain (opsional)</div>
                            <ul class="small mb-0">
                                <li>Exchange inflow/outflow: tekanan jual/beli</li>
                                <li>Exchange reserves: trend supply</li>
                                <li>Miner flow: potensi distribusi</li>
                                <li>CDD/SOPR/MVRV: konteks siklus</li>
                            </ul>
                        </div>

                        <div class="p-3 border rounded">
                            <div class="fw-semibold mb-1">Sentiment & Flow</div>
                            <ul class="small mb-0">
                                <li>Fear & Greed: risk-on vs risk-off</li>
                                <li>ETF flows: demand institusional</li>
                                <li>Stablecoin: dry powder indicator</li>
                                <li>Dominance & social sentiment: crowd behavior</li>
                            </ul>
                        </div>

                        <div class="p-3 border rounded">
                            <div class="fw-semibold mb-1">Output yang enak ditampilin</div>
                            <ul class="small mb-0">
                                <li>3–5 KPI cards: bias, trend, volatility, leverage, sentiment</li>
                                <li>“Why” bullets: 3 alasan utama (bull/bear)</li>
                                <li>Key levels + invalidation jelas</li>
                                <li>Alert rules (jika/ketika): kondisi yang bikin notif</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="df-panel p-4 mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">BTC Notes</h5>
                        <span class="badge text-bg-secondary">Manual</span>
                    </div>
                    <p class="small text-secondary mb-3">
                        Tempat catat insight harian (sementara). Nanti bisa dipersist ke database / disambung ke backtest result.
                    </p>
                    <textarea class="form-control" rows="8"
                        placeholder="Contoh:\n- Bias harian:\n- Level penting:\n- Trigger alert:\n- Plan entry/exit:\n- Hal yang harus dihindari:"></textarea>
                </div>
            </div>
        </div>
    </div>
@endsection
