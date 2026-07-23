<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Domain\Audit\ActivityLogger;
use App\Enums\P2pDisputeStatus;
use App\Enums\P2pOrderStatus;
use App\Models\Admin;
use App\Models\P2pDispute;
use App\Models\P2pOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Operator resolves a dispute. Buyer wins → force release (escrow → buyer);
 * seller wins → force cancel (refund seller, restock ad). Both paths settle the
 * escrow exactly once via the ledger.
 */
class ResolveDisputeAction
{
    public function __construct(
        private readonly ReleaseEscrowAction $release,
        private readonly RefundEscrowAction $refund,
        private readonly P2pOrderService $orders,
        private readonly MerchantStatsService $stats,
    ) {}

    /** @param  'buyer'|'seller'  $winner */
    public function execute(P2pDispute $dispute, Admin $admin, string $winner, ?string $note = null): P2pOrder
    {
        if (! in_array($winner, ['buyer', 'seller'], true)) {
            throw new RuntimeException('Winner must be "buyer" or "seller".');
        }

        $order = DB::transaction(function () use ($dispute, $admin, $winner, $note): P2pOrder {
            $dispute = P2pDispute::where('id', $dispute->id)->lockForUpdate()->firstOrFail();
            if (! $dispute->status->isOpen()) {
                throw new RuntimeException('This dispute has already been resolved.');
            }

            $order = P2pOrder::where('id', $dispute->order_id)->lockForUpdate()->firstOrFail();
            if ($order->status !== P2pOrderStatus::Disputed) {
                throw new RuntimeException('The order is not in dispute.');
            }

            if ($winner === 'buyer') {
                $this->release->execute($order);
                $this->orders->transition($order, P2pOrderStatus::ForceReleased, ['released_at' => now()], 'admin', $admin->getKey(), $note ?? 'ruled for buyer');
                $dispute->update([
                    'status' => P2pDisputeStatus::ResolvedBuyer,
                    'resolution' => $note,
                    'resolved_by' => $admin->getKey(),
                    'resolved_at' => now(),
                ]);
                $this->stats->recordCompletion($order->refresh());
            } else {
                $this->refund->execute($order);
                $order->loadMissing('asset');
                $this->orders->restoreAdAvailability($order->ad_id, $order->cryptoMoney());
                $this->orders->transition($order, P2pOrderStatus::ForceCancelled, ['cancelled_at' => now(), 'cancel_reason' => 'force_cancelled'], 'admin', $admin->getKey(), $note ?? 'ruled for seller');
                $dispute->update([
                    'status' => P2pDisputeStatus::ResolvedSeller,
                    'resolution' => $note,
                    'resolved_by' => $admin->getKey(),
                    'resolved_at' => now(),
                ]);
                $this->stats->recordFailure($order);
            }

            ActivityLogger::log('p2p.dispute.resolved', $dispute, ['winner' => $winner], actor: $admin);

            return $order;
        });

        return $order;
    }
}
