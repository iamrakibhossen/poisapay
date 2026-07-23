<?php

declare(strict_types=1);

namespace App\Domain\Custody;

use App\Domain\Chain\Evm\Evm;
use App\Domain\Custody\Contracts\AddressDeriver;
use App\Domain\Custody\Crypto\Bip32;
use App\Domain\Custody\Crypto\TronAddress;
use App\Enums\ChainType;
use InvalidArgumentException;

/**
 * Real EVM (Ethereum/BSC) deposit-address derivation (Wave 2). Public-only:
 * derives the child public key from the account xpub via BIP32 CKDpub, then the
 * address = last-20-bytes(keccak256(uncompressed pubkey)), EIP-55 checksummed.
 * No private key is needed in the online zone (D4).
 */
final class EvmAddressDeriver implements AddressDeriver
{
    public function __construct(private readonly Bip32 $bip32) {}

    public function derive(ChainType $chain, string $xpub, int $index): string
    {
        if (! $chain->isEvm()) {
            throw new InvalidArgumentException('EvmAddressDeriver only supports EVM chains.');
        }

        $account = $this->bip32->parse($xpub);
        $receive = $this->bip32->ckdPub($account, 0);   // change = 0
        $node = $this->bip32->ckdPub($receive, $index); // address index

        // TronAddress::evmHex(pubkey) = last-20-bytes(keccak256(x||y)); add EIP-55.
        return Evm::toChecksumAddress(TronAddress::evmHex($node['key']));
    }
}
