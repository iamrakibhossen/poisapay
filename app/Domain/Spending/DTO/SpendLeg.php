<?php

declare(strict_types=1);

namespace App\Domain\Spending\DTO;

use App\Models\Asset;
use App\Support\Money;

/**
 * A settled funding leg (the executed form of a {@see PlannedLeg}) — part of the
 * settlement information the engine returns and records in entry metadata.
 */
final readonly class SpendLeg
{
    public function __construct(
        public Asset $asset,
        public Money $spent,
        public Money $settlementValue,
        public bool $converted,
        public ?string $conversionId,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'asset' => $this->asset->symbol,
            'spent' => $this->spent->baseString(),
            'settlement_value' => $this->settlementValue->baseString(),
            'converted' => $this->converted,
            'conversion_id' => $this->conversionId,
        ];
    }
}
