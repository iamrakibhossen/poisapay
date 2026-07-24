<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\LedgerAggregates;
use App\Domain\Analytics\Period;
use App\Domain\Analytics\Report;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;

/**
 * Withdrawal analytics: throughput by lifecycle state, USD volume, fees earned and
 * average settlement time over the window.
 */
class WithdrawalReport extends Report
{
    public function __construct(private readonly LedgerAggregates $ledger) {}

    public function key(): string
    {
        return 'withdrawals';
    }

    public function title(): string
    {
        return 'Withdrawal Analytics';
    }

    public function build(Period $period): array
    {
        $e = $this->envelope();
        $prev = $period->previous();

        $total = Withdrawal::whereBetween('created_at', [$period->start, $period->end])->count();
        $totalPrev = Withdrawal::whereBetween('created_at', [$prev->start, $prev->end])->count();

        $count = fn (array $statuses) => Withdrawal::whereIn('status', $statuses)->whereBetween('created_at', [$period->start, $period->end])->count();
        $pending = $count(['pending', 'review']);
        $processing = $count(['approved', 'signing', 'broadcast']);
        $completed = $count(['completed']);
        $rejected = $count(['cancelled']);
        $failed = $count(['failed']);

        $volume = $this->ledger->volumeUsd('withdrawals', 'completed_at', $period, fn ($q) => $q->where('status', 'completed'));
        $volumePrev = $this->ledger->volumeUsd('withdrawals', 'completed_at', $prev, fn ($q) => $q->where('status', 'completed'));
        $fees = $this->ledger->volumeUsd('withdrawals', 'completed_at', $period, fn ($q) => $q->where('status', 'completed'), 'fee');
        $avg = $completed > 0 ? $volume / $completed : 0.0;

        $avgSeconds = (float) (Withdrawal::where('status', 'completed')
            ->whereBetween('completed_at', [$period->start, $period->end])
            ->whereNotNull('completed_at')
            ->selectRaw('avg(extract(epoch from (completed_at - created_at))) as s')->value('s') ?? 0);

        $e['kpis'] = [
            $this->trendKpi('Total Withdrawals', number_format($total), $total, $totalPrev, ['accent' => 'brand', 'icon' => 'arrow-up-tray', 'spark' => $this->summarySeries('withdrawal_count', $period)]),
            $this->kpi('Completed', number_format($completed), ['accent' => 'emerald']),
            $this->kpi('Pending / Review', number_format($pending), ['accent' => 'amber']),
            $this->kpi('Processing', number_format($processing), ['accent' => 'sky']),
            $this->kpi('Failed / Rejected', number_format($failed + $rejected), ['accent' => 'rose']),
            $this->trendKpi('Withdrawal Volume', $this->usdFmt($volume), $volume, $volumePrev, ['accent' => 'emerald', 'icon' => 'banknotes', 'spark' => $this->summarySeries('withdrawal_volume_usd', $period)]),
            $this->kpi('Withdrawal Fees', $this->usdFmt($fees), ['accent' => 'brand']),
            $this->kpi('Avg Settlement Time', $this->humanDuration($avgSeconds), ['accent' => 'sky', 'hint' => 'Requested → completed']),
            $this->kpi('Average Withdrawal', $this->usdFmt($avg), ['accent' => 'violet']),
        ];

        $e['charts'][] = $this->chart('withdrawal-count-trend', 'Withdrawal count', 'area',
            $this->bucketLabels($period), [$this->dataset('Withdrawals', $this->series(DB::table('withdrawals'), $period), '#d97706')]);

        $e['charts'][] = $this->chart('withdrawal-volume-trend', 'Withdrawal volume (USD)', 'bar',
            $this->bucketLabels($period), [$this->dataset('Volume', $this->summarySeries('withdrawal_volume_usd', $period), '#f43f5e')]);

        $e['charts'][] = $this->chart('withdrawal-by-status', 'Withdrawals by status', 'doughnut',
            ['Completed', 'Pending', 'Processing', 'Failed', 'Cancelled'],
            [$this->dataset('Count', [$completed, $pending, $processing, $failed, $rejected])], ['span' => 'half']);

        return $e;
    }

    private function humanDuration(float $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }

        return match (true) {
            $seconds < 60 => round($seconds).'s',
            $seconds < 3600 => round($seconds / 60).'m',
            $seconds < 86400 => round($seconds / 3600, 1).'h',
            default => round($seconds / 86400, 1).'d',
        };
    }
}
