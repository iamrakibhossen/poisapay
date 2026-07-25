<?php

declare(strict_types=1);

namespace App\Shop\DTOs;

use App\Shop\Enums\OrderItemKind;

/** Validated checkout intent. `idempotencyKey` makes a re-submit a no-op. */
final readonly class CheckoutData
{
    /**
     * @param  array<string, mixed>|null  $shippingAddress  buyer delivery details (physical only)
     * @param  bool  $bump  accept the sales page's configured order bump (a second line)
     * @param  int|null  $overrideAmount  replace the main unit price (a 1-click upsell at a special price)
     * @param  string|null  $parentOrderId  the order a 1-click upsell followed (attribution)
     */
    public function __construct(
        public string $productId,
        public ?string $variantId,
        public int $quantity,
        public ?string $salesPageId,
        public ?string $funnelId,
        public string $idempotencyKey,
        public ?string $couponCode = null,
        public ?array $shippingAddress = null,
        public bool $bump = false,
        public OrderItemKind $kind = OrderItemKind::Main,
        public ?int $overrideAmount = null,
        public ?string $parentOrderId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            productId: (string) $input['product_id'],
            variantId: $input['variant_id'] ?? null,
            quantity: max(1, (int) ($input['quantity'] ?? 1)),
            salesPageId: $input['sales_page_id'] ?? null,
            funnelId: $input['funnel_id'] ?? null,
            idempotencyKey: (string) ($input['idempotency_key'] ?? (string) \Illuminate\Support\Str::uuid()),
            couponCode: isset($input['coupon_code']) && trim((string) $input['coupon_code']) !== ''
                ? trim((string) $input['coupon_code'])
                : null,
            shippingAddress: ! empty($input['shipping_address']) && is_array($input['shipping_address'])
                ? $input['shipping_address']
                : null,
            bump: (bool) ($input['bump'] ?? false),
            kind: $input['kind'] ?? OrderItemKind::Main,
            overrideAmount: isset($input['override_amount']) ? (int) $input['override_amount'] : null,
            parentOrderId: $input['parent_order_id'] ?? null,
        );
    }
}
