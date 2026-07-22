<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable audit row for one provider API call (outbound or inbound). */
class CardProviderLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'card_provider_id', 'driver', 'card_id', 'direction', 'operation', 'method',
        'endpoint', 'request', 'response', 'status_code', 'latency_ms', 'success', 'error',
    ];

    protected function casts(): array
    {
        return [
            'request' => 'array',
            'response' => 'array',
            'status_code' => 'integer',
            'latency_ms' => 'integer',
            'success' => 'boolean',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CardProvider::class, 'card_provider_id');
    }
}
