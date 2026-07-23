<?php

declare(strict_types=1);

namespace App\Domain\Notification\Transports;

use App\Domain\Notification\Contracts\NotificationTransport;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Push stub — records the message to the log instead of sending. Swap for a real
 * transport (FCM, APNs) by registering it under providers.notifications.drivers.push.
 */
final class LogPushTransport implements NotificationTransport
{
    public function channel(): string
    {
        return 'push';
    }

    public function send(User $user, string $title, string $body, ?string $url = null): void
    {
        Log::info('[push:stub]', [
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);
    }
}
