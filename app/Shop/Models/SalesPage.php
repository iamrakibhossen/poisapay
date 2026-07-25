<?php

declare(strict_types=1);

namespace App\Shop\Models;

use App\Shop\Enums\SalesPageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property SalesPageStatus $status
 */
class SalesPage extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'shop_sales_pages';

    protected $fillable = [
        'seller_id', 'product_id', 'name', 'slug', 'status',
        'sections', 'draft', 'theme', 'seo', 'tracking', 'version', 'published_at',
        'bump_product_id', 'bump_price_amount', 'bump_headline', 'bump_description',
        'upsell_product_id', 'upsell_price_amount', 'upsell_headline', 'upsell_description',
    ];

    protected function casts(): array
    {
        return [
            'status' => SalesPageStatus::class,
            'sections' => 'array',
            'draft' => 'array',
            'theme' => 'array',
            'seo' => 'array',
            'tracking' => 'array',
            'published_at' => 'datetime',
            'bump_price_amount' => 'integer',
            'upsell_price_amount' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bumpProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bump_product_id');
    }

    public function upsellProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'upsell_product_id');
    }

    /** Effective bump price (minor units) — the override, else the product's price. */
    public function bumpAmount(): ?int
    {
        if (! $this->bumpProduct) {
            return null;
        }

        return $this->bump_price_amount ?? (int) $this->bumpProduct->price_amount;
    }

    /** Effective upsell price (minor units) — the override, else the product's price. */
    public function upsellAmount(): ?int
    {
        if (! $this->upsellProduct) {
            return null;
        }

        return $this->upsell_price_amount ?? (int) $this->upsellProduct->price_amount;
    }

    public function domain(): HasOne
    {
        return $this->hasOne(Domain::class);
    }

    public function isPublished(): bool
    {
        return $this->status->isLive();
    }

    public function revisions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PageRevision::class)->latest('version');
    }

    /** The working document shown in the builder (draft, falling back to the live doc). */
    public function draftDocument(): \App\Shop\Builder\BuilderDocument
    {
        return (new \App\Shop\Builder\SchemaMigrator())
            ->toDocument($this->draft ?? $this->sections, $this->theme ?? []);
    }

    /** The published document rendered to buyers. */
    public function publishedDocument(): \App\Shop\Builder\BuilderDocument
    {
        return (new \App\Shop\Builder\SchemaMigrator())
            ->toDocument($this->sections, $this->theme ?? []);
    }
}
