<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversionContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FxQuote extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'from_asset_id', 'to_asset_id', 'from_amount', 'to_amount',
        'rate', 'spread_bps', 'source', 'context', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'spread_bps' => 'integer',
            'context' => ConversionContext::class,
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'from_asset_id');
    }

    public function toAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'to_asset_id');
    }
}
