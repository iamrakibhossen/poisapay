<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OtpRequested;
use App\Notifications\OtpNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/** Deliver a requested OTP over its channel (§9.2). */
class SendOtpNotification implements ShouldQueue
{
    public function handle(OtpRequested $event): void
    {
        $route = $event->channel === 'sms' ? 'nexmo' : 'mail';

        Notification::route($route, $event->identifier)
            ->notify(new OtpNotification($event->code, $event->purpose));
    }
}
