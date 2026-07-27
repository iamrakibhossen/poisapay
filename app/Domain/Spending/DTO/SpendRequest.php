<?php

declare(strict_types=1);

namespace App\Domain\Spending\DTO;

use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Spending\Enums\SpendPurpose;
use App\Enums\LedgerSide;
use App\Models\Asset;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigInteger;
use InvalidArgumentException;

/**
 * A request to spend a user's balance. The engine sources the funds (any mix of
 * priority assets, auto-converting to the settlement asset) and lands them in the
 * caller-declared {@see $destination} — credit lines in the settlement asset that
 * MUST sum to {@see $amount}. This keeps the whole spend one atomic, balanced
 * entry while letting each module decide where the money goes (payee, fee, …).
 */
final readonly class SpendRequest
{
    /**
     * @param  list<PostingLine>  $destination  credit lines in the settlement asset, summing to $amount
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public User $user,
        public Asset $settlementAsset,
        public Money $amount,
        public SpendPurpose $purpose,
        public array $destination,
        public string $idempotencyKey,
        public ?string $memo = null,
        public array $metadata = [],
    ) {}

    /** Sanity-check the destination before any money moves. */
    public function assertValid(): void
    {
        if (! $this->amount->isPositive()) {
            throw new InvalidArgumentException('Spend amount must be positive.');
        }
        if ($this->destination === []) {
            throw new InvalidArgumentException('A spend needs at least one destination credit.');
        }

        $total = BigInteger::zero();
        foreach ($this->destination as $line) {
            if ($line->side !== LedgerSide::Credit) {
                throw new InvalidArgumentException('Spend destination lines must be credits.');
            }
            if ($line->assetId !== $this->settlementAsset->id) {
                throw new InvalidArgumentException('Spend destination must be in the settlement asset.');
            }
            $total = $total->plus($line->amount);
        }

        if (! $total->isEqualTo($this->amount->base)) {
            throw new InvalidArgumentException('Spend destination credits must equal the amount.');
        }
    }
}
