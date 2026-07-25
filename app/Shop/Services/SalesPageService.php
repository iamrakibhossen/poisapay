<?php

declare(strict_types=1);

namespace App\Shop\Services;

use App\Shop\Models\Product;
use App\Shop\Models\SalesPage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The public read path — cache-first. `publicView($slug)` assembles the whole
 * page (page config + product + seller) into one plain array and caches it in
 * Redis, so a cache hit serves the public sales page with **zero DB queries**
 * (TTFB target < 100ms).
 *
 * Invalidation is automatic (ShopServiceProvider wires the observers): a SalesPage
 * change forgets its own key; a Product change forgets the keys of all its pages.
 * Nothing clears caches by hand.
 */
class SalesPageService
{
    private const TTL = 3600; // 1h; forgotten on change, so effectively event-driven

    public static function cacheKey(string $slug): string
    {
        return "shop:page:{$slug}";
    }

    /**
     * The published page for public rendering, or null if missing/unpublished.
     *
     * @return array<string, mixed>|null
     */
    public function publicView(string $slug): ?array
    {
        return Cache::remember(self::cacheKey($slug), self::TTL, function () use ($slug): ?array {
            $page = SalesPage::query()
                ->where('slug', $slug)
                ->where('status', 'published')
                ->with(['product.seller', 'domain'])
                ->first();

            if (! $page || ! $page->product) {
                return null; // negative result is cached briefly too — protects against slug-scan floods
            }

            $product = $page->product;

            return [
                'slug' => $page->slug,
                'name' => $page->name,
                'sections' => $page->sections,
                'theme' => $page->theme,
                'seo' => $page->seo,
                'tracking' => $page->tracking,
                'version' => (int) $page->version,
                'domain' => $page->domain?->isVerified() ? $page->domain->host : null,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'summary' => $product->summary,
                    'type' => $product->type->value,
                    'price_amount' => (int) $product->price_amount,
                    'compare_price_amount' => $product->compare_price_amount !== null ? (int) $product->compare_price_amount : null,
                    'price_asset_id' => (int) $product->price_asset_id,
                ],
                'seller' => [
                    'name' => $product->seller->displayName(),
                ],
            ];
        });
    }

    /** A globally-unique, URL-safe slug for a new page (public URLs must not clash). */
    public function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'page';
        $slug = $base;
        $n = 1;

        while (SalesPage::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    public function forget(SalesPage $page): void
    {
        Cache::forget(self::cacheKey($page->slug));
    }

    /** A product changed → drop the cache of every sales page that renders it. */
    public function forgetForProduct(Product $product): void
    {
        $product->salesPages()->pluck('slug')->each(
            fn (string $slug) => Cache::forget(self::cacheKey($slug)),
        );
    }
}
