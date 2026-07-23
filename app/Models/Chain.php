<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChainType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property ChainType $key
 * @property string $name
 * @property string $native_symbol
 * @property int $min_confirmations
 * @property bool $is_evm
 * @property bool $is_active
 */
class Chain extends Model
{
    protected $fillable = [
        'key', 'name', 'native_symbol', 'min_confirmations', 'is_evm', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'key' => ChainType::class,
            'is_evm' => 'boolean',
            'is_active' => 'boolean',
            'min_confirmations' => 'integer',
        ];
    }

    /** Block-explorer URL for a transaction hash on this chain (null if unmapped). */
    public function explorerTxUrl(?string $hash): ?string
    {
        if (! $hash) {
            return null;
        }
        $base = config("poisapay.custody.explorers.{$this->key->value}");
        if (! $base) {
            return null;
        }
        // TronScan is a hash-router SPA; EVM explorers use plain /tx paths.
        $path = $this->key === ChainType::Tron ? '/#/transaction/' : '/tx/';

        return rtrim((string) $base, '/').$path.$hash;
    }

    /** Block-explorer URL for an address on this chain (null if unmapped). */
    public function explorerAddressUrl(?string $address): ?string
    {
        if (! $address) {
            return null;
        }
        $base = config("poisapay.custody.explorers.{$this->key->value}");
        if (! $base) {
            return null;
        }
        $path = $this->key === ChainType::Tron ? '/#/address/' : '/address/';

        return rtrim((string) $base, '/').$path.$address;
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /** @return HasMany<CustodyXpub, $this> */
    public function xpubs(): HasMany
    {
        return $this->hasMany(CustodyXpub::class);
    }
}
