<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Analytics\AnalyticsCache;
use App\Domain\Analytics\Period;
use App\Domain\Analytics\Report;
use App\Domain\Analytics\Reports\CardReport;
use App\Domain\Analytics\Reports\ComplianceReport;
use App\Domain\Analytics\Reports\DepositReport;
use App\Domain\Analytics\Reports\ExchangeReport;
use App\Domain\Analytics\Reports\LossReport;
use App\Domain\Analytics\Reports\OverviewReport;
use App\Domain\Analytics\Reports\ProfitLossReport;
use App\Domain\Analytics\Reports\RevenueReport;
use App\Domain\Analytics\Reports\TreasuryReport;
use App\Domain\Analytics\Reports\WalletReport;
use App\Domain\Analytics\Reports\WithdrawalReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Enterprise analytics dashboard (DollarHub structure — controller + Blade). Each
 * section is a cached {@see Report} rendered by one generic
 * template. Every request resolves a single {@see Period} so all widgets agree,
 * and every report is served from the 1-hour analytics cache — the expensive
 * ledger aggregation runs at most once per (section, window).
 */
class AnalyticsController extends Controller
{
    /** Section registry: key => [report class, heroicon, nav group]. */
    private const SECTIONS = [
        'overview' => [OverviewReport::class, 'squares-2x2', 'Overview'],
        'deposits' => [DepositReport::class, 'arrow-down-tray', 'Money Movement'],
        'withdrawals' => [WithdrawalReport::class, 'arrow-up-tray', 'Money Movement'],
        'exchange' => [ExchangeReport::class, 'arrow-path-rounded-square', 'Money Movement'],
        'revenue' => [RevenueReport::class, 'banknotes', 'Financials'],
        'pnl' => [ProfitLossReport::class, 'scale', 'Financials'],
        'loss' => [LossReport::class, 'arrow-trending-down', 'Financials'],
        'wallet' => [WalletReport::class, 'wallet', 'Balance Sheet'],
        'treasury' => [TreasuryReport::class, 'building-library', 'Balance Sheet'],
        'cards' => [CardReport::class, 'credit-card', 'Products'],
        'compliance' => [ComplianceReport::class, 'shield-check', 'Risk & Compliance'],
    ];

    public function __construct(private readonly AnalyticsCache $cache) {}

    public function index(Request $request): View
    {
        $this->guard();
        $period = $this->period($request);

        return view('admin.analytics.dashboard', $this->payload('overview', $period, $request));
    }

    public function section(Request $request, string $section): View
    {
        $this->guard();
        abort_unless(isset(self::SECTIONS[$section]) && $section !== 'overview', 404);
        $period = $this->period($request);

        return view('admin.analytics.dashboard', $this->payload($section, $period, $request));
    }

    public function export(Request $request, string $section = 'overview'): StreamedResponse
    {
        $this->guard();
        abort_unless(isset(self::SECTIONS[$section]), 404);
        $period = $this->period($request);
        $report = $this->build($section, $period);
        $title = app(self::SECTIONS[$section][0])->title();

        $filename = 'analytics-'.$section.'-'.$period->key.'.csv';

        return response()->streamDownload(function () use ($report, $title, $period) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [$title, $period->label]);
            fputcsv($out, []);
            fputcsv($out, ['Metric', 'Value']);
            foreach ($report['kpis'] as $kpi) {
                fputcsv($out, [$kpi['label'], $kpi['value'].($kpi['trend'] ? ' ('.$kpi['trend'].')' : '')]);
            }
            foreach ($report['tables'] as $table) {
                fputcsv($out, []);
                fputcsv($out, [$table['title']]);
                fputcsv($out, $table['headers']);
                foreach ($table['rows'] as $row) {
                    fputcsv($out, $row);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array<string,mixed> */
    private function payload(string $section, Period $period, Request $request): array
    {
        return [
            'section' => $section,
            'title' => app(self::SECTIONS[$section][0])->title(),
            'report' => $this->build($section, $period),
            'period' => $period,
            'presets' => Period::PRESETS,
            'compare' => $request->boolean('compare', true),
            'nav' => $this->nav($section, $request),
            'filters' => [
                'period' => $period->key,
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ],
        ];
    }

    /** @return array{kpis:array,charts:array,tables:array,alerts:array,notes:array} */
    private function build(string $section, Period $period): array
    {
        [$class] = self::SECTIONS[$section];

        return $this->cache->remember($section, $period, fn () => app($class)->build($period));
    }

    /** Sub-navigation across sections, grouped, with active state + preserved filters. */
    private function nav(string $active, Request $request): array
    {
        $query = array_filter([
            'period' => $request->query('period'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'compare' => $request->query('compare'),
        ]);

        $items = [];
        foreach (self::SECTIONS as $key => [$class, $icon, $group]) {
            $url = $key === 'overview' ? route('admin.analytics') : route('admin.analytics.section', $key);
            $items[$group][] = [
                'key' => $key,
                'title' => app($class)->title(),
                'icon' => $icon,
                'url' => $url.($query ? '?'.http_build_query($query) : ''),
                'active' => $key === $active,
            ];
        }

        return $items;
    }

    private function period(Request $request): Period
    {
        return Period::resolve($request->query('period'), $request->query('from'), $request->query('to'));
    }

    private function guard(): void
    {
        // Revenue/P&L is sensitive — gate on the reporting permission (super-admin holds all).
        $user = auth('admin')->user();
        abort_unless($user && ($user->can('view-reports') || $user->hasRole('super-admin')), 403);
    }
}
