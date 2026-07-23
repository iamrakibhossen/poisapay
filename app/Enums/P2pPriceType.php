<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/**
 * How an ad is priced. `Fixed` pins a fiat unit price; `Floating` tracks the
 * live reference rate with a margin (basis points) applied.
 */
enum P2pPriceType: string
{
    use HasMeta;

    case Fixed = 'fixed';
    case Floating = 'floating';
}
