<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal;

use App\Domain\Chain\Evm\Evm;
use App\Domain\Withdrawal\Exceptions\InvalidWithdrawalAddressException;
use App\Enums\ChainType;
use App\Models\Asset;

/**
 * Validates a crypto withdrawal destination against the asset's network:
 *   - TRON / TRC-20 → must start with `T` (Base58Check address).
 *   - EVM (BSC, Ethereum, Polygon, …) → must start with `0x` and be 20 bytes hex.
 *
 * The primary guarantee is NETWORK MATCH — a mismatched network (e.g. sending
 * BEP-20 USDT to a `T…` address) can never settle on-chain, so it is rejected up
 * front. Fiat / off-chain payouts (no chain) are skipped here.
 */
class WithdrawalAddressValidator
{
    public function validate(Asset $asset, string $address): void
    {
        $address = trim($address);
        if ($address === '') {
            throw new InvalidWithdrawalAddressException('A destination address is required.');
        }

        $chain = $this->chainType($asset);
        if ($chain === null) {
            return; // fiat / off-chain payout
        }

        if ($chain === ChainType::Tron) {
            if (! str_starts_with($address, 'T')) {
                throw new InvalidWithdrawalAddressException('A TRON (TRC-20) withdrawal must go to a “T” address.');
            }

            return;
        }

        if ($chain->isEvm()) {
            if (! str_starts_with($address, '0x') || ! Evm::isValidAddress($address)) {
                throw new InvalidWithdrawalAddressException("A {$chain->label()} withdrawal must go to a valid “0x” address.");
            }
        }
    }

    private function chainType(Asset $asset): ?ChainType
    {
        return $asset->chain?->key; // Chain::$key is cast to ChainType
    }
}
