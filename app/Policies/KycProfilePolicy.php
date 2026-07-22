<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\KycProfile;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorisation for KYC profiles (TDD §8.1). Owners (consumer users) may view
 * and create their own submissions; approving or rejecting is an operator
 * (compliance) action on the separate `admin` guard.
 */
class KycProfilePolicy
{
    public function view(Authenticatable $actor, KycProfile $profile): bool
    {
        return $actor->getAuthIdentifier() === $profile->user_id || $this->isCompliance($actor);
    }

    public function create(Authenticatable $actor): bool
    {
        return ! $actor instanceof Admin; // consumers submit their own KYC
    }

    public function approve(Authenticatable $actor, KycProfile $profile): bool
    {
        return $this->isCompliance($actor);
    }

    public function reject(Authenticatable $actor, KycProfile $profile): bool
    {
        return $this->isCompliance($actor);
    }

    private function isCompliance(Authenticatable $actor): bool
    {
        return $actor instanceof Admin && $actor->hasAnyRole(['super-admin', 'admin', 'compliance']);
    }
}
