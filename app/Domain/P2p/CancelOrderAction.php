<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Enums\P2pOrderStatus;
use App\Events\P2pOrderCancelled;
use App\Models\P2pOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Either party cancels before payment: WaitingPayment → Cancelled, seller refunded, ad restocked. */
class CancelOrderAction
{
    public function __construct(
        private readonly RefundEscrowAction $refund,
        private readonly P2pOrderService $orders,
        private readonly MerchantStatsService $stats,
    ) {}

    public function execute(P2pOrder $order, User $actor, string $reason = 'cancelled_by_user'): P2pOrder
    {
        if (! in_array($actor->getKey(), [$order->buyer_id, $order->seller_id], true)) {
            throw new RuntimeException('You are not a party to this order.');
        }

        $result = DB::transaction(function () use ($order, $actor, $reason): P2pOrder {
            $locked = P2pOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== P2pOrderStatus::WaitingPayment) {
                throw new RuntimeException('Only an unpaid order can be cancelled.');
            }

            $this->refund->execute($locked);
            $locked->loadMissing('asset');
            $this->orders->restoreAdAvailability($locked->ad_id, $locked->cryptoMoney());

            $this->orders->transition(
                $locked,
                P2pOrderStatus::Cancelled,
                ['cancelled_at' => now(), 'cancel_reason' => substr($reason, 0, 64)],
                'user',
                $actor->getKey(),
                $reason,
            );

            return $locked;
        });

        $this->stats->recordFailure($result);
        P2pOrderCancelled::dispatch($result->id);

        return $result;
    }
}
