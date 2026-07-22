<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** Merchant account lifecycle (TDD §8). */
enum MerchantStatus: string
{
    use HasMeta;

    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';

    public function canAcceptPayments(): bool
    {
        return $this === self::Active;
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Suspended => 'danger',
        };
    }
}
