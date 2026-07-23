<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MerchantStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property MerchantStatus $status
 */
class Merchant extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'business_name', 'slug', 'category', 'website', 'support_email',
        'statement_descriptor', 'settlement_asset_id', 'fee_bps', 'status',
        'auto_settle', 'suspension_reason', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MerchantStatus::class,
            'auto_settle' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function settlementAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'settlement_asset_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(MerchantInvoice::class, 'merchant_id', 'user_id');
    }

    /** Effective processing fee in basis points (own override, else the platform default). */
    public function feeBps(): int
    {
        return $this->fee_bps ?? (int) getSetting('merchant_fee_bps', 100);
    }
}
