<?php

declare(strict_types=1);

namespace App\Shop\Models;

use App\Models\Asset;
use App\Models\User;
use App\Shop\Enums\OrderStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Sell order. Money fields are an immutable record of what the Ledger already
 * moved (`ledger_entry_id`) — never re-derived here. Buyers are core Users
 * (guest checkout is not allowed).
 *
 * @property OrderStatus $status
 */
class Order extends Model
{
    use HasUuids;

    protected $table = 'sell_orders';

    protected $fillable = [
        'number', 'seller_id', 'buyer_user_id', 'sales_page_id', 'parent_order_id', 'funnel_id',
        'coupon_id', 'status', 'subtotal_amount', 'discount_amount', 'tax_amount',
        'shipping_amount', 'total_amount', 'commission_amount', 'seller_net_amount',
        'asset_id', 'payment_method', 'ledger_entry_id', 'refund_window_ends_at',
        'earnings_held', 'earnings_released_at', 'refunded_at', 'refunded_amount',
        'shipping_address', 'utm', 'idempotency_key', 'paid_at',
        'last_message_at', 'seller_unread', 'buyer_unread',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'shipping_address' => 'array',
            'utm' => 'array',
            'paid_at' => 'datetime',
            'refund_window_ends_at' => 'datetime',
            'earnings_held' => 'boolean',
            'earnings_released_at' => 'datetime',
            'refunded_at' => 'datetime',
            'last_message_at' => 'datetime',
            'seller_unread' => 'boolean',
            'buyer_unread' => 'boolean',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** The buyer — a core User (no guest checkout). */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function salesPage(): BelongsTo
    {
        return $this->belongsTo(SalesPage::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->latest('created_at');
    }

    /** The shared buyer↔seller conversation for this order. */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest('created_at');
    }

    public function total(): Money
    {
        return $this->asset->money((string) $this->total_amount);
    }
}
