<?php

namespace App\Http\Controllers;

use App\Models\QuantConnectBacktest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class QuantConnectController extends Controller
{
    /**
     * List all backtests
     */
    public function index(): JsonResponse
    {
        $backtests = QuantConnectBacktest::orderByDesc('created_at')
            ->get()
            ->map(fn($bt) => [
                'id' => $bt->backtest_id,
                'name' => $bt->name,
                'strategyType' => $bt->strategy_type,
                'totalReturn' => $bt->total_return,
                'sharpeRatio' => $bt->sharpe_ratio,
                'winRate' => $bt->win_rate,
                'maxDrawdown' => $bt->max_drawdown,
                'totalTrades' => $bt->total_trades,
                'startDate' => $bt->backtest_start?->toIso8601String(),
                'endDate' => $bt->backtest_end?->toIso8601String(),
                'importedAt' => $bt->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'backtests' => $backtests,
        ]);
    }

    /**
     * Get single backtest details
     */
    public function show(string $id): JsonResponse
    {
        $backtest = QuantConnectBacktest::where('backtest_id', $id)->first();

        if (!$backtest) {
            return response()->json([
                'success' => false,
                'error' => 'Backtest not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'backtest' => $backtest->toApiFormat(),
        ]);
    }

    /**
     * Import backtest from JSON upload
     */
    public function import(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required_without:data|file|mimes:json,txt|max:10240',
            'data' => 'required_without:file|array',
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = null;

            // Handle file upload
            if ($request->hasFile('file')) {
                $content = file_get_contents($request->file('file')->getRealPath());
                $data = json_decode($content, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Invalid JSON file: ' . json_last_error_msg(),
                    ], 422);
                }
            }
            // Handle JSON data directly
            elseif ($request->has('data')) {
                $data = $request->input('data');
            }

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'error' => 'No data provided',
                ], 422);
            }

            // Override name if provided
            if ($request->has('name')) {
                $data['Name'] = $request->input('name');
            }

            $backtest = QuantConnectBacktest::importFromQuantConnect($data, 'manual');

            return response()->json([
                'success' => true,
                'message' => 'Backtest imported successfully',
                'backtest' => $backtest->toApiFormat(),
            ]);
        } catch (\Exception $e) {
            Log::error('QuantConnect import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Webhook endpoint for QuantConnect notifications
     * QuantConnect can POST results here after backtest completes
     */
    public function webhook(Request $request): JsonResponse
    {
        // Verify webhook secret if configured
        $secret = config('services.quantconnect.webhook_secret');
        if ($secret && $request->header('X-QC-Signature') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $data = $request->all();

            Log::info('QuantConnect webhook received', [
                'backtest_id' => $data['BacktestId'] ?? $data['backtestId'] ?? 'unknown',
            ]);

            $backtest = QuantConnectBacktest::importFromQuantConnect($data, 'webhook');

            return response()->json([
                'success' => true,
                'message' => 'Backtest received',
                'backtest_id' => $backtest->backtest_id,
            ]);
        } catch (\Exception $e) {
            Log::error('QuantConnect webhook failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a backtest
     */
    public function destroy(string $id): JsonResponse
    {
        $backtest = QuantConnectBacktest::where('backtest_id', $id)->first();

        if (!$backtest) {
            return response()->json([
                'success' => false,
                'error' => 'Backtest not found',
            ], 404);
        }

        $backtest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Backtest deleted',
        ]);
    }

    /**
     * Import from QuantConnect API (if user provides API credentials)
     */
    public function importFromApi(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string',
            'api_token' => 'required|string',
            'project_id' => 'required|string',
            'backtest_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $userId = $request->input('user_id');
            $token = $request->input('api_token');
            $projectId = $request->input('project_id');
            $backtestId = $request->input('backtest_id');

            // Call QuantConnect API
            $response = \Illuminate\Support\Facades\Http::withBasicAuth($userId, $token)
                ->get("https://www.quantconnect.com/api/v2/backtests/read", [
                    'projectId' => $projectId,
                    'backtestId' => $backtestId,
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'QuantConnect API error: ' . $response->body(),
                ], $response->status());
            }

            $data = $response->json();
            
            if (!isset($data['success']) || !$data['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $data['errors'][0] ?? 'Unknown API error',
                ], 400);
            }

            $backtest = QuantConnectBacktest::importFromQuantConnect($data['backtest'], 'api');

            return response()->json([
                'success' => true,
                'message' => 'Backtest imported from QuantConnect API',
                'backtest' => $backtest->toApiFormat(),
            ]);
        } catch (\Exception $e) {
            Log::error('QuantConnect API import failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'API import failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}

