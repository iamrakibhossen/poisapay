<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CardTransactionSettled;
use App\Models\CardAuthorization;
use App\Notifications\LedgerEventNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/** On card settlement: notify the cardholder (in-app + email). Auto-discovered. */
class HandleCardTransactionSettled implements ShouldQueue
{
    public function handle(CardTransactionSettled $event): void
    {
        $auth = CardAuthorization::with('card.user')->find($event->authorizationId);
        if (! $auth || ! $auth->card) {
            return;
        }

        $amount = number_format((int) $auth->amount / 100, 2);
        $merchant = $auth->merchant ?: 'a merchant';

        $auth->card->user?->notify(new LedgerEventNotification(
            title: 'Card payment',
            body: "You spent {$auth->currency_code} {$amount} at {$merchant}.",
            event: 'card.settled',
            url: route('cards.manage', $auth->card->id),
        ));
    }
}
