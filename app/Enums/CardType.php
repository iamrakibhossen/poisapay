<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

enum CardType: string
{
    use HasMeta;

    case Virtual = 'virtual';
    case Physical = 'physical';

    public function color(): string
    {
        return $this === self::Virtual ? 'info' : 'primary';
    }
}
