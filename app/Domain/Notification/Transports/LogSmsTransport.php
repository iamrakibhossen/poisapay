<?php

declare(strict_types=1);

namespace App\Domain\Notification\Transports;

use App\Domain\Notification\Contracts\NotificationTransport;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * SMS stub — records the message to the log instead of sending. Swap for a real
 * transport (Twilio, Vonage) by registering it under providers.notifications.drivers.sms.
 */
final class LogSmsTransport implements NotificationTransport
{
    public function channel(): string
    {
        return 'sms';
    }

    public function send(User $user, string $title, string $body, ?string $url = null): void
    {
        Log::info('[sms:stub]', [
            'to' => $user->phone ?? null,
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
        ]);
    }
}
