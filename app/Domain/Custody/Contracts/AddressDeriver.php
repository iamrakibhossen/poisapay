<?php

declare(strict_types=1);

namespace App\Domain\Custody\Contracts;

use App\Enums\ChainType;

/**
 * Public (xpub) address derivation (TDD §4.2, D4). The signing zone holds the
 * private keys; the online zone can only derive addresses. Implementations must
 * be deterministic: (xpub, index) -> address.
 */
interface AddressDeriver
{
    public function derive(ChainType $chain, string $xpub, int $index): string;
}
