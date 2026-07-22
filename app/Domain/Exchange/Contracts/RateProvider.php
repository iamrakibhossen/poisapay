<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Contracts;

use App\Models\Asset;
use Brick\Math\BigDecimal;

/**
 * Source of mid-market FX/crypto rates (TDD §F2.1). Swappable for a live
 * provider; the exchange engine layers spread on top of whatever this returns.
 */
interface RateProvider
{
    /** Mid-market price of 1 unit of $from expressed in units of $to. */
    public function rate(Asset $from, Asset $to): BigDecimal;
}
