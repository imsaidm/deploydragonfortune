(() => {
  const onReady = (fn) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
      return;
    }
    fn();
  };

  onReady(() => {
    const byId = (id) => document.getElementById(id);

    const apiBaseEl = byId('sa-api-base');
    const docsLinkEl = byId('sa-open-docs');
    const refreshAllButton = byId('sa-refresh-all');
    const lastRefreshEl = byId('sa-last-refresh');

    const healthEl = byId('sa-health');
    const healthMetaEl = byId('sa-health-meta');

    const methodSelect = byId('sa-method-select');
    const methodDetailButton = byId('sa-method-detail');
    const methodStatusEl = byId('sa-method-status');

    const fromInput = byId('sa-from');
    const toInput = byId('sa-to');
    const limitInput = byId('sa-limit');
    const offsetInput = byId('sa-offset');

    const kpiPsrEl = byId('sa-kpi-psr');
    const kpiCagrEl = byId('sa-kpi-cagr');
    const kpiWinEl = byId('sa-kpi-win');
    const kpiLossEl = byId('sa-kpi-loss');
    const kpiExtraEl = byId('sa-kpi-extra');

    const balanceMetaEl = byId('sa-balance-meta');
    const balanceEmptyEl = byId('sa-balance-empty');
    const balanceCanvas = byId('sa-balance-chart');

    const ordersType = byId('sa-orders-type');
    const ordersJenis = byId('sa-orders-jenis');
    const ordersRefresh = byId('sa-orders-refresh');
    const ordersStatus = byId('sa-orders-status');
    const ordersBody = byId('sa-orders-body');

    const signalsType = byId('sa-signals-type');
    const signalsJenis = byId('sa-signals-jenis');
    const signalsRefresh = byId('sa-signals-refresh');
    const signalsStatus = byId('sa-signals-status');
    const signalsBody = byId('sa-signals-body');

    const remindersRefresh = byId('sa-reminders-refresh');
    const remindersStatus = byId('sa-reminders-status');
    const remindersBody = byId('sa-reminders-body');

    const logsRefresh = byId('sa-logs-refresh');
    const logsStatus = byId('sa-logs-status');
    const logsBody = byId('sa-logs-body');

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
      chart: null,
    };

    const escapeText = (value) => {
      if (value === null || value === undefined) return '';
      return String(value);
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

    const toApiDatetime = (dtLocalValue) => {
      if (!dtLocalValue) return '';
      const v = String(dtLocalValue).trim();
      if (!v.includes('T')) return v;
      return v.replace('T', ' ') + ':00';
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

    const setLastRefresh = () => {
      if (!lastRefreshEl) return;
      lastRefreshEl.textContent = 'Last refresh: ' + new Date().toLocaleString();
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
        opt.textContent = `${escapeText(m.nama_metode || 'Method')} (#${m.id})`;
        methodSelect.appendChild(opt);
      });

      if (state.methods.length === 0) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '(no methods)';
        methodSelect.appendChild(opt);
      }
    };

    const setKpisFromMethod = (m) => {
      if (kpiPsrEl) kpiPsrEl.textContent = formatPercent(m?.prob_sr);
      if (kpiCagrEl) kpiCagrEl.textContent = formatPercent(m?.cagr);
      if (kpiWinEl) kpiWinEl.textContent = formatPercent(m?.winrate);
      if (kpiLossEl) kpiLossEl.textContent = formatPercent(m?.lossrate);

      if (!kpiExtraEl) return;
      const extra = m?.kpi_extra;
      if (!extra) {
        kpiExtraEl.textContent = '';
        return;
      }

      try {
        const obj = typeof extra === 'string' ? JSON.parse(extra) : extra;
        const pairs = Object.entries(obj || {}).slice(0, 10);
        kpiExtraEl.textContent =
          pairs.length === 0
            ? ''
            : pairs.map(([k, v]) => `${k}: ${escapeText(v)}`).join(' · ');
      } catch (e) {
        kpiExtraEl.textContent = escapeText(extra);
      }
    };

    const renderOrders = (items) => {
      ordersBody.innerHTML = '';

      if (!Array.isArray(items) || items.length === 0) {
        clearTbody(ordersBody, 10, 'No orders.');
        return [];
      }

      const points = [];

      items.forEach((row) => {
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

        const symbol = row.symbol ?? row.ticker ?? row.pair ?? '-';
        const price = row.price ?? row.price_entry ?? row.price_exit ?? '-';
        const qty = row.quantity ?? row.qty ?? '-';
        const total = (Number(price) || 0) * (Number(qty) || 0);

        const tp = row.tp ?? row.target_tp ?? row.take_profit ?? '-';
        const sl = row.sl ?? row.target_sl ?? row.stop_loss ?? '-';

        const cols = [
          escapeText(row.datetime ?? row.date_time ?? row.created_at ?? '-'),
          escapeText(symbol),
          escapeText(row.type ?? '-'),
          escapeText(row.jenis ?? '-'),
          formatNumber(price, 2),
          formatNumber(qty, 4),
          total ? formatNumber(total, 2) : '-',
          tp !== '-' ? formatNumber(tp, 2) : '-',
          sl !== '-' ? formatNumber(sl, 2) : '-',
          row.balance !== undefined && row.balance !== null ? formatNumber(row.balance, 2) : '-',
        ];

        cols.forEach((text, idx) => {
          const td = document.createElement('td');
          td.textContent = text;
          if ([4, 5, 6, 7, 8, 9].includes(idx)) td.classList.add('text-end');
          tr.appendChild(td);
        });

        ordersBody.appendChild(tr);

        const dt = row.datetime;
        const bal = Number(row.balance);
        if (dt && Number.isFinite(bal)) points.push({ x: dt, y: bal });
      });

      return points;
    };

    const renderSignals = (items) => {
      signalsBody.innerHTML = '';
      if (!Array.isArray(items) || items.length === 0) {
        clearTbody(signalsBody, 9, 'No signals.');
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

        const symbol = row.symbol ?? row.ticker ?? row.pair ?? '-';
        const price =
          row.type === 'exit' ? row.price_exit ?? row.price ?? '-' : row.price_entry ?? row.price ?? '-';

        const cols = [
          escapeText(row.datetime ?? row.date_time ?? row.created_at ?? '-'),
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

        const cols = [
          escapeText(row.datetime ?? row.date_time ?? row.created_at ?? '-'),
          escapeText(row.message ?? '-'),
        ];

        cols.forEach((text) => {
          const td = document.createElement('td');
          td.textContent = text;
          tr.appendChild(td);
        });

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

        const cols = [
          escapeText(row.datetime ?? row.date_time ?? row.created_at ?? '-'),
          escapeText(row.message ?? '-'),
        ];

        cols.forEach((text) => {
          const td = document.createElement('td');
          td.textContent = text;
          tr.appendChild(td);
        });

        logsBody.appendChild(tr);
      });
    };

    const renderBalanceChart = async (points) => {
      const Chart = window.Chart || null;
      if (!balanceCanvas) return;

      const hasData = Array.isArray(points) && points.length > 0;
      if (balanceEmptyEl) balanceEmptyEl.style.display = hasData ? 'none' : 'block';

      if (!Chart) {
        if (balanceMetaEl) balanceMetaEl.textContent = 'Chart.js not loaded.';
        return;
      }

      if (!hasData) {
        if (state.chart) {
          state.chart.destroy();
          state.chart = null;
        }
        return;
      }

      const sorted = points.slice().sort((a, b) => String(a.x).localeCompare(String(b.x)));
      const labels = sorted.map((p) => p.x);
      const values = sorted.map((p) => p.y);

      const config = {
        type: 'line',
        data: {
          labels,
          datasets: [
            {
              label: 'Balance',
              data: values,
              borderColor: '#3b82f6',
              backgroundColor: 'rgba(59, 130, 246, 0.18)',
              pointRadius: 2,
              borderWidth: 2,
              tension: 0.25,
              fill: true,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { mode: 'index', intersect: false },
          },
          interaction: { mode: 'index', intersect: false },
          scales: {
            x: { ticks: { maxRotation: 0, autoSkip: true } },
            y: { ticks: { callback: (v) => Number(v).toLocaleString() } },
          },
        },
      };

      if (state.chart) {
        state.chart.data.labels = labels;
        state.chart.data.datasets[0].data = values;
        state.chart.update();
      } else {
        state.chart = new Chart(balanceCanvas.getContext('2d'), config);
      }

      if (balanceMetaEl) {
        const latest = sorted[sorted.length - 1];
        balanceMetaEl.textContent = `Points: ${sorted.length} · Latest: ${latest.y.toLocaleString()} (${latest.x})`;
      }
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
          methodSelect.value = String(state.methods[0].id);
          await onMethodChanged();
        } else {
          state.selectedMethodId = null;
          setKpisFromMethod(null);
          setMethodStatus('No methods.');
        }
      } catch (err) {
        state.methods = [];
        renderMethods();
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
      const q = getGlobalQuery();
      const type = ordersType?.value ?? '';
      const jenis = ordersJenis?.value ?? '';

      setTableStatus(ordersStatus, 'Loading...');
      try {
        const items = await fetchJson('/orders', { ...q, type, jenis });
        const points = renderOrders(Array.isArray(items) ? items : []);
        setTableStatus(ordersStatus, `Loaded ${Array.isArray(items) ? items.length : 0} orders.`);
        await renderBalanceChart(points);
      } catch (err) {
        clearTbody(ordersBody, 10, 'Failed to load orders.');
        setTableStatus(ordersStatus, 'Error: ' + (err?.message || String(err)));
        await renderBalanceChart([]);
      }
    };

    const loadSignals = async () => {
      const q = getGlobalQuery();
      const type = signalsType?.value ?? '';
      const jenis = signalsJenis?.value ?? '';

      setTableStatus(signalsStatus, 'Loading...');
      try {
        const items = await fetchJson('/signals', { ...q, type, jenis });
        renderSignals(Array.isArray(items) ? items : []);
        setTableStatus(signalsStatus, `Loaded ${Array.isArray(items) ? items.length : 0} signals.`);
      } catch (err) {
        clearTbody(signalsBody, 9, 'Failed to load signals.');
        setTableStatus(signalsStatus, 'Error: ' + (err?.message || String(err)));
      }
    };

    const loadReminders = async () => {
      const q = getGlobalQuery();

      setTableStatus(remindersStatus, 'Loading...');
      try {
        const items = await fetchJson('/reminders', q);
        renderReminders(Array.isArray(items) ? items : []);
        setTableStatus(remindersStatus, `Loaded ${Array.isArray(items) ? items.length : 0} reminders.`);
      } catch (err) {
        clearTbody(remindersBody, 2, 'Failed to load reminders.');
        setTableStatus(remindersStatus, 'Error: ' + (err?.message || String(err)));
      }
    };

    const loadLogs = async () => {
      const q = getGlobalQuery();

      setTableStatus(logsStatus, 'Loading...');
      try {
        const items = await fetchJson('/logs', q);
        renderLogs(Array.isArray(items) ? items : []);
        setTableStatus(logsStatus, `Loaded ${Array.isArray(items) ? items.length : 0} logs.`);
      } catch (err) {
        clearTbody(logsBody, 2, 'Failed to load logs.');
        setTableStatus(logsStatus, 'Error: ' + (err?.message || String(err)));
      }
    };

    const refreshAll = async () => {
      await loadHealth();
      await loadOrders();
      await loadSignals();
      await loadReminders();
      await loadLogs();
      setLastRefresh();
    };

    const onMethodChanged = async () => {
      const id = methodSelect.value ? Number(methodSelect.value) : null;
      state.selectedMethodId = Number.isFinite(id) ? id : null;

      if (methodDetailButton) methodDetailButton.disabled = !state.selectedMethodId;

      const m = state.methods.find((x) => Number(x.id) === state.selectedMethodId) || null;
      setKpisFromMethod(m);

      if (state.selectedMethodId) {
        const detail = await loadMethodDetail(state.selectedMethodId);
        if (detail && !detail.error) setKpisFromMethod(detail);
        setMethodStatus(`Selected method #${state.selectedMethodId}`);
      } else {
        setMethodStatus('');
      }

      await refreshAll();
    };

    const tabs = Array.from(document.querySelectorAll('.sa-tab'));
    const panels = {
      orders: byId('sa-panel-orders'),
      signals: byId('sa-panel-signals'),
      reminders: byId('sa-panel-reminders'),
      logs: byId('sa-panel-logs'),
    };

    const showTab = (key) => {
      tabs.forEach((t) => t.classList.toggle('is-active', t.getAttribute('data-tab') === key));
      Object.entries(panels).forEach(([k, el]) => {
        if (!el) return;
        el.style.display = k === key ? 'block' : 'none';
      });
    };

    tabs.forEach((btn) => {
      btn.addEventListener('click', () => showTab(btn.getAttribute('data-tab')));
    });

    methodSelect.addEventListener('change', onMethodChanged);

    if (methodDetailButton) {
      methodDetailButton.addEventListener('click', async () => {
        if (!state.selectedMethodId) return;
        const detail = await loadMethodDetail(state.selectedMethodId);
        openModal(`Method #${state.selectedMethodId}`, detail);
      });
    }

    const scheduleRefresh = (() => {
      let timer = null;
      return () => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => refreshAll(), 350);
      };
    })();

    [fromInput, toInput, limitInput, offsetInput].forEach((el) => {
      if (!el) return;
      el.addEventListener('change', scheduleRefresh);
    });

    if (ordersRefresh) ordersRefresh.addEventListener('click', loadOrders);
    if (signalsRefresh) signalsRefresh.addEventListener('click', loadSignals);
    if (remindersRefresh) remindersRefresh.addEventListener('click', loadReminders);
    if (logsRefresh) logsRefresh.addEventListener('click', loadLogs);

    if (ordersType) ordersType.addEventListener('change', loadOrders);
    if (ordersJenis) ordersJenis.addEventListener('change', loadOrders);
    if (signalsType) signalsType.addEventListener('change', loadSignals);
    if (signalsJenis) signalsJenis.addEventListener('change', loadSignals);

    if (refreshAllButton) refreshAllButton.addEventListener('click', refreshAll);

    const now = new Date();
    const from = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
    const pad = (n) => String(n).padStart(2, '0');
    const toLocalInput = (d) =>
      `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;

    if (fromInput && !fromInput.value) fromInput.value = toLocalInput(from);
    if (toInput && !toInput.value) toInput.value = toLocalInput(now);

    showTab('orders');
    loadMethods();
    loadHealth();
  });
})();

