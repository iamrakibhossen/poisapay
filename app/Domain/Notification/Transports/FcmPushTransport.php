<?php

declare(strict_types=1);

namespace App\Domain\Notification\Transports;

use App\Domain\Notification\Contracts\NotificationTransport;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push delivery via Firebase Cloud Messaging (Wave 6). Sends to every device token
 * the user has registered (see UserPushToken). No-ops when the server key is unset
 * or the user has no registered devices, so it never throws mid-notification.
 */
final class FcmPushTransport implements NotificationTransport
{
    public function channel(): string
    {
        return 'push';
    }

    public function send(User $user, string $title, string $body, ?string $url = null): void
    {
        $key = (string) config('services.fcm.key');
        if ($key === '') {
            Log::warning('[push:fcm] skipped — no FCM server key', ['user_id' => $user->id]);

            return;
        }

        $tokens = $user->pushTokens()->pluck('token')->all();
        if ($tokens === []) {
            return;
        }

        foreach (array_chunk($tokens, 500) as $chunk) {
            Http::withHeaders(['Authorization' => 'key='.$key])
                ->asJson()
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'registration_ids' => $chunk,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_filter(['url' => $url]),
                ]);
        }
    }
}
