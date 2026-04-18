<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CryptoPanic Sentiment | Dragon Fortune</title>

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
            --accent-glow: rgba(59, 130, 246, 0.3);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.6);
        }

        body {
            background-color: #05070a;
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Mesh Gradients for Glass Effect */
        .mesh-gradient {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
        }

        .orb-1 {
            width: 500px;
            height: 500px;
            background: #3b82f6;
            top: -100px;
            left: -100px;
            animation: drift 20s infinite alternate;
        }

        .orb-2 {
            width: 400px;
            height: 400px;
            background: #8b5cf6;
            bottom: -50px;
            right: -50px;
            animation: drift 25s infinite alternate-reverse;
        }

        @keyframes drift {
            from {
                transform: translate(0, 0);
            }

            to {
                transform: translate(100px, 50px);
            }
        }

        .container {
            max-width: 1200px;
            position: relative;
            z-index: 10;
        }

        /* Glass Cards */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }

        .glass-header {
            padding: 4rem 0 2rem;
            text-align: center;
        }

        .glass-header h1 {
            font-weight: 800;
            font-size: 3rem;
            letter-spacing: -1.5px;
            margin-bottom: 0.5rem;
            background: linear-gradient(to bottom, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .glass-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .table-container {
            padding: 1.5rem;
        }

        .glass-table {
            width: 100%;
            color: var(--text-primary);
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .glass-table th {
            padding: 1.25rem 1.5rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 2px;
            border-bottom: 1px solid var(--glass-border);
        }

        .glass-table tr td {
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.02);
            border-top: 1px solid var(--glass-border);
            border-bottom: 1px solid var(--glass-border);
            transition: all 0.3s ease;
        }

        .glass-table tr td:first-child {
            border-left: 1px solid var(--glass-border);
            border-radius: 16px 0 0 16px;
        }

        .glass-table tr td:last-child {
            border-right: 1px solid var(--glass-border);
            border-radius: 0 16px 16px 0;
        }

        .glass-table tr:hover td {
            background: rgba(255, 255, 255, 0.06);
            transform: scale(1.01);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .btn-glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 16px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-glass:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: white;
            color: white;
            box-shadow: 0 0 20px var(--accent-glow);
            transform: translateY(-2px);
        }

        .status-badge {
            padding: 0.5rem 1.25rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Pagination */
        .pagination-container {
            margin: 3rem 0;
            display: flex;
            justify-content: center;
        }

        .pagination .page-link {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            color: white;
            margin: 0 4px;
            border-radius: 12px !important;
            padding: 0.75rem 1.25rem;
        }

        .pagination .active .page-link {
            background: rgba(255, 255, 255, 0.2);
            border-color: white;
        }

        .pagination .disabled .page-link {
            background: rgba(255, 255, 255, 0.01);
            opacity: 0.3;
        }

        /* Filter Section */
        .glass-filter {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
        }

        .form-control-glass {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            color: white !important;
            border-radius: 12px;
            padding: 0.6rem 1rem;
        }

        .form-control-glass:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: none;
        }
        
        .score-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--glass-border);
        }
        .color-panic {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.2);
        }
        .color-calm {
            background: rgba(16, 185, 129, 0.1);
            color: #34d399;
            border-color: rgba(16, 185, 129, 0.2);
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
            <h1>CryptoPanic</h1>
            <p>Berita & Panic Score • Dragon Fortune AI</p>
        </header>

        <!-- Filter -->
        <div class="glass-filter">
            <form action="{{ url('/crypto-panic') }}" method="GET">
                <div class="row g-3 align-items-end justify-content-center">
                    <div class="col-md-3">
                        <label class="small text-secondary mb-2 ms-1">Mulai Tanggal</label>
                        <input type="date" name="start_date" class="form-control form-control-glass" value="{{ request('start_date') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="small text-secondary mb-2 ms-1">Sampai Tanggal</label>
                        <input type="date" name="end_date" class="form-control form-control-glass" value="{{ request('end_date') }}">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-glass w-100">
                            <i class="bi bi-filter me-1"></i> Filter
                        </button>
                    </div>
                    @if(request()->filled('start_date') || request()->filled('end_date'))
                    <div class="col-md-2">
                        <a href="{{ url('/crypto-panic') }}" class="btn btn-link text-secondary text-decoration-none w-100">
                            Reset
                        </a>
                    </div>
                    @endif
                </div>
            </form>
        </div>

        <div class="glass-card mb-5">
            <div class="d-flex justify-content-between align-items-center p-4 pb-0">
                <h5 class="m-0 fw-bold"><i class="bi bi-newspaper me-2"></i>Berita Terbaru</h5>
                <a href="{{ url('/fetch-crypto-panic') }}" class="btn-glass">
                    <i class="bi bi-arrow-repeat"></i> Sync Data
                </a>
            </div>

            <div class="table-container">
                <div class="table-responsive">
                    <table class="glass-table">
                        <thead>
                            <tr>
                                <th>Waktu Publicasi</th>
                                <th>Berita</th>
                                <th class="text-center">Panic Score</th>
                                <th class="text-end">Currencies</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sentiments as $data)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ \Carbon\Carbon::parse($data->published_at)->format('d M Y H:i') }}</div>
                                    <small class="text-secondary">{{ \Carbon\Carbon::parse($data->published_at)->diffForHumans() }}</small>
                                </td>
                                <td>
                                    <div class="fw-bold"><a href="{{ $data->url }}" target="_blank" class="text-white text-decoration-none">{{ $data->title }}</a></div>
                                    <small class="text-secondary">{{ $data->domain }}</small>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-center">
                                        <div class="score-circle {{ $data->panic_score > 0 ? 'color-panic' : 'color-calm' }}">
                                            {{ $data->panic_score ?? 0 }}
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <span class="status-badge bg-secondary bg-opacity-25 text-white border-0">
                                        {{ $data->currencies ?? '-' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pagination-container">
                    {{ $sentiments->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toastConfig = {
                background: 'rgba(15, 23, 42, 0.9)',
                color: '#fff',
                backdrop: 'blur(5px)',
                customClass: {
                    popup: 'glass-card'
                }
            };

            @if(session('success'))
            Swal.fire({
                ...toastConfig,
                icon: 'success',
                title: 'Berhasil',
                text: '{!! session('success') !!}',
                confirmButtonColor: '#3b82f6'
            });
            @endif

            @if(session('error'))
            Swal.fire({
                ...toastConfig,
                icon: 'error',
                title: 'Gagal',
                text: '{!! session('error') !!}',
                confirmButtonColor: '#ef4444'
            });
            @endif
        });
    </script>
</body>

</html>
