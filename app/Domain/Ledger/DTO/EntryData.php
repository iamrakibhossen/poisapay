<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTO;

use App\Enums\EntryStatus;
use Brick\Math\BigInteger;
use InvalidArgumentException;

/**
 * A balanced journal entry to be posted (TDD §5.1). Carries its own
 * idempotency key so a queue retry collapses to a no-op (D5).
 *
 * @property-read array<int, PostingLine> $lines
 */
final readonly class EntryData
{
    /** @param  array<int, PostingLine>  $lines */
    public function __construct(
        public string $type,
        public string $idempotencyKey,
        public array $lines,
        public ?string $memo = null,
        public array $metadata = [],
        public EntryStatus $status = EntryStatus::Completed,
        public ?string $reversesEntryId = null,
    ) {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal entry needs at least two lines.');
        }
    }

    /** Assert Σ debits = Σ credits before touching the DB (defence in depth vs the trigger). */
    public function assertBalanced(): void
    {
        $sum = BigInteger::zero();
        foreach ($this->lines as $line) {
            $sum = $sum->plus($line->signedAmount());
        }

        if (! $sum->isZero()) {
            throw new InvalidArgumentException("Unbalanced entry [{$this->type}]: imbalance = {$sum}");
        }
    }
}
