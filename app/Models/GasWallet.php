<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GasWallet extends Model
{
    use HasUuids;

    protected $fillable = [
        'chain_id', 'address', 'balance', 'min_threshold', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function chain(): BelongsTo
    {
        return $this->belongsTo(Chain::class);
    }

    /** Gas balance as a Money VO (native coin, 18 decimals). */
    public function money(): Money
    {
        return Money::ofBase($this->balance, 18, $this->chain?->native_symbol ?? '');
    }

    public function isLow(): bool
    {
        return BigInteger::of($this->balance)->isLessThan(BigInteger::of($this->min_threshold));
    }
}
