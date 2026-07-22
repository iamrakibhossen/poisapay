<?php

declare(strict_types=1);

namespace App\Domain\Credit;

use App\Domain\Exchange\Contracts\RateProvider;
use App\Domain\Exchange\ExchangeService;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\ConversionContext;
use App\Enums\CreditStatus;
use App\Enums\LedgerAccountType;
use App\Models\CreditLine;
use App\Models\CreditTransaction;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;

/**
 * Liquidate an under-collateralised credit line (TDD §F6). Releases collateral,
 * sells it for the principal asset via the exchange, and repays the outstanding
 * debt from the proceeds. Any surplus stays in the user's balance.
 */
class LiquidateCreditLineAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
        private readonly ExchangeService $exchange,
        private readonly RepayCreditAction $repay,
    ) {}

    public function execute(CreditLine $line): CreditLine
    {
        $line = CreditLine::whereKey($line->id)->firstOrFail();
        $line->loadMissing('user', 'collateralAsset', 'principalAsset');

        // Capture the pledged collateral before we release/zero it.
        $collateralBase = $line->collateral_amount;
        $line->update(['status' => CreditStatus::Liquidating]);

        // 1. Release the locked collateral to the user's available balance.
        DB::transaction(function () use ($line) {
            $locked = $this->accounts->forUser($line->user_id, LedgerAccountType::UserCollateralLocked, $line->collateral_asset_id);
            $available = $this->accounts->forUser($line->user_id, LedgerAccountType::UserAvailable, $line->collateral_asset_id);
            $amount = Money::ofBase($line->collateral_amount, $line->collateralAsset->decimals, $line->collateralAsset->symbol);

            $this->ledger->post(new EntryData(
                type: 'credit.liquidate.release',
                idempotencyKey: "credit:liq:release:{$line->id}",
                lines: [
                    PostingLine::debit($locked->id, $line->collateral_asset_id, $amount),
                    PostingLine::credit($available->id, $line->collateral_asset_id, $amount),
                ],
                memo: 'Liquidation: collateral released',
                metadata: ['credit_line_id' => $line->id],
            ));
            $line->update(['collateral_amount' => '0']);
        });

        // 2. Sell collateral -> principal via the exchange (best-effort market).
        if ($line->collateralAsset->id !== $line->principalAsset->id) {
            $collateralHeld = Money::ofBase($collateralBase, $line->collateralAsset->decimals, $line->collateralAsset->symbol);
            $quote = $this->exchange->quote($line->user, $line->collateralAsset, $line->principalAsset, $collateralHeld, ConversionContext::Swap);
            $this->exchange->execute($line->user, $quote, "credit:liq:swap:{$line->id}");
        }

        // 3. Repay the debt from proceeds.
        $debt = Money::ofBase(
            (new CreditService(app(RateProvider::class)))->debtBase($line->refresh()),
            $line->principalAsset->decimals,
            $line->principalAsset->symbol,
        );
        if ($debt->isPositive()) {
            $this->repay->execute($line, $debt);
        }

        $line->refresh();
        $remainingDebt = BigInteger::of($line->principal_drawn)->plus($line->accrued_fee);
        $line->update(['status' => $remainingDebt->isZero() ? CreditStatus::Repaid : CreditStatus::Defaulted]);

        CreditTransaction::create([
            'credit_line_id' => $line->id, 'type' => 'liquidate',
            'asset_id' => $line->principal_asset_id, 'amount' => $debt->baseString(),
        ]);

        return $line->refresh();
    }
}
