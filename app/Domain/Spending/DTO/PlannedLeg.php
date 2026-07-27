<?php

declare(strict_types=1);

namespace App\Domain\Spending\DTO;

use App\Models\Asset;
use App\Support\Money;

/**
 * One funding source the selector chose: how much of {@see $asset} to consume and
 * the settlement value it provides. {@see $converted} = true when the asset must
 * be auto-converted into the settlement asset (a different coin); false when it IS
 * the settlement coin and is spent 1:1.
 */
final readonly class PlannedLeg
{
    public function __construct(
        public Asset $asset,
        public Money $spend,
        public Money $settlementValue,
        public bool $converted,
    ) {}
}
