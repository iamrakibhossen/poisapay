<?php

declare(strict_types=1);

namespace App\Domain\Chain\Gas\Contracts;

use App\Domain\Chain\Gas\SponsorResult;
use App\Models\Asset;
use App\Models\DepositAddress;

/**
 * Chain-agnostic gas/energy sponsor. Ensures an on-chain address holds enough native
 * gas (TRON: TRX for energy/bandwidth; EVM: ETH/BNB) to perform a subsequent operation
 * such as a sweep, topping it up from the hot/gas wallet when needed. Implementations
 * are idempotent, bounded-retry, and fully audited.
 */
interface GasSponsor
{
    public function ensure(DepositAddress $address, Asset $asset): SponsorResult;
}
