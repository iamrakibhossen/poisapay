<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

enum RampDirection: string
{
    use HasMeta;

    case On = 'on';   // fiat -> balance
    case Off = 'off'; // balance -> fiat out

    public function label(): string
    {
        return $this === self::On ? 'On-ramp' : 'Off-ramp';
    }
}
