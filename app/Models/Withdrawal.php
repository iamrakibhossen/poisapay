<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RiskLevel;
use App\Enums\WithdrawalStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'asset_id', 'to_address', 'payout_method', 'payout_details', 'amount', 'fee', 'status', 'idempotency_key',
        'risk_score', 'risk_level', 'requires_review', 'lock_entry_id', 'settle_entry_id',
        'onchain_tx_id', 'approved_by', 'approved_at', 'completed_at', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => WithdrawalStatus::class,
            'risk_level' => RiskLevel::class,
            'risk_score' => 'integer',
            'requires_review' => 'boolean',
            'payout_details' => 'array',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** A cash payout to a bank account or mobile wallet (not an on-chain transfer). */
    public function isFiatPayout(): bool
    {
        return $this->payout_method !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function money(): Money
    {
        return $this->asset->money($this->amount);
    }

    public function feeMoney(): Money
    {
        return $this->asset->money($this->fee);
    }
}
