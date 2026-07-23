<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\P2pDispute;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Disputes are visible to the order's parties and operators; only an operator
 * with the p2p permission may resolve one.
 */
class P2pDisputePolicy
{
    public function view(Authenticatable $actor, P2pDispute $dispute): bool
    {
        if ($actor instanceof Admin) {
            return true;
        }

        $order = $dispute->order;

        return $order && in_array($actor->getAuthIdentifier(), [$order->buyer_id, $order->seller_id], true);
    }

    public function resolve(Authenticatable $actor): bool
    {
        return $actor instanceof Admin
            && ($actor->can('manage-p2p') || $actor->hasRole('super-admin'));
    }
}
