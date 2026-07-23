<?php

declare(strict_types=1);

namespace App\Domain\Security\DTO;

/**
 * Neutral IP reputation result, independent of the vendor that produced it.
 * Providers translate their own payloads into this shape; the risk engine reads
 * the normalised score/flags without knowing which vendor answered.
 */
final class IpReputation
{
    /**
     * @param  int  $riskScore  0 (clean) – 100 (malicious)
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $ip,
        public readonly int $riskScore = 0,
        public readonly bool $isProxy = false,
        public readonly bool $isTor = false,
        public readonly bool $isVpn = false,
        public readonly bool $isHosting = false,
        public readonly array $raw = [],
    ) {}

    public function isRisky(int $threshold = 70): bool
    {
        return $this->riskScore >= $threshold || $this->isTor;
    }

    public static function clean(string $ip): self
    {
        return new self($ip, 0);
    }
}
