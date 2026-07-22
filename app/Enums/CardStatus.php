<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

enum CardStatus: string
{
    use HasMeta;

    case Inactive = 'inactive';
    case Active = 'active';
    case Frozen = 'frozen';
    case Closed = 'closed';

    public function canSpend(): bool
    {
        return $this === self::Active;
    }

    public function color(): string
    {
        return match ($this) {
            self::Inactive => 'gray',
            self::Active => 'success',
            self::Frozen => 'warning',
            self::Closed => 'danger',
        };
    }
}
