<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\P2pAdStatus;
use App\Enums\P2pAdType;
use App\Enums\P2pPriceType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A P2P advertisement (offer). `available_amount` is the remaining crypto (base
 * units) that can still be ordered; it is decremented under a row lock when an
 * order opens. Fiat price/limits are indicative (fiat settles off-platform).
 */
class P2pAd extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'p2p_ads';

    protected $fillable = [
        'user_id', 'side', 'asset_id', 'fiat_currency', 'price_type', 'fixed_price',
        'margin_bps', 'min_order', 'max_order', 'available_amount', 'total_amount',
        'daily_limit', 'payment_window_min', 'min_completion_bps', 'auto_reply',
        'terms', 'countries', 'trade_hours', 'status', 'priority',
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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function paymentMethods(): BelongsToMany
    {
        return $this->belongsToMany(
            P2pPaymentMethod::class,
            'p2p_ad_payment_methods',
            'ad_id',
            'payment_method_id',
        );
    }

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
