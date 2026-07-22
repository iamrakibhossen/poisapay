<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** Real-time card authorisation state (TDD §F3.3/F3.5). */
enum CardAuthStatus: string
{
    use HasMeta;

    case Approved = 'approved';
    case Declined = 'declined';
    case Settled = 'settled';
    case Reversed = 'reversed';
    case Expired = 'expired';

    public function color(): string
    {
        return match ($this) {
            self::Approved => 'warning',
            self::Declined => 'danger',
            self::Settled => 'success',
            self::Reversed => 'gray',
            self::Expired => 'gray',
        };
    }
}
