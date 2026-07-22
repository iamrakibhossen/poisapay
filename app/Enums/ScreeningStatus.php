<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** Sanctions / PEP screening outcome (TDD §10.2). */
enum ScreeningStatus: string
{
    use HasMeta;

    case Clear = 'clear';
    case Review = 'review';
    case Hit = 'hit';

    public function color(): string
    {
        return match ($this) {
            self::Clear => 'success',
            self::Review => 'warning',
            self::Hit => 'danger',
        };
    }
}
