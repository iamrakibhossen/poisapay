<?php

declare(strict_types=1);

namespace App\Sell\Services;

use App\Sell\Models\Product;
use App\Sell\Models\Seller;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Str;

/**
 * Catalog reads + helpers. Search uses the generated `search_vector` (tsvector)
 * + GIN index for O(log n) full-text matching, and cursor pagination (never
 * OFFSET) so it stays fast at 10M products. Slugs are unique per seller among
 * live rows.
 */
class CatalogService
{
    /** A unique, URL-safe slug for this seller (ignores a product when editing). */
    public function uniqueSlug(Seller $seller, string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $n = 1;

        while ($this->slugTaken($seller, $slug, $ignoreId)) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    private function slugTaken(Seller $seller, string $slug, ?string $ignoreId): bool
    {
        return Product::query()
            ->where('seller_id', $seller->getKey())
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * Full-text + filtered product search for a seller, cursor-paginated.
     *
     * @param  array{status?: string, type?: string}  $filters
     */
    public function search(Seller $seller, ?string $query, array $filters = [], int $perPage = 20, ?string $cursor = null): CursorPaginator
    {
        return Product::query()
            ->where('seller_id', $seller->getKey())
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when(
                filled($query),
                fn ($q) => $q->whereRaw("search_vector @@ websearch_to_tsquery('simple', ?)", [$query]),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')                                   // stable tiebreak for the cursor
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }
}
