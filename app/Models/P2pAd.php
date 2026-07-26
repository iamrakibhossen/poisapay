<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\P2pAdStatus;
use App\Enums\P2pAdType;
use App\Enums\P2pPriceType;
use App\Support\Money;
use Database\Factories\P2pAdFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A P2P advertisement (offer). `available_amount` is the remaining crypto (base
 * units) that can still be ordered; it is decremented under a row lock when an
 * order opens. Fiat price/limits are indicative (fiat settles off-platform).
 *
 * @property string $id
 * @property string $user_id
 * @property P2pAdType $side
 * @property int $asset_id
 * @property string $fiat_currency
 * @property P2pPriceType $price_type
 * @property string|null $fixed_price
 * @property int|null $margin_bps
 * @property string $min_order
 * @property string $max_order
 * @property string $available_amount
 * @property string $total_amount
 * @property string|null $daily_limit
 * @property int $payment_window_min
 * @property int|null $min_completion_bps
 * @property string|null $auto_reply
 * @property string|null $terms
 * @property array<int, string>|null $countries
 * @property array<array-key, mixed>|null $trade_hours
 * @property P2pAdStatus $status
 * @property int $priority
 * @property bool $is_express
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Asset $asset
 * @property-read Collection<int, P2pPaymentMethod> $paymentMethods
 * @property-read Collection<int, P2pOrder> $orders
 */
class P2pAd extends Model
{
    /** @use HasFactory<P2pAdFactory> */
    use HasFactory;

    use HasUuids, SoftDeletes;

    protected $table = 'p2p_ads';

    protected $fillable = [
        'user_id', 'side', 'asset_id', 'fiat_currency', 'price_type', 'fixed_price',
        'margin_bps', 'min_order', 'max_order', 'available_amount', 'total_amount',
        'daily_limit', 'payment_window_min', 'min_completion_bps', 'auto_reply',
        'terms', 'countries', 'trade_hours', 'status', 'priority', 'is_express',
    ];

    protected function casts(): array
    {
        return [
            'side' => P2pAdType::class,
            'price_type' => P2pPriceType::class,
            'status' => P2pAdStatus::class,
            'countries' => 'array',
            'trade_hours' => 'array',
            'fixed_price' => 'decimal:4',
            'min_order' => 'decimal:2',
            'max_order' => 'decimal:2',
            'margin_bps' => 'integer',
            'priority' => 'integer',
            'payment_window_min' => 'integer',
            'is_express' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsToMany<P2pPaymentMethod, $this> */
    public function paymentMethods(): BelongsToMany
    {
        return $this->belongsToMany(
            P2pPaymentMethod::class,
            'p2p_ad_payment_methods',
            'ad_id',
            'payment_method_id',
        );
    }

    /** @return HasMany<P2pOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(P2pOrder::class, 'ad_id');
    }

    /** Remaining crypto still orderable on this ad. */
    public function availableMoney(): Money
    {
        return Money::ofBase($this->available_amount, $this->asset->decimals, $this->asset->symbol);
    }
}
