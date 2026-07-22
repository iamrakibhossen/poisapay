<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChainType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function xpubs(): HasMany
    {
        return $this->hasMany(CustodyXpub::class);
    }
}
