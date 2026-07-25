<?php

declare(strict_types=1);

namespace App\Shop\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasUuids;

    protected $table = 'sell_product_variants';

    protected $fillable = [
        'product_id', 'sku', 'options', 'price_amount', 'stock',
        'weight_grams', 'position', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Unlimited stock when null (digital). */
    public function isUnlimited(): bool
    {
        return $this->stock === null;
    }
}
