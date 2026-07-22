<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingPair extends Model
{
    use HasUuids;

    protected $fillable = [
        'from_asset_id', 'to_asset_id', 'spread_bps', 'min_amount', 'max_amount', 'is_active', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'spread_bps' => 'integer',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function fromAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'from_asset_id');
    }

    public function toAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'to_asset_id');
    }

    public function label(): string
    {
        return ($this->fromAsset?->symbol ?? '?').' → '.($this->toAsset?->symbol ?? '?');
    }

    public static function for(int $fromAssetId, int $toAssetId): ?self
    {
        return static::where('from_asset_id', $fromAssetId)->where('to_asset_id', $toAssetId)->first();
    }
}
