<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RiskLevel;
use App\Enums\WithdrawalStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property int $asset_id
 * @property string $to_address
 * @property string|null $payout_method
 * @property string $amount
 * @property string $fee
 * @property WithdrawalStatus $status
 * @property int $risk_score
 * @property RiskLevel|null $risk_level
 * @property string|null $onchain_tx_id
 * @property string|null $failure_reason
 * @property-read Asset $asset
 * @property-read OnchainTx|null $onchainTx
 */
class Withdrawal extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'asset_id', 'to_address', 'payout_method', 'payout_details', 'amount', 'fee', 'status', 'idempotency_key',
        'risk_score', 'risk_level', 'requires_review', 'lock_entry_id', 'settle_entry_id',
        'onchain_tx_id', 'approved_by', 'approved_at', 'completed_at', 'failure_reason', 'reserve_released_at',
        'broadcast_nonce', 'broadcast_block', 'broadcast_attempts',
    ];

    protected function casts(): array
    {
        return [
            'status' => WithdrawalStatus::class,
            'risk_level' => RiskLevel::class,
            'risk_score' => 'integer',
            'requires_review' => 'boolean',
            'payout_details' => 'encrypted:array', // PII (bank / mobile account) encrypted at rest
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'reserve_released_at' => 'datetime',
            'broadcast_nonce' => 'integer',
            'broadcast_block' => 'integer',
            'broadcast_attempts' => 'integer',
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

    /** @return BelongsTo<OnchainTx, $this> */
    public function onchainTx(): BelongsTo
    {
        return $this->belongsTo(OnchainTx::class, 'onchain_tx_id');
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
