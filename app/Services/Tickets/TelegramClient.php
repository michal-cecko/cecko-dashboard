<?php

namespace App\Services\Tickets;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramClient
{
    /**
     * Returns false when the bot is not set up or Telegram refuses the message,
     * so callers keep the alerts for the next run.
     */
    public function send(string $text): bool
    {
        $token = config('services.telegram.ticket_bot_token');
        $chatId = config('services.telegram.ticket_chat_id');

        if (blank($token) || blank($chatId)) {
            Log::warning('Ticket Telegram bot is not configured.');

            return false;
        }

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'disable_web_page_preview' => true,
            ]);
        } catch (Throwable $e) {
            Log::warning('Ticket Telegram send failed.', ['error' => $e->getMessage()]);

            return false;
        }

        if ($response->failed()) {
            Log::warning('Ticket Telegram send failed.', ['status' => $response->status()]);
        }

        return $response->successful();
    }
}
