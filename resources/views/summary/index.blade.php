@extends('layouts.app')

@section('title', 'Summary | DragonFortune')

@push('head')
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
<style>
    .derivatives-header {
        margin-bottom: 0 !important;
        padding: 0.75rem 1rem !important;
    }

    @media(max-width: 992px){

    /* hilangkan header */
    .table thead{
        display:none;
    }

    /* tiap row jadi card */
    .table tbody tr{
        display:block;
        background:white;
        border-radius:14px;
        padding:14px;
        margin-bottom:14px;
        box-shadow:0 6px 18px rgba(0,0,0,.06);
    }

    /* kolom jadi stacked */
    .table tbody td{
        display:flex;
        justify-content:space-between;
        padding:6px 0;
        border:none;
        font-size:13px;
    }

    /* label kiri */
    .table tbody td::before{
        content:attr(data-label);
        font-weight:600;
        color:#6b7280;
    }

    /* strategy highlight */
    .table tbody td[data-label="Strategy"]{
        display:block;
        font-size:15px;
        margin-bottom:6px;
    }

    .table tbody td[data-label="Strategy"]::before{
        display:none;
    }

    /* balance emphasis */
    .balance-cell{
        font-weight:700;
        color:#111827;
    }

    /* action icon */
    .table tbody td[data-label="Action"]{
        justify-content:flex-end;
    }
}
/* table */
body{
    background:#f5f7fb;
}

/* table wrapper */
.table{
    background:white;
    border-radius:14px;
    overflow:hidden;
}

/* header */
.table thead th{
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.3px;
    color:#6b7280;
    border-bottom:1px solid #eef1f5;
    background:#fafbfc;
    padding:12px 10px;
}

/* sticky header */
.table thead{
    position:sticky;
    top:0;
    z-index:10;
}

/* second header row */
.table thead tr:nth-child(2) th{
    font-weight:600;
    font-size:11px;
    color:#9ca3af;
}

/* body */
.table tbody td{
    font-size:13px;
    padding:12px 10px;
    border-top:1px solid #f1f3f7;
}

/* hover */
.table tbody tr:hover{
    background:#fafafa;
}

/* strategy column */
.strategy-title{
    font-weight:600;
    color:#111827;
}

/* highlight important metrics */
.metric-profit{
    color:#16a34a;
    font-weight:600;
}

.metric-risk{
    color:#dc2626;
    font-weight:600;
}

.metric-neutral{
    color:#374151;
}

/* ratio columns subtle */
.table tbody td:nth-child(10),
.table tbody td:nth-child(11),
.table tbody td:nth-child(12){
    background:#fcfcfd;
}

/* balance column look like wallet */
.balance-cell{
    font-weight:600;
    color:#111827;
}

/* action icon */
.action-btn{
    cursor:pointer;
    color:#9ca3af;
    transition:.15s;
}

.action-btn:hover{
    color:#111827;
    transform:scale(1.01);
}

/* sortable indicator */
.sortable{
    cursor:pointer;
    position:relative;
}

.sortable::after{
    content:"↕";
    font-size:9px;
    position:absolute;
    right:6px;
    opacity:.4;
}

.sort-asc::after{
    content:"↑";
    opacity:1;
}

.sort-desc::after{
    content:"↓";
    opacity:1;
}
/* table */


.strategy-title{ font-weight:600; }

.exchange-icon{
    width:20px;
    margin-right:6px;
}

.coin-icon{
    width:22px;
    margin-right:8px;
}

