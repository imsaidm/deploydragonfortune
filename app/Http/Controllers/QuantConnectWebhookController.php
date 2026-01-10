<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QuantConnectSignal;
use App\Services\TelegramNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class QuantConnectWebhookController extends Controller
{
    private TelegramNotificationService $telegramService;

    public function __construct(TelegramNotificationService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Verify webhook token
     */
    private function verifyToken(Request $request): bool
    {
        $expectedToken = config('services.quantconnect_webhook.token');
        
        // If no token is configured, allow all requests (for development)
        if (empty($expectedToken)) {
            return true;
        }

        $providedToken = $request->header('X-Webhook-Token') ?? $request->input('token');
        
        return $providedToken === $expectedToken;
    }

    /**
     * Receive reminder notification from QuantConnect
     * 
     * POST /api/quantconnect/reminder
     * 
     * Expected payload:
     * {
     *   "qc_id": "12345_20240101120000",
     *   "market_type": "SPOT" | "FUTURES",
     *   "symbol": "BTCUSDT",
     *   "message": "Prepare for BUY signal - SMA crossover approaching"
     * }
     */
    public function receiveReminder(Request $request)
    {
        // Verify token
        if (!$this->verifyToken($request)) {
            Log::warning('QuantConnect webhook: Invalid token', [
                'ip' => $request->ip(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'qc_id' => 'required|string',
            'market_type' => 'required|in:SPOT,FUTURES',
            'symbol' => 'required|string',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Create signal record
            $signal = QuantConnectSignal::create([
                'qc_id' => $request->qc_id,
                'type' => 'REMINDER',
                'market_type' => $request->market_type,
                'symbol' => $request->symbol,
                'message' => $request->message,
            ]);

            // Send Telegram notification
            $this->telegramService->sendNotification($signal);

            Log::info('QuantConnect reminder received', [
                'signal_id' => $signal->id,
                'qc_id' => $signal->qc_id,
                'symbol' => $signal->symbol,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reminder received and processed',
                'signal_id' => $signal->id,
            ]);
        } catch (\Exception $e) {
            Log::error('QuantConnect reminder processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Receive signal notification from QuantConnect
     * 
     * POST /api/quantconnect/signal
     * 
     * Expected payload:
     * {
     *   "qc_id": "12345_20240101120000",
     *   "market_type": "SPOT" | "FUTURES",
     *   "symbol": "BTCUSDT",
     *   "side": "BUY" | "SELL",
     *   "price": 45000.00,
     *   "tp": 46125.00,
     *   "sl": 42750.00,
     *   "leverage": 10,        // optional, for futures
     *   "margin_usd": 100.00,  // optional
     *   "quantity": 0.01,      // optional
     *   "message": "BUY signal triggered - Fast SMA crossed above Slow SMA"
     * }
     */
    public function receiveSignal(Request $request)
    {
        // Verify token
        if (!$this->verifyToken($request)) {
            Log::warning('QuantConnect webhook: Invalid token', [
                'ip' => $request->ip(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'qc_id' => 'required|string',
            'market_type' => 'required|in:SPOT,FUTURES',
            'symbol' => 'required|string',
            'side' => 'required|in:BUY,SELL',
            'price' => 'required|numeric|min:0',
            'tp' => 'required|numeric|min:0',
            'sl' => 'required|numeric|min:0',
            'leverage' => 'nullable|integer|min:1',
            'margin_usd' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|numeric|min:0',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Create signal record
            $signal = QuantConnectSignal::create([
                'qc_id' => $request->qc_id,
                'type' => 'SIGNAL',
                'market_type' => $request->market_type,
                'symbol' => $request->symbol,
                'side' => $request->side,
                'price' => $request->price,
                'tp' => $request->tp,
                'sl' => $request->sl,
                'leverage' => $request->leverage,
                'margin_usd' => $request->margin_usd,
                'quantity' => $request->quantity,
                'message' => $request->message,
            ]);

            // Send Telegram notification
            $this->telegramService->sendNotification($signal);

            Log::info('QuantConnect signal received', [
                'signal_id' => $signal->id,
                'qc_id' => $signal->qc_id,
                'symbol' => $signal->symbol,
                'side' => $signal->side,
                'price' => $signal->price,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Signal received and processed',
                'signal_id' => $signal->id,
            ]);
        } catch (\Exception $e) {
            Log::error('QuantConnect signal processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
