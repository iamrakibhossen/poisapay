<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\P2pOrderStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A P2P trade. `crypto_amount` (gross) is escrowed from the seller; on release
 * the buyer receives `net_amount` and `fee_amount` accrues to p2p:fee_income.
 * `status` is the authoritative state machine ({@see P2pOrderStatus}).
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

    public function ad(): BelongsTo
    {
        return $this->belongsTo(P2pAd::class, 'ad_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(P2pPaymentMethod::class, 'payment_method_id');
    }

    public function escrow(): HasOne
    {
        return $this->hasOne(P2pEscrow::class, 'order_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(P2pOrderMessage::class, 'order_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(P2pOrderEvent::class, 'order_id');
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(P2pDispute::class, 'order_id');
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
