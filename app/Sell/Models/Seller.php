<?php

declare(strict_types=1);

namespace App\Sell\Models;

use App\Models\Asset;
use App\Models\User;
use App\Sell\Enums\SellerStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A seller on the Sell platform — a core User approved to publish products.
 *
 * @property SellerStatus $status
 */
class Seller extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'sell_sellers';

    protected $fillable = [
        'user_id', 'brand_name', 'bio', 'website', 'country',
        'categories', 'status', 'commission_bps', 'settlement_asset_id',
        'kyc_reference', 'plan', 'reviewed_by', 'reviewed_at', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SellerStatus::class,
            'categories' => 'array',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** Core module relation (Sell consumes Users, never owns them). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function settlementAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'settlement_asset_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function salesPages(): HasMany
    {
        return $this->hasMany(SalesPage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(SellerApplication::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** Effective commission in bps — own override, else the platform default. */
    public function commissionBps(): int
    {
        return $this->commission_bps ?? (int) getSetting('sell_commission_bps', 1000);
    }

    public function canSell(): bool
    {
        return $this->status->canSell();
    }

    /** Public store name — the seller's brand, else the core account name. */
    public function displayName(): string
    {
        return $this->brand_name ?: (string) $this->user?->name;
    }
}
