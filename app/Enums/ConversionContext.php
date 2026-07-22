<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

enum ConversionContext: string
{
    use HasMeta;

    case Swap = 'swap';
    case Ramp = 'ramp';
    case CardSettle = 'card_settle';
}
