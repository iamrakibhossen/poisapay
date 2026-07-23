<?php

declare(strict_types=1);

namespace App\Domain\Notification\Transports;

use App\Domain\Notification\Contracts\NotificationTransport;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp delivery via Twilio's WhatsApp API (Wave 6). Uses the user's phone
 * number; no-ops when Twilio/WhatsApp isn't configured or the user has no phone.
 */
final class WhatsappTransport implements NotificationTransport
{
    public function channel(): string
    {
        return 'whatsapp';
    }

    public function send(User $user, string $title, string $body, ?string $url = null): void
    {
        $phone = $user->phone;
        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.token');
        $from = (string) config('services.twilio.whatsapp_from');

        if (! $phone || $sid === '' || $token === '' || $from === '') {
            return;
        }

        Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To' => 'whatsapp:'.$phone,
                'Body' => trim($title."\n".$body).($url ? "\n".$url : ''),
            ]);
    }
}
