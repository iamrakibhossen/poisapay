<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\LedgerAggregates;
use App\Domain\Analytics\Period;
use App\Domain\Analytics\Report;
use App\Enums\LedgerAccountType as T;
use App\Enums\P2pOrderStatus;
use Illuminate\Database\Query\Builder;

/**
 * P2P marketplace analytics. Platform revenue is the flow into `p2p:fee_income`
 * (ledger-authoritative); traded volume + fees are measured on *settled* orders
 * (escrow released to the buyer), valued in USD at current rates.
 */
class P2pReport extends Report
{
    /** @var list<string> Statuses where escrow settled to the buyer. */
    private array $settled;

    public function __construct(private readonly LedgerAggregates $ledger)
    {
        $this->settled = [P2pOrderStatus::Completed->value, P2pOrderStatus::ForceReleased->value];
    }

    public function key(): string
    {
        return 'p2p';
    }

    public function title(): string
    {
        return 'P2P Analytics';
    }

    /**
     * @return array{kpis: list<array<string, mixed>>, charts: list<array<string, mixed>>, tables: list<array<string, mixed>>, alerts: list<array<string, mixed>>, notes: list<string>}
     */
    public function build(Period $period): array
    {
        $e = $this->envelope();
        $prev = $period->previous();

        // Platform revenue — net taker-fee flow into the ledger income account.
        $fees = (float) $this->ledger->usdTotal([T::P2pFeeIncome], $period);
        $feesPrev = (float) $this->ledger->usdTotal([T::P2pFeeIncome], $prev);
        $feesAll = (float) $this->ledger->usdTotal([T::P2pFeeIncome]);

        // Traded volume — gross crypto released on settled orders, in USD.
        $volume = $this->ledger->volumeUsd('p2p_orders', 'released_at', $period, $this->settledFilter(), 'crypto_amount');
        $volumePrev = $this->ledger->volumeUsd('p2p_orders', 'released_at', $prev, $this->settledFilter(), 'crypto_amount');

        $trades = (int) $this->fresh('p2p_orders')
            ->whereIn('status', $this->settled)
            ->whereBetween('released_at', [$period->start, $period->end])
            ->count();
        $tradesPrev = (int) $this->fresh('p2p_orders')
            ->whereIn('status', $this->settled)
            ->whereBetween('released_at', [$prev->start, $prev->end])
            ->count();
        $avg = $trades > 0 ? $volume / $trades : 0.0;

        // Opened + disputed in the window (dispute rate = trust signal).
        $opened = (int) $this->fresh('p2p_orders')->whereBetween('created_at', [$period->start, $period->end])->count();
        $disputed = (int) $this->fresh('p2p_orders')
            ->where('status', P2pOrderStatus::Disputed->value)
            ->whereBetween('created_at', [$period->start, $period->end])
            ->count();
        $openDisputes = (int) $this->fresh('p2p_orders')->where('status', P2pOrderStatus::Disputed->value)->count();

        $e['kpis'][] = $this->trendKpi('P2P fees ('.$period->label.')', $this->usdFmt($fees), $fees, $feesPrev, ['accent' => 'brand', 'icon' => 'user-group']);
        $e['kpis'][] = $this->kpi('P2P fees (all-time)', $this->usdFmt($feesAll), ['accent' => 'emerald']);
        $e['kpis'][] = $this->trendKpi('Volume ('.$period->label.')', $this->usdFmt($volume), $volume, $volumePrev, ['accent' => 'sky', 'icon' => 'arrows-right-left']);
        $e['kpis'][] = $this->trendKpi('Completed trades ('.$period->label.')', number_format($trades), (float) $trades, (float) $tradesPrev, ['accent' => 'violet']);
        $e['kpis'][] = $this->kpi('Avg trade size', $this->usdFmt($avg), ['accent' => 'slate']);
        $e['kpis'][] = $this->kpi('Orders opened ('.$period->label.')', number_format($opened), ['accent' => 'slate']);
        $e['kpis'][] = $this->kpi('Disputes ('.$period->label.')', number_format($disputed), ['accent' => $disputed > 0 ? 'rose' : 'slate']);

        if ($openDisputes > 0) {
            $e['alerts'][] = ['level' => 'warning', 'title' => 'Open disputes', 'message' => $openDisputes.' P2P order(s) awaiting operator resolution.'];
        }

        // Completed trades per bucket (count is currency-agnostic → safe to chart).
        $e['charts'][] = $this->chart('p2p-trades', 'Completed trades', 'bar',
            $this->bucketLabels($period),
            [$this->dataset('Trades', $this->series($this->fresh('p2p_orders')->whereIn('status', $this->settled), $period, 'released_at'), '#8b5cf6')]);

        $byAsset = collect($this->ledger->usdByAsset([T::P2pFeeIncome], $period));
        if ($byAsset->isNotEmpty()) {
            $e['charts'][] = $this->chart('p2p-fees-by-asset', 'Fees by currency', 'bar',
                $byAsset->pluck('symbol')->all(), [$this->dataset('USD', $byAsset->pluck('usd')->all(), '#2053dd')], ['span' => 'half']);
        }

        // Top merchants (advertisers) by completed trades in the window.
        $top = $this->fresh('p2p_orders as o')
            ->join('p2p_ads as a', 'a.id', '=', 'o.ad_id')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->whereIn('o.status', $this->settled)
            ->whereBetween('o.released_at', [$period->start, $period->end])
            ->groupBy('u.name')
            ->selectRaw('u.name as name, count(*) as trades')
            ->orderByDesc('trades')
            ->limit(10)
            ->get();
        if ($top->isNotEmpty()) {
            $e['tables'][] = [
                'title' => 'Top merchants (by completed trades)',
                'headers' => ['Merchant', 'Trades'],
                'rows' => $top->map(fn ($r) => [$r->name, number_format((int) $r->trades)])->all(),
                'align' => ['left', 'right'],
            ];
        }

        $e['notes'][] = 'Volume and average trade size are gross crypto released on settled orders (Completed + admin force-release), valued in USD at current rates. Fees are the authoritative net ledger flow into p2p:fee_income.';

        return $e;
    }

    private function settledFilter(): callable
    {
        return fn (Builder $q) => $q->whereIn('status', $this->settled);
    }
}
