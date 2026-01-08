<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuantConnectClient
{
    private string $baseUrl;
    private string $userId;
    private string $apiToken;
    private string $organizationId;
    private int $timeoutSeconds;

    public function __construct(
        ?string $baseUrl = null,
        ?string $userId = null,
        ?string $apiToken = null,
        ?string $organizationId = null,
        ?int $timeoutSeconds = null
    ) {
        // NOTE: Do not call env() here (breaks when config is cached). Use config() only.
        $this->baseUrl = rtrim(
            $baseUrl ?? (string) config('services.quantconnect.base_url', 'https://www.quantconnect.com/api/v2'),
            '/'
        );
        $this->userId = trim((string) ($userId ?? config('services.quantconnect.user_id', '')));
        $this->apiToken = trim((string) ($apiToken ?? config('services.quantconnect.api_token', '')));
        $this->organizationId = trim((string) ($organizationId ?? config('services.quantconnect.organization_id', '')));
        $this->timeoutSeconds = $timeoutSeconds ?? (int) config('services.quantconnect.timeout', 15);
    }

    public function isConfigured(): bool
    {
        return $this->userId !== '' && $this->apiToken !== '';
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function authenticate(): array
    {
        return $this->post('/authenticate', []);
    }

    public function projects(?int $projectId = null): array
    {
        $payload = [];
        if ($projectId !== null) {
            $payload['projectId'] = $projectId;
        }

        return $this->post('/projects/read', $this->withOrganization($payload));
    }

    public function backtestsList(int $projectId, bool $includeStatistics = true): array
    {
        return $this->post('/backtests/list', $this->withOrganization([
            'projectId' => $projectId,
            'includeStatistics' => $includeStatistics,
        ]));
    }

    public function backtestsRead(int $projectId, string $backtestId): array
    {
        return $this->post('/backtests/read', $this->withOrganization([
            'projectId' => $projectId,
            'backtestId' => $backtestId,
        ]));
    }

    public function backtestsCreate(int $projectId, string $compileId, ?string $backtestName = null, array $parameters = []): array
    {
        $payload = [
            'projectId' => $projectId,
            'compileId' => $compileId,
        ];

        $backtestName = $backtestName !== null ? trim($backtestName) : null;
        if ($backtestName) {
            $payload['backtestName'] = $backtestName;
        }

        if (!empty($parameters)) {
            // Some QC endpoints accept a parameters payload; keep flexible.
            $payload['parameters'] = $parameters;
        }

        return $this->post('/backtests/create', $this->withOrganization($payload));
    }

    public function backtestsUpdate(int $projectId, string $backtestId, string $name, ?string $note = null): array
    {
        $payload = [
            'projectId' => $projectId,
            'backtestId' => $backtestId,
            'name' => $name,
        ];

        if ($note !== null) {
            $payload['note'] = $note;
        }

        return $this->post('/backtests/update', $this->withOrganization($payload));
    }

    public function backtestsDelete(int $projectId, string $backtestId): array
    {
        return $this->post('/backtests/delete', $this->withOrganization([
            'projectId' => $projectId,
            'backtestId' => $backtestId,
        ]));
    }

    public function compileCreate(int $projectId): array
    {
        return $this->post('/compile/create', $this->withOrganization([
            'projectId' => $projectId,
        ]));
    }

    public function compileRead(int $projectId, string $compileId): array
    {
        return $this->post('/compile/read', $this->withOrganization([
            'projectId' => $projectId,
            'compileId' => $compileId,
        ]));
    }

    public function filesRead(int $projectId, ?string $name = null): array
    {
        $payload = [
            'projectId' => $projectId,
        ];

        $name = $name !== null ? trim($name) : null;
        if ($name) {
            $payload['name'] = $name;
        }

        return $this->post('/files/read', $this->withOrganization($payload));
    }

    public function filesCreate(int $projectId, string $name, string $content): array
    {
        return $this->post('/files/create', $this->withOrganization([
            'projectId' => $projectId,
            'name' => $name,
            'content' => $content,
        ]));
    }

    public function filesUpdateContents(int $projectId, string $name, string $content): array
    {
        return $this->post('/files/update', $this->withOrganization([
            'projectId' => $projectId,
            'name' => $name,
            'content' => $content,
        ]));
    }

    public function filesRename(int $projectId, string $oldFileName, string $newName): array
    {
        // QC docs typically use: { projectId, name, newName } for renaming. Keep oldFileName too for compatibility.
        return $this->post('/files/update', $this->withOrganization([
            'projectId' => $projectId,
            'name' => $oldFileName,
            'oldFileName' => $oldFileName,
            'newName' => $newName,
        ]));
    }

    public function filesDelete(int $projectId, string $name): array
    {
        return $this->post('/files/delete', $this->withOrganization([
            'projectId' => $projectId,
            'name' => $name,
        ]));
    }

    public function reportBacktest(int $projectId, string $backtestId): array
    {
        return $this->post('/reports/backtest-report', $this->withOrganization([
            'projectId' => $projectId,
            'backtestId' => $backtestId,
        ]));
    }

    /**
     * Read live algorithm status for a project
     * Returns deployment info including state (Running, Stopped, etc.)
     */
    public function liveRead(int $projectId): array
    {
        return $this->post('/live/read', $this->withOrganization([
            'projectId' => $projectId,
        ]));
    }

    /**
     * List all live algorithms with optional filtering
     * Status options: 'Running', 'Stopped', 'RuntimeError', 'Liquidated'
     * 
     * @param int|null $projectId Filter by project ID
     * @param string|null $status Filter by status (Running, Stopped, RuntimeError, Liquidated)
     */
    public function liveList(?int $projectId = null, ?string $status = null): array
    {
        $payload = [];

        if ($projectId !== null) {
            $payload['projectId'] = $projectId;
        }
        if ($status !== null) {
            $payload['status'] = $status;
        }

        return $this->post('/live/list', $this->withOrganization($payload));
    }

    /**
     * Get live algorithm logs
     * Based on QC API docs: /live/logs/read
     * 
     * @param int $projectId Project ID containing the live algorithm
     * @param string $algorithmId Deploy ID (Algorithm ID) of the live running algorithm - REQUIRED
     * @param int $startLine Start line (inclusive) of logs to read, lines start at 0
     * @param int $endLine End line (exclusive) of logs to read, endLine - startLine <= 250
     */
    public function liveReadLogs(int $projectId, string $algorithmId, int $startLine = 0, int $endLine = 250): array
    {
        $payload = [
            'projectId' => $projectId,
            'algorithmId' => $algorithmId,
            'startLine' => $startLine,
            'endLine' => $endLine,
        ];

        return $this->post('/live/logs/read', $this->withOrganization($payload));
    }

    /**
     * Get live algorithm orders
     * Returns the orders of a live algorithm
     * 
     * @param int $projectId Project ID
     * @param int $start Starting index of orders (default 0)
     * @param int $end Last index of orders (max 1000 per request)
     * @param string|null $algorithmId Optional deploy/algorithm ID
     */
    public function liveReadOrders(int $projectId, int $start = 0, int $end = 100, ?string $algorithmId = null): array
    {
        $payload = [
            'projectId' => $projectId,
            'start' => $start,
            'end' => $end,
        ];

        if ($algorithmId !== null) {
            $payload['algorithmId'] = $algorithmId;
        }

        return $this->post('/live/orders/read', $this->withOrganization($payload));
    }

    /**
     * Get live algorithm portfolio state
     * Returns the portfolio holdings of a live algorithm
     * 
     * @param int $projectId Project ID
     * @param string|null $algorithmId Optional deploy/algorithm ID
     */
    public function liveReadPortfolio(int $projectId, ?string $algorithmId = null): array
    {
        $payload = ['projectId' => $projectId];

        if ($algorithmId !== null) {
            $payload['algorithmId'] = $algorithmId;
        }

        return $this->post('/live/portfolio/read', $this->withOrganization($payload));
    }

    /**
     * Get live algorithm insights
     * Returns the insights generated by a live algorithm
     * 
     * @param int $projectId Project ID
     * @param int $start Starting index of insights (default 0)
     * @param int $end Last index of insights
     * @param string|null $algorithmId Optional deploy/algorithm ID
     */
    public function liveReadInsights(int $projectId, int $start = 0, int $end = 100, ?string $algorithmId = null): array
    {
        $payload = [
            'projectId' => $projectId,
            'start' => $start,
            'end' => $end,
        ];

        if ($algorithmId !== null) {
            $payload['algorithmId'] = $algorithmId;
        }

        return $this->post('/live/insights/read', $this->withOrganization($payload));
    }

    private function withOrganization(array $payload): array
    {
        if ($this->organizationId === '') {
            return $payload;
        }

        if (array_key_exists('organizationId', $payload)) {
            return $payload;
        }

        $payload['organizationId'] = $this->organizationId;

        return $payload;
    }

    public function post(string $path, array $payload = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'status' => 400,
                'error' => [
                    'code' => 400,
                    'message' => 'QuantConnect credentials are missing. Set QC_USER_ID and QC_API_TOKEN in .env and re-run config:cache.',
                ],
            ];
        }

        $path = '/' . ltrim($path, '/');
        $url = $this->baseUrl . $path;
        $timestamp = (string) now()->timestamp;

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->withHeaders($this->buildAuthHeaders($timestamp))
                ->withOptions([
                    // Allow local/dev without CA bundle issues; enforce verification in production.
                    'verify' => app()->environment('production'),
                ])
                ->post($url, $payload);

            $json = $response->json();
            $apiSuccess = is_array($json) ? ($json['success'] ?? null) : null;

            $success = $response->successful() && ($apiSuccess === null || $apiSuccess === true);

            if (! $success) {
                $errors = is_array($json) ? ($json['errors'] ?? null) : null;
                $message = is_array($errors) ? implode('; ', array_filter($errors)) : null;
                $message = $message
                    ?? (is_array($json) ? ($json['message'] ?? null) : null)
                    ?? (is_array($json) ? ($json['error'] ?? null) : null)
                    ?? $response->body();

                return [
                    'success' => false,
                    'status' => $response->status(),
                    'data' => $json,
                    'error' => [
                        'code' => $response->status(),
                        'message' => $message,
                    ],
                ];
            }

            return [
                'success' => true,
                'status' => $response->status(),
                'data' => $json,
            ];
        } catch (\Throwable $e) {
            Log::warning('QuantConnect request error', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 500,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to reach QuantConnect API.',
                ],
            ];
        }
    }

    private function buildAuthHeaders(string $timestamp): array
    {
        // Docs: sha256("{API_TOKEN}:{timestamp}") => hex string, then Basic base64("{USER_ID}:{hashed}")
        $hashedToken = hash('sha256', $this->apiToken . ':' . $timestamp);
        $basic = base64_encode($this->userId . ':' . $hashedToken);

        return [
            'Authorization' => 'Basic ' . $basic,
            'Timestamp' => $timestamp,
        ];
    }
}
