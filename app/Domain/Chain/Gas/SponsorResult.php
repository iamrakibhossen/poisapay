<?php

declare(strict_types=1);

namespace App\Domain\Chain\Gas;

/**
 * Outcome of a gas-sponsorship attempt for one address.
 *
 * - ready:   the address already has enough native gas — the caller may proceed.
 * - pending: gas was (or is being) sent; retry on a later tick once it confirms.
 * - skipped: sponsoring is disabled — caller decides how to proceed.
 * - failed:  sponsorship dead-lettered after exhausting retries.
 */
final class SponsorResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?string $reason = null,
    ) {}

    public static function ready(): self
    {
        return new self('ready');
    }

    public static function pending(?string $reason = null): self
    {
        return new self('pending', $reason);
    }

    public static function skipped(?string $reason = null): self
    {
        return new self('skipped', $reason);
    }

    public static function failed(?string $reason = null): self
    {
        return new self('failed', $reason);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }
}
