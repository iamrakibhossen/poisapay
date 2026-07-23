<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Enums\P2pDisputeStatus;
use App\Enums\P2pOrderStatus;
use App\Events\P2pOrderDisputed;
use App\Models\P2pDispute;
use App\Models\P2pOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Either party opens a dispute on a paid order → Disputed (escrow stays locked). */
class OpenDisputeAction
{
    public function __construct(private readonly P2pOrderService $orders) {}

    public function execute(P2pOrder $order, User $actor, string $reason, ?string $detail = null): P2pDispute
    {
        if (! in_array($actor->getKey(), [$order->buyer_id, $order->seller_id], true)) {
            throw new RuntimeException('You are not a party to this order.');
        }

        $dispute = DB::transaction(function () use ($order, $actor, $reason, $detail): P2pDispute {
            $locked = P2pOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [P2pOrderStatus::BuyerPaid, P2pOrderStatus::Releasing], true)) {
                throw new RuntimeException('Only a paid order can be disputed.');
            }

            $role = $actor->getKey() === $locked->buyer_id ? 'buyer' : 'seller';

            $this->orders->transition($locked, P2pOrderStatus::Disputed, [], 'user', $actor->getKey(), "dispute: {$reason}");

            return P2pDispute::create([
                'order_id' => $locked->id,
                'opened_by' => $actor->getKey(),
                'opened_by_role' => $role,
                'reason' => substr($reason, 0, 64),
                'detail' => $detail,
                'status' => P2pDisputeStatus::Open,
            ]);
        });

        P2pOrderDisputed::dispatch($order->id, $dispute->id);
        notifyAdmins('P2P dispute opened', "Order {$order->ref} was disputed and needs review.", null, 'p2p');

        return $dispute;
    }
}
