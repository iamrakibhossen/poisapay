<?php

declare(strict_types=1);

namespace App\Sell\Enums;

/** How a line item entered the order (drives funnel attribution + reporting). */
enum OrderItemKind: string
{
    case Main = 'main';
    case OrderBump = 'order_bump';
    case Upsell = 'upsell';
    case Downsell = 'downsell';
    case CrossSell = 'cross_sell';
}
