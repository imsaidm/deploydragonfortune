(() => {
  const onReady = (fn) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
      return;
    }
    fn();
  };

  const onWindowLoaded = (fn) => {
    if (document.readyState === 'complete') {
      setTimeout(fn, 0);
      return;
    }
    window.addEventListener('load', () => setTimeout(fn, 0), { once: true });
  };

  const TraderTable = (() => {

    let balanceRequests = [];
    let state = {
        page: 1,
        search: "",
        order_by: "id",
        order_dir: "asc",
        per_page: "all"
    };

    let lastLoadedData = [];

    const init = () => {
        $('.df-sidebar').addClass('collapsed');
        bindSearch();
        bindSorting(); 
        loadData();
    };

    const loadData = () => {

        $("#tableLoading").show();

        $.get("/summary/dtable", state, res => {

            lastLoadedData = res.data;

            renderSummary(res);
            renderTable(res.data);
            renderPagination(res);

            $("#tableLoading").hide();

            afterTableLoaded(res.data); 
        });
    };

    const renderSummary = data => {
        const creatorSummary = data.creator_summary || [];
        const $container = $("#summaryCards");
        $container.empty();

        const colorClasses = ['card-activity', 'card-profit', 'card-risk', 'card-neutral'];

        creatorSummary.forEach((item, index) => {
            const creatorName = item.creator || 'SYSTEM';
            const colorClass = colorClasses[index % colorClasses.length];

            const o = Number(item.total_opening_balance || 0);
            const c = Number(item.total_closing_balance || 0);
            let percentage = 0;
            if (o > 0) {
                percentage = ((c - o) / o) * 100;
            }
            const percentageColor = percentage >= 0 ? 'text-emerald-600' : 'text-rose-600';
            const percentageSymbol = percentage >= 0 ? '+' : '';

            $container.append(`
                <div class="col-md-3">
                    <div class="crypto-card ${colorClass}" style="display: flex; flex-direction: column; align-items: center; text-align: center; padding: 14px 18px;">
                        
                        <!-- Row 1: Name -->
                        <div class="text-slate-700 font-extrabold uppercase tracking-wider mb-2" style="font-size: 14px;">
                            ${creatorName}
                        </div>
                        
                        <!-- Row 2: Strategy & Signals (CENTERED) -->
                        <div class="d-flex gap-5 mb-2 mt-1">
                            <div>
                                <span class="text-slate-800 font-black mono" style="font-size: 36px; line-height: 1;">${Number(item.total_methods)}</span>
                                <div class="text-slate-400 font-black uppercase" style="font-size: 13px; margin-top: 2px; letter-spacing: 0.05em;">strategy</div>
                            </div>
                            <div>
                                <span class="text-slate-800 font-black mono" style="font-size: 36px; line-height: 1;">${Number(item.total_signals).toLocaleString()}</span>
                                <div class="text-slate-400 font-black uppercase" style="font-size: 13px; margin-top: 2px; letter-spacing: 0.05em;">signals</div>
                            </div>
                        </div>

                        <hr class="border-slate-100 w-75 mb-3" style="margin: 0 auto;">

                        <!-- Row 3: TP & SL (CENTERED) -->
                        <div class="d-flex gap-5 mb-2">
                            <div class="d-flex align-items-center">
                                <span class="text-slate-800 font-black" style="font-size: 14px;">TP:</span>
                                <span class="text-emerald-600 font-black mono ml-2" style="font-size: 18px;">${Number(item.total_tp).toLocaleString()}x</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="text-slate-800 font-black" style="font-size: 14px;">SL:</span>
                                <span class="text-rose-600 font-black mono ml-2" style="font-size: 18px;">${Number(item.total_sl).toLocaleString()}x</span>
                            </div>
                        </div>

                        <!-- Row 4: Balances & PNL -->
                        <div class="w-100 px-3 py-2 rounded mt-2" style="background-color: #f8fafc; border: 1px solid #f1f5f9;">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-slate-500 font-bold" style="font-size: 12px;">Open:</span>
                                <span class="text-slate-800 font-black mono" style="font-size: 13px;">$${o.toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-slate-500 font-bold" style="font-size: 12px;">Close:</span>
                                <span class="text-slate-800 font-black mono" style="font-size: 13px;">$${c.toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}</span>
                            </div>
                            <div class="w-100 text-center border-top pt-2" style="border-color: #e2e8f0 !important;">
                                <div class="text-slate-500 font-bold" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;">Total PNL</div>
                                <div class="${percentageColor} font-black mono mt-1" style="font-size: 16px;">
                                    ${percentageSymbol}${percentage.toFixed(2)}%
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            `);
        });
    };

    const renderTable = data => {

        $("#tableBody").empty();

        const binanceLogo = "https://dragonfortune.ai/images/binancelogo.png";
        const bybitLogo   = "https://dragonfortune.ai/images/bybitlogo.png";

        data.forEach( (row, index) => {

            let imgCoin = "#";
            let imgExchange = "#";
            let symbol = row.pair.replace('USDT','').toLowerCase();
            let exchange = row.exchange.toLowerCase();

            imgCoin = ( symbol == "eth" ? imgCoin= "https://cryptologos.cc/logos/ethereum-eth-logo.png?v=040" : imgCoin="https://cryptologos.cc/logos/bitcoin-btc-logo.png?v=040");
            imgExchange = ( exchange == "bybit" ? bybitLogo : binanceLogo);
            $("#tableBody").append(`
                <tr class="action-btn detail-btn" data-id="${row.id}">
                    <td data-label="No.">${index+1}</td>

                    <td data-label="Strategy">
                        <div class="d-flex align-items-center">
                            <img class="coin-icon" src="${imgCoin}">
                            <div>
                                <div class="strategy-title">${row.nama_metode}</div>
                                <small class="text-muted">Created By ${row.creator}</small>
                            </div>
                        </div>
                    </td>

                    <td data-label="Exchange">
                        <img class="exchange-icon" src="${imgExchange}">
                        ${row.exchange}
                    </td>

                    <td data-label="TF">${row.tf}</td>
                    <td data-label="CAGR" class="metric-green">${Number(row.cagr).toFixed(2)}%</td>                    
                    <td data-label="Drawdown" class="metric-red">${Number(row.drawdown).toFixed(2)}%</td>
                    <td data-label="PSR">${Number(row.prob_sr).toFixed(2)}%</td>
                    <td data-label="Turnover">${Number(row.turnover).toFixed(2)}%</td>
                    <td data-label="Win">${Number(row.winrate).toFixed(2)}%</td>
                    <td data-label="Loss">${Number(row.lossrate).toFixed(2)}%</td>
                    <td data-label="Sharpe">${Number(row.sharpen_ratio).toFixed(2)}%</td>
                    <td data-label="Sortino">${Number(row.sortino_ratio).toFixed(2)}%</td>
                    <td data-label="Signal">${Number(row.total_signal).toFixed(0)}x</td>
                    <td data-label="TP">${Number(row.total_tp).toFixed(0)}x</td>
                    <td data-label="SL">${Number(row.total_sl).toFixed(0)}x</td>

                    <td data-label="Opening" class="balance-cell">${Number(row.opening_balance).toFixed(1)}</td>
                    <td data-label="Closing" class="balance-cell" id="balance-${row.id}">${Number(row.closing_balance).toFixed(1)}</td>
                    <td data-label="(%)" class="balance-cell" style="${(row.percentage_change >= 0 ? "color: green" : "color: red" )};">${Number(row.percentage_change).toFixed(2)}%</td>
                    <td data-label="Last State">${row.last_state}</td>


                </tr>
            `);

            // <td>
            //     <span class="action-btn detail-btn" data-id="${row.id}">
            //         <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
            //             <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
            //             <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
            //         </svg>
            //     </span>
            // </td>
        });
    };

    const renderPagination = meta => {

        if(state.per_page == 9999){
            $("#pagination").hide();
            $("#tableInfo").text(
                `Showing all ${meta.total} strategies`
            );
            return;
        }

        $("#pagination").show();        
        $("#pagination").empty();

        for(let i=1;i<=meta.last_page;i++){

            $("#pagination").append(`
                <li class="page-item ${i===meta.current_page?'active':''}">
                    <a class="page-link page-btn" data-page="${i}" href="#">${i}</a>
                </li>
            `);
        }

        $("#tableInfo").text(
            `Showing ${meta.from} - ${meta.to} of ${meta.total}`
        );
    };

    const bindSearch = () => {
        
        $("#searchInput").on("keyup", e => {
            cancelBalanceRequests();
            const $el = $(this);
            clearTimeout($el.data('searchTimer'));

            const triggerSearch = () => {
                state.search = e.target.value;
                state.page = 1;
                
                loadData();
            };

            if (e.key === "Enter") {
                triggerSearch();
            } else {
                $el.data('searchTimer', setTimeout(triggerSearch, 1000));
            }
        });
    };

    $(document).on("click",".page-btn",function(){
        cancelBalanceRequests();
        state.page = $(this).data("page");
        loadData();
    });

    $("#rowsPerPage").on("change", function(){
        cancelBalanceRequests();
        let val = $(this).val();
        state.per_page = val === "all" ? 9999 : val;
        state.page = 1;
        loadData();
    });

    $(document).on("click", ".detail-btn", function(){
        const id = $(this).data("id");
        const data = lastLoadedData.find(x => x.id == id);
        $("#detailContent").html(`    
            <div class="trader-modal">
                <div class="trader-header">
                    <div>
                        <h6 class="mb-1">${data.nama_metode}</h6>
                        <small class="text-muted">${data.exchange} • ${data.pair} • ${data.tf}</small>
                    </div>
                    <div class="mt-4 text-end">
                        <a href="${data.url}" target="_blank" class="btn btn-sm btn-light border">
                            Open Backtest →
                        </a>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <div class="metric-card profit">
                            <small>CAGR</small>
                            <h4>${Number(data.cagr).toFixed(2)}%</h4>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="metric-card neutral">
                            <small>Winrate</small>
                            <h4>${Number(data.winrate).toFixed(1)}%</h4>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="metric-card risk">
                            <small>Lossrate</small>
                            <h4>${Number(data.lossrate).toFixed(1)}%</h4>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <div class="stat-box">
                            <small>Total Signal</small>
                            <strong>${Number(data.total_signal).toLocaleString()}</strong>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="stat-box tp">
                            <small>Total TP</small>
                            <strong>${data.total_tp}</strong>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="stat-box sl">
                            <small>Total SL</small>
                            <strong>${data.total_sl}</strong>
                        </div>
                    </div>
                </div>

                <div class="trader-header mt-2">
                    <div>
                        <h6 class="mb-1">Description</h6>
                    </div>
                </div>

                <div class="row g-3 p-1">
                   <p>${data.description ?? "-"}</p>
                </div>

            </div>
        `);
        $("#detailModal").modal("show");
    });

    const afterTableLoaded = async data => {

        cancelBalanceRequests();
        for (const row of data) {
            await getBalanceByMethodId(row);
        }
    };

    const getBalanceByMethodId = async (data) => {

        let endpoint;
        let exchange_info = detectExchangeInfo(data);
        endpoint = `/summary/account?exchange=${exchange_info.exchange}&market_type=${exchange_info.type}&method_id=${data.id}`;
        const request_balance = $.ajax({
            url: endpoint,
            method: "GET",
            async: true
        });

        balanceRequests.push(request_balance);

        request_balance.done(res => {
            $(`#balance-${data.id}`).html(`
                <span class="fw-semibold">${Number(res.summary["total_usdt"]).toFixed(1)}</span>
            `);
        });

        request_balance.fail((xhr, status) => {
            if(status !== "abort"){
                $(`#balance-${id}`).html(`
                    <span>-</span>
                `);
            }
        });
    };

    const detectExchangeInfo = (data) => {

        const exchange = (data.exchange || 'binance').toLowerCase().trim();
        const name = (data.nama_metode || '').toLowerCase();

        if (exchange === 'bybit') {
          if (name.includes('linear') || name.includes('futures') || name.includes('future')) {
            type = 'linear';
          } else if (name.includes('inverse')) {
            type = 'inverse';
          } else {
            type = 'spot';
          }
        } else {
          if (name.includes('futures') || name.includes('future')) {
            type = 'futures';
          } else {
            type = 'spot';
          }
        }

        return { exchange, type };
    };

    const bindSorting = () => {

        $(".sortable").on("click", function () {
            const column = $(this).data("column");
            cancelBalanceRequests();

            if (state.order_by === column) {
                state.order_dir = state.order_dir === "asc" ? "desc" : "asc";
            } else {
                state.order_by = column;
                state.order_dir = "asc";
            }

            updateSortIcon(column);

            state.page = 1;
            loadData();
        });
    };

    const updateSortIcon = column => {
        $(".sortable").removeClass("sort-asc sort-desc");
        if(state.order_dir === "asc")
            $(`.sortable[data-column="${column}"]`).addClass("sort-asc");
        else
            $(`.sortable[data-column="${column}"]`).addClass("sort-desc");
    };

    const cancelBalanceRequests = () => {
        console.log("cancel balance");
        balanceRequests.forEach(req => req.abort());
        balanceRequests = [];
    };

    return { init };

  })();

  onReady(() => {
    
    onWindowLoaded(() => {
        TraderTable.init();
    });
  });
})();
