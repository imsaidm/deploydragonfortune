@extends('layouts.app')

@section('title', 'Advanced Liquidation Stream | DragonFortune Pro')

@push('head')
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts for premium typography -->
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <!-- Chart.js for liquidation pressure visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --df-bg: #0d1117;
            --df-pane-bg: rgba(22, 27, 34, 0.7);
            --df-border: rgba(48, 54, 61, 0.8);
            --df-accent: #58a6ff;
            --df-bullish: #22c55e;
            --df-bearish: #ef4444;
            --df-text-dim: #8b949e;
            --df-glass-border: rgba(255, 255, 255, 0.05);
        }

        body {
            background-color: var(--df-bg);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            overflow: hidden;
        }

        .pro-dashboard-wrapper {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 80px);
            padding: 1rem;
            gap: 1rem;
        }

        /* 3-Column Grid Layout */
        .pro-dashboard {
            display: grid;
            grid-template-columns: 280px 1fr 340px;
            gap: 1rem;
            flex-grow: 1;
            min-height: 0;
        }

        .pane {
            background: var(--df-pane-bg);
            border: 1px solid var(--df-border);
            border-radius: 12px;
            backdrop-filter: blur(12px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .pane-header {
            padding: 1rem;
            border-bottom: 1px solid var(--df-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.02);
        }

        .pane-title {
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--df-text-dim);
            margin: 0;
        }

        /* Left Side: Stats */
        .sidebar-stat {
            padding: 1.25rem;
            border-bottom: 1px solid var(--df-border);
        }

        .stat-label {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--df-text-dim);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .stat-main {
            font-size: 1.4rem;
            font-weight: 800;
            font-family: 'JetBrains Mono', monospace;
        }

        .stat-sub {
            font-size: 0.75rem;
            color: var(--df-text-dim);
        }

        /* Middle Pane: Chart */
        .middle-pane {
            background: radial-gradient(circle at top right, rgba(88, 166, 255, 0.05), transparent 40%),
                        var(--df-pane-bg);
        }

        .chart-container {
            flex-grow: 1;
            padding: 1.5rem;
            position: relative;
        }

        /* Right Side: Live Feed */
        .live-feed {
            flex-grow: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--df-border) transparent;
        }

        .feed-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--df-glass-border);
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            animation: slide-in 0.3s ease-out;
            cursor: default;
        }

        .feed-item:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .feed-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .feed-symbol {
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
        }

        .feed-side {
            font-size: 0.65rem;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .side-long { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .side-short { background: rgba(34, 197, 94, 0.2); color: #22c55e; }

        .feed-val {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            font-size: 0.95rem;
        }

        .val-large { color: #f59e0b; text-shadow: 0 0 8px rgba(245, 158, 11, 0.3); }

        /* Controls Area */
        .controls-area {
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .btn-pro {
            background: var(--df-border);
            border: 1px solid var(--df-glass-border);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.2s;
            text-transform: uppercase;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-pro:hover {
            border-color: var(--df-accent);
            background: rgba(88, 166, 255, 0.1);
        }

        .btn-pro.active {
            background: var(--df-accent);
            color: #0d1117;
            border-color: var(--df-accent);
        }

        /* Status Badges */
        .status-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.05);
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .pulse-live { background: #22c55e; box-shadow: 0 0 10px #22c55e; animation: pulse 2s infinite; }
        .pulse-offline { background: #ef4444; }
        .pulse-demo { background: #f59e0b; animation: pulse 2s infinite; }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.4; }
            100% { opacity: 1; }
        }

        @keyframes slide-in {
            from { transform: translateX(20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Filters Bar */
        .filters-bar {
            display: flex;
            gap: 0.5rem;
            background: var(--df-pane-bg);
            border: 1px solid var(--df-border);
            padding: 0.5rem 1rem;
            border-radius: 12px;
            align-items: center;
        }

        .filter-select {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--df-border);
            color: #fff;
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 6px;
            outline: none;
        }

        .filter-select:focus {
            border-color: var(--df-accent);
        }

        [x-cloak] { display: none !important; }

        /* Offline Overlay */
        .offline-overlay {
            position: absolute;
            inset: 0;
            background: rgba(13, 17, 23, 0.85);
            backdrop-filter: blur(4px);
            z-index: 100;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
    </style>
@endpush

@section('content')
<div class="pro-dashboard-wrapper" x-data="liquidationsAdvancedController({ apiKey: 'f78a531eb0ef4d06ba9559ec16a6b0c2' })">
    
    <!-- Top Filters Bar -->
    <div class="filters-bar">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-filter text-dim small"></i>
            <span class="small fw-bold text-dim text-uppercase me-2" style="font-size: 0.65rem; letter-spacing: 0.05em;">Filters</span>
        </div>

        <select class="filter-select" x-model="filters.coin">
            <option value="">All Coins</option>
            <option value="BTC">Bitcoin (BTC)</option>
            <option value="ETH">Ethereum (ETH)</option>
            <option value="SOL">Solana (SOL)</option>
            <option value="DOGE">Dogecoin (DOGE)</option>
        </select>

        <select class="filter-select" x-model="filters.minUsd">
            <option value="0">$0+ Size</option>
            <option value="10000">$10K+ Size</option>
            <option value="50000">$50K+ Size</option>
            <option value="100000">$100K+ Size</option>
        </select>

        <div class="ms-auto d-flex align-items-center gap-3">
            <div class="status-badge">
                <div class="pulse-dot" :class="{ 'pulse-live': wsConnected, 'pulse-demo': demoMode, 'pulse-offline': !wsConnected && !demoMode }"></div>
                <span x-text="wsConnected ? 'LIVE WS' : (demoMode ? 'DEMO MODE' : 'OFFLINE')"></span>
            </div>
            
            <button class="btn-pro" @click="toggleSound()" :class="{ 'active': soundEnabled }" style="width: auto; padding: 4px 12px;">
                <i class="fas" :class="soundEnabled ? 'fa-volume-up' : 'fa-volume-mute'"></i>
            </button>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="pro-dashboard">
        
        <!-- Left Sidebar: Stats -->
        <div class="pane">
            <div class="pane-header">
                <h2 class="pane-title">Market Stats</h2>
                <i class="fas fa-chart-line text-blue small"></i>
            </div>

            <div class="sidebar-stat">
                <div class="stat-label">Total Liquidations (10m)</div>
                <div class="stat-main" x-text="formatCurrency(stats.totalUsd)">$0.00</div>
                <div class="stat-sub" x-text="stats.count + ' orders tracked'">0 orders tracked</div>
            </div>

            <div class="sidebar-stat">
                <div class="stat-label text-danger">Long Liquidations</div>
                <div class="stat-main text-danger" x-text="formatCurrency(stats.longUsd)">$0.00</div>
                <div class="stat-sub" x-text="stats.longCount + ' forced sells'">0 forced sells</div>
            </div>

            <div class="sidebar-stat">
                <div class="stat-label text-success">Short Liquidations</div>
                <div class="stat-main text-success" x-text="formatCurrency(stats.shortUsd)">$0.00</div>
                <div class="stat-sub" x-text="stats.shortCount + ' forced buys'">0 forced buys</div>
            </div>

            <div class="sidebar-stat border-0">
                <div class="stat-label text-warning">Largest Event</div>
                <div class="stat-main text-warning" x-text="formatCurrency(stats.maxUsd)">$0.00</div>
                <div class="stat-sub" x-show="stats.maxOrder" x-text="stats.maxOrder?.baseAsset + ' @ ' + stats.maxOrder?.exName">---</div>
            </div>

            <div class="mt-auto controls-area">
                <button class="btn-pro" :class="wsConnected || demoMode ? 'btn-danger' : 'btn-pro'" @click="wsConnected || demoMode ? disconnect() : connect()">
                    <i class="fas" :class="wsConnected || demoMode ? 'fa-stop-circle' : 'fa-play-circle'"></i>
                    <span x-text="wsConnected || demoMode ? 'Stop Stream' : 'Start Stream'">Start Stream</span>
                </button>
                <button class="btn-pro" @click="clearOrders()">
                    <i class="fas fa-trash-alt"></i> Clear Data
                </button>
            </div>
        </div>

        <!-- Middle Pane: Visualizing Pressure -->
        <div class="pane middle-pane position-relative">
            <div class="pane-header">
                <h2 class="pane-title">Liquidation Pressure (Last 10m)</h2>
                <div class="d-flex gap-2">
                    <span class="badge bg-danger-subtle text-danger" style="font-size: 0.6rem;">LONG PRESSURE</span>
                    <span class="badge bg-success-subtle text-success" style="font-size: 0.6rem;">SHORT PRESSURE</span>
                </div>
            </div>

            <div class="chart-container">
                <div class="offline-overlay" x-show="!wsConnected && !demoMode" x-cloak>
                    <i class="fas fa-wifi-slash fa-2x text-dim"></i>
                    <div class="text-center">
                        <div class="fw-bold">Stream Disconnected</div>
                        <div class="small text-dim">Click "Start Stream" to monitor real-time flows</div>
                    </div>
                </div>
                <canvas id="liquidationChart"></canvas>
            </div>
        </div>

        <!-- Right Sidebar: Live Stream -->
        <div class="pane">
            <div class="pane-header">
                <h2 class="pane-title">Live Order Stream</h2>
                <span class="badge rounded-pill bg-primary" style="font-size: 0.6rem;" x-text="filteredOrders.length">0</span>
            </div>

            <div class="live-feed df-scrollbar">
                <template x-for="order in filteredOrders" :key="order.id">
                    <div class="feed-item">
                        <div class="feed-meta">
                            <span class="feed-symbol" x-text="order.baseAsset">BTC</span>
                            <span class="feed-side" :class="order.side === 1 ? 'side-long' : 'side-short'" x-text="order.side === 1 ? 'FORCE SELL' : 'FORCE BUY'">FORCE SELL</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-end">
                            <div class="small text-dim" style="font-size: 0.7rem;">
                                <span x-text="order.exName">Binance</span> · <span x-text="formatTime(order.time)">12:00:00</span>
                            </div>
                            <div class="feed-val" :class="{ 'val-large': order.volUsd >= 50000 }" x-text="formatCurrency(order.volUsd)">$12,450</div>
                        </div>
                    </div>
                </template>
                
                <div x-show="filteredOrders.length === 0" class="p-5 text-center text-dim">
                    <i class="fas fa-inbox fa-2x mb-2 opacity-25"></i>
                    <p class="small">Waiting for market events...</p>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@section('scripts')
    <script type="module" src="{{ asset('js/liquidations-stream-advanced-controller.js') }}"></script>
@endsection
