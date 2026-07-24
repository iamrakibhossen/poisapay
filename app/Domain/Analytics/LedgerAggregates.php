<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use App\Domain\Exchange\UsdValuation;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerSide;
use App\Models\Asset;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cross-asset ledger aggregation for analytics, valued in USD.
 *
 * Balances are always recomputed from the append-only ledger_lines (never a
 * stored number), so every figure reconciles to the book. Point-in-time balances
 * (treasury, user funds) pass no period; flow figures (revenue/expense earned in
 * a window) pass a {@see Period} to constrain by ledger_lines.created_at.
 *
 * Callers pass a homogeneous group of account types (all debit-normal OR all
 * credit-normal) — the group's balance is taken in that shared normal orientation.
 */
class LedgerAggregates
{
    public function __construct(private readonly UsdValuation $usd) {}

    /**
     * Per-asset balance for a group of account types, in its normal orientation.
     *
     * @param  list<LedgerAccountType>  $types
     * @return Collection<int,array{asset:Asset,money:Money}> keyed by asset id
     */
    public function balances(array $types, ?Period $period = null): Collection
    {
        if ($types === []) {
            return collect();
        }

        $values = array_map(fn (LedgerAccountType $t) => $t->value, $types);
        $normal = $types[0]->normalSide();

        $rows = DB::table('ledger_lines as l')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('a.type', $values)
            ->when($period, fn ($q) => $q->where('l.created_at', '>=', $period->start)->where('l.created_at', '<=', $period->end))
            ->groupBy('l.asset_id')
            ->selectRaw('l.asset_id,
                sum(case when l.side = ? then l.amount else 0 end) as debit,
                sum(case when l.side = ? then l.amount else 0 end) as credit', ['debit', 'credit'])
            ->get();

        $assets = Asset::whereIn('id', $rows->pluck('asset_id')->unique())->get()->keyBy('id');

        return $rows->mapWithKeys(function ($r) use ($assets, $normal) {
            $asset = $assets[$r->asset_id] ?? null;
            if (! $asset) {
                return [];
            }
            $debit = BigInteger::of((string) $r->debit);
            $credit = BigInteger::of((string) $r->credit);
            $bal = $normal === LedgerSide::Debit ? $debit->minus($credit) : $credit->minus($debit);

            return [$asset->id => ['asset' => $asset, 'money' => Money::ofBase($bal, $asset->decimals, $asset->symbol)]];
        });
    }

    /**
     * Total USD value of a group of account types (whole USD, 2dp).
     *
     * @param  list<LedgerAccountType>  $types
     */
    public function usdTotal(array $types, ?Period $period = null): string
    {
        return $this->sumUsd($this->balances($types, $period));
    }

    /**
     * USD value of each asset's balance for a group, largest first — feeds the
     * "by asset" breakdown charts.
     *
     * @param  list<LedgerAccountType>  $types
     * @return list<array{symbol:string,usd:float,money:string}>
     */
    public function usdByAsset(array $types, ?Period $period = null): array
    {
        return $this->balances($types, $period)
            ->map(fn ($r) => [
                'symbol' => $r['asset']->symbol,
                'usd' => (float) $this->usd->toUsd($r['asset'], $r['money']),
                'money' => $r['money']->format(),
            ])
            ->filter(fn ($r) => $r['usd'] != 0.0)
            ->sortByDesc('usd')
            ->values()
            ->all();
    }

    /**
     * USD value of a table's per-asset `amount` (base units) volume within a
     * window. Groups by asset_id in SQL, then values each asset once — no N+1.
     *
     * @param  callable(Builder):void|null  $constrain
     */
    public function volumeUsd(string $table, string $dateColumn, Period $period, ?callable $constrain = null, string $amountColumn = 'amount'): float
    {
        $query = DB::table($table)
            ->where($dateColumn, '>=', $period->start)
            ->where($dateColumn, '<=', $period->end);

        if ($constrain) {
            $constrain($query);
        }

        $rows = $query->groupBy('asset_id')->selectRaw("asset_id, sum($amountColumn) as total")->get();
        $assets = Asset::whereIn('id', $rows->pluck('asset_id')->unique())->get()->keyBy('id');

        $total = BigDecimal::zero();
        foreach ($rows as $row) {
            $asset = $assets[$row->asset_id] ?? null;
            if (! $asset) {
                continue;
            }
            $money = Money::ofBase(BigInteger::of((string) $row->total), $asset->decimals, $asset->symbol);
            $total = $total->plus(BigDecimal::of($this->usd->toUsd($asset, $money)));
        }

        return (float) (string) $total->toScale(2);
    }

    /** @param Collection<int,array{asset:Asset,money:Money}> $balances */
    public function sumUsd(Collection $balances): string
    {
        $total = BigDecimal::zero();
        foreach ($balances as $row) {
            $total = $total->plus(BigDecimal::of($this->usd->toUsd($row['asset'], $row['money'])));
        }

        return (string) $total->toScale(2);
    }
}
