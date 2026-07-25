<?php

declare(strict_types=1);

namespace App\Sell\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A license key. Pooled per product and issued atomically on purchase. The key is
 * encrypted at rest via the `encrypted` cast (transparent on read).
 */
class License extends Model
{
    use HasUuids;

    protected $table = 'sell_licenses';

    protected $fillable = ['product_id', 'order_item_id', 'key_ciphertext', 'status', 'delivered_at'];

    protected function casts(): array
    {
        return [
            'key_ciphertext' => 'encrypted',
            'delivered_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** The decrypted key (only ever exposed to the verified buyer). */
    public function key(): string
    {
        return (string) $this->key_ciphertext;
    }
}
