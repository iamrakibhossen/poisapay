<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** Tiered KYC gating limits (TDD §10.1). */
enum KycTier: string
{
    use HasMeta;

    case Unverified = 'unverified';
    case Basic = 'basic';
    case Full = 'full';

    public function rank(): int
    {
        return match ($this) {
            self::Unverified => 0,
            self::Basic => 1,
            self::Full => 2,
        };
    }

    public function atLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /** Daily withdrawal ceiling in USD minor units (0 = no withdrawal allowed). */
    public function dailyWithdrawalCeiling(): string
    {
        return match ($this) {
            self::Unverified => '0',
            self::Basic => '100000',      // $1,000.00
            self::Full => '2500000',      // $25,000.00
        };
    }

    public function canWithdraw(): bool
    {
        return $this !== self::Unverified;
    }

    /** Cards require the highest tier (TDD §F3.2). */
    public function canIssueCard(): bool
    {
        return $this === self::Full;
    }

    public function color(): string
    {
        return match ($this) {
            self::Unverified => 'gray',
            self::Basic => 'warning',
            self::Full => 'success',
        };
    }
}
