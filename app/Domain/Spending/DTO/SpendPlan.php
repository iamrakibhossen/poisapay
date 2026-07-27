<?php

declare(strict_types=1);

namespace App\Domain\Spending\DTO;

use App\Support\Money;

/**
 * The selector's funding plan: which legs to draw, whether the union covers the
 * amount, and the total settlement value that must be sourced from platform
 * liquidity (dealer inventory) to fill the conversion legs.
 */
final readonly class SpendPlan
{
    /** @param  list<PlannedLeg>  $legs */
    public function __construct(
        public array $legs,
        public bool $covers,
        public Money $conversionOutput,
    ) {}
}
