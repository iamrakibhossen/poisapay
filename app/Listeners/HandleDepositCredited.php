<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Webhook\WebhookService;
use App\Events\DepositCredited;
use App\Models\Deposit;
use App\Notifications\LedgerEventNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/** On deposit credit: notify the user and fire the deposit.confirmed webhook (§8.3). */
class HandleDepositCredited implements ShouldQueue
{
    public function __construct(private readonly WebhookService $webhooks) {}

    public function handle(DepositCredited $event): void
    {
        $deposit = Deposit::with('asset', 'user')->find($event->depositId);
        if (! $deposit) {
            return;
        }

        $deposit->user?->notify(new LedgerEventNotification(
            title: 'Deposit credited',
            body: "{$deposit->money()->format()} has landed in your wallet.",
            event: 'deposit.confirmed',
            url: route('wallet'),
        ));

        $this->webhooks->dispatch($deposit->user_id, 'deposit.confirmed', [
            'deposit_id' => $deposit->id,
            'asset' => $deposit->asset->symbol,
            'amount' => $deposit->money()->toDecimal(),
        ]);
    }
}
