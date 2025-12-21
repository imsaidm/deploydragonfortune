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

  onReady(() => {
    const byId = (id) => document.getElementById(id);

    const apiBaseEl = byId('sa-api-base');
    const docsLinkEl = byId('sa-open-docs');
    const methodRunningEl = byId('sa-method-running');
    const methodMetaEl = byId('sa-method-meta');
    const methodBacktestEl = byId('sa-method-backtest');

    const healthEl = byId('sa-health');
    const healthMetaEl = byId('sa-health-meta');

    const methodSelect = byId('sa-method-select');
    const methodDetailButton = byId('sa-method-detail');
    const methodStatusEl = byId('sa-method-status');

    const fromInput = byId('sa-from');
    const toInput = byId('sa-to');
    const limitInput = byId('sa-limit');
    const offsetInput = byId('sa-offset');

    const kpiGridEl = byId('sa-kpi-grid');

    const qcDetailButton = byId('sa-qc-detail');
    const binanceDetailButton = byId('sa-binance-detail');
    const detailPanel = byId('sa-detail');
    const qcDetailPanel = byId('sa-detail-qc');
    const binanceDetailPanel = byId('sa-detail-binance');

    const binanceLiveEl = byId('sa-binance-live');
    const binanceAccountEl = byId('sa-binance-account');
    const binanceTotalEl = byId('sa-binance-total');
    const binanceAvailableEl = byId('sa-binance-available');
    const binanceLockedEl = byId('sa-binance-locked');
    const binanceBtcEl = byId('sa-binance-btc');
    const binanceAssetsEl = byId('sa-binance-assets');
    const binanceUpdatedEl = byId('sa-binance-updated');
    const binanceHintEl = byId('sa-binance-hint');

    const binanceSymbolInput = byId('sa-binance-symbol');
    const binanceAssetsStatus = byId('sa-binance-assets-status');
    const binanceAssetsBody = byId('sa-binance-assets-body');
    const binanceOpenOrdersStatus = byId('sa-binance-open-orders-status');
    const binanceOpenOrdersBody = byId('sa-binance-open-orders-body');
    const binanceOrdersStatus = byId('sa-binance-orders-status');
    const binanceOrdersBody = byId('sa-binance-orders-body');
    const binanceTradesStatus = byId('sa-binance-trades-status');
    const binanceTradesBody = byId('sa-binance-trades-body');

    const positionsStatus = byId('sa-positions-status');
    const positionsBody = byId('sa-positions-body');

    const ordersType = byId('sa-orders-type');
    const ordersJenis = byId('sa-orders-jenis');
    const ordersStatus = byId('sa-orders-status');
    const ordersBody = byId('sa-orders-body');

    const signalsType = byId('sa-signals-type');
    const signalsJenis = byId('sa-signals-jenis');
    const signalsStatus = byId('sa-signals-status');
    const signalsBody = byId('sa-signals-body');

    const remindersStatus = byId('sa-reminders-status');
    const remindersBody = byId('sa-reminders-body');

    const logsStatus = byId('sa-logs-status');
    const logsBody = byId('sa-logs-body');

    const countPositionsEl = byId('sa-count-positions');
    const countOrdersEl = byId('sa-count-orders');
    const countSignalsEl = byId('sa-count-signals');
    const countRemindersEl = byId('sa-count-reminders');
    const countLogsEl = byId('sa-count-logs');
    const countBinanceAssetsEl = byId('sa-count-binance-assets');
    const countBinanceOpenOrdersEl = byId('sa-count-binance-open-orders');
    const countBinanceOrdersEl = byId('sa-count-binance-orders');
    const countBinanceTradesEl = byId('sa-count-binance-trades');

    const modalEl = byId('sa-modal');
    const modalTitleEl = byId('sa-modal-title');
    const modalPreEl = byId('sa-modal-pre');
    const modalCloseBtn = byId('sa-modal-close');

    if (!apiBaseEl || !methodSelect || !healthEl) return;

    const metaApiBase =
      document.querySelector('meta[name="api-base-url"]')?.getAttribute('content') ||
      document.querySelector('meta[name="apiBaseUrl"]')?.getAttribute('content') ||
      '';
    const apiBase = (metaApiBase || 'https://test.dragonfortune.ai').replace(/\/$/, '');

    apiBaseEl.textContent = apiBase;
    if (docsLinkEl) docsLinkEl.href = apiBase + '/docs';

    const state = {
      methods: [],
      selectedMethodId: null,
      methodDetail: null,
      methodMeta: null,
      latestOrders: [],
      latestSignals: [],
      latestTrades: [],
      latestPositions: [],
      latestReminders: [],
      latestLogs: [],
      binanceSummary: null,
      binanceError: null,
      binanceHint: null,
      binanceMode: null,
      binanceBaseUrl: null,
      detailSource: null,
      binanceTab: 'assets',
      binanceOpenOrders: [],
      binanceOrders: [],
      binanceTrades: [],
      autoTimer: null,
    };

    const escapeText = (value) => {
      if (value === null || value === undefined) return '';
      return String(value);
    };

    const normalize = (value) => String(value ?? '').trim().toLowerCase();

    const parseExtra = (extra) => {
      if (!extra) return null;
      try {
        const obj = typeof extra === 'string' ? JSON.parse(extra) : extra;
        if (!obj || typeof obj !== 'object') return null;
        return obj;
      } catch {
        return null;
      }
    };

    const formatNumber = (value, decimals = 2) => {
      if (value === null || value === undefined || value === '') return '-';
      const n = Number(value);
      if (!Number.isFinite(n)) return '-';
      return n.toFixed(decimals);
    };

    const formatPercent = (value) => {
      if (value === null || value === undefined || value === '') return '-';
      const n = Number(value);
      if (!Number.isFinite(n)) return '-';
      return (n > 1 ? n : n * 100).toFixed(2) + '%';
    };

    const formatRatioPercent = (value) => {
      if (value === null || value === undefined || value === '') return '-';
      const n = Number(value);
      if (!Number.isFinite(n)) return '-';
      return (n * 100).toFixed(2) + '%';
    };

    const toApiDatetime = (dtLocalValue) => {
      if (!dtLocalValue) return '';
      const v = String(dtLocalValue).trim();
      if (!v.includes('T')) return v;
      return v.replace('T', ' ') + ':00';
    };

    const formatEpochMs = (value) => {
      const n = Number(value);
      if (!Number.isFinite(n) || n <= 0) return '-';
      const dt = new Date(n);
      if (Number.isNaN(dt.getTime())) return '-';
      return dt.toISOString().replace('T', ' ').replace('Z', '');
    };

    const renderCount = (el, value) => {
      if (!el) return;
      const n = Number(value);
      el.textContent = Number.isFinite(n) && n >= 0 ? `(${n})` : '(0)';
    };

    const updateTabCounts = () => {
      renderCount(countPositionsEl, Array.isArray(state.latestPositions) ? state.latestPositions.length : 0);
      renderCount(countOrdersEl, Array.isArray(state.latestOrders) ? state.latestOrders.length : 0);
      renderCount(countSignalsEl, Array.isArray(state.latestSignals) ? state.latestSignals.length : 0);
      renderCount(countRemindersEl, Array.isArray(state.latestReminders) ? state.latestReminders.length : 0);
      renderCount(countLogsEl, Array.isArray(state.latestLogs) ? state.latestLogs.length : 0);

      const assetCountFromSummary = Number(state.binanceSummary?.summary?.asset_count);
      const assetsCount = Number.isFinite(assetCountFromSummary)
        ? assetCountFromSummary
        : Array.isArray(state.binanceSummary?.assets)
          ? state.binanceSummary.assets.length
          : 0;

      renderCount(countBinanceAssetsEl, assetsCount);
      renderCount(
        countBinanceOpenOrdersEl,
        Array.isArray(state.binanceOpenOrders) ? state.binanceOpenOrders.length : 0,
      );
      renderCount(
        countBinanceOrdersEl,
        Array.isArray(state.binanceOrders) ? state.binanceOrders.length : 0,
      );
      renderCount(
        countBinanceTradesEl,
        Array.isArray(state.binanceTrades) ? state.binanceTrades.length : 0,
      );
    };

    const buildBinanceUrl = (path, params = {}) => {
      const url = new URL(window.location.origin + path);

      Object.entries(params || {}).forEach(([key, value]) => {
        if (value === null || value === undefined) return;
        const vv = String(value).trim();
        if (vv === '') return;
        url.searchParams.set(key, vv);
      });

      return url.toString();
    };

    const fetchJson = async (path, params = {}) => {
      const url = new URL(apiBase + path);
      Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === undefined) return;
        const vv = String(value).trim();
        if (vv === '') return;
        url.searchParams.set(key, vv);
      });

      const res = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
      const text = await res.text();
      let json = null;
      try {
        json = JSON.parse(text);
      } catch (e) {
        json = null;
      }
      if (!res.ok) throw new Error(text || `HTTP ${res.status}`);
      return json ?? text;
    };

    const fetchLocalJson = async (path) => {
      const url = path.startsWith('http')
        ? path
        : `${window.location.origin}${path.startsWith('/') ? '' : '/'}${path}`;
      const res = await fetch(url, { headers: { Accept: 'application/json' } });
      const text = await res.text();
      let json = null;
      try {
        json = JSON.parse(text);
      } catch (e) {
        json = null;
      }
      if (!res.ok) {
        const msg =
          json && typeof json === 'object'
            ? [json.error || json.message, json.hint].filter(Boolean).join(' ')
            : text;
        throw new Error(msg || `HTTP ${res.status}`);
      }
      return json ?? text;
    };

    const openModal = (title, content) => {
      if (!modalEl || !modalTitleEl || !modalPreEl) return;
      modalTitleEl.textContent = title || 'Detail';
      modalPreEl.textContent =
        typeof content === 'string' ? content : JSON.stringify(content, null, 2);
      modalEl.classList.add('is-open');
      modalEl.setAttribute('aria-hidden', 'false');
    };

    const closeModal = () => {
      if (!modalEl) return;
      modalEl.classList.remove('is-open');
      modalEl.setAttribute('aria-hidden', 'true');
    };

    if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeModal);
    if (modalEl) {
      modalEl.addEventListener('click', (e) => {
        const target = e.target;
        if (target && target.getAttribute && target.getAttribute('data-close') === '1') {
          closeModal();
        }
      });
    }
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeModal();
    });

    const setMethodStatus = (text) => {
      if (!methodStatusEl) return;
      methodStatusEl.textContent = text || '';
    };

    const setTableStatus = (el, text) => {
      if (!el) return;
      el.textContent = text || '';
    };

    const clearTbody = (tbody, colSpan, message) => {
      tbody.innerHTML = '';
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = colSpan;
      td.className = 'text-secondary py-3';
      td.textContent = message || 'No data';
      tr.appendChild(td);
      tbody.appendChild(tr);
    };

    const getGlobalQuery = () => {
      const limit = Number(limitInput?.value ?? 50) || 50;
      const offset = Number(offsetInput?.value ?? 0) || 0;
      const from = toApiDatetime(fromInput?.value ?? '');
      const to = toApiDatetime(toInput?.value ?? '');
      const id_method = state.selectedMethodId || '';

      return { id_method, from_datetime: from, to_datetime: to, limit, offset };
    };

    const renderMethods = () => {
      methodSelect.innerHTML = '';

      state.methods.forEach((m) => {
        const opt = document.createElement('option');
        opt.value = String(m.id);

        const extra = parseExtra(m?.kpi_extra);
        const extraMap = buildExtraMap(extra);
        const running = resolveRunningStatus(m, extraMap).label;
        const tag = running === 'Running' ? 'RUN' : running === 'Not Running' ? 'OFF' : 'UNK';

        opt.textContent = `[${tag}] ${escapeText(m.nama_metode || 'Method')} (#${m.id})`;
        methodSelect.appendChild(opt);
      });

      if (state.methods.length === 0) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '(no methods)';
        methodSelect.appendChild(opt);
      }
    };

    const extractMethodMeta = (method) => {
      const pair = method?.pair ?? method?.symbol ?? method?.ticker ?? null;
      const tf = method?.tf ?? method?.timeframe ?? method?.interval ?? null;
      const exchange = method?.exchange ?? method?.broker ?? null;

      const extra = method?.kpi_extra;
      if (!extra) return { symbol: pair, timeframe: tf, exchange, startingBalance: null };

      try {
        const obj = typeof extra === 'string' ? JSON.parse(extra) : extra;
        if (!obj || typeof obj !== 'object') {
          return { symbol: pair, timeframe: tf, exchange, startingBalance: null };
        }

        const normalized = new Map(
          Object.entries(obj).map(([k, v]) => [normalize(k), v]),
        );
        const pick = (...keys) => {
          for (const key of keys) {
            const found = normalized.get(normalize(key));
            if (found !== undefined && found !== null && String(found).trim() !== '') return found;
          }
          return null;
        };

        return {
          symbol: pair ?? pick('pair', 'symbol', 'ticker', 'instrument'),
          timeframe: tf ?? pick('timeframe', 'time frame', 'tf', 'interval'),
          exchange: exchange ?? pick('exchange', 'broker', 'venue'),
          startingBalance: pick('starting balance', 'starting_balance', 'saldo', 'balance'),
        };
      } catch {
        return { symbol: pair, timeframe: tf, exchange, startingBalance: null };
      }
    };

    const buildExtraMap = (extra) => {
      if (!extra) return new Map();
      return new Map(Object.entries(extra).map(([k, v]) => [normalize(k), v]));
    };

    const pickExtra = (extraMap, ...keys) => {
      for (const key of keys) {
        const found = extraMap.get(normalize(key));
        if (found !== undefined && found !== null && String(found).trim() !== '') return found;
      }
      return null;
    };

    const resolveRunningStatus = (method, extraMap) => {
      const onactiveRaw = method?.onactive;
      const onactiveVal = normalize(onactiveRaw);
      if (
        onactiveRaw === true ||
        onactiveRaw === 1 ||
        ['1', 'true', 'yes', 'y', 'running', 'active', 'on'].includes(onactiveVal)
      ) {
        return { label: 'Running', className: 'text-bg-success' };
      }
      if (
        onactiveRaw === false ||
        onactiveRaw === 0 ||
        ['0', 'false', 'no', 'n', 'not running', 'stopped', 'inactive', 'off', 'pause', 'paused'].includes(
          onactiveVal,
        )
      ) {
        return { label: 'Not Running', className: 'text-bg-danger' };
      }

      const raw =
        method?.is_running ??
        method?.running ??
        method?.status ??
        method?.state ??
        pickExtra(extraMap, 'running', 'is_running', 'status', 'state');

      const val = normalize(raw);
      if (raw === true || raw === 1 || ['running', 'live', 'active', 'on', 'true'].includes(val)) {
        return { label: 'Running', className: 'text-bg-success' };
      }
      if (
        raw === false ||
        raw === 0 ||
        ['paused', 'stopped', 'inactive', 'off', 'false', 'not running'].includes(val)
      ) {
        return { label: 'Not Running', className: 'text-bg-danger' };
      }
      return { label: 'Unknown', className: 'text-bg-secondary' };
    };

    const resolveMethodTimestamp = (method, extraMap) => {
      const raw =
        method?.last_run_at ??
        method?.last_run ??
        method?.running_since ??
        method?.updated_at ??
        method?.created_at ??
        pickExtra(
          extraMap,
          'last_run',
          'last_run_at',
          'last_run_time',
          'running_since',
          'updated_at',
          'created_at',
        );

      if (raw === undefined || raw === null || String(raw).trim() === '') return null;
      return String(raw);
    };

    const getOrderSymbol = (row) =>
      row?.symbol ?? row?.ticker ?? row?.pair ?? row?.instrument ?? state.methodMeta?.symbol ?? '-';

    const getOrderDatetime = (row) =>
      escapeText(row.datetime ?? row.date_time ?? row.created_at ?? '-');

    const sortOrdersAsc = (items) =>
      items
        .slice()
        .sort((a, b) => getOrderDatetime(a).localeCompare(getOrderDatetime(b)));

    const computeTrades = (orders) => {
      const sorted = sortOrdersAsc(orders);
      const openByKey = new Map();
      const trades = [];
      const plByExitId = new Map();

      sorted.forEach((row) => {
        const type = normalize(row?.type);
        const jenis = normalize(row?.jenis);
        const symbol = normalize(getOrderSymbol(row));
        const key = `${jenis || 'default'}|${symbol || 'unknown'}`;

        const price = Number(row?.price ?? row?.price_entry ?? row?.price_exit);
        const qty = Number(row?.quantity ?? row?.qty);
        if (!Number.isFinite(price) || !Number.isFinite(qty) || qty === 0) return;

        if (type === 'entry') {
          openByKey.set(key, {
            price,
            qty,
            jenis,
            symbol,
            datetime: getOrderDatetime(row),
          });
          return;
        }

        if (type === 'exit') {
          const entry = openByKey.get(key);
          if (!entry) return;

          const isShort = jenis === 'short' || jenis === 'sell';
          const pnl = isShort ? (entry.price - price) * qty : (price - entry.price) * qty;
          const entryValue = entry.price * qty;
          const ret = entryValue ? pnl / entryValue : null;

          trades.push({
            symbol: entry.symbol,
            jenis,
            entryPrice: entry.price,
            exitPrice: price,
            qty,
            pnl,
            returnPct: ret,
            entryAt: entry.datetime,
            exitAt: getOrderDatetime(row),
          });

          if (row?.id !== undefined && row?.id !== null && Number.isFinite(pnl)) {
            plByExitId.set(row.id, {
              tp: pnl > 0 ? pnl : null,
              sl: pnl < 0 ? Math.abs(pnl) : null,
            });
          }

          openByKey.delete(key);
        }
      });

      return { trades, plByExitId };
    };

    const computePositions = (orders) => {
      const sorted = sortOrdersAsc(orders);
      const openByKey = new Map();
      const lastPriceBySymbol = new Map();

      sorted.forEach((row) => {
        const type = normalize(row?.type);
        const jenis = normalize(row?.jenis);
        const symbol = getOrderSymbol(row);
        const symbolKey = normalize(symbol);
        const key = `${jenis || 'default'}|${symbolKey || 'unknown'}`;

        const price = Number(row?.price ?? row?.price_entry ?? row?.price_exit);
        const qty = Number(row?.quantity ?? row?.qty);

        if (Number.isFinite(price)) lastPriceBySymbol.set(symbolKey, price);
        if (!Number.isFinite(price) || !Number.isFinite(qty) || qty === 0) return;

        if (type === 'entry') {
          openByKey.set(key, {
            symbol,
            jenis,
            entryPrice: price,
            qty,
            since: getOrderDatetime(row),
          });
          return;
        }

        if (type === 'exit') {
          openByKey.delete(key);
        }
      });

      const positions = [];
      openByKey.forEach((entry) => {
        const symbolKey = normalize(entry.symbol);
        const mark = lastPriceBySymbol.get(symbolKey) ?? entry.entryPrice;
        const isShort = normalize(entry.jenis) === 'short' || normalize(entry.jenis) === 'sell';
        const pnl = isShort
          ? (entry.entryPrice - mark) * entry.qty
          : (mark - entry.entryPrice) * entry.qty;

        positions.push({
          symbol: entry.symbol,
          jenis: entry.jenis,
          entryPrice: entry.entryPrice,
          mark,
          qty: entry.qty,
          pnl,
          since: entry.since,
        });
      });

      return positions;
    };

    const renderPositions = (items) => {
      if (!positionsBody) return;
      positionsBody.innerHTML = '';

      if (!Array.isArray(items) || items.length === 0) {
        clearTbody(positionsBody, 7, 'No open positions.');
        if (positionsStatus) positionsStatus.textContent = '0 open positions.';
        return;
      }

      if (positionsStatus) positionsStatus.textContent = `${items.length} open positions.`;

      items.forEach((row) => {
        const tr = document.createElement('tr');
        const cols = [
          escapeText(row.symbol ?? '-'),
          escapeText(row.jenis ?? '-'),
          formatNumber(row.entryPrice, 2),
          formatNumber(row.mark, 2),
          formatNumber(row.qty, 4),
          formatNumber(row.pnl, 2),
          escapeText(row.since ?? '-'),
        ];

        cols.forEach((text, idx) => {
          const td = document.createElement('td');
          td.textContent = text;
          if ([2, 3, 4, 5].includes(idx)) td.classList.add('text-end');
          tr.appendChild(td);
        });

        positionsBody.appendChild(tr);
      });
    };

    const renderMethodCard = () => {
      const method =
        state.methodDetail ||
        state.methods.find((x) => Number(x.id) === Number(state.selectedMethodId)) ||
        null;

      const extra = parseExtra(method?.kpi_extra);
      const extraMap = buildExtraMap(extra);
      const running = resolveRunningStatus(method, extraMap);

      if (methodRunningEl) {
        methodRunningEl.textContent = running.label.toUpperCase();
        methodRunningEl.className = `badge rounded-pill sa-status-badge ${running.className}`;
      }

      const meta = extractMethodMeta(method);
      state.methodMeta = meta;

      if (methodMetaEl) {
        const pair = meta.symbol || '-';
        const tf = meta.timeframe || '-';
        const ex = meta.exchange || '-';
        methodMetaEl.textContent = `Pair: ${pair} | TF: ${tf} | Exchange: ${ex}`;
      }

      if (methodBacktestEl) {
        const url = String(method?.url ?? '').trim();
        if (url) {
          methodBacktestEl.href = url;
          methodBacktestEl.style.display = 'inline-flex';
        } else {
          methodBacktestEl.href = '#';
          methodBacktestEl.style.display = 'none';
        }
      }

      if (methodStatusEl) {
        const stamp = resolveMethodTimestamp(method, extraMap);
        if (stamp) {
          const prefix = running.label === 'Running' ? 'Since' : 'Last run';
          methodStatusEl.textContent = `${prefix}: ${stamp}`;
        } else {
          methodStatusEl.textContent = method ? `ID: ${method.id}` : '';
        }
      }
    };

    const renderBinanceSummary = () => {
      const summary = state.binanceSummary?.summary ?? {};
      const account = state.binanceSummary?.account ?? {};
      const assets = state.binanceSummary?.assets ?? [];

      const accountLabel = account?.label || account?.type || 'SPOT';
      if (binanceAccountEl) {
        binanceAccountEl.textContent = state.binanceError
          ? `Error: ${state.binanceError}`
          : `Account: ${accountLabel}`;
      }

      if (binanceHintEl) {
        const hint = state.binanceHint || '';
        binanceHintEl.textContent = hint;
        binanceHintEl.style.display = hint ? 'block' : 'none';
        binanceHintEl.className = `small mt-1 ${
          state.binanceError ? 'text-danger' : 'text-secondary'
        }`;
      }

      const total = summary.total_usdt ?? null;
      const available = summary.available_usdt ?? null;
      const locked = summary.locked_usdt ?? null;
      const btcValue = summary.btc_value ?? null;
      const assetCount =
        summary.asset_count ?? (Array.isArray(assets) ? assets.length : null);
      const updated = summary.updated_at ?? null;

      if (binanceTotalEl) binanceTotalEl.textContent = Number.isFinite(Number(total)) ? formatNumber(total, 4) : '-';
      if (binanceAvailableEl) binanceAvailableEl.textContent = Number.isFinite(Number(available)) ? formatNumber(available, 4) : '-';
      if (binanceLockedEl) binanceLockedEl.textContent = Number.isFinite(Number(locked)) ? formatNumber(locked, 4) : '-';
      if (binanceBtcEl) binanceBtcEl.textContent = Number.isFinite(Number(btcValue)) ? formatNumber(btcValue, 6) : '-';
      if (binanceAssetsEl) binanceAssetsEl.textContent = Number.isFinite(Number(assetCount)) ? String(assetCount) : '-';
      if (binanceUpdatedEl) binanceUpdatedEl.textContent = updated ? String(updated) : '-';

      updateTabCounts();
    };

    const loadBinanceSpot = async () => {
      if (!binanceTotalEl) return;
      if (binanceLiveEl) {
        binanceLiveEl.textContent = 'Loading';
        binanceLiveEl.className = 'badge text-bg-secondary';
      }
      const url = buildBinanceUrl('/api/binance/spot/summary');
      try {
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        const text = await res.text();
        let data = null;
        try {
          data = JSON.parse(text);
        } catch {
          data = null;
        }

        state.binanceMode = data?.mode ?? null;
        state.binanceBaseUrl = data?.base_url ?? null;

        if (!res.ok || (data && data.success === false)) {
          state.binanceSummary = null;
          state.binanceError = data?.error || data?.message || text || `HTTP ${res.status}`;
          state.binanceHint = data?.hint || null;
          if (binanceLiveEl) {
            binanceLiveEl.textContent = 'Error';
            binanceLiveEl.className = 'badge text-bg-danger';
          }
        } else {
          state.binanceSummary = data;
          state.binanceError = null;
          state.binanceHint = null;

          const mode = String(data?.mode || '').toLowerCase();
          if (binanceLiveEl) {
            if (mode === 'stub') {
              binanceLiveEl.textContent = 'Stub';
              binanceLiveEl.className = 'badge text-bg-warning';
            } else if (mode === 'proxy') {
              binanceLiveEl.textContent = 'Proxy';
              binanceLiveEl.className = 'badge text-bg-info';
            } else {
              binanceLiveEl.textContent = 'Live';
              binanceLiveEl.className = 'badge text-bg-success';
            }
          }
        }
      } catch (err) {
        state.binanceSummary = null;
        state.binanceError = err?.message || String(err);
        state.binanceHint = null;
        if (binanceLiveEl) {
          binanceLiveEl.textContent = 'Error';
          binanceLiveEl.className = 'badge text-bg-danger';
        }
      }

      renderBinanceSummary();
    };

    const getBinanceSymbol = () => {
      const raw = binanceSymbolInput ? binanceSymbolInput.value : 'BTCUSDT';
      const symbol = String(raw || '')
        .trim()
        .toUpperCase();
      return symbol || 'BTCUSDT';
    };

    const renderBinanceAssetsDetail = () => {
      if (!binanceAssetsBody) return;
      const assets = Array.isArray(state.binanceSummary?.assets) ? state.binanceSummary.assets : [];

      binanceAssetsBody.innerHTML = '';
      if (!assets.length) {
        clearTbody(
          binanceAssetsBody,
          5,
          state.binanceError ? `Binance error: ${state.binanceError}` : 'No assets.',
        );
        setTableStatus(binanceAssetsStatus, assets.length ? `Loaded ${assets.length} assets.` : '');
        return;
      }

      const sorted = assets
        .slice()
        .sort((a, b) => (Number(b.value_usdt) || 0) - (Number(a.value_usdt) || 0));

      sorted.forEach((row) => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', () => openModal(`Asset ${row.asset || ''}`, row));

        const cols = [
          escapeText(row.asset ?? '-'),
          formatNumber(row.free ?? 0, 8),
          formatNumber(row.locked ?? 0, 8),
          row.price_usdt !== null && row.price_usdt !== undefined ? formatNumber(row.price_usdt, 6) : '-',
          row.value_usdt !== null && row.value_usdt !== undefined ? formatNumber(row.value_usdt, 2) : '-',
        ];

        cols.forEach((text, idx) => {
          const td = document.createElement('td');
          td.textContent = text;
          if ([1, 2, 3, 4].includes(idx)) td.classList.add('text-end');
          tr.appendChild(td);
        });

        binanceAssetsBody.appendChild(tr);
      });

      setTableStatus(binanceAssetsStatus, `Loaded ${sorted.length} assets.`);
    };

    const renderBinanceOrdersTable = (tbody, items, emptyText) => {
      if (!tbody) return;
      tbody.innerHTML = '';
      if (!Array.isArray(items) || items.length === 0) {
        clearTbody(tbody, 8, emptyText || 'No data.');
        return;
      }

      const sorted = items
        .slice()
        .sort((a, b) => (Number(b.time) || 0) - (Number(a.time) || 0));

      sorted.forEach((row) => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', () => openModal('Order', row));

        const cols = [
          formatEpochMs(row.time),
          escapeText(row.symbol ?? '-'),
          escapeText(row.side ?? '-'),
          escapeText(row.type ?? '-'),
          formatNumber(row.price ?? 0, 6),
          formatNumber(row.origQty ?? 0, 6),
          formatNumber(row.executedQty ?? 0, 6),
          escapeText(row.status ?? '-'),
        ];

        cols.forEach((text, idx) => {
          const td = document.createElement('td');
          td.textContent = text;
          if ([4, 5, 6].includes(idx)) td.classList.add('text-end');
          tr.appendChild(td);
        });

        tbody.appendChild(tr);
      });
    };

    const renderBinanceTradesTable = (tbody, items, emptyText) => {
      if (!tbody) return;
      tbody.innerHTML = '';
      if (!Array.isArray(items) || items.length === 0) {
        clearTbody(tbody, 6, emptyText || 'No data.');
        return;
      }

      const sorted = items
        .slice()
        .sort((a, b) => (Number(b.time) || 0) - (Number(a.time) || 0));

      sorted.forEach((row) => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', () => openModal('Trade', row));

        const side =
          row.isBuyer === true ? 'BUY' : row.isBuyer === false ? 'SELL' : row.side ?? '-';
        const price = Number(row.price);
        const qty = Number(row.qty);
        const quoteQty =
          row.quoteQty !== undefined && row.quoteQty !== null
            ? Number(row.quoteQty)
            : Number.isFinite(price) && Number.isFinite(qty)
              ? price * qty
              : null;

        const cols = [
          formatEpochMs(row.time),
          escapeText(row.symbol ?? '-'),
          escapeText(side),
          formatNumber(price, 6),
          formatNumber(qty, 6),
          quoteQty !== null ? formatNumber(quoteQty, 6) : '-',
        ];

        cols.forEach((text, idx) => {
          const td = document.createElement('td');
          td.textContent = text;
          if ([3, 4, 5].includes(idx)) td.classList.add('text-end');
          tr.appendChild(td);
        });

        tbody.appendChild(tr);
      });
    };

    const loadBinanceOpenOrders = async () => {
      if (!binanceOpenOrdersBody) return;
      const symbol = getBinanceSymbol();
      setTableStatus(binanceOpenOrdersStatus, 'Loading...');
      try {
        const res = await fetchLocalJson(buildBinanceUrl('/api/binance/spot/open-orders', { symbol }));
        const items = Array.isArray(res?.data) ? res.data : [];
        state.binanceOpenOrders = items;
        updateTabCounts();
        renderBinanceOrdersTable(binanceOpenOrdersBody, items, 'No open orders.');
        setTableStatus(binanceOpenOrdersStatus, `Loaded ${items.length} open orders.`);
      } catch (err) {
        state.binanceOpenOrders = [];
        updateTabCounts();
        clearTbody(binanceOpenOrdersBody, 8, 'Failed to load open orders.');
        setTableStatus(
          binanceOpenOrdersStatus,
          'Error: ' + (err?.message || String(err)),
        );
      }
    };

    const loadBinanceOrders = async () => {
      if (!binanceOrdersBody) return;
      const symbol = getBinanceSymbol();
      setTableStatus(binanceOrdersStatus, 'Loading...');
      try {
        const res = await fetchLocalJson(
          buildBinanceUrl('/api/binance/spot/orders', { symbol, limit: 50 }),
        );
        const items = Array.isArray(res?.data) ? res.data : [];
        state.binanceOrders = items;
        updateTabCounts();
        renderBinanceOrdersTable(binanceOrdersBody, items, 'No orders.');
        setTableStatus(binanceOrdersStatus, `Loaded ${items.length} orders.`);
      } catch (err) {
        state.binanceOrders = [];
        updateTabCounts();
        clearTbody(binanceOrdersBody, 8, 'Failed to load orders.');
        setTableStatus(binanceOrdersStatus, 'Error: ' + (err?.message || String(err)));
      }
    };

    const loadBinanceTrades = async () => {
      if (!binanceTradesBody) return;
      const symbol = getBinanceSymbol();
      setTableStatus(binanceTradesStatus, 'Loading...');
      try {
        const res = await fetchLocalJson(
          buildBinanceUrl('/api/binance/spot/trades', { symbol, limit: 50 }),
        );
        const items = Array.isArray(res?.data) ? res.data : [];
        state.binanceTrades = items;
        updateTabCounts();
        renderBinanceTradesTable(binanceTradesBody, items, 'No trades.');
        setTableStatus(binanceTradesStatus, `Loaded ${items.length} trades.`);
      } catch (err) {
        state.binanceTrades = [];
        updateTabCounts();
        clearTbody(binanceTradesBody, 6, 'Failed to load trades.');
        setTableStatus(binanceTradesStatus, 'Error: ' + (err?.message || String(err)));
      }
    };

    const refreshBinanceDetail = async () => {
      renderBinanceAssetsDetail();
      if (state.binanceTab === 'open-orders') await loadBinanceOpenOrders();
      if (state.binanceTab === 'orders') await loadBinanceOrders();
      if (state.binanceTab === 'trades') await loadBinanceTrades();
    };

    const renderKpiGrid = () => {
      if (!kpiGridEl) return;
      const method = state.methodDetail || {};
      const extra = parseExtra(method?.kpi_extra);
      const extraMap = buildExtraMap(extra);

      const totalOrders = Number(method?.total_orders ?? extraMap.get('total_orders'));
      const turnover = Number(method?.turnover ?? extraMap.get('turnover'));

      const items = [
        {
          label: 'Sharpe Ratio',
          value:
            method.sharpen_ratio !== undefined && method.sharpen_ratio !== null
              ? formatNumber(method.sharpen_ratio, 2)
              : '-',
        },
        {
          label: 'Sortino Ratio',
          value:
            method.sortino_ratio !== undefined && method.sortino_ratio !== null
              ? formatNumber(method.sortino_ratio, 2)
              : '-',
        },
        {
          label: 'Information Ratio',
          value:
            method.information_ratio !== undefined && method.information_ratio !== null
              ? formatNumber(method.information_ratio, 2)
              : '-',
        },
        {
          label: 'CAGR',
          value:
            method.cagr !== undefined && method.cagr !== null ? formatPercent(method.cagr) : '-',
        },
        {
          label: 'Drawdown',
          value:
            method.drawdown !== undefined && method.drawdown !== null
              ? formatPercent(method.drawdown)
              : '-',
        },
        {
          label: 'Probabilistic SR',
          value:
            method.prob_sr !== undefined && method.prob_sr !== null
              ? formatPercent(method.prob_sr)
              : '-',
        },
        {
          label: 'Win Rate',
          value:
            method.winrate !== undefined && method.winrate !== null
              ? formatPercent(method.winrate)
              : '-',
        },
        {
          label: 'Loss Rate',
          value:
            method.lossrate !== undefined && method.lossrate !== null
              ? formatPercent(method.lossrate)
              : '-',
        },
        { label: 'Total Orders', value: Number.isFinite(totalOrders) ? String(totalOrders) : '-' },
        { label: 'Turnover', value: Number.isFinite(turnover) ? formatPercent(turnover) : '-' },
      ];

      kpiGridEl.innerHTML = '';
      items.forEach((item) => {
        const wrap = document.createElement('div');
        wrap.className = 'sa-kpi-item';

        const label = document.createElement('div');
        label.className = 'label';
        label.textContent = item.label;

        const value = document.createElement('div');
        value.className = 'value';
        value.textContent = item.value;

        wrap.appendChild(label);
        wrap.appendChild(value);
        kpiGridEl.appendChild(wrap);
      });
    };

    const truncateMessage = (value, max = 50) => {
      const text = escapeText(value ?? '').replace(/\s+/g, ' ').trim();
      if (!text) return '-';
      if (text.length <= max) return text;
      return text.slice(0, Math.max(0, max - 1)) + '…';
    };

    const createMessageCell = (value) => {
      const td = document.createElement('td');
      const full = escapeText(value ?? '');
      const snippet = truncateMessage(full);

      const div = document.createElement('div');
      div.className = 'sa-message-snippet';
      div.textContent = snippet;
      if (full && snippet !== full) div.title = full;
      td.appendChild(div);
      return td;
    };

    const renderOrders = (items, plByExitId = new Map()) => {
      ordersBody.innerHTML = '';

      if (!Array.isArray(items) || items.length === 0) {
        clearTbody(ordersBody, 10, 'No orders.');
        return [];
      }

      const sorted = sortOrdersAsc(items);
      sorted.forEach((row) => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', async () => {
          try {
            const detail = await fetchJson(`/orders/${row.id}`);
            openModal(`Order #${row.id}`, detail);
          } catch (err) {
            openModal(`Order #${row.id}`, { error: err?.message || String(err) });
          }
        });

        const symbol = getOrderSymbol(row);
        const price = row.price ?? row.price_entry ?? row.price_exit ?? '-';
        const qty = row.quantity ?? row.qty ?? '-';
        const total = (Number(price) || 0) * (Number(qty) || 0);

        const computed = row?.id !== undefined && row?.id !== null ? plByExitId.get(row.id) : null;
        const tp = computed?.tp ?? row.tp ?? row.target_tp ?? row.take_profit ?? '-';
        const sl = computed?.sl ?? row.sl ?? row.target_sl ?? row.stop_loss ?? '-';

        const cols = [
          getOrderDatetime(row),
          escapeText(symbol),
          escapeText(row.type ?? '-'),
          escapeText(row.jenis ?? '-'),
          formatNumber(price, 2),
          formatNumber(qty, 4),
          total ? formatNumber(total, 2) : '-',
          tp !== '-' ? formatNumber(tp, 2) : '-',
          sl !== '-' ? formatNumber(sl, 2) : '-',
        ];

        cols.forEach((text, idx) => {
          const td = document.createElement('td');
          td.textContent = text;
          if ([4, 5, 6, 7, 8].includes(idx)) td.classList.add('text-end');
          tr.appendChild(td);
        });

        tr.appendChild(createMessageCell(row.message));

        ordersBody.appendChild(tr);

      });
    };

    const renderSignals = (items) => {
      signalsBody.innerHTML = '';
      if (!Array.isArray(items) || items.length === 0) {
        clearTbody(signalsBody, 10, 'No signals.');
        return;
      }

      items.forEach((row) => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', async () => {
          try {
            const detail = await fetchJson(`/signals/${row.id}`);
            openModal(`Signal #${row.id}`, detail);
          } catch (err) {
            openModal(`Signal #${row.id}`, { error: err?.message || String(err) });
          }
        });

        const symbol = getOrderSymbol(row);
        const price =
          row.type === 'exit' ? row.price_exit ?? row.price ?? '-' : row.price_entry ?? row.price ?? '-';

        const cols = [
          getOrderDatetime(row),
          escapeText(symbol),
          escapeText(row.type ?? '-'),
          escapeText(row.jenis ?? '-'),
          price !== '-' ? formatNumber(price, 2) : '-',
          row.target_tp !== undefined ? formatNumber(row.target_tp, 2) : '-',
          row.target_sl !== undefined ? formatNumber(row.target_sl, 2) : '-',
          row.real_tp !== undefined ? formatNumber(row.real_tp, 2) : '-',
          row.real_sl !== undefined ? formatNumber(row.real_sl, 2) : '-',
        ];

        cols.forEach((text, idx) => {
          const td = document.createElement('td');
          td.textContent = text;
          if ([4, 5, 6, 7, 8].includes(idx)) td.classList.add('text-end');
          tr.appendChild(td);
        });

        tr.appendChild(createMessageCell(row.message));

        signalsBody.appendChild(tr);
      });
    };

    const renderReminders = (items) => {
      remindersBody.innerHTML = '';
      if (!Array.isArray(items) || items.length === 0) {
        clearTbody(remindersBody, 2, 'No reminders.');
        return;
      }

      items.forEach((row) => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', async () => {
          try {
            const detail = await fetchJson(`/reminders/${row.id}`);
            openModal(`Reminder #${row.id}`, detail);
          } catch (err) {
            openModal(`Reminder #${row.id}`, { error: err?.message || String(err) });
          }
        });

        const tdDt = document.createElement('td');
        tdDt.textContent = escapeText(row.datetime ?? row.date_time ?? row.created_at ?? '-');
        tr.appendChild(tdDt);
        tr.appendChild(createMessageCell(row.message));

        remindersBody.appendChild(tr);
      });
    };

    const renderLogs = (items) => {
      logsBody.innerHTML = '';
      if (!Array.isArray(items) || items.length === 0) {
        clearTbody(logsBody, 2, 'No logs.');
        return;
      }

      items.forEach((row) => {
        const tr = document.createElement('tr');
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', async () => {
          try {
            const detail = await fetchJson(`/logs/${row.id}`);
            openModal(`Log #${row.id}`, detail);
          } catch (err) {
            openModal(`Log #${row.id}`, { error: err?.message || String(err) });
          }
        });

        const tdDt = document.createElement('td');
        tdDt.textContent = escapeText(row.datetime ?? row.date_time ?? row.created_at ?? '-');
        tr.appendChild(tdDt);
        tr.appendChild(createMessageCell(row.message));

        logsBody.appendChild(tr);
      });
    };

    const loadHealth = async () => {
      try {
        const result = await fetchJson('/health', { db: 1 });
        const ok = result?.status === 'ok';
        const dbOk = result?.db?.ok === true;
        healthEl.textContent = ok ? 'OK' : 'ERROR';
        healthEl.classList.toggle('text-success', ok);
        healthEl.classList.toggle('text-danger', !ok);
        if (healthMetaEl) healthMetaEl.textContent = `DB: ${dbOk ? 'OK' : 'N/A'}`;
      } catch (err) {
        healthEl.textContent = 'ERROR';
        healthEl.classList.add('text-danger');
        if (healthMetaEl) healthMetaEl.textContent = err?.message || String(err);
      }
    };

    const loadMethods = async () => {
      setMethodStatus('Loading methods...');
      try {
        const items = await fetchJson('/methods', { limit: 200, offset: 0 });
        state.methods = Array.isArray(items) ? items : [];
        renderMethods();

        if (state.methods.length > 0) {
          const active =
            state.methods.find((m) => m?.onactive === 1 || m?.onactive === true) ||
            state.methods.find((m) => normalize(m?.nama_metode).includes('spot v3')) ||
            state.methods[0];

          methodSelect.value = String(active?.id ?? state.methods[0].id);
          await onMethodChanged();
        } else {
          state.selectedMethodId = null;
          state.methodDetail = null;
          state.latestOrders = [];
          state.latestTrades = [];
          state.latestPositions = [];
          state.latestSignals = [];
          state.latestReminders = [];
          state.latestLogs = [];
          updateTabCounts();
          renderMethodCard();
          renderKpiGrid();
          renderBinanceSummary();
          renderPositions([]);
          setMethodStatus('No methods.');
        }
      } catch (err) {
        state.methods = [];
        renderMethods();
        state.methodDetail = null;
        state.latestOrders = [];
        state.latestTrades = [];
        state.latestPositions = [];
        state.latestSignals = [];
        state.latestReminders = [];
        state.latestLogs = [];
        updateTabCounts();
        renderMethodCard();
        renderKpiGrid();
        renderBinanceSummary();
        setMethodStatus('Error: ' + (err?.message || String(err)));
      }
    };

    const loadMethodDetail = async (id) => {
      if (!id) return null;
      try {
        return await fetchJson(`/methods/${id}`);
      } catch (err) {
        return { error: err?.message || String(err) };
      }
    };

    const loadOrders = async () => {
      if (!state.selectedMethodId) {
        state.latestOrders = [];
        state.latestTrades = [];
        state.latestPositions = [];
        state.latestSignals = [];
        state.latestReminders = [];
        state.latestLogs = [];
        updateTabCounts();
        renderPositions([]);
        renderKpiGrid();
        renderBinanceSummary();
        clearTbody(ordersBody, 10, 'Select a method to load orders.');
        setTableStatus(ordersStatus, '');
        return;
      }

      const q = getGlobalQuery();
      const type = ordersType?.value ?? '';
      const jenis = ordersJenis?.value ?? '';

      setTableStatus(ordersStatus, 'Loading...');
      try {
        const items = await fetchJson('/orders', { ...q, type, jenis });
        const orders = Array.isArray(items) ? items : [];

        state.latestOrders = orders;
        const tradeData = computeTrades(orders);
        state.latestTrades = tradeData.trades;
        state.latestPositions = computePositions(orders);

        renderPositions(state.latestPositions);
        renderOrders(orders, tradeData.plByExitId);
        renderKpiGrid();
        renderBinanceSummary();
        updateTabCounts();
        setTableStatus(ordersStatus, `Loaded ${orders.length} orders.`);
      } catch (err) {
        state.latestOrders = [];
        state.latestTrades = [];
        state.latestPositions = [];
        updateTabCounts();
        renderPositions([]);
        renderKpiGrid();
        renderBinanceSummary();
        clearTbody(ordersBody, 10, 'Failed to load orders.');
        setTableStatus(ordersStatus, 'Error: ' + (err?.message || String(err)));
      }
    };

    const loadSignals = async () => {
      if (!state.selectedMethodId) {
        state.latestSignals = [];
        updateTabCounts();
        clearTbody(signalsBody, 10, 'Select a method to load signals.');
        setTableStatus(signalsStatus, '');
        return;
      }

      const q = getGlobalQuery();
      const type = signalsType?.value ?? '';
      const jenis = signalsJenis?.value ?? '';

      setTableStatus(signalsStatus, 'Loading...');
      try {
        const items = await fetchJson('/signals', { ...q, type, jenis });
        const signals = Array.isArray(items) ? items : [];
        state.latestSignals = signals;
        renderSignals(signals);
        updateTabCounts();
        setTableStatus(signalsStatus, `Loaded ${signals.length} signals.`);
      } catch (err) {
        state.latestSignals = [];
        updateTabCounts();
        clearTbody(signalsBody, 10, 'Failed to load signals.');
        setTableStatus(signalsStatus, 'Error: ' + (err?.message || String(err)));
      }
    };

    const loadReminders = async () => {
      if (!state.selectedMethodId) {
        state.latestReminders = [];
        updateTabCounts();
        clearTbody(remindersBody, 2, 'Select a method to load reminders.');
        setTableStatus(remindersStatus, '');
        return;
      }

      const q = getGlobalQuery();

      setTableStatus(remindersStatus, 'Loading...');
      try {
        const items = await fetchJson('/reminders', q);
        const reminders = Array.isArray(items) ? items : [];
        state.latestReminders = reminders;
        renderReminders(reminders);
        updateTabCounts();
        setTableStatus(remindersStatus, `Loaded ${reminders.length} reminders.`);
      } catch (err) {
        state.latestReminders = [];
        updateTabCounts();
        clearTbody(remindersBody, 2, 'Failed to load reminders.');
        setTableStatus(remindersStatus, 'Error: ' + (err?.message || String(err)));
      }
    };

    const loadLogs = async () => {
      if (!state.selectedMethodId) {
        state.latestLogs = [];
        updateTabCounts();
        clearTbody(logsBody, 2, 'Select a method to load logs.');
        setTableStatus(logsStatus, '');
        return;
      }

      const q = getGlobalQuery();

      setTableStatus(logsStatus, 'Loading...');
      try {
        const items = await fetchJson('/logs', q);
        const logs = Array.isArray(items) ? items : [];
        state.latestLogs = logs;
        renderLogs(logs);
        updateTabCounts();
        setTableStatus(logsStatus, `Loaded ${logs.length} logs.`);
      } catch (err) {
        state.latestLogs = [];
        updateTabCounts();
        clearTbody(logsBody, 2, 'Failed to load logs.');
        setTableStatus(logsStatus, 'Error: ' + (err?.message || String(err)));
      }
    };

    const refreshAll = async () => {
      await loadHealth();
      await loadBinanceSpot();
      await loadOrders();

      if (state.detailSource === 'qc') {
        await loadSignals();
        await loadReminders();
        await loadLogs();
      }

      if (state.detailSource === 'binance') {
        await refreshBinanceDetail();
      }
    };

    const onMethodChanged = async () => {
      const id = methodSelect.value ? Number(methodSelect.value) : null;
      state.selectedMethodId = Number.isFinite(id) ? id : null;
      state.methodMeta = null;

      if (methodDetailButton) methodDetailButton.disabled = !state.selectedMethodId;

      const base = state.methods.find((x) => Number(x.id) === state.selectedMethodId) || null;
      state.methodDetail = base;
      renderMethodCard();
      renderBinanceSummary();
      renderKpiGrid();

      if (state.selectedMethodId) {
        const detail = await loadMethodDetail(state.selectedMethodId);
        if (detail && !detail.error) state.methodDetail = { ...base, ...detail };
        renderMethodCard();
        renderBinanceSummary();
        renderKpiGrid();
      } else {
        state.methodDetail = null;
        renderMethodCard();
        renderBinanceSummary();
        renderKpiGrid();
        setMethodStatus('');
      }

      await refreshAll();
    };

    const tabs = Array.from(document.querySelectorAll('.sa-tab'));
    const panels = {
      positions: byId('sa-panel-positions'),
      orders: byId('sa-panel-orders'),
      signals: byId('sa-panel-signals'),
      reminders: byId('sa-panel-reminders'),
      logs: byId('sa-panel-logs'),
    };

    const binanceTabs = Array.from(document.querySelectorAll('.sa-binance-tab'));
    const binancePanels = {
      assets: byId('sa-binance-panel-assets'),
      'open-orders': byId('sa-binance-panel-open-orders'),
      orders: byId('sa-binance-panel-orders'),
      trades: byId('sa-binance-panel-trades'),
    };

    const showTab = (key) => {
      tabs.forEach((t) => t.classList.toggle('is-active', t.getAttribute('data-tab') === key));
      Object.entries(panels).forEach(([k, el]) => {
        if (!el) return;
        el.style.display = k === key ? 'block' : 'none';
      });
    };

    const showBinanceTab = (key) => {
      const kk = key || 'assets';
      state.binanceTab = kk;
      binanceTabs.forEach((t) =>
        t.classList.toggle('is-active', t.getAttribute('data-tab') === kk),
      );
      Object.entries(binancePanels).forEach(([k, el]) => {
        if (!el) return;
        el.style.display = k === kk ? 'block' : 'none';
      });

      if (kk === 'assets') renderBinanceAssetsDetail();
      if (kk === 'open-orders') loadBinanceOpenOrders();
      if (kk === 'orders') loadBinanceOrders();
      if (kk === 'trades') loadBinanceTrades();
    };

    const setDetailSource = (source) => {
      const next = source === 'qc' || source === 'binance' ? source : null;
      state.detailSource = next;

      if (detailPanel) detailPanel.style.display = next ? 'block' : 'none';
      if (qcDetailPanel) qcDetailPanel.style.display = next === 'qc' ? 'block' : 'none';
      if (binanceDetailPanel) binanceDetailPanel.style.display = next === 'binance' ? 'block' : 'none';

      if (qcDetailButton) {
        qcDetailButton.classList.toggle('btn-primary', next === 'qc');
        qcDetailButton.classList.toggle('btn-outline-primary', next !== 'qc');
      }
      if (binanceDetailButton) {
        binanceDetailButton.classList.toggle('btn-primary', next === 'binance');
        binanceDetailButton.classList.toggle('btn-outline-primary', next !== 'binance');
      }

      if (next === 'qc') {
        showTab('logs');
        if (state.selectedMethodId) {
          loadSignals();
          loadReminders();
          loadLogs();
        }
        detailPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }

      if (next === 'binance') {
        showBinanceTab(state.binanceTab);
        detailPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    };

    tabs.forEach((btn) => {
      btn.addEventListener('click', () => showTab(btn.getAttribute('data-tab')));
    });

    binanceTabs.forEach((btn) => {
      btn.addEventListener('click', () => showBinanceTab(btn.getAttribute('data-tab')));
    });

    methodSelect.addEventListener('change', onMethodChanged);

    if (methodDetailButton) {
      methodDetailButton.addEventListener('click', async () => {
        if (!state.selectedMethodId) return;
        const detail = await loadMethodDetail(state.selectedMethodId);
        openModal(`Method #${state.selectedMethodId}`, detail);
      });
    }

    const startAutoRefresh = () => {
      if (state.autoTimer) clearInterval(state.autoTimer);
      state.autoTimer = setInterval(() => {
        refreshAll();
      }, 15000);
    };

    if (ordersType) ordersType.addEventListener('change', loadOrders);
    if (ordersJenis) ordersJenis.addEventListener('change', loadOrders);
    if (signalsType) signalsType.addEventListener('change', loadSignals);
    if (signalsJenis) signalsJenis.addEventListener('change', loadSignals);

    if (binanceSymbolInput) {
      binanceSymbolInput.addEventListener('change', () => {
        if (state.detailSource !== 'binance') return;
        showBinanceTab(state.binanceTab);
      });
    }

    if (qcDetailButton) {
      qcDetailButton.addEventListener('click', () => {
        setDetailSource(state.detailSource === 'qc' ? null : 'qc');
      });
    }

    if (binanceDetailButton) {
      binanceDetailButton.addEventListener('click', () => {
        setDetailSource(state.detailSource === 'binance' ? null : 'binance');
      });
    }

    showTab('logs');
    showBinanceTab('assets');
    setDetailSource('qc');

    onWindowLoaded(() => {
      loadMethods();
      loadHealth();
      loadBinanceSpot();
      startAutoRefresh();
    });
  });
})();
