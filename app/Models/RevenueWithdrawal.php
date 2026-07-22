<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RevenueWithdrawalStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RevenueWithdrawal extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'asset_id', 'amount', 'gas_fee', 'network', 'destination_address', 'note',
        'status', 'tx_hash', 'failure_reason', 'entry_id', 'reversal_entry_id',
        'idempotency_key', 'created_by', 'approved_by', 'approved_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RevenueWithdrawalStatus::class,
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function money(): Money
    {
        return $this->asset->money($this->amount);
    }

    public function gasMoney(): Money
    {
        return $this->asset->money($this->gas_fee ?? '0');
    }
}
