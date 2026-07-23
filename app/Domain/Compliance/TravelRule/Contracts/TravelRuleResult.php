<?php

declare(strict_types=1);

namespace App\Domain\Compliance\TravelRule\Contracts;

/**
 * Outcome of submitting a Travel Rule message.
 */
final class TravelRuleResult
{
    /**
     * @param  string  $status  submitted | pending | not_required | failed
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $providerRef = null,
    ) {}
}
