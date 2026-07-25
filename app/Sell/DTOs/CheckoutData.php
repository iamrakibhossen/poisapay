<?php

declare(strict_types=1);

namespace App\Sell\DTOs;

/** Validated checkout intent. `idempotencyKey` makes a re-submit a no-op. */
final readonly class CheckoutData
{
    public function __construct(
        public string $productId,
        public ?string $variantId,
        public int $quantity,
        public ?string $salesPageId,
        public ?string $funnelId,
        public string $idempotencyKey,
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
        );
    }
}
