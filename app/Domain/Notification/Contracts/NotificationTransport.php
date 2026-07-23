<?php

declare(strict_types=1);

namespace App\Domain\Notification\Contracts;

use App\Models\User;

/**
 * An outbound notification transport for a channel Laravel doesn't ship natively
 * (SMS, push). The stub transports log; real vendors (Twilio, Vonage, FCM, APNs)
 * implement this and register a driver in config/providers.php.
 */
interface NotificationTransport
{
    /** Channel this transport serves: 'sms' | 'push'. */
    public function channel(): string;

    public function send(User $user, string $title, string $body, ?string $url = null): void;
}
