<?php

declare(strict_types=1);

namespace App\Shop\Models;

use App\Models\Asset;
use App\Models\User;
use App\Shop\Enums\SellerStatus;
use App\Utilities\Asset as AssetUtil;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A seller on the Sell platform — a core User approved to publish products.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $brand_name
 * @property string|null $logo_path
 * @property array<int, string>|null $categories
 * @property SellerStatus $status
 * @property int|null $commission_bps
 * @property int|null $settlement_asset_id
 * @property string|null $plan
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $approved_at
 * @property-read User|null $user
 * @property-read Asset|null $settlementAsset
 */
class Seller extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'shop_sellers';

    /** Seller plan tiers — each carries an admin-configurable commission rate. */
    public const PLANS = ['free', 'pro', 'business'];

    protected $fillable = [
        'user_id', 'brand_name', 'logo_path', 'bio', 'website', 'country',
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

    /**
     * Core module relation (Sell consumes Users, never owns them).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function settlementAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'settlement_asset_id');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<SalesPage, $this> */
    public function salesPages(): HasMany
    {
        return $this->hasMany(SalesPage::class);
    }

    /** @return HasMany<Domain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<SellerApplication, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(SellerApplication::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<Coupon, $this> */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * Effective commission in bps: an explicit per-seller override wins; otherwise
     * the admin-configured rate for this seller's plan, falling back to the legacy
     * platform default. All rates are admin-controllable under Settings › Sell.
     */
    public function commissionBps(): int
    {
        if ($this->commission_bps !== null) {
            return (int) $this->commission_bps;
        }

        $plan = in_array($this->plan, self::PLANS, true) ? $this->plan : 'free';
        $default = (int) getSetting('shop_commission_bps', 1000);

        return (int) getSetting("shop_commission_bps_{$plan}", $default);
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

    /** Public URL of the store logo, or null when none is set. */
    public function logoUrl(): ?string
    {
        return AssetUtil::url($this->logo_path);
    }
}
