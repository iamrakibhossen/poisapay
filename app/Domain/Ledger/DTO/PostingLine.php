<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTO;

use App\Enums\LedgerSide;
use App\Support\Money;
use Brick\Math\BigInteger;

/**
 * One debit or credit line within a journal entry. Amount is always a positive
 * base-unit integer; the side determines direction.
 */
final readonly class PostingLine
{
    public BigInteger $amount;

    public function __construct(
        public string $accountId,
        public int $assetId,
        public LedgerSide $side,
        BigInteger|Money|string|int $amount,
    ) {
        $this->amount = match (true) {
            $amount instanceof Money => $amount->base,
            $amount instanceof BigInteger => $amount,
            default => BigInteger::of((string) $amount),
        };
    }

    public static function debit(string $accountId, int $assetId, BigInteger|Money|string|int $amount): self
    {
        return new self($accountId, $assetId, LedgerSide::Debit, $amount);
    }

    public static function credit(string $accountId, int $assetId, BigInteger|Money|string|int $amount): self
    {
        return new self($accountId, $assetId, LedgerSide::Credit, $amount);
    }

    /** Signed contribution to a running debit-credit balance check. */
    public function signedAmount(): BigInteger
    {
        return $this->side === LedgerSide::Debit ? $this->amount : $this->amount->negated();
    }
}
