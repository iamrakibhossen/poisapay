<?php

declare(strict_types=1);

namespace App\Sell\Actions\Order;

use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\User;
use App\Sell\DTOs\CheckoutData;
use App\Sell\Enums\OrderItemKind;
use App\Sell\Enums\OrderStatus;
use App\Sell\Events\OrderPlaced;
use App\Sell\Exceptions\SellException;
use App\Sell\Models\Coupon;
use App\Sell\Models\Download;
use App\Sell\Models\Order;
use App\Sell\Models\Product;
use App\Sell\Models\ProductVariant;
use App\Sell\Services\CouponService;
use App\Sell\Services\PricingService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Sell money path. A buyer pays with their PoisaPay wallet; the Ledger (single
 * source of truth) moves the money in one balanced entry:
 *
 *   DR buyer user:available (total)
 *   CR sell:commission_income (platform cut)
 *   CR seller user:available (net)
 *
 * The Sell order stores only a *record* of that entry (ledger_entry_id) — it never
 * re-derives balances. Idempotent by the order's key (a replay returns the same
 * order, never a second charge); stock is decremented atomically (no oversell).
 */
class PlaceOrder
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PricingService $pricing,
        private readonly CouponService $coupons,
    ) {}

    public function execute(User $buyer, CheckoutData $data): Order
    {
        if (! feature('sell_enabled', false)) {
            throw SellException::disabled();
        }

        $product = Product::with('seller')->findOrFail($data->productId);
        if (! $product->status->isBuyable()) {
            throw SellException::notBuyable();
        }
        $seller = $product->seller;
        if ($seller->user_id === $buyer->getKey()) {
            throw SellException::cannotBuyOwn();
        }

        $variant = $data->variantId ? ProductVariant::whereKey($data->variantId)->firstOrFail() : null;
        $assetId = (int) $product->price_asset_id;
        $break = $this->pricing->line($product, $variant, $data->quantity, $seller);

        // Apply a discount code if supplied. Commission (and the seller's net) are
        // recomputed on the *discounted* total — the platform cut follows the money.
        $subtotal = $break['line_total'];
        $coupon = $data->couponCode !== null
            ? $this->coupons->validate($seller, $product, $data->couponCode, $subtotal, $buyer)
            : null;
        $discount = $coupon?->discountFor($subtotal) ?? 0;

        // Flat shipping fee for physical goods (from product attributes). Commission
        // is taken on the discounted product amount only; shipping passes to the seller.
        $shipping = $this->pricing->shippingFee($product);
        $productNet = $subtotal - $discount;
        $commission = intdiv($productNet * $seller->commissionBps(), 10_000);
        $charge = [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'product_total' => $productNet,
            'total' => $productNet + $shipping,
            'commission' => $commission,
            'net' => $productNet - $commission + $shipping,
            'coupon_id' => $coupon?->getKey(),
        ];

        return DB::transaction(function () use ($buyer, $data, $product, $seller, $variant, $assetId, $break, $charge): Order {
            // Idempotency — a replay returns the existing order, no second charge.
            $existing = Order::where('idempotency_key', $data->idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $resolver = $this->ledger->resolver();
            $buyerAccount = $resolver->forUser($buyer, LedgerAccountType::UserAvailable, $assetId);

            // Guard the buyer's balance under lock.
            $balanceRow = DB::table('account_balances')->where('account_id', $buyerAccount->id)->lockForUpdate()->first();
            $available = Money::ofBase($balanceRow->balance ?? '0', $product->priceAsset->decimals, $product->priceAsset->symbol);
            $total = Money::ofBase((string) $charge['total'], $product->priceAsset->decimals, $product->priceAsset->symbol);
            if ($available->isLessThan($total)) {
                throw SellException::insufficientBalance();
            }

            // Atomic stock decrement for a tracked variant (no oversell).
            if ($variant && $variant->stock !== null) {
                $ok = ProductVariant::whereKey($variant->id)->where('stock', '>=', $data->quantity)
                    ->decrement('stock', $data->quantity);
                if ($ok === 0) {
                    throw SellException::outOfStock();
                }
            }

            $order = Order::create([
                'number' => 'PH-'.strtoupper(Str::random(8)),
                'seller_id' => $seller->getKey(),
                'buyer_user_id' => $buyer->getKey(),
                'sales_page_id' => $data->salesPageId,
                'funnel_id' => $data->funnelId,
                'coupon_id' => $charge['coupon_id'],
                'status' => OrderStatus::Pending,
                'subtotal_amount' => $charge['subtotal'],
                'discount_amount' => $charge['discount'],
                'shipping_amount' => $charge['shipping'],
                'total_amount' => $charge['total'],
                'commission_amount' => $charge['commission'],
                'seller_net_amount' => $charge['net'],
                'asset_id' => $assetId,
                'payment_method' => 'poisapay',
                'idempotency_key' => $data->idempotencyKey,
                'shipping_address' => $product->requires_shipping ? $data->shippingAddress : null,
            ]);

            $order->items()->create([
                'product_id' => $product->getKey(),
                'variant_id' => $variant?->getKey(),
                'kind' => OrderItemKind::Main,
                'name_snapshot' => $product->name,
                'unit_amount' => $break['unit'],
                'quantity' => $data->quantity,
                'line_total_amount' => $charge['product_total'],
                'commission_amount' => $charge['commission'],
                'seller_net_amount' => $charge['product_total'] - $charge['commission'],
            ]);

            if ($charge['coupon_id'] !== null) {
                Coupon::whereKey($charge['coupon_id'])->increment('used_count');
            }

            // The Ledger moves the money — one balanced, idempotent entry.
            $sellerAccount = $resolver->forUser($seller->user, LedgerAccountType::UserAvailable, $assetId);
            $commissionAccount = $resolver->system(LedgerAccountType::SellCommissionIncome, $assetId);

            $lines = [
                PostingLine::debit($buyerAccount->id, $assetId, (string) $charge['total']),
                PostingLine::credit($sellerAccount->id, $assetId, (string) $charge['net']),
            ];
            if ($charge['commission'] > 0) {
                $lines[] = PostingLine::credit($commissionAccount->id, $assetId, (string) $charge['commission']);
            }

            $entry = $this->ledger->post(new EntryData(
                type: 'sell.purchase',
                idempotencyKey: 'sell:order:'.$order->getKey(),
                lines: $lines,
                memo: "Order {$order->number}: {$product->name}",
                metadata: ['order_id' => $order->getKey(), 'seller_id' => $seller->getKey()],
            ));

            $order->update([
                'status' => OrderStatus::Paid,
                'ledger_entry_id' => $entry->id,
                'paid_at' => now(),
                'refund_window_ends_at' => now()->addDays((int) getSetting('sell_refund_window_days', 14)),
            ]);

            $order->events()->create([
                'type' => 'paid',
                'actor_type' => 'buyer',
                'actor_id' => $buyer->getKey(),
                'data' => ['ledger_entry_id' => $entry->id],
            ]);

            $this->grantDigitalDelivery($order, $product, $buyer);

            OrderPlaced::dispatch($order->refresh());

            return $order;
        });
    }

    /** Issue signed, count-limited download grants for a digital product's files. */
    private function grantDigitalDelivery(Order $order, Product $product, User $buyer): void
    {
        if (! $product->type->isDigitalDelivery()) {
            return;
        }

        $item = $order->items()->first();
        foreach ($product->files()->where('is_current', true)->get() as $file) {
            Download::create([
                'order_item_id' => $item->getKey(),
                'product_file_id' => $file->getKey(),
                'buyer_user_id' => $buyer->getKey(),
                'token' => Str::random(48),
                'max_downloads' => (int) getSetting('sell_download_limit', 5),
                'expires_at' => now()->addDays((int) getSetting('sell_download_ttl_days', 30)),
            ]);
        }
    }

}
