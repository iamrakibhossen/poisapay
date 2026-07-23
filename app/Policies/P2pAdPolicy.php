<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\P2pAd;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Ads are publicly listable; only the owner (or an operator) may edit or retire one.
 */
class P2pAdPolicy
{
    public function view(): bool
    {
        return true;
    }

    public function create(Authenticatable $actor): bool
    {
        return ! $actor instanceof Admin;   // consumers post ads
    }

    public function update(Authenticatable $actor, P2pAd $ad): bool
    {
        return $actor->getAuthIdentifier() === $ad->user_id || $actor instanceof Admin;
    }

    public function delete(Authenticatable $actor, P2pAd $ad): bool
    {
        return $this->update($actor, $ad);
    }
}
