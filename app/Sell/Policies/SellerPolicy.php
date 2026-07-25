<?php

declare(strict_types=1);

namespace App\Sell\Policies;

use App\Models\Admin;
use App\Sell\Models\Seller;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorization for sellers. A seller-owner (core User) may only touch their own
 * profile; operators (Admin guard) may review/suspend. Enforced everywhere the
 * Seller model is acted on — no ID-enumeration, no cross-tenant access.
 */
class SellerPolicy
{
    public function view(Authenticatable $actor, Seller $seller): bool
    {
        return $this->owns($actor, $seller) || $actor instanceof Admin;
    }

    public function update(Authenticatable $actor, Seller $seller): bool
    {
        return $this->owns($actor, $seller);
    }

    /** Operator decision on an application (approve/reject/suspend/reinstate). */
    public function review(Authenticatable $actor, Seller $seller): bool
    {
        return $actor instanceof Admin;
    }

    private function owns(Authenticatable $actor, Seller $seller): bool
    {
        return ! $actor instanceof Admin && $actor->getAuthIdentifier() === $seller->user_id;
    }
}
