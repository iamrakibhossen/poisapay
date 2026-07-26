<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A merchant's configured payout account for a rail. Account details are stored
 * encrypted at rest (never exposed to a counterparty until an order is open).
 *
 * @property string $id
 * @property string $user_id
 * @property string $payment_method_id
 * @property string|null $label
 * @property array<string, mixed> $account
 * @property bool $is_active
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read P2pPaymentMethod $method
 */
class P2pUserPaymentMethod extends Model
{
    use HasUuids;

    protected $table = 'p2p_user_payment_methods';

    protected $fillable = [
        'user_id', 'payment_method_id', 'label', 'account', 'is_active', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'account' => 'encrypted:array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<P2pPaymentMethod, $this> */
    public function method(): BelongsTo
    {
        return $this->belongsTo(P2pPaymentMethod::class, 'payment_method_id');
    }
}
