<?php

declare(strict_types=1);

namespace App\Shop\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A signed, expiring, count-limited grant to fetch one product file. */
class Download extends Model
{
    use HasUuids;

    protected $table = 'sell_downloads';

    protected $fillable = [
        'order_item_id', 'product_file_id', 'buyer_user_id', 'token',
        'max_downloads', 'download_count', 'expires_at', 'last_downloaded_at', 'revoked',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_downloaded_at' => 'datetime',
            'revoked' => 'boolean',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function productFile(): BelongsTo
    {
        return $this->belongsTo(ProductFile::class);
    }

    /** Still usable? (not revoked, not expired, under the count limit). */
    public function isUsable(): bool
    {
        return ! $this->revoked
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && $this->download_count < $this->max_downloads;
    }
}
