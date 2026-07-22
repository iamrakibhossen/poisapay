<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** A received provider event; deduped and processed by a queued job. */
class CardWebhook extends Model
{
    use HasUuids;

    protected $fillable = [
        'driver', 'provider_event_id', 'event_type', 'provider_card_ref', 'provider_tx_ref',
        'payload', 'signature_valid', 'status', 'attempts', 'error', 'received_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'attempts' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
