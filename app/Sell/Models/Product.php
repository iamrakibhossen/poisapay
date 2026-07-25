<?php

declare(strict_types=1);

namespace App\Sell\Models;

use App\Models\Asset;
use App\Sell\Enums\ProductStatus;
use App\Sell\Enums\ProductType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ProductType $type
 * @property ProductStatus $status
 */
class Product extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'sell_products';

    protected $fillable = [
        'seller_id', 'type', 'name', 'slug', 'summary', 'description', 'status',
        'price_amount', 'price_asset_id', 'compare_price_amount', 'has_variants',
        'requires_shipping', 'attributes', 'meta', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'attributes' => 'array',
            'meta' => 'array',
            'has_variants' => 'boolean',
            'requires_shipping' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function priceAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'price_asset_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProductFile::class);
    }

    public function salesPages(): HasMany
    {
        return $this->hasMany(SalesPage::class);
    }

    public function price(): Money
    {
        return $this->priceAsset->money((string) $this->price_amount);
    }

    public function isFree(): bool
    {
        return (int) $this->price_amount <= 0;
    }
}
