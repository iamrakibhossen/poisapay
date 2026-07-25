<?php

declare(strict_types=1);

namespace App\Sell\Policies;

use App\Models\Admin;
use App\Sell\Models\RefundRequest;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Buyer owns the request; seller owns the order behind it; operators can see and
 * resolve any (used once escalated). Type-based, like the other Sell policies.
 */
class RefundRequestPolicy
{
    public function view(Authenticatable $actor, RefundRequest $request): bool
    {
        return $actor instanceof Admin || $this->isBuyer($actor, $request) || $this->isSeller($actor, $request);
    }

    /** Seller resolves their own; an operator resolves any (escalated). */
    public function respond(Authenticatable $actor, RefundRequest $request): bool
    {
        return $actor instanceof Admin || $this->isSeller($actor, $request);
    }

    public function escalate(Authenticatable $actor, RefundRequest $request): bool
    {
        return $this->isBuyer($actor, $request);
    }

    public function cancel(Authenticatable $actor, RefundRequest $request): bool
    {
        return $this->isBuyer($actor, $request);
    }

    public function resolveAsAdmin(Authenticatable $actor, RefundRequest $request): bool
    {
        return $actor instanceof Admin;
    }

    private function isBuyer(Authenticatable $actor, RefundRequest $request): bool
    {
        return ! $actor instanceof Admin && (string) $request->buyer_user_id === (string) $actor->getAuthIdentifier();
    }

    private function isSeller(Authenticatable $actor, RefundRequest $request): bool
    {
        return ! $actor instanceof Admin && $request->seller?->user_id === $actor->getAuthIdentifier();
    }
}
