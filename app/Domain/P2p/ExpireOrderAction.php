<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Enums\P2pOrderStatus;
use App\Events\P2pOrderExpired;
use App\Models\P2pOrder;
use Illuminate\Support\Facades\DB;

/**
 * System-initiated expiry once the payment window elapses:
 * WaitingPayment → Expired, seller refunded, ad restocked. Idempotent — a
 * duplicate fire (e.g. job + sweep) is a no-op.
 */
class ExpireOrderAction
{
    public function __construct(
        private readonly RefundEscrowAction $refund,
        private readonly P2pOrderService $orders,
        private readonly MerchantStatsService $stats,
    ) {}

    public function execute(P2pOrder $order): P2pOrder
    {
        $result = DB::transaction(function () use ($order): P2pOrder {
            $locked = P2pOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== P2pOrderStatus::WaitingPayment) {
                return $locked;   // already moved on — no-op
            }

            $this->refund->execute($locked);
            $locked->loadMissing('asset');
            $this->orders->restoreAdAvailability($locked->ad_id, $locked->cryptoMoney());

            $this->orders->transition(
                $locked,
                P2pOrderStatus::Expired,
                ['cancelled_at' => now(), 'cancel_reason' => 'expired'],
                'system',
                null,
                'payment window elapsed',
            );

            return $locked;
        });

        if ($result->status === P2pOrderStatus::Expired) {
            $this->stats->recordFailure($result);
            P2pOrderExpired::dispatch($result->id);
        }

        return $result;
    }
}
