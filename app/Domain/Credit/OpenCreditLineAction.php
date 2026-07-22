<?php

declare(strict_types=1);

namespace App\Domain\Credit;

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\CreditStatus;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\CreditLine;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Open a crypto-backed credit line (TDD §F6): lock the pledged collateral
 * (user:available -> user:collateral:locked) and record the line. No principal
 * is drawn yet — the user draws against it separately.
 */
class OpenCreditLineAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(User $user, Asset $collateral, Money $collateralAmount, Asset $principal): CreditLine
    {
        if (! $collateralAmount->isPositive()) {
            throw new RuntimeException('Collateral must be positive.');
        }

        return DB::transaction(function () use ($user, $collateral, $collateralAmount, $principal): CreditLine {
            $available = $this->accounts->forUser($user, LedgerAccountType::UserAvailable, $collateral->id);
            $locked = $this->accounts->forUser($user, LedgerAccountType::UserCollateralLocked, $collateral->id);

            $row = DB::table('account_balances')->where('account_id', $available->id)->lockForUpdate()->first();
            $current = Money::ofBase($row->balance ?? '0', $collateral->decimals, $collateral->symbol);
            if ($current->isLessThan($collateralAmount)) {
                throw new RuntimeException('Insufficient balance to pledge as collateral.');
            }

            $line = CreditLine::create([
                'user_id' => $user->id,
                'collateral_asset_id' => $collateral->id,
                'principal_asset_id' => $principal->id,
                'collateral_amount' => $collateralAmount->baseString(),
                'principal_drawn' => '0',
                'accrued_fee' => '0',
                'status' => CreditStatus::Active,
                'last_accrued_at' => now(),
            ]);

            $this->ledger->post(new EntryData(
                type: 'credit.collateral.lock',
                idempotencyKey: "credit:lock:{$line->id}",
                lines: [
                    PostingLine::debit($available->id, $collateral->id, $collateralAmount),
                    PostingLine::credit($locked->id, $collateral->id, $collateralAmount),
                ],
                memo: 'Collateral pledged',
                metadata: ['credit_line_id' => $line->id],
            ));

            return $line->refresh();
        });
    }
}
