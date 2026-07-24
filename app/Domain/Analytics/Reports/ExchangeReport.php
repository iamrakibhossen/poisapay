<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\LedgerAggregates;
use App\Domain\Analytics\Period;
use App\Domain\Analytics\Report;
use App\Enums\LedgerAccountType as T;
use App\Models\Conversion;
use Illuminate\Support\Facades\DB;

/**
 * Exchange / swap analytics: volume, spread captured, direction mix and average
 * rate/profit per swap over the window.
 */
class ExchangeReport extends Report
{
    public function __construct(private readonly LedgerAggregates $ledger) {}

    public function key(): string
    {
        return 'exchange';
    }

    public function title(): string
    {
        return 'Exchange Analytics';
    }

    public function build(Period $period): array
    {
        $e = $this->envelope();
        $prev = $period->previous();

        $swaps = Conversion::where('context', 'swap')->whereBetween('created_at', [$period->start, $period->end])->count();
        $swapsPrev = Conversion::where('context', 'swap')->whereBetween('created_at', [$prev->start, $prev->end])->count();
        $completed = Conversion::where('context', 'swap')->whereBetween('created_at', [$period->start, $period->end])->whereNotNull('completed_at')->count();
        $volume = (float) Conversion::whereBetween('created_at', [$period->start, $period->end])->sum('notional_usd');
        $volumePrev = (float) Conversion::whereBetween('created_at', [$prev->start, $prev->end])->sum('notional_usd');
        $spread = (float) $this->ledger->usdTotal([T::FxSpreadIncome], $period);
        $avgProfit = $swaps > 0 ? $spread / $swaps : 0.0;

        $pairs = DB::table('conversions as c')
            ->join('fx_quotes as q', 'q.id', '=', 'c.quote_id')
            ->join('assets as fa', 'fa.id', '=', 'q.from_asset_id')
            ->join('assets as ta', 'ta.id', '=', 'q.to_asset_id')
            ->where('c.context', 'swap')
            ->whereBetween('c.created_at', [$period->start, $period->end])
            ->groupBy('fa.symbol', 'ta.symbol')
            ->selectRaw("fa.symbol || ' → ' || ta.symbol as pair, count(*) as c, avg(q.rate) as rate")
            ->get();

        $avgRate = $pairs->isNotEmpty() ? round((float) $pairs->avg('rate'), 4) : 0.0;

        $e['kpis'] = [
            $this->trendKpi('Total Swaps', number_format($swaps), $swaps, $swapsPrev, ['accent' => 'brand', 'icon' => 'arrow-path-rounded-square', 'spark' => $this->summarySeries('swap_count', $period)]),
            $this->trendKpi('Exchange Volume', $this->usdFmt($volume), $volume, $volumePrev, ['accent' => 'emerald', 'icon' => 'banknotes', 'spark' => $this->summarySeries('swap_volume_usd', $period)]),
            $this->kpi('Spread Earned', $this->usdFmt($spread), ['accent' => 'emerald']),
            $this->kpi('Avg Profit / Swap', $this->usdFmt($avgProfit), ['accent' => 'sky']),
            $this->kpi('Avg Rate', $avgRate ? number_format($avgRate, 4) : '—', ['accent' => 'violet']),
            $this->kpi('Failed Swaps', number_format(max(0, $swaps - $completed)), ['accent' => 'rose']),
        ];

        $e['charts'][] = $this->chart('swap-count-trend', 'Swap count', 'area',
            $this->bucketLabels($period), [$this->dataset('Swaps', $this->series(DB::table('conversions')->where('context', 'swap'), $period), '#d97706')]);

        $e['charts'][] = $this->chart('swap-volume-trend', 'Swap volume (USD)', 'bar',
            $this->bucketLabels($period), [$this->dataset('Volume', $this->summarySeries('swap_volume_usd', $period), '#10b981')]);

        if ($pairs->isNotEmpty()) {
            $e['charts'][] = $this->chart('swap-direction', 'Swaps by direction', 'doughnut',
                $pairs->pluck('pair')->all(), [$this->dataset('Count', $pairs->pluck('c')->map(fn ($c) => (int) $c)->all())], ['span' => 'half']);

            $e['tables'][] = [
                'title' => 'Swap pairs',
                'headers' => ['Pair', 'Swaps', 'Avg rate'],
                'align' => ['left', 'right', 'right'],
                'rows' => $pairs->map(fn ($p) => [$p->pair, number_format((int) $p->c), round((float) $p->rate, 4)])->all(),
            ];
        }

        return $e;
    }
}
