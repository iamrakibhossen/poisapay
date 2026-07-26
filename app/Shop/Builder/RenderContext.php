<?php

declare(strict_types=1);

namespace App\Shop\Builder;

use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Models\Product;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;
use App\Utilities\Asset as AssetUtil;
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
     * @param  list<array<string, mixed>>  $catalog  the store's other products (for the product-grid block)
     */
    public function __construct(
        public readonly string $slug,
        public readonly array $product,
        public readonly array $seller,
        public readonly array $offers = [],
        public readonly array $globals = [],
        public readonly bool $editing = false,
        public readonly string $buyFormId = 'buy',
        public readonly array $catalog = [],
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

        // The store's published catalogue (for the product-grid block). Each card
        // links to the product's published sales page, if it has one.
        $catalog = $seller ? self::catalogFor($seller) : [];

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
            catalog: $catalog,
        );
    }

    /**
     * The seller's published products as flat card view-models for the product-grid
     * block. Each links to its published sales page when one exists.
     *
     * @return list<array<string, mixed>>
     */
    private static function catalogFor(Seller $seller): array
    {
        return Product::query()
            ->where('seller_id', $seller->getKey())
            ->where('status', ProductStatus::Published)
            ->with(['priceAsset', 'salesPages' => fn ($q) => $q->where('status', SalesPageStatus::Published)])
            ->latest()
            ->limit(24)
            ->get()
            ->map(function (Product $p): array {
                $asset = $p->priceAsset;
                $page = $p->salesPages->first();

                return [
                    'name' => $p->name,
                    'summary' => $p->summary,
                    'image' => AssetUtil::url($p->image),
                    'type' => $p->type->value,
                    'price' => $asset ? $asset->money((string) $p->price_amount)->format(2) : null,
                    'comparePrice' => ($asset && $p->compare_price_amount) ? $asset->money((string) $p->compare_price_amount)->format(2) : null,
                    'url' => $page ? route('funnel.sales', ['slug' => $page->slug]) : null,
                ];
            })->all();
    }
}
