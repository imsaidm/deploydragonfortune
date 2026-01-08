<?php

namespace App\Services;

use App\Models\QuantconnectSignal;
use App\Models\QuantconnectProjectSession;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SignalService
{
    /**
     * Store a new signal in the database
     */
    public function storeSignal(array $signalData): QuantconnectSignal
    {
        // Validate the signal data
        $validatedData = $this->validateSignalData($signalData);

        // Transform the data for storage
        $transformedData = $this->transformSignalData($validatedData);

        // Create the signal
        $signal = QuantconnectSignal::create($transformedData);

        // Update project session
        $this->updateProjectSession($signal);

        Log::info('Signal stored successfully', [
            'signal_id' => $signal->id,
            'project_id' => $signal->project_id,
            'symbol' => $signal->symbol,
            'signal_type' => $signal->signal_type
        ]);

        return $signal;
    }

    /**
     * Get signals with optional filters
     */
    public function getSignals(array $filters = []): Collection
    {
        $query = QuantconnectSignal::query();

        // Apply filters
        if (isset($filters['project_id'])) {
            $query->byProject($filters['project_id']);
        }

        if (isset($filters['symbol'])) {
            $query->bySymbol($filters['symbol']);
        }

        if (isset($filters['signal_type'])) {
            $query->byType($filters['signal_type']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->byDateRange($filters['start_date'], $filters['end_date']);
        }

        if (isset($filters['recent_days'])) {
            $query->recent($filters['recent_days']);
        }

        // Default ordering
        $query->latest();

        // Apply pagination if specified
        if (isset($filters['limit'])) {
            $query->limit($filters['limit']);
        }

        return $query->get();
    }

    /**
     * Get signals for a specific project
     */
    public function getProjectSignals(int $projectId, array $filters = []): Collection
    {
        $filters['project_id'] = $projectId;
        return $this->getSignals($filters);
    }

    /**
     * Calculate PnL for entry/exit signal pairs
     */
    public function calculatePnL(QuantconnectSignal $signal): ?float
    {
        // Only calculate PnL for exit signals
        if ($signal->signal_type !== 'exit') {
            return null;
        }

        // Find the corresponding entry signal
        $entrySignal = $this->findEntrySignal($signal);

        if (!$entrySignal) {
            Log::warning('No matching entry signal found for exit signal', [
                'exit_signal_id' => $signal->id,
                'project_id' => $signal->project_id,
                'symbol' => $signal->symbol
            ]);
            return null;
        }

        // Calculate PnL based on action type
        $pnl = $this->calculatePnLFromSignals($entrySignal, $signal);

        // Update the exit signal with calculated PnL
        $signal->update(['realized_pnl' => $pnl]);

        return $pnl;
    }

    /**
     * Format signal data for Telegram notification
     */
    public function formatSignalForTelegram(QuantconnectSignal $signal): string
    {
        $emoji = $this->getEmojiForSignal($signal);
        $action = strtoupper($signal->action);
        $type = ucfirst($signal->signal_type);

        $message = "{$emoji} **SIGNAL ALERT** {$emoji}\n\n";
        $message .= "📊 **Project**: {$signal->project_name}\n";
        $message .= "💰 **Symbol**: {$signal->symbol}\n";
        $message .= "📈 **Action**: {$action} ({$type})\n";
        $message .= "💵 **Price**: $" . number_format($signal->price, 2) . "\n";

        if ($signal->quantity) {
            $message .= "📦 **Quantity**: " . number_format($signal->quantity, 4) . "\n";
        }

        if ($signal->target_price) {
            $message .= "🎯 **Target**: $" . number_format($signal->target_price, 2) . "\n";
        }

        if ($signal->stop_loss) {
            $message .= "🛡️ **Stop Loss**: $" . number_format($signal->stop_loss, 2) . "\n";
        }

        if ($signal->realized_pnl !== null) {
            $pnlEmoji = $signal->realized_pnl >= 0 ? '💰' : '📉';
            $message .= "{$pnlEmoji} **P&L**: $" . number_format($signal->realized_pnl, 2) . "\n";
        }

        if ($signal->message) {
            $message .= "\n📝 **Message**: {$signal->message}\n";
        }

        $message .= "\n⏰ **Time**: " . $signal->signal_timestamp->format('Y-m-d H:i:s T');

        return $message;
    }

    /**
     * Validate signal data structure
     */
    private function validateSignalData(array $data): array
    {
        $validator = Validator::make($data, [
            'project_id' => 'required|integer',
            'project_name' => 'nullable|string|max:255',
            'signal_type' => 'required|in:entry,exit,alert,update,error',
            'symbol' => 'required|string|max:50',
            'action' => 'required|in:buy,sell,long,short',
            'price' => 'required|numeric|min:0',
            'quantity' => 'nullable|numeric|min:0',
            'target_price' => 'nullable|numeric|min:0',
            'stop_loss' => 'nullable|numeric|min:0',
            'realized_pnl' => 'nullable|numeric',
            'message' => 'nullable|string',
            'timestamp' => 'nullable|date',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            Log::error('Signal validation failed', [
                'errors' => $validator->errors()->toArray(),
                'data' => $data
            ]);
            throw new \InvalidArgumentException('Invalid signal data: ' . $validator->errors()->first());
        }

        return $validator->validated();
    }

    /**
     * Transform signal data for database storage
     */
    private function transformSignalData(array $data): array
    {
        return [
            'project_id' => $data['project_id'],
            'project_name' => $data['project_name'] ?? null,
            'signal_type' => $data['signal_type'],
            'symbol' => strtoupper($data['symbol']),
            'action' => strtolower($data['action']),
            'price' => $data['price'],
            'quantity' => $data['quantity'] ?? null,
            'target_price' => $data['target_price'] ?? null,
            'stop_loss' => $data['stop_loss'] ?? null,
            'realized_pnl' => $data['realized_pnl'] ?? null,
            'message' => $data['message'] ?? null,
            'raw_payload' => $data,
            'webhook_received_at' => now(),
            'signal_timestamp' => isset($data['timestamp'])
                ? Carbon::parse($data['timestamp'])
                : now()
        ];
    }

    /**
     * Update or create project session
     */
    private function updateProjectSession(QuantconnectSignal $signal): void
    {
        $session = QuantconnectProjectSession::firstOrCreate(
            ['project_id' => $signal->project_id],
            [
                'project_name' => $signal->project_name ?? "Project {$signal->project_id}",
                'is_live' => true, // Assume live trading for webhook signals
                'status' => 'active'
            ]
        );

        $session->updateLastSignal();
        $session->updateActivityStatus(); // Update activity status based on recent signals
    }

    /**
     * Find the corresponding entry signal for an exit signal
     */
    private function findEntrySignal(QuantconnectSignal $exitSignal): ?QuantconnectSignal
    {
        return QuantconnectSignal::where('project_id', $exitSignal->project_id)
            ->where('symbol', $exitSignal->symbol)
            ->where('signal_type', 'entry')
            ->where('signal_timestamp', '<=', $exitSignal->signal_timestamp)
            ->orderBy('signal_timestamp', 'desc')
            ->first();
    }

    /**
     * Calculate PnL from entry and exit signals
     */
    private function calculatePnLFromSignals(QuantconnectSignal $entry, QuantconnectSignal $exit): float
    {
        $quantity = $entry->quantity ?? 1;

        // For long positions (buy entry, sell exit)
        if (in_array($entry->action, ['buy', 'long'])) {
            return ($exit->price - $entry->price) * $quantity;
        }

        // For short positions (sell entry, buy exit)
        if (in_array($entry->action, ['sell', 'short'])) {
            return ($entry->price - $exit->price) * $quantity;
        }

        return 0;
    }

    /**
     * Get appropriate emoji for signal type and action
     */
    private function getEmojiForSignal(QuantconnectSignal $signal): string
    {
        return match ($signal->signal_type) {
            'entry' => in_array($signal->action, ['buy', 'long']) ? '🚀' : '📉',
            'exit' => '🏁',
            'update' => '🔄',
            'error' => '⚠️',
            default => '📊'
        };
    }

    /**
     * Get signal statistics for a project
     */
    public function getProjectStatistics(int $projectId): array
    {
        $signals = $this->getProjectSignals($projectId);

        $totalSignals = $signals->count();
        $entrySignals = $signals->where('signal_type', 'entry')->count();
        $exitSignals = $signals->where('signal_type', 'exit')->count();

        $totalPnL = $signals->where('signal_type', 'exit')
            ->whereNotNull('realized_pnl')
            ->sum('realized_pnl');

        $winningTrades = $signals->where('signal_type', 'exit')
            ->where('realized_pnl', '>', 0)
            ->count();

        $losingTrades = $signals->where('signal_type', 'exit')
            ->where('realized_pnl', '<', 0)
            ->count();

        $winRate = $exitSignals > 0 ? ($winningTrades / $exitSignals) * 100 : 0;

        return [
            'total_signals' => $totalSignals,
            'entry_signals' => $entrySignals,
            'exit_signals' => $exitSignals,
            'total_pnl' => $totalPnL,
            'winning_trades' => $winningTrades,
            'losing_trades' => $losingTrades,
            'win_rate' => round($winRate, 2)
        ];
    }
}
