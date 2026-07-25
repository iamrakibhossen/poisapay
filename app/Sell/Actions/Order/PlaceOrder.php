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
use App\Sell\Models\OrderItem;
use App\Sell\Models\Product;
use App\Sell\Models\ProductVariant;
use App\Sell\Models\SalesPage;
use App\Sell\Services\CouponService;
use App\Sell\Services\PricingService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Sell money path. A buyer pays with their PoisaPay wallet; the Ledger (single
 * source of truth) moves the money in one balanced entry:
 *
 *   DR buyer user:available (order total)
 *   CR sell:commission_income (platform cut, summed over all lines)
 *   CR seller user:available (net)
 *
 * An order may carry more than one line: the main product plus an optional
 * order bump (a second product added at checkout). Both settle in the SAME
 * balanced entry. The bump's product + price are resolved server-side from the
 * sales page — never trusted from the client. Idempotent by the order key;
 * variant stock decremented atomically (no oversell).
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
        $bps = $seller->commissionBps();

        // ── Main line ── (override price supports a 1-click upsell at a special price)
        $mainUnit = $data->overrideAmount ?? ($variant?->price_amount ?? (int) $product->price_amount);
        $mainSubtotal = $mainUnit * max(1, $data->quantity);

        // Coupon applies to the main product only (recomputes its commission share).
        $coupon = $data->couponCode !== null
            ? $this->coupons->validate($seller, $product, $data->couponCode, $mainSubtotal, $buyer)
            : null;
        $discount = $coupon?->discountFor($mainSubtotal) ?? 0;
        $mainProductTotal = $mainSubtotal - $discount;

        $lines = [[
            'product' => $product,
            'variant' => $variant,
            'kind' => $data->kind,
            'unit' => $mainUnit,
            'quantity' => $data->quantity,
            'subtotal' => $mainSubtotal,
            'product_total' => $mainProductTotal,
            'commission' => intdiv($mainProductTotal * $bps, 10_000),
            'name' => $variant
                ? $product->name.' ('.implode(' · ', array_values($variant->options ?? [])).')'
                : $product->name,
        ]];

        // ── Order bump ── (server-authoritative: product + price come from the page)
        if ($data->bump && $data->salesPageId) {
            $bumpLine = $this->bumpLine($data->salesPageId, $product, $assetId, $bps);
            if ($bumpLine) {
                $lines[] = $bumpLine;
            }
        }

        $shipping = $this->pricing->shippingFee($product); // main product only

        $subtotal = (int) array_sum(array_column($lines, 'subtotal'));
        $productTotal = (int) array_sum(array_column($lines, 'product_total'));
        $commissionTotal = (int) array_sum(array_column($lines, 'commission'));
        $charge = [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'total' => $productTotal + $shipping,
            'commission' => $commissionTotal,
            'net' => $productTotal - $commissionTotal + $shipping,
            'coupon_id' => $coupon?->getKey(),
        ];

        return DB::transaction(function () use ($buyer, $data, $product, $seller, $variant, $assetId, $lines, $charge): Order {
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

            // Atomic stock decrement for a tracked main variant (no oversell).
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
                'parent_order_id' => $data->parentOrderId,
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

            foreach ($lines as $line) {
                $item = $order->items()->create([
                    'product_id' => $line['product']->getKey(),
                    'variant_id' => $line['variant']?->getKey(),
                    'kind' => $line['kind'],
                    'name_snapshot' => $line['name'],
                    'unit_amount' => $line['unit'],
                    'quantity' => $line['quantity'],
                    'line_total_amount' => $line['product_total'],
                    'commission_amount' => $line['commission'],
                    'seller_net_amount' => $line['product_total'] - $line['commission'],
                ]);
                $this->grantDigitalDelivery($item, $line['product'], $buyer);
            }

            if ($charge['coupon_id'] !== null) {
                Coupon::whereKey($charge['coupon_id'])->increment('used_count');
            }

            // The Ledger moves the money — one balanced, idempotent entry.
            $sellerAccount = $resolver->forUser($seller->user, LedgerAccountType::UserAvailable, $assetId);
            $commissionAccount = $resolver->system(LedgerAccountType::SellCommissionIncome, $assetId);

            $ledgerLines = [
                PostingLine::debit($buyerAccount->id, $assetId, (string) $charge['total']),
                PostingLine::credit($sellerAccount->id, $assetId, (string) $charge['net']),
            ];
            if ($charge['commission'] > 0) {
                $ledgerLines[] = PostingLine::credit($commissionAccount->id, $assetId, (string) $charge['commission']);
            }

            $entry = $this->ledger->post(new EntryData(
                type: 'sell.purchase',
                idempotencyKey: 'sell:order:'.$order->getKey(),
                lines: $ledgerLines,
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

            OrderPlaced::dispatch($order->refresh());

            return $order;
        });
    }

    /**
     * Resolve the sales page's configured order bump into a line, or null if it
     * isn't a valid add-on (missing, unbuyable, different currency, or the same
     * product as the main item). Price is taken from the page, never the client.
     *
     * @return array<string, mixed>|null
     */
    private function bumpLine(string $salesPageId, Product $main, int $assetId, int $bps): ?array
    {
        $page = SalesPage::with('bumpProduct')->find($salesPageId);
        $bump = $page?->bumpProduct;

        if (! $bump
            || ! $bump->status->isBuyable()
            || (int) $bump->price_asset_id !== $assetId
            || $bump->getKey() === $main->getKey()) {
            return null;
        }

        $unit = (int) $page->bumpAmount();

        return [
            'product' => $bump,
            'variant' => null,
            'kind' => OrderItemKind::OrderBump,
            'unit' => $unit,
            'quantity' => 1,
            'subtotal' => $unit,
            'product_total' => $unit,
            'commission' => intdiv($unit * $bps, 10_000),
            'name' => $bump->name,
        ];
    }

    /** Issue signed, count-limited download grants for a digital line's files. */
    private function grantDigitalDelivery(OrderItem $item, Product $product, User $buyer): void
    {
        if (! $product->type->isDigitalDelivery()) {
            return;
        }

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
