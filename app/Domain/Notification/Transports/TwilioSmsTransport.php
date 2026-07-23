<?php

declare(strict_types=1);

namespace App\Domain\Notification\Transports;

use App\Domain\Notification\Contracts\NotificationTransport;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real SMS delivery via Twilio (Wave 6). Selected by setting SMS_DRIVER=twilio in
 * config/providers.php; falls back to a logged no-op when the account isn't
 * configured or the user has no phone number, so it never throws mid-notification.
 */
final class TwilioSmsTransport implements NotificationTransport
{
    public function channel(): string
    {
        return 'sms';
    }

    public function send(User $user, string $title, string $body, ?string $url = null): void
    {
        $phone = $user->phone;
        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.token');
        $from = (string) config('services.twilio.from');

        if (! $phone || $sid === '' || $token === '' || $from === '') {
            Log::warning('[sms:twilio] skipped — missing phone or Twilio credentials', ['user_id' => $user->id]);

            return;
        }

        Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To' => $phone,
                'Body' => trim($title."\n".$body).($url ? "\n".$url : ''),
            ]);
    }
}
