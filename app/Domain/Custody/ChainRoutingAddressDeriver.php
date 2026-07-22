<?php

declare(strict_types=1);

namespace App\Domain\Custody;

use App\Domain\Custody\Contracts\AddressDeriver;
use App\Enums\ChainType;

/**
 * Routes address derivation per chain. TRON uses real BIP32/secp256k1 derivation
 * once custody is live; every other chain (and everything while custody is
 * simulated) falls back to the deterministic stand-in. This lets real TRON
 * custody ship incrementally without disturbing the still-simulated EVM chains
 * or the existing simulated-custody test suite.
 */
class ChainRoutingAddressDeriver implements AddressDeriver
{
    public function __construct(
        private readonly TronAddressDeriver $tron,
        private readonly DeterministicAddressDeriver $simulated,
    ) {}

    public function derive(ChainType $chain, string $xpub, int $index): string
    {
        if ($chain === ChainType::Tron && ! config('poisapay.custody_simulated')) {
            return $this->tron->derive($chain, $xpub, $index);
        }

        return $this->simulated->derive($chain, $xpub, $index);
    }
}
