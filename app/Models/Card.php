<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CardNetwork;
use App\Enums\CardStatus;
use App\Enums\CardType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'card_provider_id', 'program', 'type', 'network', 'issuer_card_ref',
        'cardholder_ref', 'last4', 'exp_month', 'exp_year',
        'status', 'daily_limit', 'per_tx_limit', 'settlement_currency', 'frozen_by',
        'nickname', 'online_enabled', 'atm_enabled', 'contactless_enabled',
        'allowed_countries', 'blocked_mccs', 'pin_hash', 'replaced_by', 'closed_at',
    ];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return [
            'type' => CardType::class,
            'network' => CardNetwork::class,
            'status' => CardStatus::class,
            'exp_month' => 'integer',
            'exp_year' => 'integer',
            'online_enabled' => 'boolean',
            'atm_enabled' => 'boolean',
            'contactless_enabled' => 'boolean',
            'allowed_countries' => 'array',
            'blocked_mccs' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function hasPin(): bool
    {
        return ! is_null($this->pin_hash);
    }

    public function displayName(): string
    {
        return $this->nickname ?: ($this->type->label().' ····'.$this->last4);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function frozenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frozen_by');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CardProvider::class, 'card_provider_id');
    }

    public function authorizations(): HasMany
    {
        return $this->hasMany(CardAuthorization::class);
    }

    public function metadata(): HasMany
    {
        return $this->hasMany(CardMetadata::class);
    }
}
