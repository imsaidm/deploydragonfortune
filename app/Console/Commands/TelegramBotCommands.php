<?php

namespace App\Console\Commands;

use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TelegramBotCommands extends Command
{
    protected $signature = 'telegram:bot-commands';
    protected $description = 'Poll Telegram for bot commands like /id';

    public function handle(TelegramNotificationService $telegram)
    {
        $this->info("Scanning Telegram for /id commands...");
        
        $updates = $telegram->getUpdates();

        if (isset($updates['ok']) && $updates['ok'] && !empty($updates['result'])) {
            foreach ($updates['result'] as $update) {
                $updateId = $update['update_id'];
                
                // Avoid processing same update twice
                if (Cache::has("tg_update_{$updateId}")) continue;

                $message = $update['message'] ?? $update['channel_post'] ?? null;
                if (!$message) continue;

                $text = $message['text'] ?? '';
                $chatId = $message['chat']['id'] ?? null;

                if (str_contains($text, '/id') && $chatId) {
                    $this->info("Responding to /id in chat: {$chatId}");
                    
                    $reply = "🔍 *Dragon Fortune ID Finder*\n\n" .
                             "🆔 Chat ID: `{$chatId}`\n" .
                             "👤 Requested by: " . ($message['from']['first_name'] ?? 'User') . "\n\n" .
                             "Copy this ID and paste it in the Admin Panel.";

                    $telegram->sendMessageToId($chatId, $reply);
                }

                Cache::put("tg_update_{$updateId}", true, now()->addHours(24));
            }
        }

        return 0;
    }
}
