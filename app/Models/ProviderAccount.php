<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A user's cardholder token at one provider program. */
class ProviderAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'card_provider_id', 'driver', 'provider_ref', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CardProvider::class, 'card_provider_id');
    }
}
