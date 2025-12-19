<?php

namespace App\Http\Controllers;

use App\Services\QuantConnectClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class QuantConnectController extends Controller
{
    public function authenticate(QuantConnectClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'QuantConnect credentials are missing. Set QC_USER_ID and QC_API_TOKEN in .env, then run: php artisan optimize:clear && php artisan config:cache',
                ],
            ], 400);
        }

        $cacheKey = 'qc:auth:' . sha1($client->getBaseUrl() . '|' . $client->getUserId());

        $result = Cache::remember($cacheKey, now()->addSeconds(15), function () use ($client) {
            return $client->authenticate();
        });

        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status);
    }

    public function projects(QuantConnectClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'QuantConnect credentials are missing. Set QC_USER_ID and QC_API_TOKEN in .env, then run: php artisan optimize:clear && php artisan config:cache',
                ],
            ], 400);
        }

        $cacheKey = 'qc:projects:' . sha1($client->getBaseUrl() . '|' . $client->getUserId());

        $result = Cache::remember($cacheKey, now()->addSeconds(30), function () use ($client) {
            return $client->projects();
        });

        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status);
    }

    public function backtests(Request $request, QuantConnectClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'QuantConnect credentials are missing. Set QC_USER_ID and QC_API_TOKEN in .env, then run: php artisan optimize:clear && php artisan config:cache',
                ],
            ], 400);
        }

        $projectId = (int) $request->query('projectId', 0);
        if ($projectId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Missing or invalid projectId.',
                ],
            ], 422);
        }

        $includeStatistics = filter_var(
            $request->query('includeStatistics', '1'),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );
        $includeStatistics = $includeStatistics !== false;

        $cacheKey = 'qc:backtests:' . sha1(
            $client->getBaseUrl() . '|' . $client->getUserId() . '|' . $projectId . '|' . ($includeStatistics ? '1' : '0')
        );

        $result = Cache::remember($cacheKey, now()->addSeconds(15), function () use ($client, $projectId, $includeStatistics) {
            return $client->backtestsList($projectId, $includeStatistics);
        });

        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status);
    }

    public function compileCreate(Request $request, QuantConnectClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'QuantConnect credentials are missing. Set QC_USER_ID and QC_API_TOKEN in .env, then run: php artisan optimize:clear && php artisan config:cache',
                ],
            ], 400);
        }

        $projectId = (int) $request->input('projectId', 0);
        if ($projectId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Missing or invalid projectId.',
                ],
            ], 422);
        }

        $result = $client->compileCreate($projectId);
        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status);
    }

    public function compileRead(Request $request, QuantConnectClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'QuantConnect credentials are missing. Set QC_USER_ID and QC_API_TOKEN in .env, then run: php artisan optimize:clear && php artisan config:cache',
                ],
            ], 400);
        }

        $projectId = (int) $request->query('projectId', 0);
        $compileId = (string) $request->query('compileId', '');
        if ($projectId <= 0 || trim($compileId) === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Missing or invalid projectId/compileId.',
                ],
            ], 422);
        }

        $result = $client->compileRead($projectId, $compileId);
        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status);
    }

    public function files(Request $request, QuantConnectClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'QuantConnect credentials are missing. Set QC_USER_ID and QC_API_TOKEN in .env, then run: php artisan optimize:clear && php artisan config:cache',
                ],
            ], 400);
        }

        $projectId = (int) $request->query('projectId', 0);
        $name = $request->query('name');

        if ($projectId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Missing or invalid projectId.',
                ],
            ], 422);
        }

        $result = $client->filesRead($projectId, is_string($name) ? $name : null);
        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status);
    }

    public function filesCreate(Request $request, QuantConnectClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'QuantConnect credentials are missing.',
                ],
            ], 400);
        }

        $projectId = (int) $request->input('projectId', 0);
        $name = (string) $request->input('name', '');
        $content = (string) $request->input('content', '');

        if ($projectId <= 0 || trim($name) === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Missing or invalid projectId/name.',
                ],
            ], 422);
        }

        $result = $client->filesCreate($projectId, $name, $content);
        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status);
    }

    public function filesUpdate(Request $request, QuantConnectClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'QuantConnect credentials are missing.',
                ],
            ], 400);
        }

        $projectId = (int) $request->input('projectId', 0);
        $name = (string) $request->input('name', '');
        $content = (string) $request->input('content', '');

        if ($projectId <= 0 || trim($name) === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Missing or invalid projectId/name.',
                ],
            ], 422);
        }

        $result = $client->filesUpdateContents($projectId, $name, $content);
        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status);
    }

    public function filesRename(Request $request, QuantConnectClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'QuantConnect credentials are missing.',
                ],
            ], 400);
        }

        $projectId = (int) $request->input('projectId', 0);
        $oldFileName = (string) $request->input('oldFileName', $request->input('name', ''));
        $newName = (string) $request->input('newName', '');

        if ($projectId <= 0 || trim($oldFileName) === '' || trim($newName) === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Missing or invalid projectId/oldFileName/newName.',
                ],
            ], 422);
        }

        $result = $client->filesRename($projectId, $oldFileName, $newName);
        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status);
    }

    public function filesDelete(Request $request, QuantConnectClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'QuantConnect credentials are missing.',
                ],
            ], 400);
        }

        $projectId = (int) $request->input('projectId', 0);
        $name = (string) $request->input('name', '');

        if ($projectId <= 0 || trim($name) === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Missing or invalid projectId/name.',
                ],
            ], 422);
        }

        $result = $client->filesDelete($projectId, $name);
        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status);
    }

    public function backtestsCreate(Request $request, QuantConnectClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'QuantConnect credentials are missing.',
                ],
            ], 400);
        }

        $projectId = (int) $request->input('projectId', 0);
        $compileId = (string) $request->input('compileId', '');
        $backtestName = $request->input('backtestName');

        if ($projectId <= 0 || trim($compileId) === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Missing or invalid projectId/compileId.',
                ],
            ], 422);
        }

        $result = $client->backtestsCreate($projectId, $compileId, is_string($backtestName) ? $backtestName : null);
        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status);
    }

    public function backtestsRead(Request $request, QuantConnectClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'QuantConnect credentials are missing.',
                ],
            ], 400);
        }

        $projectId = (int) $request->query('projectId', 0);
        $backtestId = (string) $request->query('backtestId', '');

        if ($projectId <= 0 || trim($backtestId) === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Missing or invalid projectId/backtestId.',
                ],
            ], 422);
        }

        $result = $client->backtestsRead($projectId, $backtestId);
        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status);
    }

    public function backtestsUpdate(Request $request, QuantConnectClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'QuantConnect credentials are missing.',
                ],
            ], 400);
        }

        $projectId = (int) $request->input('projectId', 0);
        $backtestId = (string) $request->input('backtestId', '');
        $name = (string) $request->input('name', '');
        $note = $request->input('note');

        if ($projectId <= 0 || trim($backtestId) === '' || trim($name) === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Missing or invalid projectId/backtestId/name.',
                ],
            ], 422);
        }

        $result = $client->backtestsUpdate($projectId, $backtestId, $name, is_string($note) ? $note : null);
        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status);
    }

    public function backtestsDelete(Request $request, QuantConnectClient $client): JsonResponse
    {
        if (! $client->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 400,
                    'message' => 'QuantConnect credentials are missing.',
                ],
            ], 400);
        }

        $projectId = (int) $request->input('projectId', 0);
        $backtestId = (string) $request->input('backtestId', '');

        if ($projectId <= 0 || trim($backtestId) === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Missing or invalid projectId/backtestId.',
                ],
            ], 422);
        }

        $result = $client->backtestsDelete($projectId, $backtestId);
        $status = (int) ($result['status'] ?? 200);

        return response()->json($result, $status);
    }

    public function backtestReport(Request $request, QuantConnectClient $client): Response
    {
        if (! $client->isConfigured()) {
            return response('QuantConnect credentials are missing.', 400);
        }

        $projectId = (int) $request->query('projectId', 0);
        $backtestId = (string) $request->query('backtestId', '');

        if ($projectId <= 0 || trim($backtestId) === '') {
            return response('Missing or invalid projectId/backtestId.', 422);
        }

        $result = $client->reportBacktest($projectId, $backtestId);
        if (($result['success'] ?? false) !== true) {
            $message = (string) data_get($result, 'error.message', 'Failed to load report.');
            $status = (int) ($result['status'] ?? 500);
            return response($message, $status);
        }

        $report = (string) data_get($result, 'data.report', '');

        $escaped = htmlspecialchars($report, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>QuantConnect Backtest Report</title>
  <style>
    html, body { height: 100%; margin: 0; }
    iframe { border: 0; width: 100%; height: 100%; }
  </style>
</head>
<body>
  <iframe sandbox srcdoc="$escaped"></iframe>
</body>
</html>
HTML;

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
