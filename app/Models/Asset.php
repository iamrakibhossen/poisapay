<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssetKind;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    protected $fillable = [
        'currency_id', 'symbol', 'name', 'kind', 'currency_code', 'chain_id', 'contract_address',
        'decimals', 'min_confirmations', 'withdrawal_min', 'withdrawal_fee',
        'is_stablecoin', 'is_active', 'deposit_enabled', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AssetKind::class,
            'decimals' => 'integer',
            'min_confirmations' => 'integer',
            'is_stablecoin' => 'boolean',
            'is_active' => 'boolean',
            'deposit_enabled' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function chain(): BelongsTo
    {
        return $this->belongsTo(Chain::class);
    }

    /** The logical coin this per-chain deployment belongs to. */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function depositMethods(): HasMany
    {
        return $this->hasMany(DepositMethod::class)->where('is_active', true)->orderBy('sort');
    }

    /** Active + deposit-enabled assets a user may fund. */
    public function scopeDepositable($query)
    {
        return $query->where('is_active', true)->where('deposit_enabled', true);
    }

    public function isFiat(): bool
    {
        return $this->kind === AssetKind::Fiat;
    }

    public function isNative(): bool
    {
        return $this->kind === AssetKind::Crypto && is_null($this->contract_address);
    }

    public function requiredConfirmations(): int
    {
        return $this->min_confirmations ?? $this->chain?->min_confirmations ?? 12;
    }

    /** Wrap a base-unit amount in a Money VO carrying this asset's scale + symbol. */
    public function money(string|int|null $base): Money
    {
        return Money::ofBase($base ?? '0', $this->decimals, $this->symbol);
    }

    public function zero(): Money
    {
        return Money::zero($this->decimals, $this->symbol);
    }
}
