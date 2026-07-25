<?php

declare(strict_types=1);

namespace App\Shop\Actions\Order;

use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\Admin;
use App\Models\JournalEntry;
use App\Shop\Enums\OrderStatus;
use App\Shop\Events\OrderStatusChanged;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\Download;
use App\Shop\Models\License;
use App\Shop\Models\Order;
use App\Shop\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Refunds a paid order — the reverse of {@see PlaceOrder} — for a given amount
 * (full or partial). One balanced, idempotent ledger entry returns the amount to
 * the buyer and pulls it back proportionally:
 *   - the seller's share (net portion) — from their HELD balance if earnings are
 *     still on hold, otherwise from their spendable balance (which may go negative
 *     if released earnings were already spent; the seller then owes it back);
 *   - the platform's share (commission portion) — from Sell commission income.
 *
 * The split is pro-rata: commissionBack = amount × commission ÷ total. Cumulative
 * refunds are tracked on `orders.refunded_amount`; the order becomes Refunded once
 * fully repaid (then stock is restored + downloads/licences revoked), otherwise
 * PartiallyRefunded. Held earnings for a fully-refunded order never release.
 */
class RefundOrder
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * @param  int  $amount  minor units to refund to the buyer (clamped to what remains)
     * @param  Model|null  $actor  the User (seller) or Admin performing the refund; null = system
     * @param  string  $idempotencyKey  unique per refund transaction (e.g. per request or per order)
     */
    public function execute(Order $order, int $amount, ?Model $actor, string $reason, string $idempotencyKey): Order
    {
        if (! $order->status->isPaid()) {
            throw ShopException::notRefundable();
        }

        return DB::transaction(function () use ($order, $amount, $actor, $reason, $idempotencyKey): Order {
            /** @var Order $fresh */
            $fresh = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            // Self-idempotent: if this exact refund entry already posted, don't
            // re-apply the balance bump.
            if (JournalEntry::where('idempotency_key', $idempotencyKey)->exists()) {
                return $fresh;
            }

            $assetId = (int) $fresh->asset_id;
            $net = (int) $fresh->seller_net_amount;
            $commission = (int) $fresh->commission_amount;
            $total = $net + $commission; // == captured on the ledger at sale
            $already = (int) ($fresh->refunded_amount ?? 0);
            $remaining = max(0, $total - $already);

            $amount = min($amount, $remaining);
            if ($amount <= 0) {
                throw ShopException::notRefundable();
            }

            // Pro-rata split of this refund between platform commission and seller net.
            $commissionBack = $total > 0 ? intdiv($amount * $commission, $total) : 0;
            $sellerBack = $amount - $commissionBack;

            $resolver = $this->ledger->resolver();
            $buyerAccount = $resolver->forUser($fresh->buyer_user_id, LedgerAccountType::UserAvailable, $assetId);

            $stillHeld = $fresh->earnings_held && $fresh->earnings_released_at === null;
            $sellerAccount = $resolver->forUser(
                $order->seller->user,
                $stillHeld ? LedgerAccountType::UserLocked : LedgerAccountType::UserAvailable,
                $assetId,
            );
            $commissionAccount = $resolver->system(LedgerAccountType::ShopCommissionIncome, $assetId);

            $lines = [PostingLine::credit($buyerAccount->id, $assetId, (string) $amount)];
            if ($sellerBack > 0) {
                $lines[] = PostingLine::debit($sellerAccount->id, $assetId, (string) $sellerBack);
            }
            if ($commissionBack > 0) {
                $lines[] = PostingLine::debit($commissionAccount->id, $assetId, (string) $commissionBack);
            }

            $entry = $this->ledger->post(new EntryData(
                type: 'shop.refund',
                idempotencyKey: $idempotencyKey,
                lines: $lines,
                memo: "Refund order {$fresh->number}",
                metadata: ['order_id' => $fresh->getKey(), 'seller_id' => $fresh->seller_id, 'amount' => $amount, 'reason' => $reason],
            ));

            $refundedTotal = $already + $amount;
            $fully = $refundedTotal >= $total;
            $target = $fully ? OrderStatus::Refunded : OrderStatus::PartiallyRefunded;
            $from = $fresh->status;

            if ($target !== $from && ! $from->canTransitionTo($target)) {
                throw ShopException::notRefundable();
            }

            $fresh->update([
                'status' => $target,
                'refunded_amount' => $refundedTotal,
                'refunded_at' => $fully ? now() : $fresh->refunded_at,
            ]);

            // Only on a full refund do we claw back the goods.
            if ($fully) {
                $itemIds = $fresh->items()->pluck('id');
                foreach ($fresh->items as $item) {
                    if ($item->variant_id) {
                        ProductVariant::whereKey($item->variant_id)->whereNotNull('stock')->increment('stock', (int) $item->quantity);
                    }
                }
                Download::whereIn('order_item_id', $itemIds)->update(['revoked' => true]);
                License::whereIn('order_item_id', $itemIds)->update(['status' => 'revoked']);
            }

            $fresh->events()->create([
                'type' => $fully ? 'refunded' : 'partially_refunded',
                'actor_type' => $actor instanceof Admin ? 'admin' : ($actor ? 'seller' : 'system'),
                'actor_id' => $actor?->getKey() ?? $fresh->seller_id,
                'data' => ['reason' => $reason, 'amount' => $amount, 'ledger_entry_id' => $entry->id],
            ]);

            if ($target !== $from) {
                OrderStatusChanged::dispatch($fresh, $from, $target);
            }

            return $fresh->refresh();
        });
    }

    /** Convenience: refund everything that's still refundable on the order. */
    public function full(Order $order, ?Model $actor = null, string $reason = ''): Order
    {
        $remaining = (int) $order->seller_net_amount + (int) $order->commission_amount - (int) ($order->refunded_amount ?? 0);

        return $this->execute($order, $remaining, $actor, $reason, 'shop:refund:order:'.$order->getKey());
    }
}
