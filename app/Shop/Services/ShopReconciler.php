<?php

declare(strict_types=1);

namespace App\Shop\Services;

use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Shop\Enums\OrderStatus;
use App\Shop\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Integrity harness for Shop money: it re-derives the truth from the ledger and
 * asserts it matches what the order rows claim. Every discrepancy is a real
 * problem — the ledger and the denormalized `orders.*` columns must never drift.
 *
 * Checks:
 *   - every captured order carries a ledger entry (no silent free goods);
 *   - no `shop.purchase` entry points at a missing order (no orphans);
 *   - per asset, ledger GMV == Σ orders.total_amount (captured orders);
 *   - per asset, ledger refunds == Σ orders.refunded_amount;
 *   - platform commission never goes negative (would mean we refunded more
 *     commission than we ever earned);
 *   - negative seller balances are surfaced (a seller who spent released
 *     earnings then got refunded now owes money back — expected, but visible).
 */
class ShopReconciler
{
    public function __construct(private readonly ShopRevenueService $revenue) {}

    /**
     * @return array{issues: array<int, array{code: string, severity: string, subject: string, detail: string}>, stats: array{orders: int, assets: int}}
     */
    public function run(): array
    {
        $issues = [];

        $captured = array_map(fn (OrderStatus $s) => $s->value, array_filter(OrderStatus::cases(), fn (OrderStatus $s) => $s->isPaid()));

        // 1) Captured orders must reference a ledger entry.
        Order::query()
            ->whereIn('status', $captured)
            ->whereNull('ledger_entry_id')
            ->select(['id', 'number'])
            ->chunkById(500, function ($orders) use (&$issues) {
                foreach ($orders as $order) {
                    $issues[] = $this->issue('missing_ledger_entry', 'critical', "order {$order->number}", 'captured order has no ledger entry');
                }
            }, 'id');

        // 2) No purchase entry may point at a missing order.
        $orphans = DB::table('journal_entries as e')
            ->leftJoin('shop_orders as o', 'o.id', '=', DB::raw("(e.metadata->>'order_id')::uuid"))
            ->where('e.type', 'shop.purchase')
            ->whereNull('o.id')
            ->pluck('e.id');
        foreach ($orphans as $entryId) {
            $issues[] = $this->issue('orphan_purchase_entry', 'critical', "entry {$entryId}", 'shop.purchase entry has no matching order');
        }

        // 3–5) Per-asset ledger vs order-column reconciliation.
        $assetIds = Order::query()->whereIn('status', $captured)->distinct()->pluck('asset_id')->all();
        foreach (Asset::whereIn('id', $assetIds)->get() as $asset) {
            $ledgerGmv = $this->revenue->gmv($asset)->baseString();
            $orderGmv = (string) (Order::where('asset_id', $asset->id)->whereIn('status', $captured)->sum('total_amount') ?: '0');
            if ($ledgerGmv !== $orderGmv) {
                $issues[] = $this->issue('gmv_mismatch', 'critical', $asset->symbol, "ledger GMV {$ledgerGmv} != Σ orders.total_amount {$orderGmv}");
            }

            $ledgerRefunded = $this->revenue->refunded($asset)->baseString();
            $orderRefunded = (string) (Order::where('asset_id', $asset->id)->sum('refunded_amount') ?: '0');
            if ($ledgerRefunded !== $orderRefunded) {
                $issues[] = $this->issue('refund_mismatch', 'critical', $asset->symbol, "ledger refunds {$ledgerRefunded} != Σ orders.refunded_amount {$orderRefunded}");
            }

            if ($this->revenue->commission($asset)->base->isNegative()) {
                $issues[] = $this->issue('commission_insolvent', 'critical', $asset->symbol, 'shop commission balance is negative');
            }
        }

        // 6) Negative seller balances (informational — the seller owes it back).
        $negatives = DB::table('account_balances as b')
            ->join('ledger_accounts as a', 'a.id', '=', 'b.account_id')
            ->whereIn('a.type', [LedgerAccountType::UserAvailable->value, LedgerAccountType::UserLocked->value])
            ->whereIn('a.asset_id', $assetIds)
            ->where('b.balance', '<', 0)
            ->count();
        if ($negatives > 0) {
            $issues[] = $this->issue('negative_user_balance', 'warning', 'balances', "{$negatives} user balance(s) negative in a shop settlement asset");
        }

        return [
            'issues' => $issues,
            'stats' => ['orders' => Order::whereIn('status', $captured)->count(), 'assets' => count($assetIds)],
        ];
    }

    /** @return array{code: string, severity: string, subject: string, detail: string} */
    private function issue(string $code, string $severity, string $subject, string $detail): array
    {
        return compact('code', 'severity', 'subject', 'detail');
    }
}
