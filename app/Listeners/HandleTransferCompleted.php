<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TransferCompleted;
use App\Models\Transfer;
use App\Notifications\LedgerEventNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * On a completed internal transfer: notify the recipient (money received) and the
 * sender (money sent). Auto-discovered by its handle() type-hint — do not register
 * manually (see AppServiceProvider note on double-firing).
 */
class HandleTransferCompleted implements ShouldQueue
{
    public function handle(TransferCompleted $event): void
    {
        $transfer = Transfer::with(['sender', 'recipient', 'asset'])->find($event->transferId);
        if (! $transfer) {
            return;
        }

        $amount = $transfer->money()->format();

        // Recipient — the incoming money alert (this is what was previously missing).
        if ($transfer->recipient) {
            $from = $transfer->sender?->name ?: 'a PoisaPay user';
            $transfer->recipient->notify(new LedgerEventNotification(
                title: 'Money received',
                body: "You received {$amount} from {$from}.",
                event: 'transfer.received',
                url: route('transactions'),
            ));
        }

        // Sender — a record of the money sent.
        if ($transfer->sender) {
            $to = $transfer->recipient?->name ?: 'a PoisaPay user';
            $transfer->sender->notify(new LedgerEventNotification(
                title: 'Money sent',
                body: "You sent {$amount} to {$to}.",
                event: 'transfer.sent',
                url: route('transactions'),
            ));
        }
    }
}
