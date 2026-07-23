<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Domain\Notification\Contracts\NotificationTransport;
use App\Models\User;

/**
 * Resolves and dispatches the configured transport for a non-native channel
 * (sms, push). Reads config/providers.php so the active vendor is swappable per
 * channel; an unmapped channel is a silent no-op (feature simply off).
 */
final class NotificationTransportManager
{
    /** @var array<string, NotificationTransport|null> */
    private array $resolved = [];

    public function for(string $channel): ?NotificationTransport
    {
        if (! array_key_exists($channel, $this->resolved)) {
            $driver = config("providers.notifications.$channel");
            $class = $driver ? config("providers.notifications.drivers.$channel.$driver") : null;
            $this->resolved[$channel] = $class ? app($class) : null;
        }

        return $this->resolved[$channel];
    }

    public function dispatch(string $channel, User $user, string $title, string $body, ?string $url = null): void
    {
        $this->for($channel)?->send($user, $title, $body, $url);
    }
}
