<?php

declare(strict_types=1);

namespace App\Domain\Notification\Transports;

use App\Domain\Notification\Contracts\NotificationTransport;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Telegram delivery via the Bot API (Wave 6). Requires a bot token and the user's
 * linked chat id (users.telegram_chat_id); no-ops otherwise.
 */
final class TelegramTransport implements NotificationTransport
{
    public function channel(): string
    {
        return 'telegram';
    }

    public function send(User $user, string $title, string $body, ?string $url = null): void
    {
        $chatId = $user->telegram_chat_id;
        $botToken = (string) config('services.telegram.bot_token');

        if (! $chatId || $botToken === '') {
            return;
        }

        Http::asJson()->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => trim($title."\n".$body).($url ? "\n".$url : ''),
        ]);
    }
}
