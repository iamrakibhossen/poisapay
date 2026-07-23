<?php

declare(strict_types=1);

namespace App\Domain\Kyc\Contracts;

use App\Domain\Kyc\DTO\KycVerificationResult;
use App\Models\KycProfile;

/**
 * Identity-verification provider (TDD §10.1). The default `manual` provider
 * represents operator review; automated vendors (Onfido, SumSub, Jumio) drop in
 * by implementing this interface and registering a driver in config/providers.php.
 */
interface KycProvider
{
    /** Stable provider identifier (persisted alongside the profile). */
    public function name(): string;

    /** Begin verification for a submitted profile; real vendors open a session. */
    public function submit(KycProfile $profile): KycVerificationResult;
}
