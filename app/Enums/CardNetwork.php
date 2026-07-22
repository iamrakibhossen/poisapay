<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

enum CardNetwork: string
{
    use HasMeta;

    case Visa = 'visa';
    case Mastercard = 'mastercard';

    public function label(): string
    {
        return $this === self::Visa ? 'Visa' : 'Mastercard';
    }
}
