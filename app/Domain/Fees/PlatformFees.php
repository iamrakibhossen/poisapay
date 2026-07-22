<?php

declare(strict_types=1);

namespace App\Domain\Fees;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Platform percentage fees for deposits and withdrawals — the admin's cut,
 * booked to fee:income (visible in the Revenue Wallet). Percentages are
 * admin-configurable via the settings engine (default 1%). All math is in base
 * (minor) units and floored, so the fee never rounds in the user's favour.
 */
class PlatformFees
{
    public static function depositPercent(): string
    {
        return (string) getSetting('deposit_fee_percent', config('poisapay.deposit_fee_percent', '1'));
    }

    public static function withdrawalPercent(): string
    {
        return (string) getSetting('withdrawal_fee_percent', config('poisapay.withdrawal_fee_percent', '1'));
    }

    /** Fee (base units) for a deposit of `$amountBase`. */
    public static function depositFee(string $amountBase): string
    {
        return self::of($amountBase, self::depositPercent());
    }

    /** Fee (base units) for a withdrawal of `$amountBase`. */
    public static function withdrawalFee(string $amountBase): string
    {
        return self::of($amountBase, self::withdrawalPercent());
    }

    /** floor(amountBase × percent / 100) in base units. */
    public static function of(string $amountBase, string $percent): string
    {
        if (BigDecimal::of($percent)->isZero()) {
            return '0';
        }

        return (string) BigDecimal::of($amountBase)
            ->multipliedBy($percent)
            ->dividedBy(100, 0, RoundingMode::DOWN);
    }
}
