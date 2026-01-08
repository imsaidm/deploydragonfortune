<?php

namespace App\Http\Controllers;

use App\Models\QuantconnectSignal;
use App\Models\QuantconnectProjectSession;
use App\Services\SignalService;
use App\Services\QuantConnectClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class SignalManagerController extends Controller
{
    protected SignalService $signalService;
    protected QuantConnectClient $quantConnectClient;

    public function __construct(SignalService $signalService, QuantConnectClient $quantConnectClient)
    {
        $this->signalService = $signalService;
        $this->quantConnectClient = $quantConnectClient;
    }

    /**
     * Display the signal manager dashboard
     */
    public function dashboard(): View
    {
        return view('signal-manager.dashboard');
    }

    /**
     * Get signals with filtering options
     */
    public function getSignals(Request $request): JsonResponse
    {
        try {
            $filters = $this->buildFilters($request);

            $query = QuantconnectSignal::query()
                ->with('projectSession')
                ->latest();

            // Apply filters
            if (!empty($filters['project_id'])) {
                $query->byProject($filters['project_id']);
            }

            if (!empty($filters['symbol'])) {
                $query->bySymbol($filters['symbol']);
            }

            if (!empty($filters['signal_type'])) {
                $query->byType($filters['signal_type']);
            }

            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                $query->byDateRange($filters['start_date'], $filters['end_date']);
            }

            // Pagination
            $perPage = min($request->get('per_page', 25), 100);
            $signals = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $signals->items(),
                'pagination' => [
                    'current_page' => $signals->currentPage(),
                    'last_page' => $signals->lastPage(),
                    'per_page' => $signals->perPage(),
                    'total' => $signals->total(),
                    'from' => $signals->firstItem(),
                    'to' => $signals->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to retrieve signals: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get available projects from QuantConnect API with real-time status
     */
    public function getProjects(): JsonResponse
    {
        try {
            if (!$this->quantConnectClient->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 400,
                        'message' => 'QuantConnect credentials are missing. Set QC_USER_ID and QC_API_TOKEN in .env'
                    ]
                ], 400);
            }

            // Cache projects for 30 seconds to avoid excessive API calls
            $cacheKey = 'qc:projects:' . sha1($this->quantConnectClient->getBaseUrl() . '|' . $this->quantConnectClient->getUserId());

            $result = Cache::remember($cacheKey, now()->addSeconds(30), function () {
                return $this->quantConnectClient->projects();
            });

            if (!($result['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => $result['status'] ?? 500,
                        'message' => $result['error']['message'] ?? 'Failed to fetch projects'
                    ]
                ], $result['status'] ?? 500);
            }

            // Get REAL-TIME live status from /live/list API
            $liveListResult = $this->quantConnectClient->liveList();
            $liveStatuses = [];

            if ($liveListResult['success'] && !empty($liveListResult['data']['live'])) {
                // Get LATEST deployment per project (by launched date)
                foreach ($liveListResult['data']['live'] as $live) {
                    $projectId = $live['projectId'];
                    $launchedDate = $live['launched'] ?? $live['dtLaunched'] ?? '1970-01-01';

                    if (!isset($liveStatuses[$projectId])) {
                        $liveStatuses[$projectId] = $live;
                    } else {
                        $existingLaunched = $liveStatuses[$projectId]['launched'] ?? $liveStatuses[$projectId]['dtLaunched'] ?? '1970-01-01';
                        if (strtotime($launchedDate) > strtotime($existingLaunched)) {
                            $liveStatuses[$projectId] = $live;
                        }
                    }
                }
            }

            // Merge with local project sessions to show signal counts
            $projects = collect($result['data']['projects'] ?? []);
            $localSessions = QuantconnectProjectSession::all()->keyBy('project_id');

            $enrichedProjects = $projects->map(function ($project) use ($localSessions, $liveStatuses) {
                $session = $localSessions->get($project['projectId']);
                $liveStatus = $liveStatuses[$project['projectId']] ?? null;

                // Determine real-time status from QC API
                $qcStatus = null;
                $qcBrokerage = null;
                $qcEquity = null;
                $isRunning = false;
                $isLive = false;

                if ($liveStatus) {
                    $qcStatus = $liveStatus['status'] ?? $liveStatus['state'] ?? null;
                    $qcBrokerage = $liveStatus['brokerage'] ?? null;
                    $qcEquity = $liveStatus['equity'] ?? null;
                    $isRunning = in_array($qcStatus, ['Running', 'Deploying', 'InQueue']);
                    $isLive = $qcBrokerage && !in_array($qcBrokerage, ['Paper Trading', 'PaperBrokerage']);
                }

                // Fallback to codeRunning from projects API
                if (!$isRunning && ($project['codeRunning'] ?? false)) {
                    $isRunning = true;
                    $qcStatus = 'Running';
                }

                return [
                    'project_id' => $project['projectId'],
                    'name' => $project['name'],
                    'description' => $project['description'] ?? '',
                    'created' => $project['created'],
                    'modified' => $project['modified'],
                    'language' => $project['language'] ?? 'C#',
                    'owner_id' => $project['ownerId'] ?? null,
                    'owner' => $project['owner'] ?? false,
                    'encrypted' => $project['encrypted'] ?? false,
                    'code_running' => $isRunning,
                    'lean_environment' => $project['leanEnvironment'] ?? 0,
                    'paper_equity' => $project['paperEquity'] ?? 0,
                    'last_live_deployment' => $project['lastLiveDeployment'] ?? null,
                    // Real-time status from QC /live/list
                    'qc_status' => $qcStatus,
                    'qc_brokerage' => $qcBrokerage,
                    'qc_equity' => $qcEquity,
                    'is_running' => $isRunning,
                    'is_live' => $isLive,
                    // Local session data (signals count etc)
                    'local_session' => $session ? [
                        'is_live' => $session->is_live,
                        'status' => $session->status,
                        'activity_status' => $session->activity_status,
                        'last_signal_at' => $session->last_signal_at?->toISOString(),
                        'signals_count' => $session->signals_count,
                        'recent_signals_count' => $session->recent_signals_count,
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $enrichedProjects->values()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to retrieve projects: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Select a project for monitoring
     */
    public function selectProject(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'project_id' => 'required|integer|min:1',
                'project_name' => 'required|string|max:255',
                'is_live' => 'boolean'
            ]);

            $projectId = $request->get('project_id');
            $projectName = $request->get('project_name');
            $isLive = $request->get('is_live', false);

            // Store selected project in session
            Session::put('selected_project', [
                'project_id' => $projectId,
                'project_name' => $projectName,
                'is_live' => $isLive,
                'selected_at' => now()->toISOString()
            ]);

            // Create or update project session
            $projectSession = QuantconnectProjectSession::updateOrCreate(
                ['project_id' => $projectId],
                [
                    'project_name' => $projectName,
                    'is_live' => $isLive,
                    'status' => 'active'
                ]
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'project_id' => $projectId,
                    'project_name' => $projectName,
                    'is_live' => $isLive,
                    'session' => $projectSession
                ],
                'message' => "Project '{$projectName}' selected successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to select project: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get current selected project from session
     */
    public function getSelectedProject(): JsonResponse
    {
        $selectedProject = Session::get('selected_project');

        if (!$selectedProject) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No project selected'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $selectedProject
        ]);
    }

    /**
     * Get project status and monitoring information
     */
    public function getProjectStatus(Request $request): JsonResponse
    {
        try {
            $projectId = $request->get('project_id');

            if (!$projectId) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 400,
                        'message' => 'Project ID is required'
                    ]
                ], 400);
            }

            $projectSession = QuantconnectProjectSession::where('project_id', $projectId)->first();

            if (!$projectSession) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 404,
                        'message' => 'Project session not found'
                    ]
                ], 404);
            }

            // Update activity status before returning data
            $projectSession->updateActivityStatus();
            $projectSession->refresh();

            // Get project details from QuantConnect API
            $projectDetails = null;
            if ($this->quantConnectClient->isConfigured()) {
                $result = $this->quantConnectClient->projects((int) $projectId);
                if ($result['success'] && !empty($result['data']['projects'])) {
                    $projectDetails = $result['data']['projects'][0] ?? null;
                }
            }

            // Get recent signal activity
            $recentSignals = QuantconnectSignal::where('project_id', $projectId)
                ->where('signal_timestamp', '>=', now()->subHours(24))
                ->orderBy('signal_timestamp', 'desc')
                ->limit(10)
                ->get();

            // Calculate performance metrics for this project
            $totalSignals = QuantconnectSignal::where('project_id', $projectId)->count();
            $realizedPnl = QuantconnectSignal::where('project_id', $projectId)
                ->whereNotNull('realized_pnl')
                ->sum('realized_pnl');

            $profitableSignals = QuantconnectSignal::where('project_id', $projectId)
                ->where('realized_pnl', '>', 0)
                ->count();

            $unprofitableSignals = QuantconnectSignal::where('project_id', $projectId)
                ->where('realized_pnl', '<', 0)
                ->count();

            // Merge project session with QuantConnect project details
            $enrichedSession = array_merge($projectSession->toArray(), [
                'qc_project_details' => $projectDetails,
                'owner' => $projectDetails['owner'] ?? false,
                'encrypted' => $projectDetails['encrypted'] ?? false,
                'code_running' => $projectDetails['codeRunning'] ?? false,
                'lean_environment' => $projectDetails['leanEnvironment'] ?? 0,
                'paper_equity' => $projectDetails['paperEquity'] ?? 0,
                'last_live_deployment' => $projectDetails['lastLiveDeployment'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'project_session' => $enrichedSession,
                    'recent_signals' => $recentSignals,
                    'performance' => [
                        'total_signals' => $totalSignals,
                        'total_realized_pnl' => round($realizedPnl, 2),
                        'profitable_signals' => $profitableSignals,
                        'unprofitable_signals' => $unprofitableSignals,
                        'win_rate' => $profitableSignals + $unprofitableSignals > 0
                            ? round(($profitableSignals / ($profitableSignals + $unprofitableSignals)) * 100, 2)
                            : 0,
                    ],
                    'last_updated' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to retrieve project status: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get signal statistics and performance metrics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $filters = $this->buildFilters($request);

            $query = QuantconnectSignal::query();

            // Apply same filters as getSignals
            if (!empty($filters['project_id'])) {
                $query->byProject($filters['project_id']);
            }

            if (!empty($filters['symbol'])) {
                $query->bySymbol($filters['symbol']);
            }

            if (!empty($filters['signal_type'])) {
                $query->byType($filters['signal_type']);
            }

            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                $query->byDateRange($filters['start_date'], $filters['end_date']);
            }

            // Calculate statistics
            $totalSignals = $query->count();
            $entrySignals = (clone $query)->where('signal_type', 'entry')->count();
            $exitSignals = (clone $query)->where('signal_type', 'exit')->count();

            // PnL calculations
            $realizedPnl = (clone $query)->whereNotNull('realized_pnl')->sum('realized_pnl');
            $profitableSignals = (clone $query)->where('realized_pnl', '>', 0)->count();
            $unprofitableSignals = (clone $query)->where('realized_pnl', '<', 0)->count();

            // Recent activity (last 24 hours)
            $recentSignals = (clone $query)->where('signal_timestamp', '>=', now()->subDay())->count();

            // Symbol distribution
            $symbolStats = (clone $query)
                ->selectRaw('symbol, COUNT(*) as count, AVG(realized_pnl) as avg_pnl')
                ->whereNotNull('realized_pnl')
                ->groupBy('symbol')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => [
                        'total_signals' => $totalSignals,
                        'entry_signals' => $entrySignals,
                        'exit_signals' => $exitSignals,
                        'recent_signals_24h' => $recentSignals,
                    ],
                    'performance' => [
                        'total_realized_pnl' => round($realizedPnl, 2),
                        'profitable_signals' => $profitableSignals,
                        'unprofitable_signals' => $unprofitableSignals,
                        'win_rate' => $profitableSignals + $unprofitableSignals > 0
                            ? round(($profitableSignals / ($profitableSignals + $unprofitableSignals)) * 100, 2)
                            : 0,
                    ],
                    'symbol_breakdown' => $symbolStats->map(function ($stat) {
                        return [
                            'symbol' => $stat->symbol,
                            'signal_count' => $stat->count,
                            'avg_pnl' => round($stat->avg_pnl, 2)
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to retrieve statistics: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Sync project status from QuantConnect API
     * Uses /live/list to get accurate status (Running, Stopped, RuntimeError, Liquidated)
     */
    public function syncProjectStatus(Request $request): JsonResponse
    {
        try {
            if (!$this->quantConnectClient->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 400,
                        'message' => 'QuantConnect credentials are missing'
                    ]
                ], 400);
            }

            $projectId = $request->get('project_id');
            $syncAll = $request->get('sync_all', false);

            $syncedProjects = [];

            if ($syncAll) {
                // Sync all local project sessions
                $projectSessions = QuantconnectProjectSession::all();
            } elseif ($projectId) {
                // Sync specific project
                $projectSessions = QuantconnectProjectSession::where('project_id', $projectId)->get();
            } else {
                // Sync selected project from session
                $selectedProject = Session::get('selected_project');
                if (!$selectedProject) {
                    return response()->json([
                        'success' => true,
                        'data' => [],
                        'message' => 'No project to sync'
                    ]);
                }
                $projectSessions = QuantconnectProjectSession::where('project_id', $selectedProject['project_id'])->get();
            }

            // Get all live algorithms using /live/list API
            // This is more efficient - one API call instead of multiple
            $liveListResult = $this->quantConnectClient->liveList();
            $liveAlgorithms = [];

            if ($liveListResult['success'] && !empty($liveListResult['data']['live'])) {
                // QC returns multiple deployments per project (deployment history)
                // We need to get the LATEST deployment (most recent launched date)
                foreach ($liveListResult['data']['live'] as $live) {
                    $projectId = $live['projectId'];
                    $launchedDate = $live['launched'] ?? $live['dtLaunched'] ?? '1970-01-01';

                    // Only store if no existing entry OR current is more recent
                    if (!isset($liveAlgorithms[$projectId])) {
                        $liveAlgorithms[$projectId] = $live;
                    } else {
                        $existingLaunched = $liveAlgorithms[$projectId]['launched'] ?? $liveAlgorithms[$projectId]['dtLaunched'] ?? '1970-01-01';

                        // Compare dates - keep the most recent deployment
                        if (strtotime($launchedDate) > strtotime($existingLaunched)) {
                            $liveAlgorithms[$projectId] = $live;
                        }
                    }
                }
            }

            foreach ($projectSessions as $session) {
                try {
                    $isRunning = false;
                    $isLive = false;
                    $deploymentStatus = 'stopped';
                    $deploymentId = null;
                    $algorithmId = null;
                    $liveState = null;
                    $brokerage = null;
                    $equity = null;

                    // Check if this project has a live algorithm from /live/list
                    if (isset($liveAlgorithms[$session->project_id])) {
                        $live = $liveAlgorithms[$session->project_id];

                        $deploymentId = $live['deployId'] ?? null;
                        $algorithmId = $live['algorithmId'] ?? null;
                        // Try both 'status' and 'state' fields
                        $liveState = $live['status'] ?? $live['state'] ?? null;
                        $brokerage = $live['brokerage'] ?? null;
                        $equity = $live['equity'] ?? null;

                        // Status based on QC API response
                        $isRunning = in_array($liveState, ['Running', 'Deploying', 'InQueue']);
                        $isLive = $brokerage !== 'Paper Trading' && $brokerage !== 'PaperBrokerage' && $brokerage !== null;
                    }

                    // ALSO check /live/read for more accurate real-time status
                    // /live/read tends to be more up-to-date than /live/list
                    $liveReadResult = $this->quantConnectClient->liveRead((int) $session->project_id);
                    if ($liveReadResult['success'] && !empty($liveReadResult['data'])) {
                        $liveData = $liveReadResult['data'];

                        if (isset($liveData['live'])) {
                            $liveDetail = $liveData['live'];
                            $deploymentId = $deploymentId ?? ($liveDetail['deployId'] ?? null);

                            // Get status from /live/read (more accurate)
                            $readState = $liveDetail['status'] ?? $liveDetail['state'] ?? null;
                            if ($readState) {
                                $liveState = $readState;
                                $isRunning = in_array($readState, ['Running', 'Deploying', 'InQueue']);
                            }

                            // Update brokerage/equity if not set
                            $brokerage = $brokerage ?? ($liveDetail['brokerage'] ?? null);
                            $equity = $equity ?? ($liveDetail['equity'] ?? null);

                            // Check brokerage for live status
                            if ($brokerage) {
                                $isLive = !in_array($brokerage, ['Paper Trading', 'PaperBrokerage']);
                            }
                        }
                    }

                    // Also check project details for codeRunning flag as final backup
                    $projectResult = $this->quantConnectClient->projects((int) $session->project_id);
                    if ($projectResult['success'] && !empty($projectResult['data']['projects'])) {
                        $project = $projectResult['data']['projects'][0] ?? null;
                        if ($project) {
                            // codeRunning is a reliable indicator
                            $codeRunning = $project['codeRunning'] ?? false;
                            if ($codeRunning && !$isRunning) {
                                $isRunning = true;
                                $liveState = $liveState ?? 'Running';
                            }
                        }
                    }

                    // Determine final status
                    $newStatus = $isRunning ? 'running' : 'stopped';

                    // Update session
                    $session->update([
                        'is_live' => $isLive,
                        'status' => $newStatus,
                        'last_heartbeat_at' => $isRunning ? now() : $session->last_heartbeat_at,
                    ]);

                    // Update activity status
                    $session->updateActivityStatus();
                    $session->refresh();

                    $syncedProjects[] = [
                        'project_id' => $session->project_id,
                        'project_name' => $session->project_name,
                        'is_live' => $isLive,
                        'is_running' => $isRunning,
                        'status' => $newStatus,
                        'activity_status' => $session->activity_status,
                        'qc_live_state' => $liveState,
                        'deployment_id' => $deploymentId,
                        'brokerage' => $brokerage,
                        'equity' => $equity,
                        'synced_at' => now()->toISOString(),
                    ];
                } catch (\Exception $e) {
                    $syncedProjects[] = [
                        'project_id' => $session->project_id,
                        'project_name' => $session->project_name,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Clear projects cache to force refresh
            $cacheKey = 'qc:projects:' . sha1($this->quantConnectClient->getBaseUrl() . '|' . $this->quantConnectClient->getUserId());
            Cache::forget($cacheKey);

            return response()->json([
                'success' => true,
                'data' => $syncedProjects,
                'message' => 'Synced ' . count($syncedProjects) . ' project(s)',
                'synced_at' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to sync project status: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get KPI/Statistics from QuantConnect Live Algorithm
     * Includes: Sharpe Ratio, Sortino Ratio, CAGR, Drawdown, Win Rate, etc.
     */
    public function getProjectKpi(Request $request): JsonResponse
    {
        try {
            $projectId = $request->get('project_id');

            if (!$projectId) {
                // Get from session
                $selectedProject = Session::get('selected_project');
                $projectId = $selectedProject['project_id'] ?? null;
            }

            if (!$projectId) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 400, 'message' => 'No project selected']
                ], 400);
            }

            // Cache KPI for 30 seconds
            $cacheKey = 'qc:kpi:' . $projectId;

            $kpiData = Cache::remember($cacheKey, now()->addSeconds(30), function () use ($projectId) {
                $statistics = [];
                $runtimeStatistics = [];
                $live = [];
                $dataSource = 'none';

                // 1. Try to get from live/read first
                $liveReadResult = $this->quantConnectClient->liveRead((int) $projectId);

                if ($liveReadResult['success'] && !empty($liveReadResult['data'])) {
                    $data = $liveReadResult['data'];
                    $live = $data['live'] ?? $data;
                    $statistics = $live['statistics'] ?? $data['statistics'] ?? [];
                    $runtimeStatistics = $live['runtimeStatistics'] ?? $data['runtimeStatistics'] ?? [];
                    $dataSource = 'live';
                }

                // 2. Get latest backtest which has statistics inline
                $backtestList = $this->quantConnectClient->backtestsList((int) $projectId, true);
                $latestBacktest = null;

                if ($backtestList['success'] && !empty($backtestList['data']['backtests'])) {
                    // Get latest completed backtest
                    $latestBacktest = collect($backtestList['data']['backtests'])
                        ->filter(fn($bt) => ($bt['completed'] ?? false) === true)
                        ->sortByDesc('created')
                        ->first();

                    if ($latestBacktest) {
                        $dataSource = $dataSource === 'live' ? 'live+backtest' : 'backtest';
                    }
                }

                // Return combined data
                return [
                    'data_source' => $dataSource,

                    // Core performance metrics - prefer live runtimeStatistics, fallback to backtest
                    'sharpe_ratio' => $this->extractStatValue($statistics, $runtimeStatistics, ['Sharpe Ratio', 'SharpeRatio'])
                        ?? $latestBacktest['sharpeRatio'] ?? null,
                    'sortino_ratio' => $this->extractStatValue($statistics, $runtimeStatistics, ['Sortino Ratio', 'SortinoRatio'])
                        ?? $latestBacktest['sortinoRatio'] ?? null,
                    'cagr' => $this->extractStatValue($statistics, $runtimeStatistics, ['Compounding Annual Return', 'CAGR', 'Annual Return'])
                        ?? ($latestBacktest['compoundingAnnualReturn'] ?? null),
                    'drawdown' => $this->extractStatValue($statistics, $runtimeStatistics, ['Drawdown', 'Max Drawdown'])
                        ?? ($latestBacktest['drawdown'] ?? null),
                    'probabilistic_sharpe' => $this->extractStatValue($statistics, $runtimeStatistics, ['Probabilistic Sharpe Ratio', 'PSR'])
                        ?? ($latestBacktest['psr'] ?? null),

                    // Win/Loss metrics
                    'win_rate' => $this->extractStatValue($statistics, $runtimeStatistics, ['Win Rate', 'WinRate'])
                        ?? ($latestBacktest['winRate'] ?? null),
                    'loss_rate' => $this->extractStatValue($statistics, $runtimeStatistics, ['Loss Rate', 'LossRate'])
                        ?? ($latestBacktest['lossRate'] ?? null),
                    'profit_loss_ratio' => $this->extractStatValue($statistics, $runtimeStatistics, ['Profit-Loss Ratio', 'ProfitLossRatio']),

                    // Trade metrics
                    'total_orders' => $this->extractStatValue($statistics, $runtimeStatistics, ['Total Orders', 'TotalOrders', 'Total Trades'])
                        ?? ($latestBacktest['trades'] ?? null),
                    'total_trades' => $this->extractStatValue($statistics, $runtimeStatistics, ['Total Trades', 'TotalTrades'])
                        ?? ($latestBacktest['trades'] ?? null),
                    'winning_trades' => $this->extractStatValue($statistics, $runtimeStatistics, ['Winning Trades', 'WinningTrades']),
                    'losing_trades' => $this->extractStatValue($statistics, $runtimeStatistics, ['Losing Trades', 'LosingTrades']),

                    // Returns
                    'total_return' => $this->extractStatValue($statistics, $runtimeStatistics, ['Total Net Profit', 'Net Profit', 'TotalReturn', 'Return'])
                        ?? ($latestBacktest['netProfit'] ?? null),
                    'average_win' => $this->extractStatValue($statistics, $runtimeStatistics, ['Average Win', 'AverageWin']),
                    'average_loss' => $this->extractStatValue($statistics, $runtimeStatistics, ['Average Loss', 'AverageLoss']),
                    'largest_win' => $this->extractStatValue($statistics, $runtimeStatistics, ['Largest Win', 'LargestWin']),
                    'largest_loss' => $this->extractStatValue($statistics, $runtimeStatistics, ['Largest Loss', 'LargestLoss']),

                    // Portfolio metrics
                    'equity' => $live['equity'] ?? $this->parseStatValue($runtimeStatistics['Equity'] ?? null),
                    'holdings' => $live['holdings'] ?? $this->parseStatValue($runtimeStatistics['Holdings'] ?? null),
                    'unrealized_pnl' => $this->extractStatValue($statistics, $runtimeStatistics, ['Unrealized', 'Unrealized PnL']),
                    'fees' => $this->extractStatValue($statistics, $runtimeStatistics, ['Total Fees', 'Fees']),
                    'turnover' => $this->extractStatValue($statistics, $runtimeStatistics, ['Turnover', 'Portfolio Turnover']),
                    'volume' => $this->extractStatValue($statistics, $runtimeStatistics, ['Volume', 'Total Volume']),

                    // Risk metrics from backtest
                    'alpha' => $this->extractStatValue($statistics, $runtimeStatistics, ['Alpha'])
                        ?? ($latestBacktest['alpha'] ?? null),
                    'beta' => $this->extractStatValue($statistics, $runtimeStatistics, ['Beta'])
                        ?? ($latestBacktest['beta'] ?? null),
                    'information_ratio' => $this->extractStatValue($statistics, $runtimeStatistics, ['Information Ratio']),
                    'treynor_ratio' => $this->extractStatValue($statistics, $runtimeStatistics, ['Treynor Ratio'])
                        ?? ($latestBacktest['treynorRatio'] ?? null),

                    // Timestamps
                    'started_at' => $live['launched'] ?? $live['dtLaunched'] ?? null,
                    'last_update' => $live['lastUpdate'] ?? null,
                    'backtest_name' => $latestBacktest['name'] ?? null,
                    'backtest_created' => $latestBacktest['created'] ?? null,

                    // Raw data for debugging
                    '_raw_statistics' => $statistics,
                    '_raw_runtime' => $runtimeStatistics,
                    '_raw_backtest' => $latestBacktest ? array_diff_key($latestBacktest, array_flip(['result', 'sparkline'])) : null,
                ];
            });

            if (!$kpiData) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 404, 'message' => 'No live data found for this project']
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $kpiData,
                'project_id' => $projectId
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 500, 'message' => 'Failed to get KPI: ' . $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Helper to extract statistic value from multiple possible keys
     */
    private function extractStatValue(array $statistics, array $runtimeStats, array $keys)
    {
        foreach ($keys as $key) {
            if (isset($statistics[$key])) {
                return $this->parseStatValue($statistics[$key]);
            }
            if (isset($runtimeStats[$key])) {
                return $this->parseStatValue($runtimeStats[$key]);
            }
        }
        return null;
    }

    /**
     * Parse stat value (handle percentage strings, etc)
     */
    private function parseStatValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        // If already numeric, return as-is
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Handle percentage strings like "61.00%"
        if (is_string($value) && str_contains($value, '%')) {
            return (float) str_replace(['%', ','], '', $value);
        }

        // Handle currency strings like "$1,234.56"
        if (is_string($value) && str_contains($value, '$')) {
            return (float) str_replace(['$', ','], '', $value);
        }

        // Try to extract numeric value
        if (is_string($value)) {
            $cleaned = preg_replace('/[^0-9.\-]/', '', $value);
            if (is_numeric($cleaned)) {
                return (float) $cleaned;
            }
        }

        return $value;
    }

    /**
     * Debug endpoint to check raw QC API responses
     */
    public function debugQcApi(Request $request): JsonResponse
    {
        try {
            if (!$this->quantConnectClient->isConfigured()) {
                return response()->json(['error' => 'QC not configured'], 400);
            }

            $projectId = $request->get('project_id', 27309216);

            // Get raw /live/list response
            $liveListResult = $this->quantConnectClient->liveList();

            // Get raw /live/read response for specific project
            $liveReadResult = $this->quantConnectClient->liveRead((int) $projectId);

            // Find project in live list
            $projectInList = null;
            if (!empty($liveListResult['data']['live'])) {
                foreach ($liveListResult['data']['live'] as $live) {
                    if ($live['projectId'] == $projectId) {
                        $projectInList = $live;
                        break;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'project_id' => $projectId,
                'live_list' => [
                    'success' => $liveListResult['success'] ?? false,
                    'total_live_algorithms' => count($liveListResult['data']['live'] ?? []),
                    'project_found' => $projectInList !== null,
                    'project_data' => $projectInList,
                    'all_projects' => collect($liveListResult['data']['live'] ?? [])->map(fn($l) => [
                        'projectId' => $l['projectId'],
                        'status' => $l['status'] ?? 'N/A',
                        'brokerage' => $l['brokerage'] ?? 'N/A',
                    ])->toArray(),
                ],
                'live_read' => [
                    'success' => $liveReadResult['success'] ?? false,
                    'raw_data' => $liveReadResult['data'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export signals data
     */
    public function exportSignals(Request $request): JsonResponse
    {
        try {
            $format = $request->get('format', 'json');
            $filters = $this->buildFilters($request);

            $query = QuantconnectSignal::query()
                ->with('projectSession')
                ->latest();

            // Apply filters (same as getSignals)
            if (!empty($filters['project_id'])) {
                $query->byProject($filters['project_id']);
            }

            if (!empty($filters['symbol'])) {
                $query->bySymbol($filters['symbol']);
            }

            if (!empty($filters['signal_type'])) {
                $query->byType($filters['signal_type']);
            }

            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                $query->byDateRange($filters['start_date'], $filters['end_date']);
            }

            // Limit export to prevent memory issues
            $signals = $query->limit(10000)->get();

            if ($format === 'csv') {
                // Return CSV data structure for frontend to handle
                $csvData = $signals->map(function ($signal) {
                    return [
                        'timestamp' => $signal->signal_timestamp->toISOString(),
                        'project_id' => $signal->project_id,
                        'project_name' => $signal->project_name,
                        'signal_type' => $signal->signal_type,
                        'symbol' => $signal->symbol,
                        'action' => $signal->action,
                        'price' => $signal->price,
                        'quantity' => $signal->quantity,
                        'target_price' => $signal->target_price,
                        'stop_loss' => $signal->stop_loss,
                        'realized_pnl' => $signal->realized_pnl,
                        'message' => $signal->message,
                    ];
                });

                return response()->json([
                    'success' => true,
                    'format' => 'csv',
                    'data' => $csvData,
                    'filename' => 'quantconnect_signals_' . now()->format('Y-m-d_H-i-s') . '.csv'
                ]);
            }

            // Default JSON export
            return response()->json([
                'success' => true,
                'format' => 'json',
                'data' => $signals,
                'filename' => 'quantconnect_signals_' . now()->format('Y-m-d_H-i-s') . '.json',
                'count' => $signals->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to export signals: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Build filters array from request parameters
     */
    private function buildFilters(Request $request): array
    {
        $filters = [];

        if ($request->has('project_id') && $request->get('project_id') !== '') {
            $filters['project_id'] = (int) $request->get('project_id');
        }

        if ($request->has('symbol') && $request->get('symbol') !== '') {
            $filters['symbol'] = strtoupper($request->get('symbol'));
        }

        if ($request->has('signal_type') && $request->get('signal_type') !== '') {
            $filters['signal_type'] = $request->get('signal_type');
        }

        if ($request->has('start_date') && $request->get('start_date') !== '') {
            $filters['start_date'] = Carbon::parse($request->get('start_date'))->startOfDay();
        }

        if ($request->has('end_date') && $request->get('end_date') !== '') {
            $filters['end_date'] = Carbon::parse($request->get('end_date'))->endOfDay();
        }

        return $filters;
    }

    /**
     * Get Live Algorithm Orders from QuantConnect
     * Endpoint: /live/orders/read
     */
    public function getLiveOrders(Request $request): JsonResponse
    {
        try {
            $projectId = $request->get('project_id');

            if (!$projectId) {
                $selectedProject = Session::get('selected_project');
                $projectId = $selectedProject['project_id'] ?? null;
            }

            if (!$projectId) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 400, 'message' => 'No project selected']
                ], 400);
            }

            if (!$this->quantConnectClient->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 400, 'message' => 'QuantConnect credentials not configured']
                ], 400);
            }

            $start = (int) $request->get('start', 0);
            $end = (int) $request->get('end', 100);
            $algorithmId = $request->get('algorithm_id');

            // Cache for 15 seconds
            $cacheKey = "qc:orders:{$projectId}:{$start}:{$end}";

            $result = Cache::remember($cacheKey, now()->addSeconds(15), function () use ($projectId, $start, $end, $algorithmId) {
                return $this->quantConnectClient->liveReadOrders((int) $projectId, $start, $end, $algorithmId);
            });

            if (!($result['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => $result['status'] ?? 500,
                        'message' => $result['error']['message'] ?? 'Failed to fetch orders'
                    ]
                ], $result['status'] ?? 500);
            }

            $orders = $result['data']['orders'] ?? [];

            // Transform orders to standardized format
            $transformedOrders = collect($orders)->map(function ($order) {
                // Handle nested symbol structure from QC API
                $symbol = $order['symbol'] ?? null;
                if (is_array($symbol)) {
                    $symbol = $symbol['value'] ?? $symbol['id'] ?? $symbol['permtick'] ?? json_encode($symbol);
                }

                // Map direction: 0 = buy, 1 = sell
                $direction = $order['direction'] ?? null;
                if ($direction === 0) $direction = 'buy';
                elseif ($direction === 1) $direction = 'sell';

                // Map status: 3 = filled, etc
                $statusMap = [0 => 'new', 1 => 'submitted', 2 => 'partiallyFilled', 3 => 'filled', 5 => 'canceled', 6 => 'invalid', 7 => 'cancelPending', 8 => 'updateSubmitted'];
                $status = $order['status'] ?? null;
                if (is_numeric($status) && isset($statusMap[$status])) {
                    $status = $statusMap[$status];
                }

                // Get fill info from events if available
                $fillPrice = $order['price'] ?? 0;
                $fillQuantity = abs($order['quantity'] ?? 0);
                $fee = 0;
                if (!empty($order['events'])) {
                    $lastEvent = end($order['events']);
                    $fillPrice = $lastEvent['fillPrice'] ?? $fillPrice;
                    $fillQuantity = abs($lastEvent['fillQuantity'] ?? $fillQuantity);
                    $fee = $lastEvent['orderFeeAmount'] ?? 0;
                }

                return [
                    'id' => $order['id'] ?? $order['orderId'] ?? null,
                    'symbol' => $symbol,
                    'type' => $order['type'] ?? $order['orderType'] ?? null,
                    'direction' => $direction,
                    'status' => $status,
                    'quantity' => $order['quantity'] ?? 0,
                    'filled_quantity' => $fillQuantity,
                    'price' => $fillPrice,
                    'value' => $order['value'] ?? 0,
                    'fee' => $fee,
                    'created_time' => $order['createdTime'] ?? $order['time'] ?? null,
                    'last_fill_time' => $order['lastFillTime'] ?? null,
                    'tag' => $order['tag'] ?? null,
                    'broker_id' => $order['brokerId'][0] ?? null,
                    '_raw' => $order,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $transformedOrders,
                'project_id' => $projectId,
                'count' => count($transformedOrders),
                'pagination' => [
                    'start' => $start,
                    'end' => $end,
                    'fetched' => count($transformedOrders),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 500, 'message' => 'Failed to get orders: ' . $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Get Live Algorithm Logs from QuantConnect
     * Endpoint: /live/read/log
     */
    public function getLiveLogs(Request $request): JsonResponse
    {
        try {
            $projectId = $request->get('project_id');

            if (!$projectId) {
                $selectedProject = Session::get('selected_project');
                $projectId = $selectedProject['project_id'] ?? null;
            }

            if (!$projectId) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 400, 'message' => 'No project selected']
                ], 400);
            }

            if (!$this->quantConnectClient->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 400, 'message' => 'QuantConnect credentials not configured']
                ], 400);
            }

            $algorithmId = $request->get('algorithm_id');

            // If no algorithmId provided, get it from live/read
            if (!$algorithmId) {
                $liveReadResult = $this->quantConnectClient->liveRead((int) $projectId);
                if ($liveReadResult['success'] && !empty($liveReadResult['data'])) {
                    $liveData = $liveReadResult['data'];
                    $algorithmId = $liveData['deployId'] ?? $liveData['live']['deployId'] ?? null;
                }
            }

            // If still no algorithmId, we can't fetch logs
            if (!$algorithmId) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 400, 'message' => 'No algorithmId (deployId) found for this project. Make sure the algorithm is deployed live.']
                ], 400);
            }

            // QC API uses startLine/endLine (line numbers), max 250 lines per request
            $startLine = $request->get('start_line') ? (int) $request->get('start_line') : 0;
            $endLine = $request->get('end_line') ? (int) $request->get('end_line') : 250;

            // Ensure we don't exceed QC limit of 250 lines
            if (($endLine - $startLine) > 250) {
                $endLine = $startLine + 250;
            }

            // Cache for 10 seconds
            $cacheKey = "qc:logs:{$projectId}:{$algorithmId}:{$startLine}:{$endLine}";

            $result = Cache::remember($cacheKey, now()->addSeconds(10), function () use ($projectId, $algorithmId, $startLine, $endLine) {
                return $this->quantConnectClient->liveReadLogs((int) $projectId, $algorithmId, $startLine, $endLine);
            });

            if (!($result['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => $result['status'] ?? 500,
                        'message' => $result['error']['message'] ?? 'Failed to fetch logs'
                    ]
                ], $result['status'] ?? 500);
            }

            // Handle both LiveLogs and liveLogs keys (QC API inconsistency)
            $logs = $result['data']['LiveLogs'] ?? $result['data']['liveLogs'] ?? $result['data']['logs'] ?? [];

            // If no logs from QC API, fallback to our database signals as activity log
            if (empty($logs)) {
                // Get signals from database as "logs"
                $signals = QuantconnectSignal::where('project_id', $projectId)
                    ->orderBy('signal_timestamp', 'desc')
                    ->limit(200)
                    ->get();

                $transformedLogs = $signals->map(function ($signal, $index) {
                    $action = strtoupper($signal->action ?? 'UNKNOWN');
                    $type = $signal->signal_type ?? 'signal';
                    $symbol = $signal->symbol ?? 'UNKNOWN';
                    $price = $signal->price ?? 0;
                    $message = $signal->message ?? '';

                    // Format message like QC logs
                    $formattedMessage = "[TRADE] {$type} {$action} {$symbol} @ {$price}";
                    if ($message) {
                        $formattedMessage .= " | {$message}";
                    }

                    return [
                        'id' => $signal->id,
                        'timestamp' => $signal->signal_timestamp?->format('Y-m-d H:i:s'),
                        'message' => $formattedMessage,
                        'type' => 'TRADE',
                        'source' => 'database',
                        '_raw' => $signal->toArray(),
                    ];
                })->values();

                return response()->json([
                    'success' => true,
                    'data' => $transformedLogs,
                    'project_id' => $projectId,
                    'count' => count($transformedLogs),
                    'source' => 'database_signals',
                    'note' => 'Logs from saved signals (QC API returned empty)'
                ]);
            }

            // Transform logs - handle both array of strings and array of objects
            $transformedLogs = collect($logs)->map(function ($log, $index) {
                if (is_string($log)) {
                    // Parse log string: "2026-01-02 19:00:00 : [TRADE] Signal sent"
                    $parts = explode(' : ', $log, 2);
                    $timestamp = $parts[0] ?? null;
                    $message = $parts[1] ?? $log;

                    // Determine log type from content
                    $type = 'INFO';
                    if (str_contains($message, '[TRADE]') || str_contains($message, 'Signal sent')) {
                        $type = 'TRADE';
                    } elseif (str_contains($message, 'Warning') || str_contains($message, 'WARNING')) {
                        $type = 'WARNING';
                    } elseif (str_contains($message, 'Error') || str_contains($message, 'ERROR')) {
                        $type = 'ERROR';
                    } elseif (str_contains($message, '[INFO]')) {
                        $type = 'INFO';
                    }

                    return [
                        'id' => $index,
                        'timestamp' => $timestamp,
                        'message' => $message,
                        'type' => $type,
                        '_raw' => $log,
                    ];
                }

                // Already an object
                return [
                    'id' => $log['id'] ?? $index,
                    'timestamp' => $log['time'] ?? $log['timestamp'] ?? null,
                    'message' => $log['message'] ?? $log['log'] ?? '',
                    'type' => $log['type'] ?? $log['level'] ?? 'INFO',
                    '_raw' => $log,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $transformedLogs,
                'project_id' => $projectId,
                'count' => count($transformedLogs),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 500, 'message' => 'Failed to get logs: ' . $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Get Live Algorithm Insights from QuantConnect
     * Endpoint: /live/insights/read
     */
    public function getLiveInsights(Request $request): JsonResponse
    {
        try {
            $projectId = $request->get('project_id');

            if (!$projectId) {
                $selectedProject = Session::get('selected_project');
                $projectId = $selectedProject['project_id'] ?? null;
            }

            if (!$projectId) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 400, 'message' => 'No project selected']
                ], 400);
            }

            if (!$this->quantConnectClient->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 400, 'message' => 'QuantConnect credentials not configured']
                ], 400);
            }

            $start = (int) $request->get('start', 0);
            $end = (int) $request->get('end', 100);
            $algorithmId = $request->get('algorithm_id');

            // Cache for 15 seconds
            $cacheKey = "qc:insights:{$projectId}:{$start}:{$end}";

            $result = Cache::remember($cacheKey, now()->addSeconds(15), function () use ($projectId, $start, $end, $algorithmId) {
                return $this->quantConnectClient->liveReadInsights((int) $projectId, $start, $end, $algorithmId);
            });

            if (!($result['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => $result['status'] ?? 500,
                        'message' => $result['error']['message'] ?? 'Failed to fetch insights'
                    ]
                ], $result['status'] ?? 500);
            }

            $insights = $result['data']['insights'] ?? [];

            // Transform insights
            $transformedInsights = collect($insights)->map(function ($insight) {
                return [
                    'id' => $insight['id'] ?? null,
                    'symbol' => $insight['symbol'] ?? null,
                    'type' => $insight['type'] ?? null,
                    'direction' => $insight['direction'] ?? null,
                    'period' => $insight['period'] ?? null,
                    'magnitude' => $insight['magnitude'] ?? null,
                    'confidence' => $insight['confidence'] ?? null,
                    'weight' => $insight['weight'] ?? null,
                    'generated_time' => $insight['generatedTime'] ?? $insight['generatedTimeUtc'] ?? null,
                    'close_time' => $insight['closeTime'] ?? $insight['closeTimeUtc'] ?? null,
                    'score' => [
                        'magnitude' => $insight['score']['magnitude'] ?? null,
                        'direction' => $insight['score']['direction'] ?? null,
                        'is_final' => $insight['score']['isFinalScore'] ?? false,
                    ],
                    'reference_value' => $insight['referenceValue'] ?? null,
                    'reference_value_final' => $insight['referenceValueFinal'] ?? null,
                    'source_model' => $insight['sourceModel'] ?? null,
                    '_raw' => $insight,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $transformedInsights,
                'project_id' => $projectId,
                'count' => count($transformedInsights),
                'pagination' => [
                    'start' => $start,
                    'end' => $end,
                    'fetched' => count($transformedInsights),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 500, 'message' => 'Failed to get insights: ' . $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Get Live Algorithm Holdings/Portfolio from QuantConnect
     * Endpoint: /live/portfolio/read
     */
    public function getLiveHoldings(Request $request): JsonResponse
    {
        try {
            $projectId = $request->get('project_id');

            if (!$projectId) {
                $selectedProject = Session::get('selected_project');
                $projectId = $selectedProject['project_id'] ?? null;
            }

            if (!$projectId) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 400, 'message' => 'No project selected']
                ], 400);
            }

            if (!$this->quantConnectClient->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 400, 'message' => 'QuantConnect credentials not configured']
                ], 400);
            }

            $algorithmId = $request->get('algorithm_id');

            // Cache for 15 seconds
            $cacheKey = "qc:portfolio:{$projectId}";

            $result = Cache::remember($cacheKey, now()->addSeconds(15), function () use ($projectId, $algorithmId) {
                return $this->quantConnectClient->liveReadPortfolio((int) $projectId, $algorithmId);
            });

            if (!($result['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => $result['status'] ?? 500,
                        'message' => $result['error']['message'] ?? 'Failed to fetch portfolio'
                    ]
                ], $result['status'] ?? 500);
            }

            $portfolio = $result['data']['portfolio'] ?? $result['data'] ?? [];
            $holdings = $portfolio['holdings'] ?? $portfolio['securities'] ?? [];

            // Transform holdings
            $transformedHoldings = collect($holdings)->map(function ($holding, $symbol) {
                // Handle both array and object formats
                $symbolName = is_string($symbol) ? $symbol : ($holding['symbol'] ?? $holding['Symbol'] ?? 'Unknown');

                return [
                    'symbol' => $symbolName,
                    'quantity' => $holding['quantity'] ?? $holding['Quantity'] ?? 0,
                    'average_price' => $holding['averagePrice'] ?? $holding['AveragePrice'] ?? 0,
                    'market_price' => $holding['marketPrice'] ?? $holding['Price'] ?? 0,
                    'market_value' => $holding['marketValue'] ?? $holding['MarketValue'] ?? 0,
                    'unrealized_pnl' => $holding['unrealizedPnL'] ?? $holding['UnrealizedPnL'] ?? 0,
                    'unrealized_pnl_percent' => $holding['unrealizedPnLPercent'] ?? null,
                    'currency' => $holding['currency'] ?? $holding['Currency'] ?? 'USD',
                    'asset_type' => $holding['type'] ?? $holding['securityType'] ?? null,
                    '_raw' => $holding,
                ];
            })->values();

            // Portfolio summary
            $summary = [
                'total_value' => $portfolio['totalPortfolioValue'] ?? $portfolio['TotalPortfolioValue'] ?? null,
                'cash' => $portfolio['cash'] ?? $portfolio['Cash'] ?? null,
                'holdings_value' => $portfolio['totalHoldingsValue'] ?? $portfolio['TotalHoldingsValue'] ?? null,
                'unrealized_pnl' => $portfolio['unrealizedPnl'] ?? $portfolio['TotalUnrealizedPnl'] ?? null,
                'margin_used' => $portfolio['marginUsed'] ?? $portfolio['TotalMarginUsed'] ?? null,
                'margin_remaining' => $portfolio['marginRemaining'] ?? null,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'holdings' => $transformedHoldings,
                    'summary' => $summary,
                ],
                'project_id' => $projectId,
                'count' => count($transformedHoldings),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 500, 'message' => 'Failed to get holdings: ' . $e->getMessage()]
            ], 500);
        }
    }
}
