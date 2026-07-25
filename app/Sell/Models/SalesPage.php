<?php

declare(strict_types=1);

namespace App\Sell\Models;

use App\Sell\Enums\SalesPageStatus;
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

    protected $table = 'sell_sales_pages';

    protected $fillable = [
        'seller_id', 'product_id', 'name', 'slug', 'status',
        'sections', 'theme', 'seo', 'tracking', 'version', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SalesPageStatus::class,
            'sections' => 'array',
            'theme' => 'array',
            'seo' => 'array',
            'tracking' => 'array',
            'published_at' => 'datetime',
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

    public function domain(): HasOne
    {
        return $this->hasOne(Domain::class);
    }

    public function isPublished(): bool
    {
        return $this->status->isLive();
    }
}
