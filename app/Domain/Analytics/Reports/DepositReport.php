<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\LedgerAggregates;
use App\Domain\Analytics\Period;
use App\Domain\Analytics\Report;
use App\Models\Deposit;
use Illuminate\Support\Facades\DB;

/**
 * Deposit analytics: throughput, conversion (detected → credited), USD volume and
 * currency mix over the window, with period-over-period trends.
 */
class DepositReport extends Report
{
    public function __construct(private readonly LedgerAggregates $ledger) {}

    public function key(): string
    {
        return 'deposits';
    }

    public function title(): string
    {
        return 'Deposit Analytics';
    }

    public function build(Period $period): array
    {
        $e = $this->envelope();
        $prev = $period->previous();

        $total = Deposit::whereBetween('created_at', [$period->start, $period->end])->count();
        $totalPrev = Deposit::whereBetween('created_at', [$prev->start, $prev->end])->count();
        $completed = Deposit::where('status', 'credited')->whereBetween('created_at', [$period->start, $period->end])->count();
        $pending = Deposit::whereIn('status', ['detected', 'confirming'])->whereBetween('created_at', [$period->start, $period->end])->count();
        $failed = Deposit::where('status', 'orphaned')->whereBetween('created_at', [$period->start, $period->end])->count();

        $volume = $this->ledger->volumeUsd('deposits', 'credited_at', $period, fn ($q) => $q->where('status', 'credited'));
        $volumePrev = $this->ledger->volumeUsd('deposits', 'credited_at', $prev, fn ($q) => $q->where('status', 'credited'));
        $avg = $completed > 0 ? $volume / $completed : 0.0;

        $e['kpis'] = [
            $this->trendKpi('Total Deposits', number_format($total), $total, $totalPrev, ['accent' => 'brand', 'icon' => 'arrow-down-tray', 'spark' => $this->summarySeries('deposit_count', $period)]),
            $this->kpi('Completed', number_format($completed), ['accent' => 'emerald']),
            $this->kpi('Pending', number_format($pending), ['accent' => 'amber']),
            $this->kpi('Failed / Orphaned', number_format($failed), ['accent' => 'rose']),
            $this->trendKpi('Deposit Volume', $this->usdFmt($volume), $volume, $volumePrev, ['accent' => 'emerald', 'icon' => 'banknotes', 'spark' => $this->summarySeries('deposit_volume_usd', $period)]),
            $this->kpi('Average Deposit', $this->usdFmt($avg), ['accent' => 'sky']),
        ];

        $e['charts'][] = $this->chart('deposit-count-trend', 'Deposit count', 'area',
            $this->bucketLabels($period), [$this->dataset('Deposits', $this->series(DB::table('deposits'), $period), '#d97706')]);

        $e['charts'][] = $this->chart('deposit-volume-trend', 'Deposit volume (USD)', 'bar',
            $this->bucketLabels($period), [$this->dataset('Volume', $this->summarySeries('deposit_volume_usd', $period), '#10b981')]);

        $byCurrency = DB::table('deposits as d')->join('assets as a', 'a.id', '=', 'd.asset_id')
            ->whereBetween('d.created_at', [$period->start, $period->end])
            ->groupBy('a.symbol')->selectRaw('a.symbol, count(*) as c')->pluck('c', 'symbol');
        if ($byCurrency->isNotEmpty()) {
            $e['charts'][] = $this->chart('deposit-by-currency', 'Deposits by currency', 'doughnut',
                $byCurrency->keys()->all(), [$this->dataset('Count', $byCurrency->values()->all())], ['span' => 'half']);
        }

        $e['charts'][] = $this->chart('deposit-by-status', 'Deposits by status', 'doughnut',
            ['Credited', 'Pending', 'Orphaned'], [$this->dataset('Count', [$completed, $pending, $failed])], ['span' => 'half']);

        $e['notes'][] = 'Deposits by country and by payment provider are not tracked — on-chain deposits carry no geo/provider attribution.';

        return $e;
    }
}
