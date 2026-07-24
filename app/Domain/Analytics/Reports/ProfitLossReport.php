<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\LedgerAggregates;
use App\Domain\Analytics\Period;
use App\Domain\Analytics\Report;
use App\Enums\LedgerAccountType as T;

/**
 * Profit & Loss. Gross revenue (income accounts) less the costs we actually
 * record on the ledger (gas, card-program loss). Cost lines with no ledger source
 * — payroll, marketing, infra, support — are listed honestly as "not tracked"
 * rather than invented, so margins reflect real, reconciled figures.
 */
class ProfitLossReport extends Report
{
    private const INCOME = [T::FeeIncome, T::FeeCard, T::FxSpreadIncome, T::P2pFeeIncome];

    public function __construct(private readonly LedgerAggregates $ledger) {}

    public function key(): string
    {
        return 'pnl';
    }

    public function title(): string
    {
        return 'Profit & Loss';
    }

    public function build(Period $period): array
    {
        $e = $this->envelope();
        $prev = $period->previous();

        $revenue = (float) $this->ledger->usdTotal(self::INCOME, $period);
        $revenuePrev = (float) $this->ledger->usdTotal(self::INCOME, $prev);
        $gas = (float) $this->ledger->usdTotal([T::GasExpense], $period);
        $cardLoss = (float) $this->ledger->usdTotal([T::CardProgramLoss], $period);
        $cost = $gas + $cardLoss;
        $net = round($revenue - $cost, 2);
        $netPrev = round($revenuePrev - (float) $this->ledger->usdTotal([T::GasExpense], $prev) - (float) $this->ledger->usdTotal([T::CardProgramLoss], $prev), 2);

        $grossMargin = $revenue > 0 ? round((($revenue - $cost) / $revenue) * 100, 1) : 0.0;
        $netMargin = $revenue > 0 ? round(($net / $revenue) * 100, 1) : 0.0;

        $e['kpis'] = [
            $this->trendKpi('Gross Revenue', $this->usdFmt($revenue), $revenue, $revenuePrev, ['accent' => 'emerald', 'icon' => 'banknotes']),
            $this->kpi('Operating Cost', $this->usdFmt($cost), ['accent' => 'rose', 'icon' => 'arrow-trending-down']),
            $this->trendKpi('Net Profit', $this->usdFmt($net), $net, $netPrev, ['accent' => $net >= 0 ? 'emerald' : 'rose', 'icon' => 'scale']),
            $this->kpi('Gross Margin', $grossMargin.'%', ['accent' => 'brand']),
            $this->kpi('Net Margin', $netMargin.'%', ['accent' => $netMargin >= 0 ? 'brand' : 'rose']),
        ];

        // P&L waterfall-style breakdown table.
        $e['tables'][] = [
            'title' => 'P&L statement ('.$period->label.')',
            'headers' => ['Line item', 'Amount (USD)'],
            'align' => ['left', 'right'],
            'rows' => [
                ['Gross Revenue', $this->usdFmt($revenue)],
                ['— Transaction Fees', $this->usdFmt((float) $this->ledger->usdTotal([T::FeeIncome], $period))],
                ['— Exchange Spread', $this->usdFmt((float) $this->ledger->usdTotal([T::FxSpreadIncome], $period))],
                ['— Card Fees', $this->usdFmt((float) $this->ledger->usdTotal([T::FeeCard], $period))],
                ['— P2P / Merchant Fees', $this->usdFmt((float) $this->ledger->usdTotal([T::P2pFeeIncome], $period))],
                ['Gas / Network Cost', '('.$this->usdFmt($gas).')'],
                ['Card Program Loss', '('.$this->usdFmt($cardLoss).')'],
                ['Net Profit', $this->usdFmt($net)],
            ],
        ];

        // Profit trend from the summary table (revenue − gas).
        $rev = $this->summarySeries('revenue_usd', $period);
        $gasSeries = $this->summarySeries('gas_expense_usd', $period);
        $profit = array_map(fn ($r, $g) => round($r - $g, 2), $rev, $gasSeries);
        $e['charts'][] = $this->chart('pnl-trend', 'Profit trend (revenue − gas)', 'bar',
            $this->bucketLabels($period), [
                $this->dataset('Revenue', $rev, '#10b981'),
                $this->dataset('Net profit', $profit, '#d97706'),
            ]);

        $e['notes'][] = 'Not tracked yet (no ledger/data source): infrastructure, card-provider, payroll, marketing, affiliate, support, compliance and partner costs, plus interest income. Add these as ledger expense accounts or admin-entered figures to complete the P&L.';

        return $e;
    }
}
