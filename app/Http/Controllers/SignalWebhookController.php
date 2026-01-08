<?php

namespace App\Http\Controllers;

use App\Services\SignalService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SignalWebhookController extends Controller
{
    public function __construct(
        private SignalService $signalService,
        private TelegramService $telegramService
    ) {}

    /**
     * Receive webhook signal from QuantConnect
     */
    public function receiveSignal(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $requestId = uniqid('webhook_', true);

        Log::info('Webhook signal received', [
            'request_id' => $requestId,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_length' => strlen($request->getContent()),
            'timestamp' => now()->toISOString()
        ]);

        try {
            // Validate webhook signature for security
            if (!$this->validateWebhookSignature($request)) {
                Log::warning('Webhook signature validation failed', [
                    'request_id' => $requestId,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'timestamp' => now()->toISOString()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_SIGNATURE',
                        'message' => 'Webhook signature validation failed',
                        'timestamp' => now()->toISOString(),
                        'request_id' => $requestId
                    ]
                ], 401);
            }

            // Validate request data structure
            $validatedData = $this->validateSignalData($request);

            // Process and transform signal data
            $processedData = $this->processSignalData($validatedData);

            // Store signal in database using SignalService
            $signal = $this->signalService->storeSignal($processedData);

            Log::info('Signal stored successfully', [
                'request_id' => $requestId,
                'signal_id' => $signal->id,
                'project_id' => $signal->project_id,
                'symbol' => $signal->symbol,
                'signal_type' => $signal->signal_type,
                'action' => $signal->action,
                'price' => $signal->price,
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);

            // Calculate PnL for exit signals
            if ($signal->signal_type === 'exit') {
                try {
                    $pnl = $this->signalService->calculatePnL($signal);
                    if ($pnl !== null) {
                        Log::info('PnL calculated for exit signal', [
                            'request_id' => $requestId,
                            'signal_id' => $signal->id,
                            'calculated_pnl' => $pnl
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to calculate PnL for exit signal', [
                        'request_id' => $requestId,
                        'signal_id' => $signal->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Send Telegram notification (non-blocking)
            $telegramSuccess = false;
            try {
                $telegramSuccess = $this->telegramService->sendSignalNotification($signal);

                if ($telegramSuccess) {
                    Log::info('Telegram notification sent successfully', [
                        'request_id' => $requestId,
                        'signal_id' => $signal->id
                    ]);
                } else {
                    Log::warning('Telegram notification failed to send', [
                        'request_id' => $requestId,
                        'signal_id' => $signal->id
                    ]);
                }
            } catch (\Exception $e) {
                // Log Telegram error but don't fail the webhook
                Log::error('Failed to send Telegram notification', [
                    'request_id' => $requestId,
                    'signal_id' => $signal->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Signal webhook processed successfully', [
                'request_id' => $requestId,
                'signal_id' => $signal->id,
                'project_id' => $signal->project_id,
                'symbol' => $signal->symbol,
                'signal_type' => $signal->signal_type,
                'telegram_sent' => $telegramSuccess,
                'processing_time_ms' => $processingTime,
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'signal_id' => $signal->id,
                    'message' => 'Signal processed successfully',
                    'telegram_sent' => $telegramSuccess,
                    'processing_time_ms' => $processingTime,
                    'timestamp' => now()->toISOString(),
                    'request_id' => $requestId
                ]
            ], 200);
        } catch (ValidationException $e) {
            Log::warning('Webhook validation failed', [
                'request_id' => $requestId,
                'errors' => $e->errors(),
                'ip' => $request->ip(),
                'payload_preview' => substr($request->getContent(), 0, 500),
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request data',
                    'details' => $e->errors(),
                    'timestamp' => now()->toISOString(),
                    'request_id' => $requestId
                ]
            ], 422);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
                'payload_preview' => substr($request->getContent(), 0, 500),
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'timestamp' => now()->toISOString()
            ]);

            // Send error notification to Telegram if configured
            try {
                $this->telegramService->sendErrorNotification(
                    'Webhook processing failed: ' . $e->getMessage(),
                    [
                        'request_id' => $requestId,
                        'ip' => $request->ip(),
                        'timestamp' => now()->toISOString()
                    ]
                );
            } catch (\Exception $telegramError) {
                Log::error('Failed to send error notification to Telegram', [
                    'request_id' => $requestId,
                    'original_error' => $e->getMessage(),
                    'telegram_error' => $telegramError->getMessage()
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PROCESSING_ERROR',
                    'message' => 'Failed to process signal',
                    'timestamp' => now()->toISOString(),
                    'request_id' => $requestId
                ]
            ], 500);
        }
    }

    /**
     * Validate webhook signature using HMAC
     */
    private function validateWebhookSignature(Request $request): bool
    {
        $webhookSecret = config('services.quantconnect.webhook_secret');

        // Skip validation if no secret is configured (development mode)
        if (empty($webhookSecret)) {
            Log::warning('Webhook secret not configured - skipping signature validation');
            return true;
        }

        $signature = $request->header('X-QuantConnect-Signature');
        if (empty($signature)) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Validate incoming signal data structure
     * Supports both original format and QuantConnect script format:
     * - signal_type OR type (ENTRY/EXIT/ALERT/entry/exit/alert)
     * - action OR jenis (LONG/SHORT/BUY/SELL)
     * - target_price OR target_tp
     * - stop_loss OR target_sl
     */
    private function validateSignalData(Request $request): array
    {
        // Normalize field names from QuantConnect format
        $data = $request->all();

        // Map 'type' to 'signal_type' if using QuantConnect format
        if (isset($data['type']) && !isset($data['signal_type'])) {
            $data['signal_type'] = strtolower($data['type']);
        }

        // Map 'jenis' to 'action' if using QuantConnect format
        if (isset($data['jenis']) && !isset($data['action'])) {
            $data['action'] = strtolower($data['jenis']);
        }

        // Map 'target_tp' to 'target_price' if using QuantConnect format
        if (isset($data['target_tp']) && !isset($data['target_price'])) {
            $data['target_price'] = $data['target_tp'];
        }

        // Map 'target_sl' to 'stop_loss' if using QuantConnect format
        if (isset($data['target_sl']) && !isset($data['stop_loss'])) {
            $data['stop_loss'] = $data['target_sl'];
        }

        // Map 'algorithm_name' to 'project_name' if not set
        if (isset($data['algorithm_name']) && !isset($data['project_name'])) {
            $data['project_name'] = $data['algorithm_name'];
        }

        // Add timestamp if not provided
        if (!isset($data['timestamp'])) {
            $data['timestamp'] = now()->toISOString();
        }

        // Merge normalized data back to request
        $request->merge($data);

        return $request->validate([
            'project_id' => 'required|integer|min:1',
            'project_name' => 'nullable|string|max:255',
            'algorithm_name' => 'nullable|string|max:255',
            'signal_type' => 'required|string|in:entry,exit,alert,update,error',
            'symbol' => 'required|string|max:50',
            'action' => 'required|string|in:buy,sell,long,short',
            'price' => 'required|numeric|min:0',
            'quantity' => 'nullable|numeric|min:0',
            'target_price' => 'nullable|numeric|min:0',
            'stop_loss' => 'nullable|numeric|min:0',
            'realized_pnl' => 'nullable|numeric',
            'message' => 'nullable|string|max:1000',
            'timestamp' => 'required|date',
            'metadata' => 'nullable|array',
            'source' => 'nullable|string|max:100'
        ]);
    }

    /**
     * Process and transform signal data for storage
     */
    private function processSignalData(array $data): array
    {
        return [
            'project_id' => $data['project_id'],
            'project_name' => $data['project_name'] ?? $data['algorithm_name'] ?? null,
            'signal_type' => strtolower($data['signal_type']),
            'symbol' => strtoupper($data['symbol']),
            'action' => strtolower($data['action']),
            'price' => $data['price'],
            'quantity' => $data['quantity'] ?? null,
            'target_price' => $data['target_price'] ?? null,
            'stop_loss' => $data['stop_loss'] ?? null,
            'realized_pnl' => $data['realized_pnl'] ?? null,
            'message' => $data['message'] ?? null,
            'raw_payload' => request()->all(),
            'webhook_received_at' => now(),
            'signal_timestamp' => $data['timestamp'],
            'source' => $data['source'] ?? 'quantconnect'
        ];
    }
}
