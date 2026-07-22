<?php

declare(strict_types=1);

namespace App\Models;

use App\Card\Enums\CardProviderDriver;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardProvider extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'slug', 'driver', 'network', 'bin', 'supports_virtual', 'supports_physical',
        'settlement_currency', 'api_base', 'is_demo', 'is_active', 'sort', 'config',
    ];

    protected function casts(): array
    {
        return [
            'driver' => CardProviderDriver::class,
            'supports_virtual' => 'boolean',
            'supports_physical' => 'boolean',
            'is_demo' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
            'config' => 'array',
        ];
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
