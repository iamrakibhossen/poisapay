<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\P2pOrderStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A P2P trade. `crypto_amount` (gross) is escrowed from the seller; on release
 * the buyer receives `net_amount` and `fee_amount` accrues to p2p:fee_income.
 * `status` is the authoritative state machine ({@see P2pOrderStatus}).
 *
 * @property string $id
 * @property string $ref
 * @property string $ad_id
 * @property string $buyer_id
 * @property string $seller_id
 * @property int $asset_id
 * @property string $crypto_amount
 * @property string $fee_amount
 * @property string $net_amount
 * @property int $taker_fee_bps
 * @property string $fiat_amount
 * @property string $price
 * @property string $fiat_currency
 * @property string|null $payment_method_id
 * @property P2pOrderStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $buyer_paid_at
 * @property Carbon|null $released_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property array<string, mixed>|null $meta
 * @property string|null $taker_ip
 * @property string|null $taker_fingerprint
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read P2pAd $ad
 * @property-read User $buyer
 * @property-read User $seller
 * @property-read Asset $asset
 * @property-read P2pPaymentMethod|null $paymentMethod
 * @property-read P2pEscrow|null $escrow
 * @property-read Collection<int, P2pOrderMessage> $messages
 * @property-read Collection<int, P2pOrderEvent> $events
 * @property-read P2pDispute|null $dispute
 * @property-read Collection<int, P2pReview> $reviews
 */
class P2pOrder extends Model
{
    use HasUuids;

    protected $table = 'p2p_orders';

    protected $fillable = [
        'ref', 'ad_id', 'buyer_id', 'seller_id', 'asset_id', 'crypto_amount',
        'fee_amount', 'net_amount', 'taker_fee_bps', 'fiat_amount', 'price',
        'fiat_currency', 'payment_method_id', 'status', 'expires_at',
        'buyer_paid_at', 'released_at', 'cancelled_at', 'cancel_reason', 'meta',
        'taker_ip', 'taker_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'status' => P2pOrderStatus::class,
            'expires_at' => 'datetime',
            'buyer_paid_at' => 'datetime',
            'released_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'fiat_amount' => 'decimal:2',
            'price' => 'decimal:4',
            'taker_fee_bps' => 'integer',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<P2pAd, $this> */
    public function ad(): BelongsTo
    {
        // withTrashed so an order still resolves its ad after the ad is retired.
        return $this->belongsTo(P2pAd::class, 'ad_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<P2pPaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(P2pPaymentMethod::class, 'payment_method_id');
    }

    /** @return HasOne<P2pEscrow, $this> */
    public function escrow(): HasOne
    {
        return $this->hasOne(P2pEscrow::class, 'order_id');
    }

    /** @return HasMany<P2pOrderMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(P2pOrderMessage::class, 'order_id');
    }

    /** @return HasMany<P2pOrderEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(P2pOrderEvent::class, 'order_id');
    }

    /** @return HasOne<P2pDispute, $this> */
    public function dispute(): HasOne
    {
        return $this->hasOne(P2pDispute::class, 'order_id');
    }

    /** @return HasMany<P2pReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(P2pReview::class, 'order_id');
    }

    /** The review this user left on this order, if any. */
    public function reviewBy(string $userId): ?P2pReview
    {
        return $this->reviews->firstWhere('rater_id', $userId);
    }

    public function cryptoMoney(): Money
    {
        return Money::ofBase($this->crypto_amount, $this->asset->decimals, $this->asset->symbol);
    }

    public function feeMoney(): Money
    {
        return Money::ofBase($this->fee_amount, $this->asset->decimals, $this->asset->symbol);
    }

    public function netMoney(): Money
    {
        return Money::ofBase($this->net_amount, $this->asset->decimals, $this->asset->symbol);
    }
}
