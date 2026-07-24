<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\LedgerAggregates;
use App\Domain\Analytics\Period;
use App\Domain\Analytics\Report;
use App\Enums\LedgerAccountType as T;
use App\Models\CardDispute;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;

/**
 * Loss analytics. Sums the losses we can actually source from the book:
 * chargebacks (lost card disputes), card-program loss postings, failed
 * settlements and any solvency deficit. Fraud/slippage/refund lines with no
 * discrete source are declared honestly rather than fabricated.
 */
class LossReport extends Report
{
    private const USER_FUNDS = [T::UserAvailable, T::UserLocked, T::UserCardHold, T::UserP2pEscrow, T::UserCollateralLocked];

    private const TREASURY = [T::TreasuryHot, T::TreasuryCold, T::TreasuryPending];

    public function __construct(private readonly LedgerAggregates $ledger) {}

    public function key(): string
    {
        return 'loss';
    }

    public function title(): string
    {
        return 'Loss Analytics';
    }

    public function build(Period $period): array
    {
        $e = $this->envelope();

        $chargebacks = (float) CardDispute::where('status', 'lost')->whereBetween('created_at', [$period->start, $period->end])->sum('amount') / 100;
        $chargebackCount = CardDispute::where('status', 'lost')->whereBetween('created_at', [$period->start, $period->end])->count();
        $cardLoss = (float) $this->ledger->usdTotal([T::CardProgramLoss], $period);
        $failedSettlements = Withdrawal::where('status', 'failed')->whereBetween('created_at', [$period->start, $period->end])->count();

        $deficit = max(0.0, (float) $this->ledger->usdTotal(self::USER_FUNDS) - (float) $this->ledger->usdTotal(self::TREASURY));
        $avgRisk = round((float) (Withdrawal::whereBetween('created_at', [$period->start, $period->end])->avg('risk_score') ?? 0), 1);

        $totalLoss = round($chargebacks + $cardLoss + $deficit, 2);

        $e['kpis'] = [
            $this->kpi('Total Loss', $this->usdFmt($totalLoss), ['accent' => 'rose', 'icon' => 'arrow-trending-down']),
            $this->kpi('Chargebacks', $this->usdFmt($chargebacks), ['accent' => 'rose', 'hint' => number_format($chargebackCount).' disputes lost']),
            $this->kpi('Card Program Loss', $this->usdFmt($cardLoss), ['accent' => 'amber']),
            $this->kpi('Failed Settlements', number_format($failedSettlements), ['accent' => 'amber']),
            $this->kpi('Ledger Deficit', $this->usdFmt($deficit), ['accent' => $deficit > 0 ? 'rose' : 'emerald']),
            $this->kpi('Avg Withdrawal Risk', $avgRisk.' / 100', ['accent' => $avgRisk >= 60 ? 'rose' : 'sky']),
        ];

        $pairs = array_filter([
            'Chargebacks' => $chargebacks,
            'Card Program Loss' => $cardLoss,
            'Ledger Deficit' => $deficit,
        ], fn ($v) => $v > 0);
        if ($pairs !== []) {
            $e['charts'][] = $this->chart('loss-by-category', 'Loss by category', 'doughnut',
                array_keys($pairs), [$this->dataset('USD', array_values($pairs))], ['span' => 'half']);
        }

        $e['charts'][] = $this->chart('chargeback-trend', 'Chargebacks over time', 'bar',
            $this->bucketLabels($period), [$this->dataset('Lost disputes', $this->series(DB::table('card_disputes')->where('status', 'lost'), $period), '#f43f5e')], ['span' => 'half']);

        if ($deficit > 0) {
            $e['alerts'][] = ['level' => 'critical', 'title' => 'Unexpected ledger deficit',
                'message' => 'User liabilities exceed treasury by '.$this->usdFmt($deficit).'.'];
        }

        $e['notes'][] = 'Not separately tracked: fraud loss, exchange slippage, refund loss, expired-transaction loss, operational and crypto/wallet loss. These need dedicated loss ledger accounts or tags to break out.';

        return $e;
    }
}
