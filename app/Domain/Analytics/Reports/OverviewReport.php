<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\LedgerAggregates;
use App\Domain\Analytics\Period;
use App\Domain\Analytics\Report;
use App\Enums\LedgerAccountType as T;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;

/**
 * Executive overview — the single-glance health of the platform. Blends user
 * growth, balance-sheet, revenue/profit and money-movement into one KPI wall,
 * plus the cross-cutting alerts (solvency, KYC backlog, queue failures, large
 * withdrawals) that leadership needs surfaced first.
 */
class OverviewReport extends Report
{
    private const USER_FUNDS = [T::UserAvailable, T::UserLocked, T::UserCardHold, T::UserP2pEscrow, T::UserCollateralLocked];

    private const TREASURY = [T::TreasuryHot, T::TreasuryCold, T::TreasuryPending];

    private const INCOME = [T::FeeIncome, T::FeeCard, T::FxSpreadIncome, T::P2pFeeIncome];

    public function __construct(private readonly LedgerAggregates $ledger) {}

    public function key(): string
    {
        return 'overview';
    }

    public function title(): string
    {
        return 'Executive Overview';
    }

    public function build(Period $period): array
    {
        $e = $this->envelope();
        $prev = $period->previous();

        $users = User::count();
        $newUsers = User::whereBetween('created_at', [$period->start, $period->end])->count();
        $newUsersPrev = User::whereBetween('created_at', [$prev->start, $prev->end])->count();
        $verified = User::where('kyc_status', 'approved')->count();
        $pendingKyc = User::where('kyc_status', 'pending')->count();

        $walletUsd = (float) $this->ledger->usdTotal(self::USER_FUNDS);
        $revenue = (float) $this->ledger->usdTotal(self::INCOME, $period);
        $revenuePrev = (float) $this->ledger->usdTotal(self::INCOME, $prev);
        $gas = (float) $this->ledger->usdTotal([T::GasExpense], $period);
        $net = round($revenue - $gas, 2);

        $depVol = $this->ledger->volumeUsd('deposits', 'credited_at', $period, fn ($q) => $q->where('status', 'credited'));
        $depVolPrev = $this->ledger->volumeUsd('deposits', 'credited_at', $prev, fn ($q) => $q->where('status', 'credited'));
        $wdVol = $this->ledger->volumeUsd('withdrawals', 'completed_at', $period, fn ($q) => $q->where('status', 'completed'));
        $wdVolPrev = $this->ledger->volumeUsd('withdrawals', 'completed_at', $prev, fn ($q) => $q->where('status', 'completed'));

        $e['kpis'] = [
            $this->kpi('Total Users', number_format($users), ['accent' => 'brand', 'icon' => 'users']),
            $this->trendKpi('New Users', number_format($newUsers), $newUsers, $newUsersPrev, ['accent' => 'emerald', 'icon' => 'user-plus', 'spark' => $this->summarySeries('new_users', $period)]),
            $this->kpi('Verified Users', number_format($verified), ['accent' => 'emerald', 'icon' => 'check-badge']),
            $this->kpi('Pending KYC', number_format($pendingKyc), ['accent' => 'amber', 'icon' => 'identification']),
            $this->kpi('Customer Balances', $this->usdFmt($walletUsd), ['accent' => 'brand', 'icon' => 'wallet']),
            $this->trendKpi('Revenue', $this->usdFmt($revenue), $revenue, $revenuePrev, ['accent' => 'emerald', 'icon' => 'banknotes', 'spark' => $this->summarySeries('revenue_usd', $period)]),
            $this->kpi('Net Profit', $this->usdFmt($net), ['accent' => $net >= 0 ? 'emerald' : 'rose', 'icon' => 'scale']),
            $this->trendKpi('Deposit Volume', $this->usdFmt($depVol), $depVol, $depVolPrev, ['accent' => 'sky', 'icon' => 'arrow-down-tray', 'spark' => $this->summarySeries('deposit_volume_usd', $period)]),
            $this->trendKpi('Withdrawal Volume', $this->usdFmt($wdVol), $wdVol, $wdVolPrev, ['accent' => 'violet', 'icon' => 'arrow-up-tray', 'spark' => $this->summarySeries('withdrawal_volume_usd', $period)]),
        ];

        $labels = $this->bucketLabels($period);
        $e['charts'][] = $this->chart('overview-flow', 'Deposits vs withdrawals', 'line', $labels, [
            $this->dataset('Deposits', $this->series(DB::table('deposits'), $period), '#10b981'),
            $this->dataset('Withdrawals', $this->series(DB::table('withdrawals'), $period), '#f43f5e'),
        ]);

        $e['charts'][] = $this->chart('overview-revenue', 'Revenue trend', 'area', $labels,
            [$this->dataset('Revenue (USD)', $this->summarySeries('revenue_usd', $period), '#d97706')], ['span' => 'half']);

        $kyc = User::groupBy('kyc_status')->selectRaw('kyc_status, count(*) as c')->pluck('c', 'kyc_status');
        $e['charts'][] = $this->chart('overview-kyc', 'KYC status mix', 'doughnut',
            ['Unverified', 'Pending', 'Approved', 'Rejected'],
            [$this->dataset('Users', [
                (int) ($kyc['none'] ?? 0), (int) ($kyc['pending'] ?? 0),
                (int) ($kyc['approved'] ?? 0), (int) ($kyc['rejected'] ?? 0),
            ])], ['span' => 'half']);

        $this->addAlerts($e, $walletUsd, $pendingKyc, $period);

        return $e;
    }

    private function addAlerts(array &$e, float $walletUsd, int $pendingKyc, Period $period): void
    {
        $treasury = (float) $this->ledger->usdTotal(self::TREASURY);
        if ($treasury < $walletUsd) {
            $e['alerts'][] = ['level' => 'critical', 'title' => 'Solvency alert',
                'message' => 'Treasury ('.$this->usdFmt($treasury).') is below customer balances ('.$this->usdFmt($walletUsd).').'];
        }

        if ($pendingKyc > 25) {
            $e['alerts'][] = ['level' => 'warn', 'title' => 'KYC backlog', 'message' => number_format($pendingKyc).' verifications pending.'];
        }

        $failedJobs = DB::table('failed_jobs')->count();
        if ($failedJobs > 0) {
            $e['alerts'][] = ['level' => 'warn', 'title' => 'Failed queue jobs', 'message' => number_format($failedJobs).' jobs in the failed queue.'];
        }

        $largeReview = Withdrawal::where('requires_review', true)->whereIn('status', ['pending', 'review'])->count();
        if ($largeReview > 0) {
            $e['alerts'][] = ['level' => 'info', 'title' => 'Withdrawals awaiting review', 'message' => number_format($largeReview).' flagged withdrawals need a decision.'];
        }
    }
}
