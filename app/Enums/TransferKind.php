<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** Send-money product type (TDD §F4). */
enum TransferKind: string
{
    use HasMeta;

    case Internal = 'internal';
    case Payout = 'payout';
    case Remittance = 'remittance';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Instant P2P',
            self::Payout => 'Fiat Payout',
            self::Remittance => 'Remittance',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Internal => 'success',
            self::Payout => 'info',
            self::Remittance => 'primary',
        };
    }
}
