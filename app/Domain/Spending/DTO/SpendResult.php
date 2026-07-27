<?php

declare(strict_types=1);

namespace App\Domain\Spending\DTO;

use App\Domain\Spending\SpendingEngine;
use App\Models\JournalEntry;
use App\Support\Money;

/**
 * Settlement information returned by {@see SpendingEngine::spend()}:
 * the posted settlement entry, the funding legs used, and the settled amount.
 * {@see $replayed} = true when an idempotent retry returned the existing entry.
 */
final readonly class SpendResult
{
    /** @param  list<SpendLeg>  $legs */
    public function __construct(
        public JournalEntry $entry,
        public array $legs,
        public Money $settled,
        public bool $replayed = false,
    ) {}

    /** @return list<string> */
    public function conversionIds(): array
    {
        return array_values(array_filter(array_map(fn (SpendLeg $l) => $l->conversionId, $this->legs)));
    }
}
