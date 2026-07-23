<?php

declare(strict_types=1);

namespace App\Domain\Kyc\Providers;

use App\Domain\Kyc\Contracts\KycProvider;
use App\Domain\Kyc\DTO\KycVerificationResult;
use App\Models\KycProfile;

/**
 * Default KYC provider: no automated check — an operator adjudicates the profile
 * from the admin review queue. Preserves today's manual workflow while giving
 * real vendors a seam to plug into.
 */
final class ManualKycProvider implements KycProvider
{
    public function name(): string
    {
        return 'manual';
    }

    public function submit(KycProfile $profile): KycVerificationResult
    {
        return new KycVerificationResult(
            status: 'pending',
            reference: null,
            livenessPassed: $profile->liveness_passed,
        );
    }
}