.metric-green{ color:#16a34a; font-weight:600; }
.metric-red{ color:#dc2626; font-weight:600; }

.summary-card{
    background:white;
    border-radius:14px;
    padding:18px;
    box-shadow:0 4px 14px rgba(0,0,0,.04);
}

.action-btn{
    cursor:pointer;
    color:#6b7280;
}

.action-btn:hover{
    color:#111827;
}

.sa-logo-img {
    width: 30%;
    height: 30%;
    object-fit: cover;
    display: block;
}

.sortable{
    cursor:pointer;
    position:relative;
}

.sortable::after{
    content:"↕";
    position:absolute;
    right:6px;
    font-size:10px;
    opacity:.3;
}

.sort-asc::after{
    content:"↑";
    opacity:1;
}

.sort-desc::after{
    content:"↓";
    opacity:1;
}



/* Soft Clean Technical Theme (Boss's Request) */
.crypto-card {
    background: #ffffff;
    border: 1px solid #f1f5f9; /* slate-100 */
    border-left-width: 4px !important;
    border-radius: 0.75rem; /* rounded-xl */
    padding: 1.25rem; /* p-5 */
    position: relative;
    overflow: hidden;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
    transition: all 0.2s ease;
}

.crypto-card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    border-color: #e2e8f0; /* slate-200 */
}

/* Row 1 Accent Colors */
.card-activity { border-left-color: #3b82f6 !important; } /* blue-500 */
.card-profit { border-left-color: #10b981 !important; }   /* emerald-500 */
.card-risk { border-left-color: #f59e0b !important; }      /* amber-500 */
.card-neutral { border-left-color: #64748b !important; }   /* slate-500 */

/* Typography Utilities */
.text-slate-400 { color: #94a3b8; }
.text-slate-700 { color: #334155; }
.text-slate-800 { color: #1e293b; }
.text-emerald-600 { color: #059669; }
.text-rose-600 { color: #e11d48; }

.tracking-wider { letter-spacing: 0.05em; }
.font-black { font-weight: 900; }
.font-extrabold { font-weight: 800; }


/* start modal */
.trader-modal{
    font-size:14px;
}

.trader-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.coin-icon{
    width:36px;
    opacity:.9;
}

/* metric cards */
.metric-card{
    background:#f9fafb;
    border-radius:14px;
    padding:16px;
    text-align:center;
}

.metric-card h4{
    margin:4px 0 0;
    font-weight:700;
}

.metric-card.profit{
    background:linear-gradient(135deg,#ecfdf5,#ffffff);
    color:#16a34a;
}

.metric-card.risk{
    background:linear-gradient(135deg,#fef2f2,#ffffff);
    color:#dc2626;
}

.metric-card.neutral{
    background:linear-gradient(135deg,#eff6ff,#ffffff);
    color:#2563eb;
}

/* ratio box */
.ratio-box{
    display:flex;
    justify-content:space-between;
    background:#f8fafc;
    border-radius:14px;
    padding:14px;
}

.ratio-box small{
    display:block;
    color:#6b7280;
}

.ratio-box strong{
    font-size:16px;
}

/* stat */
.stat-box{
    background:#f9fafb;
    border-radius:12px;
    padding:14px;
    text-align:center;
}

.stat-box.tp{
    color:#16a34a;
}

.stat-box.sl{
    color:#dc2626;
}
/* end modal */
</style>
@endpush

@section('content')
<div class="container-fluid">

    <div class="derivatives-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <h1 class="mb-0">Strategy Summary</h1>
            </div>
        </div>
    </div>

    <div class="row g-3 pt-4 mb-4" id="summaryCards"></div>
    <div class="d-flex justify-content-between mb-3">
        <div>
            <select id="rowsPerPage" class="form-select form-select-sm" style="width:140px">
                <option value="10">10 rows</option>
                <option value="25">25 rows</option>
                <option value="50">50 rows</option>
                <option value="100">100 rows</option>
                <option selected value="all">Show All</option>
            </select>
        </div>
        <input type="text" id="searchInput" class="form-control w-25" placeholder="Search strategy...">
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white">
                    <tr>
                        <th rowspan="2" class="align-middle">No.</th>
                        <th rowspan="2" class="align-middle">Strategy</th>
                        <th rowspan="2" class="align-middle">Exchange</th>
                        <th rowspan="2" class="align-middle">TF</th>
                        <th rowspan="2" class="sortable align-middle" data-column="cagr">CAGR</th>
                        <th rowspan="2" class="sortable align-middle" data-column="drawdown">Draw</br>down</th>
                        <th rowspan="2" class="sortable align-middle" data-column="prob_sr">PSR</th>
                        <th rowspan="2" class="sortable align-middle" data-column="turnover">Turn</br>over</th>
                        <th colspan="2" class="text-center">Rate</th>
                        <th colspan="2" class="text-center">Ratio</th>
                        <th colspan="3" class="text-center">Total</th>
                        <th colspan="3" class="text-center">Balance</th>
                        <th rowspan="2" class="text-center align-middle" data-column="last_state">Last</br>State</th>
                        {{-- <th rowspan="2" class="text-center"></th> --}}
                    </tr>
                    <tr>
                        <th class="sortable align-middle" data-column="winrate">Win</th>
                        <th class="sortable align-middle" data-column="lossrate">Loss</th>
                        <th class="sortable" data-column="sharpen_ratio">Sharpe</th>
                        <th class="sortable" data-column="sortino_ratio">Sortino</th>
                        <th class="sortable" data-column="total_signal">Signal</th>
                        <th class="sortable" data-column="total_tp">TP</th>
                        <th class="sortable" data-column="total_sl">SL</th>
                        <th class="sortable" data-column="opening_balance">Opening</th>
                        <th class="sortable" data-column="closing_balance">Closing</th>
                        <th class="sortable" data-column="percentage_change">(%)</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>

        <div id="tableLoading" class="text-center py-5">
            <div class="spinner-border text-secondary"></div>
        </div>

        <div class="card-footer bg-white border-0">

            <div class="d-flex justify-content-between align-items-center">

                <small id="tableInfo" class="text-muted"></small>
                <ul class="pagination mb-0" id="pagination"></ul>

            </div>

        </div>
    </div>
</div>
<div class="modal fade" id="detailModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Strategy Detail</h6>
         <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="detailContent"></div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    @php
        $saDashboardJsPath = public_path('js/summary/index.js');
        $saDashboardJsVersion = file_exists($saDashboardJsPath) ? filemtime($saDashboardJsPath) : null;
        $saDashboardJsSrc = asset('js/summary/index.js') . ($saDashboardJsVersion ? ('?v=' . $saDashboardJsVersion) : '');
    @endphp
    <script src="{{ $saDashboardJsSrc }}" defer></script>
@endsection
