<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitPayout extends Model
{
    use HasUuids;

    protected $fillable = [
        'asset_id', 'amount', 'destination', 'network', 'destination_address',
        'status', 'tx_hash', 'gas_fee', 'completed_at', 'note', 'entry_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function money(): Money
    {
        return $this->asset->money($this->amount);
    }

    /** Badge colour for the payout status. */
    public function statusColor(): string
    {
        return match ($this->status) {
            'completed' => 'success',
            'recorded' => 'info',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    /** Short tx hash for display, e.g. 0xabcd…1234. */
    public function shortTxHash(): ?string
    {
        if (! $this->tx_hash) {
            return null;
        }

        return mb_strlen($this->tx_hash) > 14
            ? mb_substr($this->tx_hash, 0, 8).'…'.mb_substr($this->tx_hash, -4)
            : $this->tx_hash;
    }
}
