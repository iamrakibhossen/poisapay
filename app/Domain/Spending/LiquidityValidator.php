<?php

declare(strict_types=1);

namespace App\Domain\Spending;

use App\Domain\Ledger\AccountResolver;
use App\Domain\Spending\Exceptions\InsufficientLiquidityException;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Support\Money;

/**
 * Guards platform liquidity BEFORE any auto-conversion. Every conversion leg
 * delivers the settlement asset out of dealer inventory, so the house must hold
 * at least the total conversion output. Rejecting up-front means a spend never
 * half-converts and then fails — never approve a spend that cannot settle.
 *
 * (The exchange engine re-checks per-leg under a row lock; this is the fast,
 * aggregate, human-messaged gate in front of it.)
 */
class LiquidityValidator
{
    public function __construct(private readonly AccountResolver $accounts) {}

    public function assertCanConvert(Asset $settlement, Money $needed): void
    {
        if (! $needed->isPositive()) {
            return;
        }

        $inventory = $this->accounts->system(LedgerAccountType::TradingInventory, $settlement->id)
            ->fresh('balance')->money();

        if ($inventory->isLessThan($needed)) {
            throw new InsufficientLiquidityException('Insufficient platform liquidity for automatic conversion.');
        }
    }
}
