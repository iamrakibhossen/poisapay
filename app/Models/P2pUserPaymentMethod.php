<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A merchant's configured payout account for a rail. Account details are stored
 * encrypted at rest (never exposed to a counterparty until an order is open).
 */
class P2pUserPaymentMethod extends Model
{
    use HasUuids;

    protected $table = 'p2p_user_payment_methods';

    protected $fillable = [
        'user_id', 'payment_method_id', 'label', 'account', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'account' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(P2pPaymentMethod::class, 'payment_method_id');
    }
}
