<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/**
 * Which side of the book an ad sits on. A `sell` ad offers USDT for fiat
 * (the advertiser is the seller and their USDT is escrowed when an order opens);
 * a `buy` ad wants to buy USDT (the order-opener becomes the seller).
 */
enum P2pAdType: string
{
    use HasMeta;

    case Buy = 'buy';
    case Sell = 'sell';

    public function color(): string
    {
        return $this === self::Buy ? 'success' : 'info';
    }
}
