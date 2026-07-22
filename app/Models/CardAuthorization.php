<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CardAuthStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardAuthorization extends Model
{
    use HasUuids;

    protected $fillable = [
        'card_id', 'network_auth_id', 'amount', 'currency_code', 'mcc', 'merchant',
        'funding_asset_id', 'held_amount', 'quote_id', 'status',
        'hold_entry_id', 'settle_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => CardAuthStatus::class,
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function fundingAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'funding_asset_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(FxQuote::class, 'quote_id');
    }

    public function holdEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'hold_entry_id');
    }

    public function settleEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'settle_entry_id');
    }
}
