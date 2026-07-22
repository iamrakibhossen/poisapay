<?php

declare(strict_types=1);

namespace App\Domain\Kyc;

use App\Enums\KycTier;
use App\Models\User;

/**
 * Small read helpers for KYC-gated capabilities (TDD §10.1 / §F3.2). These
 * centralise the tier thresholds so callers never hard-code them.
 */
class KycService
{
    /** Minimum tier required before any withdrawal is permitted. */
    public function requiredTierForWithdrawal(): KycTier
    {
        return KycTier::Basic;
    }

    public function userCanWithdraw(User $user): bool
    {
        return $user->kyc_tier->canWithdraw()
            && $user->kyc_tier->atLeast($this->requiredTierForWithdrawal());
    }

    /** Card issuance requires the highest (Full) tier. */
    public function userCanIssueCard(User $user): bool
    {
        return $user->kyc_tier->atLeast(KycTier::Full);
    }
}
