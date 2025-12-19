@extends('layouts.app')

@section('title', 'Backtest Result | DragonFortune')

@section('content')
    <div class="d-flex flex-column h-100 gap-3">
        <div class="derivatives-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h1 class="mb-0">Backtest Result</h1>
                    </div>
                    <p class="mb-0 text-secondary">
                        Integrasi QuantConnect (manage projects, files, compile, backtests).
                    </p>
                </div>
            </div>
        </div>

	        <div class="df-panel p-4">
	            <div class="d-flex flex-column gap-3">
                <div>
                    <div class="fw-semibold mb-1">Kebutuhan awal</div>
                    <div class="text-secondary small">
                        Isi di server `.env`: <code>QC_USER_ID</code> dan <code>QC_API_TOKEN</code> (opsional: <code>QC_ORGANIZATION_ID</code>).
                        Setelah itu jalankan: <code>php artisan optimize:clear && php artisan config:cache</code>.
                    </div>
                </div>

                <div class="d-flex flex-column gap-2">
                    <div class="fw-semibold">Status konfigurasi</div>
                    <div class="small text-secondary d-flex flex-column gap-1">
                        <div><span class="text-muted">Base URL:</span> <code>{{ $qc['base_url'] ?? '-' }}</code></div>
                        <div><span class="text-muted">User ID:</span> <code>{{ ($qc['user_id_masked'] ?? '') !== '' ? $qc['user_id_masked'] : '(belum di-set)' }}</code></div>
                        <div><span class="text-muted">Organization ID:</span> <code>{{ ($qc['organization_id_masked'] ?? '') !== '' ? $qc['organization_id_masked'] : '(opsional)' }}</code></div>
                        <div>
                            <span class="text-muted">Configured:</span>
                            @if(($qc['configured'] ?? false) === true)
                                <span class="text-success fw-semibold">YES</span>
                            @else
                                <span class="text-danger fw-semibold">NO</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button id="qc-auth-test" class="btn btn-primary" @disabled(!($qc['configured'] ?? false))>
                        Test Authentication
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ route('api.quantconnect.authenticate', absolute: false) }}" target="_blank" rel="noopener">
                        Open JSON
                    </a>
                    <div class="text-secondary small">
                        Endpoint: <code>{{ route('api.quantconnect.authenticate', absolute: false) }}</code>
                    </div>
                </div>

                <div>
                    <div class="fw-semibold mb-2">Hasil test</div>
                    <pre id="qc-auth-output" class="mb-0 p-3 bg-body-tertiary rounded small text-wrap" style="min-height: 96px; white-space: pre-wrap;">Klik tombol “Test Authentication”.</pre>
                </div>
            </div>
        </div>

        <div class="df-panel p-4">
            <div class="d-flex flex-column gap-3">
                <div>
                    <div class="fw-semibold mb-1">Projects & Backtests</div>
                    <div class="text-secondary small">
                        Ini ambil data langsung dari QuantConnect API (server-side). Kalau nanti mau diproteksi, bisa kita pasang auth/role.
                    </div>
                </div>

                @if(($qc['configured'] ?? false) !== true)
                    <div class="text-secondary">Belum bisa load data karena QC belum di-set.</div>
                @else
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button id="qc-load-projects" class="btn btn-outline-primary btn-sm">
                            Load Projects
                        </button>

                        <select id="qc-project-select" class="form-select form-select-sm" style="min-width: 240px; max-width: 420px;">
                            <option value="">(pilih project)</option>
                        </select>

                        <label class="form-check d-flex align-items-center gap-2 m-0">
                            <input id="qc-include-stats" class="form-check-input" type="checkbox" checked>
                            <span class="form-check-label small text-secondary">Include stats</span>
                        </label>

                        <button id="qc-load-backtests" class="btn btn-outline-primary btn-sm" disabled>
                            Load Backtests
                        </button>
                    </div>

                    <div id="qc-data-status" class="small text-secondary"></div>

                    <div class="d-flex flex-column gap-2">
                        <div class="fw-semibold">Compile & Run Backtest</div>
                        <div class="d-flex align-items-end gap-2 flex-wrap">
                            <button id="qc-compile-create" class="btn btn-outline-primary btn-sm" disabled>
                                Compile
                            </button>
                            <button id="qc-compile-refresh" class="btn btn-outline-secondary btn-sm" disabled>
                                Check Compile
                            </button>
                            <div class="flex-grow-1"></div>
                            <input id="qc-backtest-name" class="form-control form-control-sm" style="min-width: 220px; max-width: 360px;"
                                placeholder="Backtest name (optional)">
                            <button id="qc-backtest-create" class="btn btn-primary btn-sm" disabled>
                                Create Backtest
                            </button>
                        </div>
                        <div id="qc-compile-status" class="small text-secondary"></div>
                        <pre id="qc-compile-logs" class="mb-0 p-3 bg-body-tertiary rounded small text-wrap"
                            style="min-height: 90px; white-space: pre-wrap;">Compile logs will appear here.</pre>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-secondary">
                                    <th style="min-width: 220px;">Name</th>
                                    <th style="min-width: 140px;">Status</th>
                                    <th style="min-width: 170px;">Created</th>
                                    <th style="min-width: 120px;" class="text-end">Net Profit</th>
                                    <th style="min-width: 120px;" class="text-end">Sharpe</th>
                                    <th style="min-width: 120px;" class="text-end">Drawdown</th>
                                    <th style="min-width: 120px;" class="text-end">Win Rate</th>
                                    <th style="min-width: 100px;" class="text-end">Trades</th>
                                    <th style="min-width: 210px;">Backtest ID</th>
                                    <th style="min-width: 160px;" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="qc-backtests-body" class="small"></tbody>
                        </table>
                    </div>
                @endif
	            </div>
	        </div>

	        <div class="df-panel p-4">
	            <div class="d-flex flex-column gap-3">
	                <div>
	                    <div class="fw-semibold mb-1">Project Files</div>
	                    <div class="text-secondary small">
	                        Edit file langsung di QuantConnect project (create/update/rename/delete).
	                    </div>
	                </div>

	                @if(($qc['configured'] ?? false) !== true)
	                    <div class="text-secondary">Belum bisa load data karena QC belum di-set.</div>
	                @else
	                    <div class="d-flex align-items-center gap-2 flex-wrap">
	                        <button id="qc-files-load" class="btn btn-outline-primary btn-sm" disabled>
	                            Load Files
	                        </button>

	                        <select id="qc-file-select" class="form-select form-select-sm" style="min-width: 240px; max-width: 420px;" disabled>
	                            <option value="">(pilih file)</option>
	                        </select>

	                        <button id="qc-file-reload" class="btn btn-outline-secondary btn-sm" disabled>
	                            Reload File
	                        </button>
	                    </div>

	                    <div class="d-flex align-items-center gap-2 flex-wrap">
	                        <input id="qc-file-new-name" class="form-control form-control-sm" style="min-width: 240px; max-width: 420px;"
	                            placeholder="newfile.py">
	                        <button id="qc-file-create" class="btn btn-outline-primary btn-sm" disabled>
	                            Create
	                        </button>

	                        <input id="qc-file-rename-name" class="form-control form-control-sm" style="min-width: 240px; max-width: 420px;"
	                            placeholder="rename to...">
	                        <button id="qc-file-rename" class="btn btn-outline-secondary btn-sm" disabled>
	                            Rename
	                        </button>

	                        <button id="qc-file-delete" class="btn btn-outline-danger btn-sm" disabled>
	                            Delete
	                        </button>
	                    </div>

	                    <div class="d-flex align-items-center gap-2 flex-wrap">
	                        <button id="qc-file-save" class="btn btn-primary btn-sm" disabled>
	                            Save
	                        </button>
	                        <div id="qc-files-status" class="small text-secondary"></div>
	                    </div>

	                    <textarea id="qc-file-editor" class="form-control font-monospace" rows="14"
	                        placeholder="Select a file to load its content..." disabled></textarea>
	                @endif
	            </div>
	        </div>
	    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            const button = document.getElementById('qc-auth-test');
            const output = document.getElementById('qc-auth-output');
            if (!button || !output) return;

            const endpoint = @json(route('api.quantconnect.authenticate', absolute: false));

            button.addEventListener('click', async () => {
                button.disabled = true;
                output.textContent = 'Checking QuantConnect authentication...';

                try {
                    const response = await fetch(endpoint, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const text = await response.text();

                    try {
                        const json = JSON.parse(text);
                        output.textContent = JSON.stringify(json, null, 2);
                    } catch (e) {
                        output.textContent = text;
                    }
                } catch (error) {
                    output.textContent = 'Error: ' + (error && error.message ? error.message : String(error));
                } finally {
                    button.disabled = false;
                }
            });
        })();
    </script>

	    <script>
	        (function () {
	            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

	            const loadProjectsButton = document.getElementById('qc-load-projects');
	            const projectSelect = document.getElementById('qc-project-select');
	            const includeStatsToggle = document.getElementById('qc-include-stats');
	            const loadBacktestsButton = document.getElementById('qc-load-backtests');
	            const backtestsBody = document.getElementById('qc-backtests-body');
	            const statusEl = document.getElementById('qc-data-status');

	            const compileCreateButton = document.getElementById('qc-compile-create');
	            const compileRefreshButton = document.getElementById('qc-compile-refresh');
	            const compileStatusEl = document.getElementById('qc-compile-status');
	            const compileLogsEl = document.getElementById('qc-compile-logs');
	            const backtestNameInput = document.getElementById('qc-backtest-name');
	            const backtestCreateButton = document.getElementById('qc-backtest-create');

	            const filesLoadButton = document.getElementById('qc-files-load');
	            const fileSelect = document.getElementById('qc-file-select');
	            const fileReloadButton = document.getElementById('qc-file-reload');
	            const fileNewNameInput = document.getElementById('qc-file-new-name');
	            const fileCreateButton = document.getElementById('qc-file-create');
	            const fileRenameInput = document.getElementById('qc-file-rename-name');
	            const fileRenameButton = document.getElementById('qc-file-rename');
	            const fileDeleteButton = document.getElementById('qc-file-delete');
	            const fileSaveButton = document.getElementById('qc-file-save');
	            const filesStatusEl = document.getElementById('qc-files-status');
	            const fileEditor = document.getElementById('qc-file-editor');

            if (!loadProjectsButton || !projectSelect || !includeStatsToggle || !loadBacktestsButton || !backtestsBody || !statusEl) {
                return;
            }

	            const projectsEndpoint = @json(route('api.quantconnect.projects', absolute: false));
	            const backtestsEndpoint = @json(route('api.quantconnect.backtests', absolute: false));
	            const compileCreateEndpoint = @json(route('api.quantconnect.compile.create', absolute: false));
	            const compileReadEndpoint = @json(route('api.quantconnect.compile.read', absolute: false));
	            const backtestsCreateEndpoint = @json(route('api.quantconnect.backtests.create', absolute: false));
	            const backtestsUpdateEndpoint = @json(route('api.quantconnect.backtests.update', absolute: false));
	            const backtestsDeleteEndpoint = @json(route('api.quantconnect.backtests.delete', absolute: false));
	            const reportBacktestEndpoint = @json(route('api.quantconnect.reports.backtest', absolute: false));

	            const filesReadEndpoint = @json(route('api.quantconnect.files.read', absolute: false));
	            const filesCreateEndpoint = @json(route('api.quantconnect.files.create', absolute: false));
	            const filesUpdateEndpoint = @json(route('api.quantconnect.files.update', absolute: false));
	            const filesRenameEndpoint = @json(route('api.quantconnect.files.rename', absolute: false));
	            const filesDeleteEndpoint = @json(route('api.quantconnect.files.delete', absolute: false));

	            const setStatus = (text) => {
	                statusEl.textContent = text || '';
	            };

	            const setCompileStatus = (text) => {
	                if (!compileStatusEl) return;
	                compileStatusEl.textContent = text || '';
	            };

	            const setCompileLogs = (text) => {
	                if (!compileLogsEl) return;
	                compileLogsEl.textContent = text || '';
	            };

	            const setFilesStatus = (text) => {
	                if (!filesStatusEl) return;
	                filesStatusEl.textContent = text || '';
	            };

	            const getProjectId = () => {
	                const v = String(projectSelect.value || '').trim();
	                const n = Number(v);
	                return Number.isFinite(n) && n > 0 ? Math.trunc(n) : 0;
	            };

	            let currentCompileId = '';
	            let currentCompileState = '';
	            let compilePollTimer = null;
	            let isCompiling = false;

	            const stopCompilePolling = () => {
	                if (compilePollTimer) {
	                    clearTimeout(compilePollTimer);
	                    compilePollTimer = null;
	                }
	            };

	            const setControlsState = () => {
	                const hasProject = getProjectId() > 0;

	                loadBacktestsButton.disabled = !hasProject;

	                if (compileCreateButton) compileCreateButton.disabled = !hasProject || isCompiling;
	                if (compileRefreshButton) compileRefreshButton.disabled = !hasProject || currentCompileId === '';
	                if (backtestCreateButton) {
	                    backtestCreateButton.disabled = !hasProject || currentCompileId === '' || currentCompileState !== 'BuildSuccess';
	                }

	                if (filesLoadButton) filesLoadButton.disabled = !hasProject;
	                if (fileSelect) fileSelect.disabled = !hasProject;
	                if (fileCreateButton) fileCreateButton.disabled = !hasProject;
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
                return n.toFixed(2) + '%';
            };

            const formatDate = (iso) => {
                if (!iso) return '-';
                if (typeof iso === 'string' && iso.includes(' ') && !iso.includes('T')) {
                    return iso;
                }
                const d = new Date(iso);
                if (Number.isNaN(d.getTime())) return escapeText(iso);
                return d.toLocaleString();
            };

	            const fetchJson = async (url, options = {}) => {
	                const response = await fetch(url, {
	                    credentials: 'same-origin',
	                    headers: {
	                        'Accept': 'application/json',
	                        ...(options.headers || {}),
	                    },
	                    ...options,
	                });
	                const text = await response.text();
	                let json;
	                try { json = JSON.parse(text); } catch (e) { json = null; }

	                if (!response.ok || (json && json.success === false)) {
	                    const message = json?.error?.message || json?.message || text || `HTTP ${response.status}`;
	                    throw new Error(message);
	                }

	                return json ?? {};
	            };

	            const postJson = async (url, body) => {
	                return fetchJson(url, {
	                    method: 'POST',
	                    headers: {
	                        'Content-Type': 'application/json',
	                        'X-Requested-With': 'XMLHttpRequest',
	                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
	                    },
	                    body: JSON.stringify(body ?? {}),
	                });
	            };

            const clearBacktests = () => {
                backtestsBody.innerHTML = '';
            };

            const renderBacktests = (items) => {
                clearBacktests();

	                if (!Array.isArray(items) || items.length === 0) {
	                    const tr = document.createElement('tr');
	                    const td = document.createElement('td');
	                    td.colSpan = 10;
	                    td.className = 'text-secondary py-3';
	                    td.textContent = 'Tidak ada backtest.';
	                    tr.appendChild(td);
	                    backtestsBody.appendChild(tr);
	                    return;
                }

	                items.forEach((bt) => {
	                    const tr = document.createElement('tr');
	                    const backtestId = escapeText(bt?.backtestId ?? bt?.backtestID ?? bt?.id ?? '');

	                    const cols = [
	                        escapeText(bt?.name ?? '-'),
	                        escapeText(bt?.status ?? '-'),
	                        formatDate(bt?.created),
	                        formatPercent(bt?.netProfit),
	                        formatNumber(bt?.sharpeRatio),
	                        formatPercent(bt?.drawdown),
	                        formatPercent(bt?.winRate),
	                        escapeText(bt?.trades ?? '-'),
	                        backtestId !== '' ? backtestId : '-',
	                    ];

	                    cols.forEach((text, idx) => {
	                        const td = document.createElement('td');
	                        td.textContent = text;
	                        if ([3, 4, 5, 6, 7].includes(idx)) td.classList.add('text-end');
	                        tr.appendChild(td);
	                    });

	                    const actionsTd = document.createElement('td');
	                    actionsTd.className = 'text-end';

	                    const group = document.createElement('div');
	                    group.className = 'btn-group btn-group-sm';

	                    const reportBtn = document.createElement('button');
	                    reportBtn.type = 'button';
	                    reportBtn.className = 'btn btn-outline-secondary';
	                    reportBtn.textContent = 'Report';
	                    reportBtn.disabled = backtestId === '';
	                    reportBtn.addEventListener('click', () => {
	                        const projectId = getProjectId();
	                        if (!projectId || backtestId === '') return;
	                        const url = `${reportBacktestEndpoint}?projectId=${encodeURIComponent(String(projectId))}&backtestId=${encodeURIComponent(backtestId)}`;
	                        window.open(url, '_blank', 'noopener');
	                    });

	                    const renameBtn = document.createElement('button');
	                    renameBtn.type = 'button';
	                    renameBtn.className = 'btn btn-outline-secondary';
	                    renameBtn.textContent = 'Rename';
	                    renameBtn.disabled = backtestId === '';
	                    renameBtn.addEventListener('click', async () => {
	                        const projectId = getProjectId();
	                        if (!projectId || backtestId === '') return;
	                        const nextName = window.prompt('Rename backtest to:', escapeText(bt?.name ?? ''));
	                        if (!nextName || !nextName.trim()) return;

	                        try {
	                            setStatus('Renaming backtest...');
	                            await postJson(backtestsUpdateEndpoint, {
	                                projectId,
	                                backtestId,
	                                name: nextName.trim(),
	                            });
	                            setStatus('Backtest renamed.');
	                            await loadBacktests();
	                        } catch (err) {
	                            setStatus('Error renaming backtest: ' + (err?.message || String(err)));
	                        }
	                    });

	                    const deleteBtn = document.createElement('button');
	                    deleteBtn.type = 'button';
	                    deleteBtn.className = 'btn btn-outline-danger';
	                    deleteBtn.textContent = 'Delete';
	                    deleteBtn.disabled = backtestId === '';
	                    deleteBtn.addEventListener('click', async () => {
	                        const projectId = getProjectId();
	                        if (!projectId || backtestId === '') return;
	                        if (!window.confirm(`Delete backtest ${backtestId}?`)) return;

	                        try {
	                            setStatus('Deleting backtest...');
	                            await postJson(backtestsDeleteEndpoint, { projectId, backtestId });
	                            setStatus('Backtest deleted.');
	                            await loadBacktests();
	                        } catch (err) {
	                            setStatus('Error deleting backtest: ' + (err?.message || String(err)));
	                        }
	                    });

	                    group.appendChild(reportBtn);
	                    group.appendChild(renameBtn);
	                    group.appendChild(deleteBtn);

	                    actionsTd.appendChild(group);
	                    tr.appendChild(actionsTd);

	                    backtestsBody.appendChild(tr);
	                });
	            };

            const loadProjects = async () => {
                loadProjectsButton.disabled = true;
                setStatus('Loading projects...');

                try {
                    const result = await fetchJson(projectsEndpoint);
                    const projects = result?.data?.projects || [];

                    projectSelect.innerHTML = '';

                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = '(pilih project)';
                    projectSelect.appendChild(defaultOption);

	                    projects.forEach((p) => {
	                        const option = document.createElement('option');
	                        option.value = String(p.projectId);
	                        option.textContent = `${p.name} (#${p.projectId})`;
	                        projectSelect.appendChild(option);
	                    });

	                    setStatus(`Loaded ${projects.length} projects.`);
	                    setControlsState();
	                } catch (err) {
	                    setStatus('Error loading projects: ' + (err?.message || String(err)));
	                } finally {
	                    loadProjectsButton.disabled = false;
	                    setControlsState();
	                }
	            };

	            const loadBacktests = async () => {
	                const projectId = getProjectId();
	                if (!projectId) return;

	                loadBacktestsButton.disabled = true;
	                clearBacktests();
	                setStatus('Loading backtests...');

	                try {
	                    const includeStatistics = includeStatsToggle.checked ? '1' : '0';
	                    const url = `${backtestsEndpoint}?projectId=${encodeURIComponent(String(projectId))}&includeStatistics=${includeStatistics}`;
	                    const result = await fetchJson(url);

	                    const items = result?.data?.backtests || result?.data?.backtest || [];
	                    setStatus(`Loaded ${items.length} backtests.`);
	                    renderBacktests(items);
	                } catch (err) {
	                    setStatus('Error loading backtests: ' + (err?.message || String(err)));
	                } finally {
	                    setControlsState();
	                }
	            };

	            const normalizeCompile = (result) => {
	                const compile = result?.data?.compile ?? result?.data ?? {};
	                const compileId = escapeText(compile?.compileId ?? compile?.id ?? result?.data?.compileId ?? result?.data?.id ?? '');
	                const state = escapeText(compile?.state ?? compile?.status ?? result?.data?.state ?? '');
	                const logs = escapeText(compile?.logs ?? result?.data?.logs ?? '');
	                return { compileId, state, logs };
	            };

	            const readCompile = async (projectId, compileId) => {
	                const url = `${compileReadEndpoint}?projectId=${encodeURIComponent(String(projectId))}&compileId=${encodeURIComponent(String(compileId))}`;
	                return fetchJson(url);
	            };

	            const updateCompileView = (compileId, state, logs) => {
	                const parts = [];
	                if (compileId) parts.push(`Compile ID: ${compileId}`);
	                if (state) parts.push(`State: ${state}`);
	                setCompileStatus(parts.join(' | '));
	                if (logs) setCompileLogs(logs);
	            };

	            const pollCompileUntilDone = async (projectId, compileId, attempt = 0) => {
	                stopCompilePolling();

	                const maxAttempts = 60; // ~2 minutes (2s interval)
	                if (attempt >= maxAttempts) {
	                    isCompiling = false;
	                    setControlsState();
	                    setCompileStatus(`Compile ID: ${compileId} | State: ${currentCompileState || 'Unknown'} | (timeout)`);
	                    return;
	                }

	                try {
	                    const result = await readCompile(projectId, compileId);
	                    const { state, logs } = normalizeCompile(result);
	                    if (state) currentCompileState = state;
	                    updateCompileView(compileId, currentCompileState, logs);
	                    setControlsState();

	                    if (currentCompileState === 'BuildSuccess' || currentCompileState === 'BuildError') {
	                        isCompiling = false;
	                        setControlsState();
	                        return;
	                    }
	                } catch (err) {
	                    setCompileStatus('Error reading compile: ' + (err?.message || String(err)));
	                }

	                compilePollTimer = setTimeout(() => pollCompileUntilDone(projectId, compileId, attempt + 1), 2000);
	            };

	            const startCompile = async () => {
	                const projectId = getProjectId();
	                if (!projectId || !compileCreateButton) return;

	                compileCreateButton.disabled = true;
	                stopCompilePolling();
	                isCompiling = true;
	                currentCompileId = '';
	                currentCompileState = '';
	                setCompileStatus('Creating compile...');
	                setCompileLogs('');
	                setControlsState();

	                try {
	                    const result = await postJson(compileCreateEndpoint, { projectId });
	                    const { compileId, state, logs } = normalizeCompile(result);
	                    currentCompileId = compileId || '';
	                    currentCompileState = state || '';

	                    if (!currentCompileId) {
	                        throw new Error('Compile created but compileId is missing in response.');
	                    }

	                    updateCompileView(currentCompileId, currentCompileState, logs);
	                    setControlsState();
	                    await pollCompileUntilDone(projectId, currentCompileId, 0);
	                } catch (err) {
	                    isCompiling = false;
	                    setCompileStatus('Error creating compile: ' + (err?.message || String(err)));
	                } finally {
	                    setControlsState();
	                }
	            };

	            const refreshCompile = async () => {
	                const projectId = getProjectId();
	                if (!projectId || !currentCompileId) return;

	                try {
	                    setCompileStatus(`Refreshing compile ${currentCompileId}...`);
	                    const result = await readCompile(projectId, currentCompileId);
	                    const { state, logs } = normalizeCompile(result);
	                    if (state) currentCompileState = state;
	                    updateCompileView(currentCompileId, currentCompileState, logs);
	                    setControlsState();
	                } catch (err) {
	                    setCompileStatus('Error reading compile: ' + (err?.message || String(err)));
	                }
	            };

	            const createBacktest = async () => {
	                const projectId = getProjectId();
	                if (!projectId || !currentCompileId) return;

	                const backtestName = backtestNameInput?.value ? String(backtestNameInput.value).trim() : '';

	                if (backtestCreateButton) backtestCreateButton.disabled = true;
	                setStatus('Creating backtest...');

	                try {
	                    const payload = { projectId, compileId: currentCompileId };
	                    if (backtestName) payload.backtestName = backtestName;

	                    const result = await postJson(backtestsCreateEndpoint, payload);
	                    const id = escapeText(result?.data?.backtestId ?? result?.data?.backtest?.backtestId ?? result?.data?.id ?? '');
	                    setStatus(id ? `Backtest created: ${id}` : 'Backtest created.');
	                    await loadBacktests();
	                } catch (err) {
	                    setStatus('Error creating backtest: ' + (err?.message || String(err)));
	                } finally {
	                    setControlsState();
	                }
	            };

		            const normalizeFilesList = (result) => {
		                const data = result?.data ?? {};
		                const files = data?.files ?? data?.Files ?? [];

		                if (Array.isArray(files)) return files;
		                if (files && typeof files === 'object') return Object.values(files);
		                return [];
		            };

		            const normalizeFileContent = (result) => {
		                const data = result?.data ?? {};
		                const file = data?.file ?? data?.File ?? {};
		                const firstFromFiles = Array.isArray(data?.files) ? data.files[0] : null;
		                return escapeText(
		                    data?.content ??
		                    data?.Content ??
		                    firstFromFiles?.content ??
		                    firstFromFiles?.Content ??
		                    data?.file?.content ??
		                    data?.file?.Content ??
		                    file?.content ??
		                    file?.Content ??
		                    data?.file?.text ??
		                    file?.text ??
		                    file?.Text ??
		                    ''
		                );
		            };

		            const loadFilesList = async () => {
		                const projectId = getProjectId();
		                if (!projectId) return;

		                if (filesLoadButton) filesLoadButton.disabled = true;
		                setFilesStatus('Loading files...');

		                try {
		                    const url = `${filesReadEndpoint}?projectId=${encodeURIComponent(String(projectId))}`;
		                    const result = await fetchJson(url);
		                    const files = normalizeFilesList(result);

		                    if (fileSelect) {
		                        const previousSelection = escapeText(fileSelect.value || '').trim();
		                        fileSelect.innerHTML = '';

		                        const defaultOption = document.createElement('option');
		                        defaultOption.value = '';
		                        defaultOption.textContent = '(pilih file)';
		                        fileSelect.appendChild(defaultOption);

		                        const isDirectory = (item, name) => {
		                            if (name.endsWith('/')) return true;
		                            if (!item || typeof item !== 'object') return false;
		                            if (Boolean(item?.isDirectory ?? item?.IsDirectory ?? item?.directory ?? item?.Directory)) return true;
		                            const t = String(item?.type ?? item?.Type ?? '').toLowerCase();
		                            return t === 'directory' || t === 'folder';
		                        };

		                        const names = files
		                            .map((f) => {
		                                if (typeof f === 'string') return { name: f, isDir: f.endsWith('/') };
		                                const name = escapeText(f?.name ?? f?.fileName ?? f?.path ?? f?.key ?? f?.Key ?? '');
		                                return { name, isDir: isDirectory(f, name) };
		                            })
		                            .filter((x) => x.name !== '')
		                            .sort((a, b) => a.name.localeCompare(b.name));

		                        names.forEach(({ name, isDir }) => {
		                                const opt = document.createElement('option');
		                                opt.value = name;
		                                opt.textContent = name;
		                                if (isDir) opt.disabled = true;
		                                fileSelect.appendChild(opt);
		                            });

		                        if (previousSelection !== '') {
		                            const stillExists = Array.from(fileSelect.options).some((o) => o.value === previousSelection);
		                            if (stillExists) {
		                                fileSelect.value = previousSelection;
		                            }
		                        }
		                    }

		                    setFilesStatus(`Loaded ${files.length} items.`);
		                } catch (err) {
		                    setFilesStatus('Error loading files: ' + (err?.message || String(err)));
		                } finally {
		                    if (filesLoadButton) filesLoadButton.disabled = getProjectId() === 0;
		                    setControlsState();
		                }
		            };

	            const loadFile = async (name) => {
	                const projectId = getProjectId();
	                if (!projectId || !name) return;

	                if (fileReloadButton) fileReloadButton.disabled = true;
	                setFilesStatus(`Loading ${name}...`);

	                try {
	                    const url = `${filesReadEndpoint}?projectId=${encodeURIComponent(String(projectId))}&name=${encodeURIComponent(String(name))}`;
	                    const result = await fetchJson(url);
	                    const content = normalizeFileContent(result);

		                    if (fileEditor) {
		                        fileEditor.value = content;
		                        fileEditor.disabled = false;
		                    }

		                    if (fileSaveButton) fileSaveButton.disabled = false;
		                    if (fileRenameButton) fileRenameButton.disabled = false;
		                    if (fileDeleteButton) fileDeleteButton.disabled = false;
		                    if (fileReloadButton) fileReloadButton.disabled = false;

		                    setFilesStatus(`Loaded ${name}.`);
		                } catch (err) {
		                    setFilesStatus('Error loading file: ' + (err?.message || String(err)));
		                } finally {
		                    if (fileReloadButton && (fileSelect?.value ?? '') !== '') fileReloadButton.disabled = false;
		                }
		            };

	            const saveFile = async () => {
	                const projectId = getProjectId();
	                const name = escapeText(fileSelect?.value ?? '');
	                if (!projectId || !name || !fileEditor) return;

	                if (fileSaveButton) fileSaveButton.disabled = true;
	                setFilesStatus('Saving...');

	                try {
	                    await postJson(filesUpdateEndpoint, {
	                        projectId,
	                        name,
	                        content: String(fileEditor.value ?? ''),
	                    });
	                    setFilesStatus(`Saved ${name}.`);
	                } catch (err) {
	                    setFilesStatus('Error saving file: ' + (err?.message || String(err)));
	                } finally {
	                    if (fileSaveButton) fileSaveButton.disabled = false;
	                }
	            };

	            const createFile = async () => {
	                const projectId = getProjectId();
	                const name = fileNewNameInput?.value ? String(fileNewNameInput.value).trim() : '';
	                if (!projectId || !name) return;

	                if (fileCreateButton) fileCreateButton.disabled = true;
	                setFilesStatus('Creating file...');

	                try {
	                    await postJson(filesCreateEndpoint, { projectId, name, content: '' });
	                    setFilesStatus(`Created ${name}.`);
	                    if (fileNewNameInput) fileNewNameInput.value = '';
	                    await loadFilesList();
	                    if (fileSelect) fileSelect.value = name;
	                    await loadFile(name);
	                } catch (err) {
	                    setFilesStatus('Error creating file: ' + (err?.message || String(err)));
	                } finally {
	                    setControlsState();
	                }
	            };

	            const renameFile = async () => {
	                const projectId = getProjectId();
	                const oldFileName = escapeText(fileSelect?.value ?? '');
	                const newName = fileRenameInput?.value ? String(fileRenameInput.value).trim() : '';
	                if (!projectId || !oldFileName || !newName) return;

	                if (fileRenameButton) fileRenameButton.disabled = true;
	                setFilesStatus('Renaming file...');

	                try {
	                    await postJson(filesRenameEndpoint, { projectId, oldFileName, newName });
	                    setFilesStatus(`Renamed to ${newName}.`);
	                    if (fileRenameInput) fileRenameInput.value = '';
	                    await loadFilesList();
	                    if (fileSelect) fileSelect.value = newName;
	                    await loadFile(newName);
	                } catch (err) {
	                    setFilesStatus('Error renaming file: ' + (err?.message || String(err)));
	                } finally {
	                    if (fileRenameButton) fileRenameButton.disabled = false;
	                }
	            };

	            const deleteFile = async () => {
	                const projectId = getProjectId();
	                const name = escapeText(fileSelect?.value ?? '');
	                if (!projectId || !name) return;

	                if (!window.confirm(`Delete file ${name}?`)) return;

	                if (fileDeleteButton) fileDeleteButton.disabled = true;
	                setFilesStatus('Deleting file...');

	                try {
	                    await postJson(filesDeleteEndpoint, { projectId, name });
	                    setFilesStatus(`Deleted ${name}.`);

	                    if (fileSelect) fileSelect.value = '';
	                    if (fileEditor) {
	                        fileEditor.value = '';
	                        fileEditor.disabled = true;
	                    }
	                    if (fileSaveButton) fileSaveButton.disabled = true;
	                    if (fileRenameButton) fileRenameButton.disabled = true;
	                    if (fileDeleteButton) fileDeleteButton.disabled = true;

	                    await loadFilesList();
	                } catch (err) {
	                    setFilesStatus('Error deleting file: ' + (err?.message || String(err)));
	                } finally {
	                    if (fileDeleteButton) fileDeleteButton.disabled = false;
	                }
	            };

	            loadProjectsButton.addEventListener('click', loadProjects);

	            projectSelect.addEventListener('change', () => {
	                stopCompilePolling();
	                isCompiling = false;
	                currentCompileId = '';
	                currentCompileState = '';
	                setCompileStatus('');
	                setCompileLogs('');
	                setFilesStatus('');
	                clearBacktests();

	                if (fileSelect) fileSelect.value = '';
	                if (fileEditor) {
	                    fileEditor.value = '';
	                    fileEditor.disabled = true;
	                }
		                if (fileSaveButton) fileSaveButton.disabled = true;
		                if (fileRenameButton) fileRenameButton.disabled = true;
		                if (fileDeleteButton) fileDeleteButton.disabled = true;
		                if (fileReloadButton) fileReloadButton.disabled = true;

		                setControlsState();
		                if (getProjectId()) {
		                    loadBacktests();
		                    loadFilesList();
	                }
	            });

	            loadBacktestsButton.addEventListener('click', loadBacktests);

	            if (compileCreateButton) compileCreateButton.addEventListener('click', startCompile);
	            if (compileRefreshButton) compileRefreshButton.addEventListener('click', refreshCompile);
	            if (backtestCreateButton) backtestCreateButton.addEventListener('click', createBacktest);

	            if (filesLoadButton) filesLoadButton.addEventListener('click', loadFilesList);
		            if (fileSelect) {
		                fileSelect.addEventListener('change', () => {
		                    const name = escapeText(fileSelect.value || '').trim();
		                    if (!name) {
		                        if (fileEditor) {
		                            fileEditor.value = '';
		                            fileEditor.disabled = true;
		                        }
		                        if (fileSaveButton) fileSaveButton.disabled = true;
		                        if (fileRenameButton) fileRenameButton.disabled = true;
		                        if (fileDeleteButton) fileDeleteButton.disabled = true;
		                        if (fileReloadButton) fileReloadButton.disabled = true;
		                        return;
		                    }
		                    loadFile(name);
		                });
		            }
	            if (fileReloadButton) {
	                fileReloadButton.addEventListener('click', () => {
	                    const name = escapeText(fileSelect?.value ?? '').trim();
	                    if (!name) return;
	                    loadFile(name);
	                });
	            }
	            if (fileSaveButton) fileSaveButton.addEventListener('click', saveFile);
	            if (fileCreateButton) fileCreateButton.addEventListener('click', createFile);
	            if (fileRenameButton) fileRenameButton.addEventListener('click', renameFile);
	            if (fileDeleteButton) fileDeleteButton.addEventListener('click', deleteFile);

	            // Auto load projects once on page load
	            setControlsState();
	            loadProjects();
	        })();
	    </script>
@endsection
