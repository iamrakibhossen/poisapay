<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Analytics\AnalyticsCache;
use App\Domain\Analytics\LedgerAggregates;
use App\Domain\Analytics\Period;
use App\Domain\Analytics\Trend;
use App\Enums\KycStatus;
use App\Enums\LedgerAccountType as T;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\KycProfile;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\AdminAttention;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use Throwable;

/**
 * Admin operational command center (DollarHub structure — controller + Blade).
 *
 * The action-first counterpart to the analytics dashboard: what needs a human
 * *now* (triage queues), the day's financial pulse, live money movement, and a
 * system-health traffic light. Expensive ledger figures are served from the
 * shared 1-hour analytics cache; triage counts from the 30s AdminAttention cache.
 */
class DashboardController extends Controller
{
    private const USER_FUNDS = [T::UserAvailable, T::UserLocked, T::UserCardHold, T::UserP2pEscrow, T::UserCollateralLocked];

    private const TREASURY = [T::TreasuryHot, T::TreasuryCold, T::TreasuryPending];

    private const INCOME = [T::FeeIncome, T::FeeCard, T::FxSpreadIncome, T::P2pFeeIncome];

    public function __construct(
        private readonly LedgerAggregates $ledger,
        private readonly AnalyticsCache $cache,
    ) {}

    public function index(): View
    {
        abort_unless(auth('admin')->user()->isOperator(), 403);

        $period = Period::resolve('last_30_days');
        $money = $this->cache->remember('dashboard.pulse', $period, fn () => $this->financials($period));

        return view('admin.dashboard', array_merge($money, [
            'attention' => AdminAttention::items(),
            'health' => $this->health(),
            'kycQueue' => KycProfile::with('user')->where('status', KycStatus::Pending->value)->latest()->limit(5)->get(),
            'reviewQueue' => Withdrawal::with('user', 'asset')->where('requires_review', true)
                ->whereIn('status', [WithdrawalStatus::Review->value, WithdrawalStatus::Pending->value])
                ->latest()->limit(6)->get(),
        ]));
    }

    /** Financial pulse KPIs + money-movement chart, all USD-valued from the ledger. */
    private function financials(Period $period): array
    {
        $prev = $period->previous();
        $series = $this->metricSeries($period);

        $custUsd = (float) $this->ledger->usdTotal(self::USER_FUNDS);
        $hot = (float) $this->ledger->usdTotal([T::TreasuryHot]);
        $cold = (float) $this->ledger->usdTotal([T::TreasuryCold]);
        $pending = (float) $this->ledger->usdTotal([T::TreasuryPending]);
        $reserve = $hot + $cold + $pending;
        $ratio = $custUsd > 0 ? round(($reserve / $custUsd) * 100, 1) : 100.0;

        $revenue = (float) $this->ledger->usdTotal(self::INCOME, $period);
        $revenuePrev = (float) $this->ledger->usdTotal(self::INCOME, $prev);
        $newUsers = User::whereBetween('created_at', [$period->start, $period->end])->count();
        $newUsersPrev = User::whereBetween('created_at', [$prev->start, $prev->end])->count();

        return [
            'pulse' => [
                $this->trendKpi('Revenue (30d)', $this->usd($revenue), $revenue, $revenuePrev, [
                    'accent' => 'emerald', 'icon' => 'banknotes', 'spark' => $series['revenue_usd'],
                ]),
                $this->kpi('Customer Balances', $this->usd($custUsd), ['accent' => 'brand', 'icon' => 'wallet', 'hint' => 'Total user liability']),
                $this->kpi('Treasury Reserve', $this->usd($reserve), [
                    'accent' => $ratio >= 100 ? 'sky' : 'rose', 'icon' => 'building-library', 'hint' => 'Reserve ratio '.$ratio.'%',
                ]),
                $this->trendKpi('New Users (30d)', number_format($newUsers), $newUsers, $newUsersPrev, [
                    'accent' => 'violet', 'icon' => 'user-plus', 'spark' => $series['new_users'],
                ]),
            ],
            'treasury' => ['hot' => $hot, 'cold' => $cold, 'pending' => $pending, 'reserve' => $reserve, 'liabilities' => $custUsd, 'ratio' => $ratio, 'solvent' => $reserve >= $custUsd],
            'flowChart' => [
                'id' => 'dash-flow',
                'title' => 'Money movement · last 30 days',
                'type' => 'line',
                'span' => 'full',
                'labels' => $series['labels'],
                'datasets' => [
                    ['label' => 'Deposits', 'data' => $series['deposit_count'], 'color' => '#10b981'],
                    ['label' => 'Withdrawals', 'data' => $series['withdrawal_count'], 'color' => '#f43f5e'],
                ],
            ],
        ];
    }

