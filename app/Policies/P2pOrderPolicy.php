<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\P2pOrder;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Order authorisation: only the two counterparties (or an operator) may see an
 * order; each lifecycle action is restricted to the correct party.
 */
class P2pOrderPolicy
{
    public function view(Authenticatable $actor, P2pOrder $order): bool
    {
        return $this->isParty($actor, $order) || $actor instanceof Admin;
    }

    public function markPaid(Authenticatable $actor, P2pOrder $order): bool
    {
        return $actor->getAuthIdentifier() === $order->buyer_id;
    }

    public function release(Authenticatable $actor, P2pOrder $order): bool
    {
        return $actor->getAuthIdentifier() === $order->seller_id;
    }

    public function cancel(Authenticatable $actor, P2pOrder $order): bool
    {
        return $this->isParty($actor, $order);
    }

    public function dispute(Authenticatable $actor, P2pOrder $order): bool
    {
        return $this->isParty($actor, $order);
    }

    private function isParty(Authenticatable $actor, P2pOrder $order): bool
    {
        return in_array($actor->getAuthIdentifier(), [$order->buyer_id, $order->seller_id], true);
    }
}
