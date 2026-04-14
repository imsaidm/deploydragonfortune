<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Santiment Advanced | Dragon Fortune</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.1);
            --glass-blur: blur(20px);
            --btc-color: #f7931a;
            --eth-color: #627eea;
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.6);
        }

        body {
            background-color: #05070a;
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Animated Mesh Background */
        .mesh-gradient {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            filter: blur(100px);
            opacity: 0.3;
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            width: 600px;
            height: 600px;
        }

        .orb-1 {
            background: #8b5cf6;
            top: -200px;
            right: -200px;
            animation: move 20s infinite alternate;
        }

        .orb-2 {
            background: #3b82f6;
            bottom: -200px;
            left: -200px;
            animation: move 25s infinite alternate-reverse;
        }

        @keyframes move {
            from {
                transform: translate(0, 0);
            }

            to {
                transform: translate(-100px, 100px);
            }
        }

        .container {
            max-width: 1100px;
        }

        .glass-header {
            padding: 4rem 0 3rem;
            text-align: center;
        }

        .glass-header h1 {
            font-weight: 800;
            font-size: 3.5rem;
            letter-spacing: -2px;
            background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Filter Section */
        .glass-filter {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 2rem;
            margin-bottom: 3rem;
        }

        .form-control-glass {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            color: white !important;
            border-radius: 14px;
            padding: 0.75rem 1rem;
        }

        .form-control-glass:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: none;
        }

        .btn-glass {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 14px;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-glass:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: white;
            color: white;
            transform: translateY(-2px);
        }

        /* Daily Group Containers */
        .daily-section {
            margin-bottom: 4rem;
        }

        .date-label {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .date-label::after {
            content: "";
            flex: 1;
            height: 1px;
            background: linear-gradient(to right, var(--glass-border), transparent);
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 32px;
            padding: 2rem;
            height: 100%;
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .glass-card:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.3);
            transform: scale(1.02);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
        }

        .asset-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
        }

        .asset-title i {
            font-size: 1.75rem;
        }

        .btc-color {
            color: var(--btc-color);
        }

        .eth-color {
            color: var(--eth-color);
        }

        .metric-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }

        .metric-item:last-child {
            border-bottom: none;
        }

        .metric-info {
            display: flex;
            flex-direction: column;
        }

        .metric-name {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }

        .metric-val {
            font-weight: 700;
            font-size: 1.1rem;
            font-variant-numeric: tabular-nums;
        }

        /* Specific Metric Colors */
        .val-address {
            color: #60a5fa;
        }

        .val-inflow {
            color: #f87171;
        }

        .val-outflow {
            color: #4ade80;
        }

        .val-social {
            color: #fbbf24;
        }

        .val-mvrv {
            color: #f472b6;
        }

        /* Pagination */
        .pagination .page-link {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            color: white;
            border-radius: 12px !important;
            margin: 0 4px;
        }

        .pagination .active .page-link {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>

<body>
    <div class="mesh-gradient">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
    </div>

    <div class="container">
        <header class="glass-header">
            <h1>Santiment</h1>
            <p class="text-secondary">Sentiment & Network Analysis • Dragon Fortune</p>
            <div class="mt-4">
                <a href="{{ url('/fetch-santiment-history') }}" class="btn-glass">
                    <i class="bi bi-arrow-repeat me-2"></i> Sync Santiment
                </a>
            </div>
        </header>

        <!-- Filter -->
        <div class="glass-filter">
            <form action="{{ url('/santiment') }}" method="GET">
                <div class="row g-4 align-items-end justify-content-center">
                    <div class="col-md-4">
                        <label class="small text-secondary mb-2 ms-2">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control form-control-glass" value="{{ request('start_date') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="small text-secondary mb-2 ms-2">Sampai Tanggal</label>
                        <input type="date" name="end_date" class="form-control form-control-glass" value="{{ request('end_date') }}">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-glass w-100">
                            Filter Data
                        </button>
                    </div>
                </div>
            </form>
        </div>

        @foreach($dates as $dateObj)
        @php
        $dateKey = $dateObj->api_timestamp->format('Y-m-d');
        $dateData = $groupedData->get($dateKey);
        @endphp

        <div class="daily-section">
            <div class="date-label">
                <i class="bi bi-calendar3"></i>
                {{ $dateObj->api_timestamp->format('d M Y') }}
            </div>

            <div class="row g-4">
                <!-- Bitcoin -->
                <div class="col-md-6">
                    <div class="glass-card">
                        <div class="asset-title btc-color">
                            <i class="bi bi-currency-bitcoin"></i> Bitcoin
                        </div>

                        @php $btc = $dateData ? $dateData->get('bitcoin') : null; @endphp
                        <div class="metric-list">
                            <div class="metric-item">
                                <div class="metric-info">
                                    <span class="metric-name">Active Addresses</span>
                                    <span class="metric-val val-address">{{ $btc && $btc->get('daily_active_addresses') ? number_format($btc->get('daily_active_addresses')->value) : '-' }}</span>
                                </div>
                                <i class="bi bi-people text-info opacity-50"></i>
                            </div>
                            <div class="metric-item">
                                <div class="metric-info">
                                    <span class="metric-name">Exch. Inflow</span>
                                    <span class="metric-val val-inflow">{{ $btc && $btc->get('exchange_inflow') ? number_format($btc->get('exchange_inflow')->value) : '-' }}</span>
                                </div>
                                <i class="bi bi-box-arrow-in-right text-danger opacity-50"></i>
                            </div>
                            <div class="metric-item">
                                <div class="metric-info">
                                    <span class="metric-name">Exch. Outflow</span>
                                    <span class="metric-val val-outflow">{{ $btc && $btc->get('exchange_outflow') ? number_format($btc->get('exchange_outflow')->value) : '-' }}</span>
                                </div>
                                <i class="bi bi-box-arrow-right text-success opacity-50"></i>
                            </div>
                            <div class="metric-item">
                                <div class="metric-info">
                                    <span class="metric-name">Social Volume</span>
                                    <span class="metric-val val-social">{{ $btc && $btc->get('social_volume_total') ? number_format($btc->get('social_volume_total')->value) : '-' }}</span>
                                </div>
                                <i class="bi bi-chat-dots text-warning opacity-50"></i>
                            </div>
                            <div class="metric-item">
                                <div class="metric-info">
                                    <span class="metric-name">MVRV USD</span>
                                    <span class="metric-val val-mvrv">{{ $btc && $btc->get('mvrv_usd') ? number_format($btc->get('mvrv_usd')->value, 2) : '-' }}</span>
                                </div>
                                <i class="bi bi-graph-up-arrow text-primary opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ethereum -->
                <div class="col-md-6">
                    <div class="glass-card">
                        <div class="asset-title eth-color">
                            <i class="bi bi-layers"></i> Ethereum
                        </div>

                        @php $eth = $dateData ? $dateData->get('ethereum') : null; @endphp
                        <div class="metric-list">
                            <div class="metric-item">
                                <div class="metric-info">
                                    <span class="metric-name">Active Addresses</span>
                                    <span class="metric-val val-address">{{ $eth && $eth->get('daily_active_addresses') ? number_format($eth->get('daily_active_addresses')->value) : '-' }}</span>
                                </div>
                                <i class="bi bi-people text-info opacity-50"></i>
                            </div>
                            <div class="metric-item">
                                <div class="metric-info">
                                    <span class="metric-name">Exch. Inflow</span>
                                    <span class="metric-val val-inflow">{{ $eth && $eth->get('exchange_inflow') ? number_format($eth->get('exchange_inflow')->value) : '-' }}</span>
                                </div>
                                <i class="bi bi-box-arrow-in-right text-danger opacity-50"></i>
                            </div>
                            <div class="metric-item">
                                <div class="metric-info">
                                    <span class="metric-name">Exch. Outflow</span>
                                    <span class="metric-val val-outflow">{{ $eth && $eth->get('exchange_outflow') ? number_format($eth->get('exchange_outflow')->value) : '-' }}</span>
                                </div>
                                <i class="bi bi-box-arrow-right text-success opacity-50"></i>
                            </div>
                            <div class="metric-item">
                                <div class="metric-info">
                                    <span class="metric-name">Social Volume</span>
                                    <span class="metric-val val-social">{{ $eth && $eth->get('social_volume_total') ? number_format($eth->get('social_volume_total')->value) : '-' }}</span>
                                </div>
                                <i class="bi bi-chat-dots text-warning opacity-50"></i>
                            </div>
                            <div class="metric-item">
                                <div class="metric-info">
                                    <span class="metric-name">MVRV USD</span>
                                    <span class="metric-val val-mvrv">{{ $eth && $eth->get('mvrv_usd') ? number_format($eth->get('mvrv_usd')->value, 2) : '-' }}</span>
                                </div>
                                <i class="bi bi-graph-up-arrow text-primary opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

        <div class="d-flex justify-content-center pb-5">
            {{ $dates->links('pagination::bootstrap-5') }}
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: "{{ session('success') }}",
                background: 'rgba(15, 23, 42, 0.9)',
                color: '#fff',
                confirmButtonColor: '#8b5cf6'
            });
            @endif

            @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: "{{ session('error') }}",
                background: 'rgba(15, 23, 42, 0.9)',
                color: '#fff',
                confirmButtonColor: '#ef4444'
            });
            @endif
        });
    </script>
</body>

</html>