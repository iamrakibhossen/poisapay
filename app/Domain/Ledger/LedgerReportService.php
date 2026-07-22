<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Enums\LedgerAccountType;
use App\Enums\LedgerSide;
use App\Models\Asset;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;

/**
 * Financial/ledger reports derived purely from ledger_lines (TDD §5.4). No
 * numbers are stored — every report is recomputed from the append-only ledger,
 * so it always reconciles.
 */
class LedgerReportService
{
    /**
     * Debit/credit totals per (account type, asset), plus grand totals proving
     * Σdebit = Σcredit across the whole book.
     */
    public function trialBalance(): array
    {
        $rows = DB::table('ledger_lines as l')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.account_id')
            ->selectRaw('a.type, l.asset_id,
                sum(case when l.side = ? then l.amount else 0 end) as debit,
                sum(case when l.side = ? then l.amount else 0 end) as credit', ['debit', 'credit'])
            ->groupBy('a.type', 'l.asset_id')
            ->get();

        $assets = Asset::whereIn('id', $rows->pluck('asset_id')->unique())->get()->keyBy('id');

        $totalDebit = BigInteger::zero();
        $totalCredit = BigInteger::zero();

        $out = $rows->map(function ($r) use ($assets, &$totalDebit, &$totalCredit) {
            $asset = $assets[$r->asset_id] ?? null;
            $debit = BigInteger::of((string) $r->debit);
            $credit = BigInteger::of((string) $r->credit);
            $totalDebit = $totalDebit->plus($debit);
            $totalCredit = $totalCredit->plus($credit);

            $type = LedgerAccountType::tryFrom($r->type);
            $normal = $type?->normalSide() ?? LedgerSide::Debit;
            $balance = $normal === LedgerSide::Debit ? $debit->minus($credit) : $credit->minus($debit);

            return [
                'type' => $type?->label() ?? $r->type,
                'type_raw' => $r->type,
                'asset' => $asset?->symbol ?? '#'.$r->asset_id,
                'debit' => $asset ? Money::ofBase($debit, $asset->decimals, $asset->symbol)->format() : (string) $debit,
                'credit' => $asset ? Money::ofBase($credit, $asset->decimals, $asset->symbol)->format() : (string) $credit,
                'balance' => $asset ? Money::ofBase($balance, $asset->decimals, $asset->symbol)->format() : (string) $balance,
            ];
        })->sortBy(['type', 'asset'])->values()->all();

        return [
            'rows' => $out,
            'balanced' => $totalDebit->isEqualTo($totalCredit),
            'total_debit' => (string) $totalDebit,
            'total_credit' => (string) $totalCredit,
        ];
    }

    /** Income vs expense per asset (fee/spread income, gas/loss expense). */
    public function incomeStatement(): array
    {
        $income = [LedgerAccountType::FeeIncome, LedgerAccountType::FeeCard, LedgerAccountType::FxSpreadIncome];
        $expense = [LedgerAccountType::GasExpense, LedgerAccountType::CardProgramLoss];

        $rows = [];
        foreach (Asset::where('is_active', true)->get() as $asset) {
            $inc = $this->accountBalance($income, $asset);
            $exp = $this->accountBalance($expense, $asset);
            if ($inc->isZero() && $exp->isZero()) {
                continue;
            }
            $rows[] = [
                'asset' => $asset->symbol,
                'income' => $inc->format(),
                'expense' => $exp->format(),
                'net' => $inc->minus($exp)->format(),
                'net_positive' => $inc->isGreaterThanOrEqual($exp),
            ];
        }

        return $rows;
    }

    /** Solvency per asset: treasury controlled vs user liabilities (§5.4). */
    public function solvency(): array
    {
        $treasury = [LedgerAccountType::TreasuryHot, LedgerAccountType::TreasuryCold, LedgerAccountType::TreasuryPending];
        $userFunds = [LedgerAccountType::UserAvailable, LedgerAccountType::UserLocked, LedgerAccountType::UserCardHold];

        $rows = [];
        foreach (Asset::where('is_active', true)->get() as $asset) {
            $treasuryBal = $this->accountBalance($treasury, $asset);
            $liability = $this->accountBalance($userFunds, $asset);
            if ($treasuryBal->isZero() && $liability->isZero()) {
                continue;
            }
            $rows[] = [
                'asset' => $asset->symbol,
                'treasury' => $treasuryBal->format(),
                'liabilities' => $liability->format(),
                'surplus' => $treasuryBal->minus($liability)->format(),
                'solvent' => $treasuryBal->isGreaterThanOrEqual($liability),
            ];
        }

        return $rows;
    }

    /** Net balance across a set of account types for an asset (in their normal orientation). */
    private function accountBalance(array $types, Asset $asset): Money
    {
        $typeValues = array_map(fn (LedgerAccountType $t) => $t->value, $types);

        $row = DB::table('ledger_lines as l')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('a.type', $typeValues)
            ->where('l.asset_id', $asset->id)
            ->selectRaw('sum(case when l.side = ? then l.amount else 0 end) as debit,
                sum(case when l.side = ? then l.amount else 0 end) as credit', ['debit', 'credit'])
            ->first();

        $debit = BigInteger::of((string) ($row->debit ?? '0'));
        $credit = BigInteger::of((string) ($row->credit ?? '0'));

        // These groups are all debit-normal (treasury/expense) OR all credit-normal
        // (user funds/income). Use the first type's normal side.
        $normal = $types[0]->normalSide();
        $bal = $normal === LedgerSide::Debit ? $debit->minus($credit) : $credit->minus($debit);

        return Money::ofBase($bal, $asset->decimals, $asset->symbol);
    }
}
