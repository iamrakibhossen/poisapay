<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** How a deposit method is funded (§6.1). */
enum DepositMethodType: string
{
    use HasMeta;

    case Bank = 'bank';
    case Mobile = 'mobile';
    case Crypto = 'crypto';
    case Manual = 'manual';

    /** On-chain crypto methods resolve to a wallet address; the rest are manual/off-chain. */
    public function isManual(): bool
    {
        return $this !== self::Crypto;
    }

    public function label(): string
    {
        return match ($this) {
            self::Bank => 'Bank transfer',
            self::Mobile => 'Mobile wallet',
            self::Crypto => 'Crypto network',
            self::Manual => 'Manual',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Bank => 'building-library',
            self::Mobile => 'device-phone-mobile',
            self::Crypto => 'cube',
            self::Manual => 'banknotes',
        };
    }
}
