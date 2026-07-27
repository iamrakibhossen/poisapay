<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GasWalletHealth;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GasWallet extends Model
{
    use HasUuids;

    protected $fillable = [
        'chain_id', 'address', 'balance', 'min_threshold', 'critical_threshold', 'is_active',
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
        return Money::ofBase((string) $this->balance, 18, $this->chain?->native_symbol ?? '');
    }

    /** Warning threshold (top-up-soon alert level) — the historical `min_threshold`. */
    public function warningThreshold(): BigInteger
    {
        return BigInteger::of((string) $this->min_threshold);
    }

    /**
     * Critical threshold: below this the wallet cannot realistically pay network
     * gas. 0 (the default / legacy rows) means "no critical tier".
     */
    public function criticalThreshold(): BigInteger
    {
        return BigInteger::of((string) ($this->critical_threshold ?? '0'));
    }

    /**
     * Two-tier health: Critical (below the critical threshold, when one is set),
     * else Warning (below the warning threshold), else Healthy.
     */
    public function health(): GasWalletHealth
    {
        $balance = BigInteger::of((string) $this->balance);
        $critical = $this->criticalThreshold();

        if ($critical->isGreaterThan(BigInteger::zero()) && $balance->isLessThan($critical)) {
            return GasWalletHealth::Critical;
        }

        if ($balance->isLessThan($this->warningThreshold())) {
            return GasWalletHealth::Warning;
        }

        return GasWalletHealth::Healthy;
    }

    /**
     * Below the warning threshold (historical behaviour). Kept for backwards
     * compatibility — callers wanting the critical tier should use {@see health()}.
     */
    public function isLow(): bool
    {
        return BigInteger::of((string) $this->balance)->isLessThan($this->warningThreshold());
    }

    /** In the critical tier: the wallet cannot realistically pay network gas. */
    public function isCritical(): bool
    {
        return $this->health() === GasWalletHealth::Critical;
    }

    /**
     * True unless critical — the gate the settlement path consults before it
     * broadcasts. A warning-level wallet returns true (still broadcasts).
     */
    public function canPayGas(): bool
    {
        return ! $this->isCritical();
    }
}