    /** Daily rollup metrics aligned to the period buckets (instant — summary table). */
    private function metricSeries(Period $period): array
    {
        $rows = DB::table('analytics_daily_metrics')
            ->whereBetween('day', [$period->start->toDateString(), $period->end->toDateString()])
            ->get()
            ->groupBy('metric')
            ->map(fn ($g) => $g->pluck('value', 'day')->all());

        $buckets = $period->buckets();
        $pick = fn (string $metric) => array_map(fn ($b) => (float) ($rows[$metric][$b['key']] ?? 0), $buckets);

        return [
            'labels' => array_map(fn ($b) => $b['label'], $buckets),
            'deposit_count' => $pick('deposit_count'),
            'withdrawal_count' => $pick('withdrawal_count'),
            'revenue_usd' => $pick('revenue_usd'),
            'new_users' => $pick('new_users'),
        ];
    }

    /** Lightweight system-health traffic light (db/redis/queue/failed jobs). */
    private function health(): array
    {
        $check = function (callable $fn): array {
            try {
                return $fn();
            } catch (Throwable $e) {
                return ['down', substr($e->getMessage(), 0, 40)];
            }
        };

        $failed = $check(fn () => [DB::table('failed_jobs')->count() > 0 ? 'warn' : 'ok', DB::table('failed_jobs')->count().' failed']);
        $pending = $check(fn () => [DB::table('jobs')->count() > 500 ? 'warn' : 'ok', DB::table('jobs')->count().' queued']);

        return [
            ['label' => 'Database', ...$this->pair($check(fn () => (DB::connection()->select('select 1') ? ['ok', 'connected'] : ['down', 'no response'])))],
            ['label' => 'Redis', ...$this->pair($check(fn () => [Redis::connection()->ping() ? 'ok' : 'down', 'reachable']))],
            ['label' => 'Queue', ...$this->pair($pending)],
            ['label' => 'Failed jobs', ...$this->pair($failed)],
        ];
    }

    /** @param array{0:string,1:string} $r */
    private function pair(array $r): array
    {
        return ['status' => $r[0], 'detail' => $r[1]];
    }

    private function kpi(string $label, string $value, array $opts = []): array
    {
        return array_merge(['label' => $label, 'value' => $value, 'accent' => 'brand', 'trend' => null, 'hint' => null, 'icon' => null], $opts);
    }

    private function trendKpi(string $label, string $value, float $cur, float $prev, array $opts = []): array
    {
        $t = Trend::compute($cur, $prev);

        return $this->kpi($label, $value, array_merge(['trend' => $t['label'], 'trendUp' => $t['direction'] === 'up', 'trendGood' => $t['good']], $opts));
    }

    private function usd(float $v): string
    {
        $abs = abs($v);

        return match (true) {
            $abs >= 1_000_000 => '$'.rtrim(rtrim(number_format($v / 1_000_000, 2), '0'), '.').'M',
            $abs >= 100_000 => '$'.rtrim(rtrim(number_format($v / 1_000, 1), '0'), '.').'K',
            default => '$'.number_format($v, 2),
        };
    }
}
