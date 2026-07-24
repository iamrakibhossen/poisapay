<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Analytics\Period;
use App\Domain\Exchange\UsdValuation;
use App\Enums\LedgerAccountType;
use App\Models\AnalyticsDailyMetric;
use App\Models\Asset;
use App\Models\Conversion;
use App\Models\Deposit;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Materialises the daily analytics rollups (see analytics_daily_metrics). Runs
 * hourly to re-derive USD-valued per-day figures — deposits, withdrawals, swaps,
 * revenue, gas cost and signups — so the dashboard's time-series charts read a
 * tiny summary table instead of scanning the ledger on every request.
 *
 * Idempotent: each (day, metric) is upserted, so re-running only refreshes.
 */
class RollupAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $lookbackDays = 90) {}

    public function handle(UsdValuation $usd): void
    {
        $today = CarbonImmutable::now()->startOfDay();
        $assets = Asset::all()->keyBy('id');

        for ($i = $this->lookbackDays; $i >= 0; $i--) {
            $day = $today->subDays($i);
            $window = Period::resolve('custom', $day->toDateString(), $day->toDateString());

            $metrics = [
                'new_users' => (float) User::whereBetween('created_at', [$window->start, $window->end])->count(),
                'deposit_count' => (float) Deposit::where('status', 'credited')->whereBetween('credited_at', [$window->start, $window->end])->count(),
                'deposit_volume_usd' => $this->volumeUsd(Deposit::where('status', 'credited')->whereBetween('credited_at', [$window->start, $window->end]), $assets, $usd),
                'withdrawal_count' => (float) Withdrawal::where('status', 'completed')->whereBetween('completed_at', [$window->start, $window->end])->count(),
                'withdrawal_volume_usd' => $this->volumeUsd(Withdrawal::where('status', 'completed')->whereBetween('completed_at', [$window->start, $window->end]), $assets, $usd),
                'swap_count' => (float) Conversion::where('context', 'swap')->whereBetween('created_at', [$window->start, $window->end])->count(),
                'swap_volume_usd' => (float) Conversion::whereBetween('created_at', [$window->start, $window->end])->sum('notional_usd'),
                'revenue_usd' => $this->ledgerUsd([LedgerAccountType::FeeIncome, LedgerAccountType::FeeCard, LedgerAccountType::FxSpreadIncome, LedgerAccountType::P2pFeeIncome], $window, $assets, $usd),
                'gas_expense_usd' => $this->ledgerUsd([LedgerAccountType::GasExpense], $window, $assets, $usd),
            ];

            foreach ($metrics as $metric => $value) {
                AnalyticsDailyMetric::updateOrCreate(
                    ['day' => $day->toDateString(), 'metric' => $metric],
                    ['value' => round($value, 2)],
                );
            }
        }
    }

    /** Sum a model query's `amount` (base units) valued in USD, grouped by asset. */
    private function volumeUsd($query, $assets, UsdValuation $usd): float
    {
        $total = BigDecimal::zero();

        foreach ((clone $query)->selectRaw('asset_id, sum(amount) as total')->groupBy('asset_id')->get() as $row) {
            $asset = $assets[$row->asset_id] ?? null;
            if (! $asset) {
                continue;
            }
            $money = Money::ofBase(BigInteger::of((string) $row->total), $asset->decimals, $asset->symbol);
            $total = $total->plus(BigDecimal::of($usd->toUsd($asset, $money)));
        }

        return (float) (string) $total->toScale(2, RoundingMode::DOWN);
    }

    /** Net USD movement into a group of ledger account types within the window. */
    private function ledgerUsd(array $types, Period $window, $assets, UsdValuation $usd): float
    {
        $values = array_map(fn (LedgerAccountType $t) => $t->value, $types);
        $normalCredit = $types[0]->normalSide()->value === 'credit';

        $rows = DB::table('ledger_lines as l')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('a.type', $values)
            ->whereBetween('l.created_at', [$window->start, $window->end])
            ->groupBy('l.asset_id')
            ->selectRaw('l.asset_id,
                sum(case when l.side = ? then l.amount else 0 end) as debit,
                sum(case when l.side = ? then l.amount else 0 end) as credit', ['debit', 'credit'])
            ->get();

        $total = BigDecimal::zero();
        foreach ($rows as $row) {
            $asset = $assets[$row->asset_id] ?? null;
            if (! $asset) {
                continue;
            }
            $debit = BigInteger::of((string) $row->debit);
            $credit = BigInteger::of((string) $row->credit);
            $bal = $normalCredit ? $credit->minus($debit) : $debit->minus($credit);
            $money = Money::ofBase($bal, $asset->decimals, $asset->symbol);
            $total = $total->plus(BigDecimal::of($usd->toUsd($asset, $money)));
        }

        return (float) (string) $total->toScale(2, RoundingMode::DOWN);
    }
}
