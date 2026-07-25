<?php

declare(strict_types=1);

namespace App\Sell\Builder;

use App\Sell\Models\SalesPage;
use Illuminate\Support\Str;

/**
 * Everything a block needs beyond its own node to render: the live product/seller,
 * effective offers (bump/upsell), the resolved design tokens (globals), and the
 * render mode. Commerce blocks (buy button, product, order bump…) read their
 * dynamic data from here rather than from static props, so what the seller sees in
 * the editor is exactly what a buyer gets.
 */
final class RenderContext
{
    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $seller
     * @param  array<string, mixed>  $offers
     * @param  array<string, mixed>  $globals  resolved design tokens (colors/typography/buttons/…)
     */
    public function __construct(
        public readonly string $slug,
        public readonly array $product,
        public readonly array $seller,
        public readonly array $offers = [],
        public readonly array $globals = [],
        public readonly bool $editing = false,
        public readonly string $buyFormId = 'buy',
    ) {}

    /**
     * Resolve the dynamic view-model from a SalesPage. Prices are formatted with
     * the product's price asset; the seller identity comes from the store profile.
     *
     * @param  array<string, mixed>  $globals
     */
    public static function fromSalesPage(SalesPage $page, array $globals = [], bool $editing = false): self
    {
        $product = $page->product;
        $seller = $page->seller;
        $asset = $product?->priceAsset;

        $money = static fn (?int $minor): ?string => ($asset && $minor !== null)
            ? $asset->money($minor)->format(2)
            : null;

        $sellerName = $seller?->displayName() ?? '';

        return new self(
            slug: $page->slug,
            product: [
                'name' => $product?->name,
                'summary' => $product?->summary,
                'description' => $product?->description,
                'type' => $product?->type->value,
                'price' => $money($product?->price_amount !== null ? (int) $product->price_amount : null),
                'comparePrice' => $money($product?->compare_price_amount !== null ? (int) $product->compare_price_amount : null),
                'hasVariants' => (bool) ($product?->has_variants),
            ],
            seller: [
                'name' => $sellerName,
                'initials' => Str::of($sellerName)->explode(' ')->take(2)
                    ->map(fn ($w) => Str::substr($w, 0, 1))->implode(''),
                'logo' => $seller?->logoUrl(),
            ],
            offers: [
                'bump' => $page->bumpProduct ? [
                    'headline' => $page->bump_headline ?: __('Add this to your order'),
                    'description' => $page->bump_description,
                    'price' => $money($page->bumpAmount()),
                ] : null,
                'upsell' => $page->upsellProduct ? [
                    'headline' => $page->upsell_headline,
                    'price' => $money($page->upsellAmount()),
                ] : null,
            ],
            globals: $globals,
            editing: $editing,
        );
    }
}
