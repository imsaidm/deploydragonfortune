const TraderTable = (() => {

    let balanceRequests = [];
    let state = {
        page: 1,
        search: "",
        order_by: "id",
        order_dir: "asc",
        per_page: 10
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

        const colorClasses = ['card-activity', 'card-profit', 'card-risk', 'card-profit'];

        creatorSummary.forEach((item, index) => {
            const creatorName = item.creator || 'SYSTEM';
            const colorClass = colorClasses[index % colorClasses.length];

            $container.append(`
                <div class="col-md-3">
                    <div class="crypto-card ${colorClass}" style="padding: 18px 22px; min-height: 130px; display: flex; flex-direction: column; justify-content: center;">
                        <div style="font-size: 15px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 2px;">
                            ${creatorName}
                        </div>
                        <div style="font-size: 42px; font-weight: 900; line-height: 1; color: #111827; margin-bottom: 12px; display: flex; align-items: baseline; gap: 8px;">
                            ${Number(item.total_methods)}
                            <span style="font-size: 14px; font-weight: 600; opacity: 0.5; text-transform: uppercase;">Strategy</span>
                        </div>
                        <div style="font-size: 15px; font-weight: 700; display: flex; justify-content: space-between; border-top: 1px solid rgba(0,0,0,0.06); padding-top: 12px; margin-top: 2px;">
                            <div>Sig: <span style="color: #18181b;">${Number(item.total_signals).toLocaleString()}</span></div>
                            <div>TP: <span class="text-success">${Number(item.total_tp).toLocaleString()}</span></div>
                            <div>SL: <span class="text-danger">${Number(item.total_sl).toLocaleString()}</span></div>
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

        data.forEach(row => {

            let imgCoin = "#";
            let imgExchange = "#";
            let symbol = row.pair.replace('USDT','').toLowerCase();
            let exchange = row.exchange.toLowerCase();

            imgCoin = ( symbol == "eth" ? imgCoin= "https://cryptologos.cc/logos/ethereum-eth-logo.png?v=040" : imgCoin="https://cryptologos.cc/logos/bitcoin-btc-logo.png?v=040");
            imgExchange = ( exchange == "bybit" ? bybitLogo : binanceLogo);
            $("#tableBody").append(`
                <tr>

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
                    <td data-label="Information">${Number(row.information_ratio).toFixed(2)}%</td>
                    <td data-label="Signal">${Number(row.total_signal).toFixed(0)}x</td>
                    <td data-label="TP">${Number(row.total_tp).toFixed(0)}x</td>
                    <td data-label="SL">${Number(row.total_sl).toFixed(0)}x</td>

                    <td data-label="Opening" class="balance-cell">${Number(row.opening_balance).toFixed(1)}</td>
                    <td data-label="Closing" class="balance-cell" id="balance-${row.id}">${Number(row.closing_balance).toFixed(1)}</td>

                    <td>
                        <span class="action-btn detail-btn" data-id="${row.id}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                            </svg>
                        </span>
                    </td>

                </tr>
            `);
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
            <h5>${data.nama_metode}</h5>
            <p>CAGR: ${data.cagr}%</p>
            <p>Sharpe: ${data.sharpen_ratio}</p>
            <p>Sortino: ${data.sortino_ratio}</p>
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

$(document).ready(()=> TraderTable.init());
