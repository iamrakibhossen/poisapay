<?php

declare(strict_types=1);

namespace App\Domain\Revenue;

use App\Enums\LedgerAccountType;
use App\Enums\LedgerSide;
use App\Models\Asset;
use App\Support\Money;
use Brick\Math\BigInteger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the company Revenue Wallet. The wallet has no table of its own —
 * its balance and every "revenue transaction" are derived from ledger_lines that
 * credit the platform's income accounts (fee income, card fees, FX spread). This
 * keeps a single source of truth: the double-entry ledger.
 */
class RevenueService
{
    /** The ledger accounts that make up the revenue wallet. */
    public const REVENUE_TYPES = [
        LedgerAccountType::FeeIncome,
        LedgerAccountType::FeeCard,
        LedgerAccountType::FxSpreadIncome,
    ];

    /**
     * Current spendable revenue for an asset — the net balance of the fee-income
     * accounts. Withdrawals debit these accounts directly, so this already nets
     * out anything withdrawn (owner:payout is only the cumulative payout record).
     */
    public function balance(Asset $asset): Money
    {
        return Money::ofBase($this->accountBalance(self::REVENUE_TYPES, $asset->id), $asset->decimals, $asset->symbol);
    }

    /** Cumulative revenue already withdrawn (owner:payout), for reporting. */
    public function withdrawn(Asset $asset): Money
    {
        return Money::ofBase($this->accountBalance([LedgerAccountType::OwnerPayout], $asset->id), $asset->decimals, $asset->symbol);
    }

    /** Lifetime revenue collected (gross credits to the revenue accounts). */
    public function collected(Asset $asset, ?CarbonImmutable $since = null, ?CarbonImmutable $until = null): Money
    {
        $q = $this->revenueLinesQuery($asset->id);
        if ($since) {
            $q->where('e.created_at', '>=', $since);
        }
        if ($until) {
            $q->where('e.created_at', '<', $until);
        }
        $sum = (string) ($q->sum('l.amount') ?? '0');

        return Money::ofBase($sum, $asset->decimals, $asset->symbol);
    }

    /** @return array{today: Money, week: Money, month: Money, lifetime: Money} */
    public function stats(Asset $asset): array
    {
        $now = CarbonImmutable::now();

        return [
            'today' => $this->collected($asset, $now->startOfDay()),
            'week' => $this->collected($asset, $now->startOfWeek()),
            'month' => $this->collected($asset, $now->startOfMonth()),
            'lifetime' => $this->collected($asset),
        ];
    }

    /** Daily revenue series for a chart. @return array<int, array{label: string, value: float}> */
    public function dailySeries(Asset $asset, int $days = 14): array
    {
        return collect(range($days - 1, 0))->map(function (int $d) use ($asset) {
            $day = CarbonImmutable::today()->subDays($d);
            $amount = $this->collected($asset, $day, $day->addDay());

            return ['label' => $day->format('M j'), 'value' => (float) $amount->toDecimal()];
        })->all();
    }

    /** Monthly revenue series for a chart. @return array<int, array{label: string, value: float}> */
    public function monthlySeries(Asset $asset, int $months = 6): array
    {
        return collect(range($months - 1, 0))->map(function (int $m) use ($asset) {
            $month = CarbonImmutable::now()->subMonths($m)->startOfMonth();
            $amount = $this->collected($asset, $month, $month->addMonth());

            return ['label' => $month->format('M Y'), 'value' => (float) $amount->toDecimal()];
        })->all();
    }

    /**
     * Filtered, paginatable query of revenue transactions (one row per fee credit).
     *
     * @param  array{asset_id?: int, fee_type?: string, user?: string, from?: string, to?: string}  $filters
     */
    public function transactionsQuery(array $filters = []): Builder
    {
        $q = DB::table('ledger_lines as l')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.entry_id')
            ->join('assets as ast', 'ast.id', '=', 'l.asset_id')
            ->leftJoin('users as u', 'u.id', '=', DB::raw("(e.metadata->>'user_id')::uuid"))
            ->whereIn('a.type', array_map(fn ($t) => $t->value, self::REVENUE_TYPES))
            ->where('l.side', LedgerSide::Credit->value)
            ->selectRaw('l.id, l.amount, l.entry_id, l.asset_id, a.type as account_type, e.type as entry_type,
                e.created_at, ast.symbol, ast.decimals, u.name as user_name, u.email as user_email');

        if (! empty($filters['asset_id'])) {
            $q->where('l.asset_id', $filters['asset_id']);
        }
        if (! empty($filters['fee_type'])) {
            $q->where('a.type', $filters['fee_type']);
        }
        if (! empty($filters['user'])) {
            $q->where(fn ($w) => $w->where('u.name', 'like', '%'.$filters['user'].'%')->orWhere('u.email', 'like', '%'.$filters['user'].'%'));
        }
        if (! empty($filters['from'])) {
            $q->where('e.created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $q->where('e.created_at', '<=', $filters['to'].' 23:59:59');
        }

        return $q->orderByDesc('e.created_at');
    }

    /** Human fee-type label for a revenue line. */
    public function feeTypeLabel(string $accountType, string $entryType): string
    {
        return match ($accountType) {
            LedgerAccountType::FeeCard->value => 'Card Fee',
            LedgerAccountType::FxSpreadIncome->value => 'FX Margin',
            default => match (true) {
                str_contains($entryType, 'withdrawal') => 'Withdrawal Fee',
                str_contains($entryType, 'deposit') => 'Deposit Fee',
                str_contains($entryType, 'merchant') => 'Service Fee',
                str_contains($entryType, 'adjust') => 'Adjustment',
                default => 'Service Fee',
            },
        };
    }

    private function revenueLinesQuery(int $assetId): Builder
    {
        return DB::table('ledger_lines as l')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.entry_id')
            ->whereIn('a.type', array_map(fn ($t) => $t->value, self::REVENUE_TYPES))
            ->where('l.side', LedgerSide::Credit->value)
            ->where('l.asset_id', $assetId);
    }

    /** Net balance (in normal orientation) across account types for an asset. */
    private function accountBalance(array $types, int $assetId): BigInteger
    {
        $row = DB::table('ledger_lines as l')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('a.type', array_map(fn ($t) => $t->value, $types))
            ->where('l.asset_id', $assetId)
            ->selectRaw('sum(case when l.side = ? then l.amount else 0 end) as debit,
                sum(case when l.side = ? then l.amount else 0 end) as credit', ['debit', 'credit'])
            ->first();

        $debit = BigInteger::of((string) ($row->debit ?? '0'));
        $credit = BigInteger::of((string) ($row->credit ?? '0'));

        return $types[0]->normalSide() === LedgerSide::Debit ? $debit->minus($credit) : $credit->minus($debit);
    }
}
