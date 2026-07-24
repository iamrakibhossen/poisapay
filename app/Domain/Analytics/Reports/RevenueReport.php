<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\LedgerAggregates;
use App\Domain\Analytics\Period;
use App\Domain\Analytics\Report;
use App\Enums\LedgerAccountType as T;

/**
 * Revenue analytics. Revenue is the flow into the income ledger accounts within
 * the window (authoritative), broken down by fee type and asset. Comparison mode
 * diffs the window against the like-for-like previous window.
 */
class RevenueReport extends Report
{
    /** @var array<string,array{0:T,1:string}> label => [account type, accent] */
    private const STREAMS = [
        'Transaction Fees' => [T::FeeIncome, 'brand'],
        'Exchange Spread' => [T::FxSpreadIncome, 'emerald'],
        'Card Fees' => [T::FeeCard, 'sky'],
        'P2P / Merchant Fees' => [T::P2pFeeIncome, 'violet'],
    ];

    private const INCOME = [T::FeeIncome, T::FeeCard, T::FxSpreadIncome, T::P2pFeeIncome];

    public function __construct(private readonly LedgerAggregates $ledger) {}

    public function key(): string
    {
        return 'revenue';
    }

    public function title(): string
    {
        return 'Revenue Analytics';
    }

    public function build(Period $period): array
    {
        $e = $this->envelope();
        $prev = $period->previous();

        $current = (float) $this->ledger->usdTotal(self::INCOME, $period);
        $previous = (float) $this->ledger->usdTotal(self::INCOME, $prev);
        $allTime = (float) $this->ledger->usdTotal(self::INCOME);

        $e['kpis'][] = $this->trendKpi('Revenue ('.$period->label.')', $this->usdFmt($current), $current, $previous, ['accent' => 'brand', 'icon' => 'banknotes', 'spark' => $this->summarySeries('revenue_usd', $period)]);
        $e['kpis'][] = $this->kpi('Total Revenue (all-time)', $this->usdFmt($allTime), ['accent' => 'emerald']);

        // Per-stream KPIs + doughnut breakdown.
        $streamPairs = [];
        foreach (self::STREAMS as $label => [$type, $accent]) {
            $val = (float) $this->ledger->usdTotal([$type], $period);
            $prevVal = (float) $this->ledger->usdTotal([$type], $prev);
            $e['kpis'][] = $this->trendKpi($label, $this->usdFmt($val), $val, $prevVal, ['accent' => $accent]);
            if ($val != 0.0) {
                $streamPairs[$label] = $val;
            }
        }

        $e['charts'][] = $this->chart('revenue-trend', 'Revenue trend', 'area',
            $this->bucketLabels($period), [$this->dataset('Revenue (USD)', $this->summarySeries('revenue_usd', $period), '#d97706')]);

        if ($streamPairs !== []) {
            $e['charts'][] = $this->chart('revenue-by-type', 'Revenue by fee type', 'doughnut',
                array_keys($streamPairs), [$this->dataset('USD', array_values($streamPairs))], ['span' => 'half']);
        }

        $byAsset = collect($this->ledger->usdByAsset(self::INCOME, $period));
        if ($byAsset->isNotEmpty()) {
            $e['charts'][] = $this->chart('revenue-by-asset', 'Revenue by currency', 'bar',
                $byAsset->pluck('symbol')->all(), [$this->dataset('USD', $byAsset->pluck('usd')->all(), '#10b981')], ['span' => 'half']);
        }

        $e['notes'][] = 'Revenue by country, payment method and merchant is not yet attributable — fee ledger postings are not tagged with those dimensions.';

        return $e;
    }
}
