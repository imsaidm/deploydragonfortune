<?php

namespace App\Jobs;

use App\Models\QcMethod;
use App\Models\QcPriceNotification;
use App\Models\QcSignal;
use App\Services\TelegramNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use RuntimeException;

class ProcessQcPriceNotificationJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = [10, 30, 60];
    public $timeout = 120;

    public function __construct(
        public int $methodId,
        public float $marketPrice,
        public ?string $source = 'quantconnect',
        public ?string $occurredAt = null
    ) {
        $this->onQueue('telegram-price-alerts');
    }

    public function handle(TelegramNotificationService $telegram): void
    {
        if ($this->marketPrice <= 0) {
            return;
        }

        $method = QcMethod::with('telegramChannels')->find($this->methodId);
        if (! $method) {
            Log::warning('Price notification skipped: method not found.', [
                'method_id' => $this->methodId,
            ]);
            return;
        }

        $signal = $this->latestOpenEntrySignal($this->methodId);
        if (! $signal) {
            Log::info('Price notification skipped: no open entry signal.', [
                'method_id' => $this->methodId,
            ]);
            return;
        }

        $entryPrice = (float) $signal->price_entry;
        if ($entryPrice <= 0) {
            Log::warning('Price notification skipped: entry price is missing.', [
                'method_id' => $this->methodId,
                'signal_id' => $signal->id,
            ]);
            return;
        }

        $movementPercentage = (($this->marketPrice - $entryPrice) / $entryPrice) * 100;
        if ($movementPercentage === 0.0) {
            return;
        }

        $direction = $movementPercentage > 0 ? 'up' : 'down';
        $stepPercentage = (float) (
            $direction === 'up'
                ? $method->notify_up_percentage
                : $method->notify_down_percentage
        );

        if ($stepPercentage <= 0) {
            return;
        }

        $levelCount = (int) floor(abs($movementPercentage) / $stepPercentage);
        if ($levelCount < 1) {
            return;
        }

        for ($level = 1; $level <= $levelCount; $level++) {
            $levelPercentage = round($level * $stepPercentage, 4);
            $notification = $this->reserveNotification(
                $signal,
                $direction,
                $stepPercentage,
                $levelPercentage,
                $entryPrice,
                $movementPercentage
            );

            if ($notification->telegram_sent_at) {
                continue;
            }

            $this->sendNotification(
                $telegram,
                $method,
                $signal,
                $notification,
                $direction,
                $stepPercentage,
                $levelPercentage,
                $entryPrice,
                $movementPercentage
            );
        }
    }

    private function latestOpenEntrySignal(int $methodId): ?QcSignal
    {
        $signalId = DB::connection('methods')
            ->table('qc_signal as entry_signal')
            ->where('entry_signal.id_method', $methodId)
            ->whereRaw('LOWER(entry_signal.type) = ?', ['entry'])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('qc_signal as exit_signal')
                    ->whereColumn('exit_signal.id_method', 'entry_signal.id_method')
                    ->whereRaw('LOWER(exit_signal.type) = ?', ['exit'])
                    ->whereColumn('exit_signal.created_at', '>', 'entry_signal.created_at');
            })
            ->orderByDesc('entry_signal.created_at')
            ->orderByDesc('entry_signal.id')
            ->value('entry_signal.id');

        return $signalId ? QcSignal::find((int) $signalId) : null;
    }

    private function reserveNotification(
        QcSignal $signal,
        string $direction,
        float $stepPercentage,
        float $levelPercentage,
        float $entryPrice,
        float $movementPercentage
    ): QcPriceNotification {
        $attributes = [
            'qc_signal_id' => $signal->id,
            'direction' => $direction,
            'level_percentage' => $levelPercentage,
        ];

        $values = [
            'id_method' => $signal->id_method,
            'step_percentage' => $stepPercentage,
            'entry_price' => $entryPrice,
            'market_price' => $this->marketPrice,
            'movement_percentage' => $movementPercentage,
            'source' => $this->source,
        ];

        try {
            $notification = QcPriceNotification::firstOrCreate($attributes, $values);
        } catch (QueryException) {
            $notification = QcPriceNotification::where($attributes)->firstOrFail();
        }

        if (! $notification->telegram_sent_at) {
            $notification->fill($values)->save();
        }

        return $notification;
    }

    private function sendNotification(
        TelegramNotificationService $telegram,
        QcMethod $method,
        QcSignal $signal,
        QcPriceNotification $notification,
        string $direction,
        float $stepPercentage,
        float $levelPercentage,
        float $entryPrice,
        float $movementPercentage
    ): void {
        if (! config('services.telegram.enabled', false)) {
            Log::info('Price notification skipped: Telegram is disabled.', [
                'notification_id' => $notification->id,
            ]);
            return;
        }

        $lockKey = 'qc_price_notification_' . $notification->id;
        if (! Cache::add($lockKey, true, 300)) {
            return;
        }

        try {
            $chatIds = $method->telegramChannels
                ->where('is_active', true)
                ->pluck('chat_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($chatIds)) {
                $notification->update([
                    'telegram_sent_at' => now(),
                    'telegram_response' => [
                        'success' => true,
                        'message' => 'No active Telegram channels.',
                    ],
                ]);
                return;
            }

            $message = $this->buildMessage(
                $method,
                $signal,
                $direction,
                $stepPercentage,
                $levelPercentage,
                $entryPrice,
                $movementPercentage
            );

            $responses = [];
            foreach ($chatIds as $chatId) {
                $token = $chatId === config('services.telegram.dev_chat_id')
                    ? config('services.telegram.dev_bot_token')
                    : null;

                $responses[] = $telegram->sendToSingleChannel(
                    (string) $chatId,
                    $message,
                    $token,
                    'qc_price_notification_' . $notification->id
                );
            }

            $failed = collect($responses)->filter(function (array $response) {
                return ! ($response['success'] ?? false) && ! ($response['skipped'] ?? false);
            });

            if ($failed->isNotEmpty()) {
                $notification->update([
                    'telegram_response' => [
                        'success' => false,
                        'results' => $responses,
                    ],
                ]);

                throw new RuntimeException('One or more Telegram price notifications failed.');
            }

            $notification->update([
                'telegram_sent_at' => now(),
                'telegram_response' => [
                    'success' => true,
                    'results' => $responses,
                ],
            ]);
        } finally {
            Cache::forget($lockKey);
        }
    }

    private function buildMessage(
        QcMethod $method,
        QcSignal $signal,
        string $direction,
        float $stepPercentage,
        float $levelPercentage,
        float $entryPrice,
        float $movementPercentage
    ): string {
        $directionText = $direction === 'up' ? 'Naik' : 'Turun';
        $sign = $direction === 'up' ? '+' : '-';
        $directionTone = $direction === 'up' ? '🟢 ALERT HARGA NAIK 🟢' : '🔴 ALERT HARGA TURUN 🔴';
        $strategyName = $this->cleanMarkdown((string) ($method->nama_metode ?: 'Unknown Strategy'));
        $creator = $this->cleanMarkdown((string) ($method->creator ?: '-'));
        $pair = $this->cleanMarkdown((string) ($method->pair ?: '-'));
        $exchange = $this->cleanMarkdown((string) ($method->exchange ?: '-'));
        $timeframe = $this->cleanMarkdown((string) ($method->tf ?: '-'));
        $side = $this->cleanMarkdown(strtoupper((string) ($signal->jenis ?: '-')));
        $source = $this->cleanMarkdown((string) ($this->source ?: 'quantconnect'));
        $eventTime = $this->occurredAt
            ? Carbon::parse($this->occurredAt)->setTimezone('Asia/Jakarta')
            : now()->setTimezone('Asia/Jakarta');

        $message = "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🐉 *DRAGONFORTUNE PRICE ALERT*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "⚠️ `Notifikasi pergerakan harga, bukan signal entry baru.`\n\n";
        $message .= "📊 *Strategy Info*\n";
        $message .= "├ Name: `{$strategyName}`\n";
        $message .= "├ Creator: `{$creator}`\n";
        $message .= "├ Exchange: `{$exchange}`\n";
        $message .= "├ Pair: `{$pair}`\n";
        $message .= "└ Timeframe: `{$timeframe}`\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📬 *{$directionTone}*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "📌 *Price Movement Detail*\n";
        $message .= "├ Status: `Harga {$directionText}`\n";
        $message .= "├ Level Trigger: `{$sign}{$this->formatPercent($levelPercentage)}%`\n";
        $message .= "├ Step Alert: `{$this->formatPercent($stepPercentage)}%`\n";
        $message .= "└ Pergerakan Saat Ini: `{$this->formatSignedPercent($movementPercentage)}%`\n\n";
        $message .= "💰 *Harga*\n";
        $message .= "├ Entry: `$ {$this->formatPrice($entryPrice)}`\n";
        $message .= "└ Market: `$ {$this->formatPrice($this->marketPrice)}`\n\n";
        $message .= "ℹ️ *Info*\n";
        $message .= "├ Side: `{$side}`\n";
        $message .= "├ Signal Entry: `#{$signal->id}`\n";
        $message .= "├ Sumber: `{$source}`\n";
        $message .= "└ Waktu: `" . $eventTime->format('d M Y, H:i:s') . " WIB`";

        return $message;
    }

    private function cleanMarkdown(string $value): string
    {
        return str_replace(['_', '*', '`', '[', ']'], ' ', $value);
    }

    private function formatPrice(float $value): string
    {
        return rtrim(rtrim(number_format($value, 8, '.', ','), '0'), '.');
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    private function formatSignedPercent(float $value): string
    {
        $sign = $value > 0 ? '+' : '';

        return $sign . $this->formatPercent($value);
    }
}
