<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** Crypto-backed credit line state (TDD §F6). */
enum CreditStatus: string
{
    use HasMeta;

    case Active = 'active';
    case MarginCall = 'margin_call';
    case Liquidating = 'liquidating';
    case Repaid = 'repaid';
    case Defaulted = 'defaulted';

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::MarginCall => 'warning',
            self::Liquidating, self::Defaulted => 'danger',
            self::Repaid => 'gray',
        };
    }
}
