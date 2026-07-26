<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\LedgerAggregates;
use App\Domain\Analytics\Period;
use App\Domain\Analytics\Report;
use App\Enums\LedgerAccountType as T;
use App\Shop\Enums\OrderStatus;
use Illuminate\Database\Query\Builder;

/**
 * Shop (marketplace) analytics. Platform revenue is the flow into
 * `shop:commission_income` (ledger-authoritative, nets refunds); GMV and merchant
 * payouts are the buyer/seller money volumes on captured orders, valued in USD.
 */
class ShopReport extends Report
{
    /** @var list<string> Order statuses whose money was captured. */
    private array $captured;

    public function __construct(private readonly LedgerAggregates $ledger)
    {
        $this->captured = array_map(
            fn (OrderStatus $s) => $s->value,
            array_filter(OrderStatus::cases(), fn (OrderStatus $s) => $s->isPaid()),
        );
    }

    public function key(): string
    {
        return 'shop';
    }

    public function title(): string
    {
        return 'Shop Analytics';
    }

    public function build(Period $period): array
    {
        $e = $this->envelope();
        $prev = $period->previous();

        // Platform revenue — net commission flow (refund clawbacks already netted).
        $commission = (float) $this->ledger->usdTotal([T::ShopCommissionIncome], $period);
        $commissionPrev = (float) $this->ledger->usdTotal([T::ShopCommissionIncome], $prev);
        $commissionAll = (float) $this->ledger->usdTotal([T::ShopCommissionIncome]);

        // GMV / merchant payouts — buyer-paid + seller-net volume on captured orders.
        $gmv = $this->ledger->volumeUsd('shop_orders', 'paid_at', $period, $this->capturedFilter(), 'total_amount');
        $gmvPrev = $this->ledger->volumeUsd('shop_orders', 'paid_at', $prev, $this->capturedFilter(), 'total_amount');
        $merchant = $this->ledger->volumeUsd('shop_orders', 'paid_at', $period, $this->capturedFilter(), 'seller_net_amount');
        $refunds = $this->ledger->volumeUsd('shop_orders', 'refunded_at', $period, null, 'refunded_amount');

        $orders = (int) $this->fresh('shop_orders')
            ->whereIn('status', $this->captured)
            ->whereBetween('paid_at', [$period->start, $period->end])
            ->count();
        $ordersPrev = (int) $this->fresh('shop_orders')
            ->whereIn('status', $this->captured)
            ->whereBetween('paid_at', [$prev->start, $prev->end])
            ->count();
        $aov = $orders > 0 ? $gmv / $orders : 0.0;

        $e['kpis'][] = $this->trendKpi('Commission ('.$period->label.')', $this->usdFmt($commission), $commission, $commissionPrev, ['accent' => 'brand', 'icon' => 'building-storefront']);
        $e['kpis'][] = $this->kpi('Commission (all-time)', $this->usdFmt($commissionAll), ['accent' => 'emerald']);
        $e['kpis'][] = $this->trendKpi('GMV ('.$period->label.')', $this->usdFmt($gmv), $gmv, $gmvPrev, ['accent' => 'sky', 'icon' => 'shopping-cart']);
        $e['kpis'][] = $this->trendKpi('Orders ('.$period->label.')', number_format($orders), (float) $orders, (float) $ordersPrev, ['accent' => 'violet']);
        $e['kpis'][] = $this->kpi('Avg order value', $this->usdFmt($aov), ['accent' => 'slate']);
        $e['kpis'][] = $this->kpi('Merchant payouts ('.$period->label.')', $this->usdFmt($merchant), ['accent' => 'slate']);
        $e['kpis'][] = $this->kpi('Refunds ('.$period->label.')', $this->usdFmt($refunds), ['accent' => 'rose']);

        // Orders per bucket (count is currency-agnostic, so safe to chart directly).
        $e['charts'][] = $this->chart('shop-orders', 'Orders', 'bar',
            $this->bucketLabels($period),
            [$this->dataset('Orders', $this->series($this->fresh('shop_orders')->whereIn('status', $this->captured), $period, 'paid_at'), '#6366f1')]);

        $byAsset = collect($this->ledger->usdByAsset([T::ShopCommissionIncome], $period));
        if ($byAsset->isNotEmpty()) {
            $e['charts'][] = $this->chart('shop-commission-by-asset', 'Commission by currency', 'bar',
                $byAsset->pluck('symbol')->all(), [$this->dataset('USD', $byAsset->pluck('usd')->all(), '#d97706')], ['span' => 'half']);
        }

        // Top products by units sold in the window.
        $top = $this->fresh('shop_order_items as i')
            ->join('shop_orders as o', 'o.id', '=', 'i.order_id')
            ->whereIn('o.status', $this->captured)
            ->whereBetween('o.paid_at', [$period->start, $period->end])
            ->groupBy('i.name_snapshot')
            ->selectRaw('i.name_snapshot as name, sum(i.quantity) as units, count(*) as lines')
            ->orderByDesc('units')
            ->limit(10)
            ->get();
        if ($top->isNotEmpty()) {
            $e['tables'][] = [
                'title' => 'Top products (by units)',
                'headers' => ['Product', 'Units', 'Orders'],
                'rows' => $top->map(fn ($r) => [$r->name, number_format((int) $r->units), number_format((int) $r->lines)])->all(),
                'align' => ['left', 'right', 'right'],
            ];
        }

        $e['notes'][] = 'GMV, merchant payouts and refunds are valued in USD at current rates; commission is the authoritative net ledger flow. Refunds are dated at full-refund time, so partial refunds inside the window may lag.';
        $e['notes'][] = 'Revenue by country and product category is not yet attributable — order postings are not tagged with those dimensions.';

        return $e;
    }

    private function capturedFilter(): callable
    {
        return fn (Builder $q) => $q->whereIn('status', $this->captured);
    }
}
