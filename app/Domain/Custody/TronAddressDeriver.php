<?php

declare(strict_types=1);

namespace App\Domain\Custody;

use App\Domain\Custody\Contracts\AddressDeriver;
use App\Domain\Custody\Crypto\Bip32;
use App\Domain\Custody\Crypto\TronAddress;
use App\Enums\ChainType;
use InvalidArgumentException;

/**
 * Real TRON deposit-address derivation. Given an account xpub (m/44'/195'/0'),
 * derives the receive address at m/44'/195'/0'/0/{index} using BIP32 public-only
 * (CKDpub) derivation — no private key is required or present in the online zone.
 * The matching private key (for sweeps/withdrawals) comes from {@see EnvSeedSignerKeyProvider}
 * and derives to the identical address (verified by test).
 */
class TronAddressDeriver implements AddressDeriver
{
    public function __construct(private readonly Bip32 $bip32) {}

    public function derive(ChainType $chain, string $xpub, int $index): string
    {
        if ($chain !== ChainType::Tron) {
            throw new InvalidArgumentException('TronAddressDeriver only supports the TRON chain.');
        }

        $account = $this->bip32->parse($xpub);
        $receive = $this->bip32->ckdPub($account, 0);          // change = 0
        $address = $this->bip32->ckdPub($receive, $index);     // index

        return TronAddress::fromPublicKey($address['key']);
    }
}
