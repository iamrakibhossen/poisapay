<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Console\Commands\P2pProcessTimeouts;
use App\Domain\P2p\ExpireOrderAction;
use App\Enums\P2pOrderStatus;
use App\Models\P2pOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fires at an order's payment deadline and expires it if still unpaid. Guarded
 * so an early run (e.g. the sync queue ignoring the delay) is a no-op — the
 * scheduled sweep {@see P2pProcessTimeouts} is the backstop.
 */
class P2pExpireOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $orderId) {}

    public function handle(ExpireOrderAction $action): void
    {
        $order = P2pOrder::find($this->orderId);

        if (! $order || $order->status !== P2pOrderStatus::WaitingPayment) {
            return;
        }

        if ($order->expires_at && $order->expires_at->isFuture()) {
            return;   // not yet due
        }

        $action->execute($order);
    }
}
