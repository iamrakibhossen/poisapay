<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Enums\P2pOrderStatus;
use App\Events\P2pBuyerMarkedPaid;
use App\Models\P2pOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Buyer attests the fiat payment was sent: WaitingPayment → BuyerPaid. */
class MarkBuyerPaidAction
{
    public function __construct(private readonly P2pOrderService $orders) {}

    public function execute(P2pOrder $order, User $actor): P2pOrder
    {
        if ($actor->getKey() !== $order->buyer_id) {
            throw new RuntimeException('Only the buyer can mark the payment as sent.');
        }

        $order = DB::transaction(function () use ($order, $actor): P2pOrder {
            $locked = P2pOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== P2pOrderStatus::WaitingPayment) {
                throw new RuntimeException('This order is not awaiting payment.');
            }

            $this->orders->transition(
                $locked,
                P2pOrderStatus::BuyerPaid,
                ['buyer_paid_at' => now()],
                'user',
                $actor->getKey(),
                'buyer marked payment sent',
            );

            return $locked;
        });

        P2pBuyerMarkedPaid::dispatch($order->id);

        return $order;
    }
}
