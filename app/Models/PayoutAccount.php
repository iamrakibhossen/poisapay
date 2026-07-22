<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's saved fiat payout destination (bank account or mobile-wallet number).
 * Users may keep multiple accounts per currency and reuse them at cash-out time.
 */
class PayoutAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'asset_id', 'withdrawal_method_id', 'label',
        'account_name', 'account_number', 'bank_name', 'is_favorite', 'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
            'last_used_at' => 'datetime',
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

    public function method(): BelongsTo
    {
        return $this->belongsTo(WithdrawalMethod::class, 'withdrawal_method_id');
    }

    /** A short human label for this account (bank •••1234 / bKash 017…). */
    public function displayLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }

        $provider = $this->method?->name ?? $this->bank_name ?? 'Account';

        return $provider.' •••'.substr($this->account_number, -4);
    }
}
