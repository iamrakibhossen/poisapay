<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\LedgerLine;
use Brick\Math\BigInteger;

/**
 * Read-only treasury balance lookups from the ledger (debit − credit; treasury
 * accounts are debit-normal). Extracted so the Hot Wallet, Cold Wallet and
 * Liquidity pages share one implementation. No business logic — pure aggregation.
 */
class TreasuryBalances
{
    public static function of(LedgerAccountType $type, Asset $asset): Money
    {
        $scope = fn ($q) => $q->where('type', $type->value)->where('asset_id', $asset->id);

        $credit = (string) LedgerLine::whereHas('account', $scope)->where('side', 'credit')->sum('amount');
        $debit = (string) LedgerLine::whereHas('account', $scope)->where('side', 'debit')->sum('amount');

        return Money::ofBase(BigInteger::of($debit)->minus($credit), $asset->decimals, $asset->symbol);
    }
}
