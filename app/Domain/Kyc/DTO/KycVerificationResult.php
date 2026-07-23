<?php

declare(strict_types=1);

namespace App\Domain\Kyc\DTO;

/**
 * Neutral result of kicking off identity verification, independent of the vendor.
 * Manual review returns `pending`; automated vendors (Onfido, SumSub, Jumio)
 * return their session reference and, once checks complete, an approved/rejected
 * status plus liveness/document sub-results.
 */
final class KycVerificationResult
{
    /**
     * @param  string  $status  'pending' | 'approved' | 'rejected'
     * @param  array<string, mixed>  $checks  per-check results (document, face, address)
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $reference = null,
        public readonly ?bool $livenessPassed = null,
        public readonly array $checks = [],
    ) {}

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
