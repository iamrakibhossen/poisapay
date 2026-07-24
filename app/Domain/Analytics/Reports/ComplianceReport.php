<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\Period;
use App\Domain\Analytics\Report;
use App\Models\AmlAlert;
use App\Models\ComplianceCase;
use App\Models\KycProfile;
use App\Models\ScreeningResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Compliance analytics: KYC funnel, AML alert load, sanctions hits and case risk.
 * KYC/blocked figures are portfolio state; alert/screening figures respect the
 * window.
 */
class ComplianceReport extends Report
{
    public function key(): string
    {
        return 'compliance';
    }

    public function title(): string
    {
        return 'Compliance Analytics';
    }

    public function build(Period $period): array
    {
        $e = $this->envelope();

        $kyc = User::groupBy('kyc_status')->selectRaw('kyc_status, count(*) as c')->pluck('c', 'kyc_status');
        $verified = (int) ($kyc['approved'] ?? 0);
        $pending = (int) ($kyc['pending'] ?? 0);
        $rejected = (int) ($kyc['rejected'] ?? 0);
        $none = (int) ($kyc['none'] ?? 0);

        $openAlerts = AmlAlert::where('status', 'open')->count();
        $windowAlerts = AmlAlert::whereBetween('created_at', [$period->start, $period->end])->count();
        $escalated = AmlAlert::where('status', 'escalated')->count();
        $sanctionHits = ScreeningResult::where('result', 'hit')->whereBetween('created_at', [$period->start, $period->end])->count();
        $highRisk = ComplianceCase::whereIn('risk_level', ['high', 'critical'])->where('status', '!=', 'closed')->count();
        $blocked = User::where('is_frozen', true)->count();

        $procSeconds = (float) (KycProfile::whereNotNull('reviewed_at')
            ->whereBetween('reviewed_at', [$period->start, $period->end])
            ->selectRaw('avg(extract(epoch from (reviewed_at - created_at))) as s')->value('s') ?? 0);

        $e['kpis'] = [
            $this->kpi('Verified Users', number_format($verified), ['accent' => 'emerald', 'icon' => 'check-badge']),
            $this->kpi('Pending KYC', number_format($pending), ['accent' => 'amber', 'icon' => 'identification']),
            $this->kpi('Rejected KYC', number_format($rejected), ['accent' => 'rose']),
            $this->kpi('Open AML Alerts', number_format($openAlerts), ['accent' => 'rose', 'icon' => 'shield-exclamation']),
            $this->kpi('Escalated', number_format($escalated), ['accent' => 'rose']),
            $this->kpi('Sanction Hits', number_format($sanctionHits), ['accent' => 'rose', 'icon' => 'no-symbol']),
            $this->kpi('High-Risk Cases', number_format($highRisk), ['accent' => 'amber']),
            $this->kpi('Blocked Accounts', number_format($blocked), ['accent' => 'rose', 'icon' => 'lock-closed']),
            $this->kpi('Avg KYC Review Time', $this->humanDuration($procSeconds), ['accent' => 'sky']),
        ];

        $e['charts'][] = $this->chart('kyc-funnel', 'KYC funnel', 'bar',
            ['Unverified', 'Pending', 'Approved', 'Rejected'],
            [$this->dataset('Users', [$none, $pending, $verified, $rejected], '#d97706')], ['span' => 'half']);

        $bySeverity = AmlAlert::whereBetween('created_at', [$period->start, $period->end])
            ->groupBy('severity')->selectRaw('severity, count(*) as c')->pluck('c', 'severity');
        if ($bySeverity->isNotEmpty()) {
            $e['charts'][] = $this->chart('aml-severity', 'AML alerts by severity', 'doughnut',
                $bySeverity->keys()->map(fn ($s) => ucfirst((string) $s))->all(),
                [$this->dataset('Count', $bySeverity->values()->map(fn ($c) => (int) $c)->all())], ['span' => 'half']);
        }

        $e['charts'][] = $this->chart('aml-trend', 'AML alerts over time', 'area',
            $this->bucketLabels($period), [$this->dataset('Alerts', $this->series(DB::table('aml_alerts'), $period), '#f43f5e')]);

        if ($pending > 25) {
            $e['alerts'][] = ['level' => 'warn', 'title' => 'KYC backlog', 'message' => number_format($pending).' verifications are pending review.'];
        }
        if ($openAlerts > 0) {
            $e['alerts'][] = ['level' => 'info', 'title' => 'Open AML alerts', 'message' => number_format($openAlerts).' alerts awaiting disposition.'];
        }

        $e['notes'][] = 'Travel Rule request volume is not tracked — no data source is wired for it yet.';

        return $e;
    }

    private function humanDuration(float $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }

        return match (true) {
            $seconds < 3600 => round($seconds / 60).'m',
            $seconds < 86400 => round($seconds / 3600, 1).'h',
            default => round($seconds / 86400, 1).'d',
        };
    }
}
