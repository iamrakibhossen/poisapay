<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssetKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Logical coin (USDT, ETH, BDT) that groups one-or-more per-chain {@see Asset}
 * deployments ("networks"). Owns coin-level identity so it is not duplicated
 * across networks. Balances/ledger stay on the Asset (per chain).
 */
class Currency extends Model
{
    protected $fillable = [
        'symbol', 'name', 'kind', 'is_stablecoin', 'display_decimals', 'icon', 'sort', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AssetKind::class,
            'is_stablecoin' => 'boolean',
            'display_decimals' => 'integer',
            'sort' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** All per-chain deployments (networks) of this coin. */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class)->orderBy('sort');
    }

    /** Alias reading better at call sites that think in "networks". */
    public function networks(): HasMany
    {
        return $this->assets();
    }

    public function isFiat(): bool
    {
        return $this->kind === AssetKind::Fiat;
    }
}
