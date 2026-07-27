<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Two-tier health of a gas (hot-wallet native-coin) balance, derived from the
 * GasWallet's warning (`min_threshold`) and `critical_threshold` levels.
 *
 *  - Healthy  : balance is at/above the warning threshold. Silent.
 *  - Warning  : below warning but can still pay network gas. Alert only —
 *               withdrawals continue.
 *  - Critical : below the critical threshold; the wallet cannot realistically
 *               pay network gas, so on-chain broadcasts for that chain are held
 *               until it is topped up.
 */
enum GasWalletHealth: string
{
    case Healthy = 'healthy';
    case Warning = 'warning';
    case Critical = 'critical';

    /**
     * Only a CRITICAL wallet cannot realistically pay gas, so only it holds
     * withdrawals. A warning-level wallet still broadcasts normally.
     */
    public function blocksWithdrawals(): bool
    {
        return $this === self::Critical;
    }

    /** Warning + Critical raise an operator alert; Healthy is silent. */
    public function isAlertable(): bool
    {
        return $this !== self::Healthy;
    }

    /** Ordinal severity, for comparing/escalating. */
    public function rank(): int
    {
        return match ($this) {
            self::Healthy => 0,
            self::Warning => 1,
            self::Critical => 2,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::Warning => 'Low (warning)',
            self::Critical => 'Critically low',
        };
    }
}
