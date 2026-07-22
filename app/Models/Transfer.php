<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransferKind;
use App\Enums\TransferStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    use HasUuids;

    protected $fillable = [
        'sender_id', 'recipient_id', 'recipient_handle', 'asset_id', 'amount',
        'kind', 'status', 'entry_id', 'idempotency_key', 'memo', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => TransferKind::class,
            'status' => TransferStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function money(): Money
    {
        return $this->asset->money($this->amount);
    }
}
