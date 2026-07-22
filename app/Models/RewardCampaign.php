<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardCampaign extends Model
{
    use HasUuids;

    protected $fillable = [
        'key', 'name', 'type', 'asset_id', 'amount', 'rate_bps',
        'min_spend', 'max_reward', 'is_active', 'starts_at', 'ends_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rate_bps' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** True when active and within any configured date window. */
    public function isLive(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }
        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    /** The fixed payout as Money (fixed campaigns only). */
    public function fixedMoney(): ?Money
    {
        if ($this->type !== 'fixed' || $this->amount === null || ! $this->asset) {
            return null;
        }

        return $this->asset->money($this->amount);
    }

    /** Resolve a live campaign by key. */
    public static function live(string $key): ?self
    {
        $campaign = static::where('key', $key)->first();

        return $campaign && $campaign->isLive() ? $campaign : null;
    }
}
