<?php

declare(strict_types=1);

namespace App\Domain\Credit;

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\CreditLine;
use App\Models\CreditTransaction;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Draw principal against a credit line (TDD §F6). Platform records a receivable
 * (credit:principal) and credits the user's available balance. Rejected if the
 * draw would push LTV above the line's max.
 */
class DrawCreditAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
        private readonly CreditService $credit,
    ) {}

    public function execute(CreditLine $line, Money $amount): CreditLine
    {
        return DB::transaction(function () use ($line, $amount): CreditLine {
            $line = CreditLine::whereKey($line->id)->lockForUpdate()->firstOrFail();
            $line->loadMissing('principalAsset', 'collateralAsset');

            $headroom = Money::ofBase(
                $this->credit->availableToDrawBase($line),
                $line->principalAsset->decimals,
                $line->principalAsset->symbol,
            );
            if ($amount->isGreaterThanOrEqual($headroom) && ! $amount->equals($headroom)) {
                throw new RuntimeException('Draw exceeds available credit at the maximum LTV.');
            }

            $principalReceivable = $this->accounts->system(LedgerAccountType::CreditPrincipal, $line->principal_asset_id);
            $available = $this->accounts->forUser($line->user_id, LedgerAccountType::UserAvailable, $line->principal_asset_id);

            $entry = $this->ledger->post(new EntryData(
                type: 'credit.draw',
                idempotencyKey: 'credit:draw:'.$line->id.':'.$line->principal_drawn,
                lines: [
                    PostingLine::debit($principalReceivable->id, $line->principal_asset_id, $amount),
                    PostingLine::credit($available->id, $line->principal_asset_id, $amount),
                ],
                memo: 'Credit draw',
                metadata: ['credit_line_id' => $line->id],
            ));

            $line->principal_drawn = (string) BigInteger::of($line->principal_drawn)->plus($amount->base);
            $line->ltv_bps = $this->credit->currentLtvBps($line);
            $line->save();

            CreditTransaction::create([
                'credit_line_id' => $line->id, 'type' => 'draw',
                'asset_id' => $line->principal_asset_id, 'amount' => $amount->baseString(),
                'entry_id' => $entry->id,
            ]);

            return $line->refresh();
        });
    }
}
