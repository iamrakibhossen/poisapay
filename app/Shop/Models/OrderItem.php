<?php

declare(strict_types=1);

namespace App\Shop\Models;

use App\Shop\Enums\OrderItemKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property OrderItemKind $kind
 */
class OrderItem extends Model
{
    use HasUuids;

    protected $table = 'shop_order_items';

    protected $fillable = [
        'order_id', 'product_id', 'variant_id', 'kind', 'name_snapshot',
        'unit_amount', 'quantity', 'line_total_amount', 'commission_amount',
        'seller_net_amount', 'fulfilment_status',
    ];

    protected function casts(): array
    {
        return ['kind' => OrderItemKind::class];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** License keys issued for this line (License-type products). */
    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    /** Signed download grants issued for this line (digital products). */
    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }
}
