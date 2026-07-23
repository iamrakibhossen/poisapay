<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\P2p\ExpireOrderAction;
use App\Enums\P2pOrderStatus;
use App\Models\P2pOrder;
use Illuminate\Console\Command;
use Throwable;

/**
 * Backstop sweep: expire any P2P orders whose payment window has elapsed and
 * refund their escrow. Runs every minute; idempotent with the per-order job.
 */
class P2pProcessTimeouts extends Command
{
    protected $signature = 'p2p:process-timeouts';

    protected $description = 'Expire P2P orders past their payment window and refund escrow.';

    public function handle(ExpireOrderAction $action): int
    {
        $due = P2pOrder::where('status', P2pOrderStatus::WaitingPayment->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->limit(500)
            ->get();

        $count = 0;
        foreach ($due as $order) {
            try {
                $action->execute($order);
                $count++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $this->info("Expired {$count} P2P order(s).");

        return self::SUCCESS;
    }
}
