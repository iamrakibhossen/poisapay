<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Webhook\WebhookService;
use App\Events\WithdrawalCompleted;
use App\Models\Withdrawal;
use App\Notifications\LedgerEventNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleWithdrawalCompleted implements ShouldQueue
{
    public function __construct(private readonly WebhookService $webhooks) {}

    public function handle(WithdrawalCompleted $event): void
    {
        $withdrawal = Withdrawal::with('asset', 'user')->find($event->withdrawalId);
        if (! $withdrawal) {
            return;
        }

        $withdrawal->user?->notify(new LedgerEventNotification(
            title: 'Withdrawal completed',
            body: "Your withdrawal of {$withdrawal->money()->format()} has been sent.",
            event: 'withdrawal.completed',
            url: route('wallet'),
        ));

        $this->webhooks->dispatch($withdrawal->user_id, 'withdrawal.completed', [
            'withdrawal_id' => $withdrawal->id,
            'asset' => $withdrawal->asset->symbol,
            'amount' => $withdrawal->money()->toDecimal(),
            'to_address' => $withdrawal->to_address,
        ]);
    }
}
