<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AlertStatus;
use App\Enums\CaseStatus;
use App\Enums\DepositStatus;
use App\Enums\KycStatus;
use App\Enums\P2pDisputeStatus;
use App\Enums\RevenueWithdrawalStatus;
use App\Enums\SupportTicketStatus;
use App\Enums\SweepStatus;
use App\Enums\WithdrawalStatus;
use App\Models\AmlAlert;
use App\Models\CardDispute;
use App\Models\ComplianceCase;
use App\Models\Deposit;
use App\Models\KycProfile;
use App\Models\P2pDispute;
use App\Models\RevenueWithdrawal;
use App\Models\SupportTicket;
use App\Models\Sweep;
use App\Models\WebhookLog;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * "Needs attention" counts driving the sidebar badges, the always-on topbar
 * indicator and the dashboard triage grid. Pure read-only aggregation over
 * existing models — no business logic. Cached briefly so it costs one lookup per
 * request burst, not one query per nav render.
 */
class AdminAttention
{
    private const CACHE_KEY = 'admin.attention.counts';

    private const TTL_SECONDS = 30;

    /**
     * Presentation metadata per queue: [label, route name, heroicon, severity].
     * Severity drives the red (critical) / amber (warn/info) marks. Single source
     * shared by the topbar indicator and the dashboard so they never disagree.
     *
     * @var array<string,array{0:string,1:string,2:string,3:string}>
     */
    private const META = [
        'withdrawals_review' => ['Withdrawals in review', 'admin.withdrawals', 'shield-exclamation', 'critical'],
        'compliance_open' => ['Compliance alerts', 'admin.compliance', 'exclamation-triangle', 'critical'],
        'webhooks_failed' => ['Failed webhooks', 'admin.webhook-logs', 'inbox-stack', 'critical'],
        'kyc_pending' => ['KYC to review', 'admin.kyc', 'identification', 'warn'],
        'deposits_pending' => ['Pending deposits', 'admin.deposits', 'arrow-down-tray', 'warn'],
        'sweeps_pending' => ['Sweeps pending', 'admin.sweeps', 'arrows-pointing-in', 'warn'],
        'card_disputes_open' => ['Card disputes', 'admin.card-disputes', 'scale', 'warn'],
        'p2p_disputes_open' => ['P2P disputes', 'admin.p2p-disputes', 'user-group', 'warn'],
        'sell_refunds_escalated' => ['Refunds to review', 'admin.sell-refunds', 'arrow-uturn-left', 'warn'],
        'settlements_pending' => ['Settlements pending', 'admin.settlements', 'check-badge', 'warn'],
        'support_open' => ['Open support tickets', 'admin.support', 'lifebuoy', 'info'],
    ];

    /** @return array<string, int> keyed counts referenced by AdminMenu `badge` keys */
    public static function counts(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL_SECONDS, fn (): array => [
            'deposits_pending' => Deposit::whereIn('status', [DepositStatus::Detected->value, DepositStatus::Confirming->value])->count(),
            'withdrawals_review' => Withdrawal::where('status', WithdrawalStatus::Review->value)->count(),
            'kyc_pending' => KycProfile::where('status', KycStatus::Pending->value)->count(),
            'compliance_open' => ComplianceCase::whereIn('status', [CaseStatus::Open->value, CaseStatus::Investigating->value])->count()
                + AmlAlert::whereIn('status', [AlertStatus::Open->value, AlertStatus::Escalated->value])->count(),
            'card_disputes_open' => CardDispute::whereIn('status', ['open', 'represented'])->count(),
            'p2p_disputes_open' => P2pDispute::whereIn('status', [P2pDisputeStatus::Open->value, P2pDisputeStatus::UnderReview->value])->count(),
            'sell_refunds_escalated' => \App\Sell\Models\RefundRequest::where('status', \App\Sell\Enums\RefundRequestStatus::Escalated->value)->count(),
            'sweeps_pending' => Sweep::where('status', SweepStatus::Pending->value)->count(),
            'support_open' => SupportTicket::where('status', SupportTicketStatus::Open->value)->count(),
            'webhooks_failed' => WebhookLog::where('status', '>=', 400)->where('resolved', false)->count(),
            'settlements_pending' => RevenueWithdrawal::where('status', RevenueWithdrawalStatus::Pending->value)->count(),
        ]);
    }

    /** Count for a single badge key (0 when unknown). */
    public static function count(string $key): int
    {
        return self::counts()[$key] ?? 0;
    }

    /**
     * Enriched, display-ready triage list — active queues first, then by severity
     * (critical → info), then by largest count. Rows whose route is missing are
     * still returned with `route => null` so callers can render them read-only.
     *
     * @return list<array{key:string,label:string,count:int,icon:string,severity:string,active:bool,route:?string}>
     */
    public static function items(): array
    {
        $counts = self::counts();
        $rank = ['critical' => 0, 'warn' => 1, 'info' => 2];

        $items = [];
        foreach (self::META as $key => [$label, $route, $icon, $severity]) {
            $count = $counts[$key] ?? 0;
            $items[] = [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'icon' => $icon,
                'severity' => $severity,
                'active' => $count > 0,
                'route' => Route::has($route) ? route($route) : null,
            ];
        }

        $sortKey = fn ($i) => [$i['active'] ? 0 : 1, $rank[$i['severity']], -$i['count']];
        usort($items, fn ($a, $b) => $sortKey($a) <=> $sortKey($b));

        return $items;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
