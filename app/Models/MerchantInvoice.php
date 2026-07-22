<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantInvoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'merchant_id', 'asset_id', 'amount', 'fee_amount', 'reference', 'memo',
        'status', 'payer_id', 'entry_id', 'expires_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function money(): Money
    {
        return $this->asset->money($this->amount);
    }

    public function feeMoney(): Money
    {
        return $this->asset->money($this->fee_amount ?? '0');
    }

    /** What the merchant actually receives — gross minus the processing fee. */
    public function netMoney(): Money
    {
        return $this->money()->minus($this->feeMoney());
    }

    public function isPayable(): bool
    {
        return $this->status === 'pending' && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
