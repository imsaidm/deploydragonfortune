<?php

namespace App\Jobs;

use App\Models\QcMethod;
use App\Models\QcPriceNotification;
use App\Models\QcSignal;
use App\Services\TelegramNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class SendQcPriceNotificationEventJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = [10, 30, 60];
    public $timeout = 120;

    public function __construct(
        public int $methodId,
        public float $marketPrice,
        public string $direction,
        public float $levelPercentage,
        public ?int $qcSignalId = null,
        public ?string $eventType = 'qc_price_event',
        public ?float $stepPercentage = null,
        public ?float $entryPrice = null,
        public ?float $movementPercentage = null,
        public ?string $source = 'quantconnect',
        public ?string $occurredAt = null,
        public ?string $eventUid = null
    ) {
        $this->eventUid = $this->eventUid ?: (string) Str::uuid();
        $this->onQueue('telegram-price-alerts');
    }

    public function handle(TelegramNotificationService $telegram): void
    {
        $method = QcMethod::with('telegramChannels')->find($this->methodId);
        if (! $method) {
            Log::warning('QC price event skipped: method not found.', [
                'method_id' => $this->methodId,
            ]);
            return;
        }

        $signal = $this->resolveSignal();
        if (! $signal) {
            Log::warning('QC price event skipped: entry signal not found.', [
                'method_id' => $this->methodId,
                'qc_signal_id' => $this->qcSignalId,
            ]);
            return;
        }

        $entryPrice = $this->entryPrice ?: (float) $signal->price_entry;
        if ($entryPrice <= 0 || $this->marketPrice <= 0) {
            Log::warning('QC price event skipped: invalid price payload.', [
                'method_id' => $this->methodId,
                'qc_signal_id' => $signal->id,
                'entry_price' => $entryPrice,
                'market_price' => $this->marketPrice,
            ]);
            return;
        }

        $movementPercentage = $this->movementPercentage
            ?? (($this->marketPrice - $entryPrice) / $entryPrice) * 100;

        $notification = $this->reserveNotification($signal, $entryPrice, $movementPercentage);
        if ($notification->telegram_sent_at) {
            return;
        }

        $this->sendNotification($telegram, $method, $signal, $notification, $entryPrice, $movementPercentage);
    }

    private function resolveSignal(): ?QcSignal
    {
        if ($this->qcSignalId) {
            return QcSignal::query()
                ->where('id_method', $this->methodId)
                ->where('id', $this->qcSignalId)
                ->first();
        }

        $signalId = DB::connection('methods')
            ->table('qc_signal as entry_signal')
            ->where('entry_signal.id_method', $this->methodId)
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
        float $entryPrice,
        float $movementPercentage
    ): QcPriceNotification {
        $levelPercentage = round($this->levelPercentage, 4);
        $direction = strtolower($this->direction);

        $values = [
            'id_method' => $signal->id_method,
            'qc_signal_id' => $signal->id,
            'direction' => $direction,
            'step_percentage' => $this->stepPercentage ?? abs($levelPercentage),
            'level_percentage' => $levelPercentage,
            'entry_price' => $entryPrice,
            'market_price' => $this->marketPrice,
            'movement_percentage' => $movementPercentage,
            'source' => $this->source,
            'event_uid' => $this->eventUid,
        ];

        try {
            $notification = QcPriceNotification::firstOrCreate(
                ['event_uid' => $this->eventUid],
                $values
            );
        } catch (QueryException) {
            $notification = QcPriceNotification::where('event_uid', $this->eventUid)->firstOrFail();
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
        float $entryPrice,
        float $movementPercentage
    ): void {
        if (! config('services.telegram.enabled', false)) {
            Log::info('QC price event skipped: Telegram is disabled.', [
                'notification_id' => $notification->id,
            ]);
            return;
        }

        $lockKey = 'qc_price_notification_event_' . $notification->id;
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
                        'event_type' => $this->eventType,
                        'message' => 'No active Telegram channels.',
                    ],
                ]);
                return;
            }

            $message = $this->buildMessage($method, $signal, $entryPrice, $movementPercentage);
            $responses = [];

            foreach ($chatIds as $chatId) {
                $token = $chatId === config('services.telegram.dev_chat_id')
                    ? config('services.telegram.dev_bot_token')
                    : null;

                $responses[] = $telegram->sendToSingleChannel(
                    (string) $chatId,
                    $message,
                    $token,
                    'qc_price_notification_event_' . $notification->id
                );
            }

            $failed = collect($responses)->filter(function (array $response) {
                return ! ($response['success'] ?? false) && ! ($response['skipped'] ?? false);
            });

            if ($failed->isNotEmpty()) {
                $notification->update([
                    'telegram_response' => [
                        'success' => false,
                        'event_type' => $this->eventType,
                        'results' => $responses,
                    ],
                ]);

                throw new RuntimeException('One or more Telegram QC price event notifications failed.');
            }

            $notification->update([
                'telegram_sent_at' => now(),
                'telegram_response' => [
                    'success' => true,
                    'event_type' => $this->eventType,
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
        float $entryPrice,
        float $movementPercentage
    ): string {
        $eventTime = $this->occurredAt
            ? Carbon::parse($this->occurredAt)->setTimezone('Asia/Jakarta')
            : now()->setTimezone('Asia/Jakarta');

        $eventType = $this->eventLabel((string) ($this->eventType ?: 'qc_price_event'));
        $directionText = $this->direction === 'up' ? 'Naik' : 'Turun';
        $strategyName = $this->cleanMarkdown((string) ($method->nama_metode ?: 'Unknown Strategy'));
        $creator = $this->cleanMarkdown((string) ($method->creator ?: '-'));
        $pair = $this->cleanMarkdown((string) ($method->pair ?: '-'));
        $exchange = $this->cleanMarkdown((string) ($method->exchange ?: '-'));
        $side = $this->cleanMarkdown(strtoupper((string) ($signal->jenis ?: '-')));
        $source = $this->cleanMarkdown((string) ($this->source ?: 'quantconnect'));

        $message = "*DRAGONFORTUNE ALERT HARGA*\n";
        $message .= "==============================\n";
        $message .= "`{$pair}` | `{$side}` | `{$exchange}`\n\n";
        $message .= "*Ringkasan*\n";
        $message .= "- Event: `{$eventType}`\n";
        $message .= "- Arah gerak: `{$directionText}`\n";
        $message .= "- Level trigger: `{$this->formatSignedPercent($this->levelPercentage)}%`\n";
        $message .= "- Pergerakan saat ini: `{$this->formatSignedPercent($movementPercentage)}%`\n\n";
        $message .= "*Harga*\n";
        $message .= "- Entry: `$ {$this->formatPrice($entryPrice)}`\n";
        $message .= "- Market: `$ {$this->formatPrice($this->marketPrice)}`\n\n";
        $message .= "*Strategi*\n";
        $message .= "- Nama: `{$strategyName}`\n";
        $message .= "- Creator: `{$creator}`\n";
        $message .= "- Signal Entry: `#{$signal->id}`\n\n";
        $message .= "*Info*\n";
        $message .= "- Sumber: `{$source}`\n";
        $message .= "- Waktu: `" . $eventTime->format('d M Y, H:i:s') . " WIB`";

        return $message;
    }

    private function eventLabel(string $eventType): string
    {
        return match (strtolower($eventType)) {
            'breakout_up' => 'Breakout naik',
            'pullback_down' => 'Pullback turun',
            'breakdown_down' => 'Breakdown turun',
            'recovery_up' => 'Recovery naik',
            'back_to_entry' => 'Kembali dekat entry',
            default => ucwords(str_replace(['_', '-'], ' ', $eventType)),
        };
    }

    private function cleanMarkdown(string $value): string
    {
        return str_replace(['_', '*', '`', '[', ']'], ' ', $value);
    }

    private function formatPrice(float $value): string
    {
        return rtrim(rtrim(number_format($value, 8, '.', ','), '0'), '.');
    }

    private function formatSignedPercent(float $value): string
    {
        $sign = $value > 0 ? '+' : '';

        return $sign . rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
