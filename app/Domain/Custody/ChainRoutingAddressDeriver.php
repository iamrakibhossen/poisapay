<?php

declare(strict_types=1);

namespace App\Domain\Custody;

use App\Domain\Custody\Contracts\AddressDeriver;
use App\Enums\ChainType;

/**
 * Routes address derivation per chain. TRON and EVM (Ethereum/BSC) use real
 * BIP32/secp256k1 derivation once custody is live; everything while custody is
 * simulated falls back to the deterministic stand-in. This lets real custody ship
 * incrementally without disturbing the existing simulated-custody test suite.
 */
class ChainRoutingAddressDeriver implements AddressDeriver
{
    public function __construct(
        private readonly TronAddressDeriver $tron,
        private readonly EvmAddressDeriver $evm,
        private readonly DeterministicAddressDeriver $simulated,
    ) {}

    public function derive(ChainType $chain, string $xpub, int $index): string
    {
        if (config('poisapay.custody_simulated')) {
            return $this->simulated->derive($chain, $xpub, $index);
        }

        return match (true) {
            $chain === ChainType::Tron => $this->tron->derive($chain, $xpub, $index),
            $chain->isEvm() => $this->evm->derive($chain, $xpub, $index),
            default => $this->simulated->derive($chain, $xpub, $index),
        };
    }
}
