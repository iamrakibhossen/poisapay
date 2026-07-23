<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Enums\P2pOrderStatus;
use App\Events\P2pOrderCompleted;
use App\Models\P2pOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seller confirms fiat receipt and releases escrow to the buyer:
 * BuyerPaid → Releasing → Completed (escrow → buyer + fee income).
 */
class ConfirmReleaseAction
{
    public function __construct(
        private readonly ReleaseEscrowAction $release,
        private readonly P2pOrderService $orders,
        private readonly MerchantStatsService $stats,
    ) {}

    public function execute(P2pOrder $order, User $actor): P2pOrder
    {
        if ($actor->getKey() !== $order->seller_id) {
            throw new RuntimeException('Only the seller can release the escrow.');
        }

        $result = DB::transaction(function () use ($order, $actor): P2pOrder {
            $locked = P2pOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [P2pOrderStatus::BuyerPaid, P2pOrderStatus::Releasing], true)) {
                throw new RuntimeException('This order is not awaiting release.');
            }

            if ($locked->status === P2pOrderStatus::BuyerPaid) {
                $this->orders->transition($locked, P2pOrderStatus::Releasing, [], 'user', $actor->getKey(), 'seller releasing');
            }

            $this->release->execute($locked);

            $this->orders->transition(
                $locked,
                P2pOrderStatus::Completed,
                ['released_at' => now()],
                'user',
                $actor->getKey(),
                'escrow released to buyer',
            );

            return $locked;
        });

        $this->stats->recordCompletion($result->refresh());
        P2pOrderCompleted::dispatch($result->id);

        return $result;
    }
}
