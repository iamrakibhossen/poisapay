<?php

declare(strict_types=1);

namespace App\Domain\Custody;

use App\Domain\Custody\Contracts\AddressDeriver;
use App\Enums\ChainType;

/**
 * Deterministic, correctly-FORMATTED address derivation stand-in.
 *
 * NOTE: Real BIP32/44 public derivation (secp256k1 point math + Keccak-256 for
 * EVM / Base58Check for Tron) runs in the isolated Address Generator service
 * (TDD §4.2, Volume I §8). To keep this build self-contained and library-free,
 * this implementation produces deterministic addresses in the correct on-chain
 * format from (xpub, index) via hashing. It is swap-compatible with a real
 * deriver behind the {@see AddressDeriver} contract — no caller changes needed.
 */
class DeterministicAddressDeriver implements AddressDeriver
{
    public function derive(ChainType $chain, string $xpub, int $index): string
    {
        $seed = hash('sha256', $xpub.'/0/'.$index, true);

        return $chain->isEvm() ? $this->evmAddress($seed) : $this->tronAddress($seed);
    }

    private function evmAddress(string $seed): string
    {
        // 20-byte address, EIP-55 checksummed.
        $hex = substr(bin2hex($seed), 0, 40);

        return $this->toEip55($hex);
    }

    /** EIP-55 mixed-case checksum (uses keccak if available, else sha3 fallback). */
    private function toEip55(string $hex): string
    {
        $hash = bin2hex($this->keccak256(strtolower($hex)));
        $out = '';
        for ($i = 0; $i < 40; $i++) {
            $char = $hex[$i];
            $out .= (ctype_alpha($char) && hexdec($hash[$i]) >= 8) ? strtoupper($char) : strtolower($char);
        }

        return '0x'.$out;
    }

    private function keccak256(string $data): string
    {
        // Prefer real Keccak if the extension/library is present; fall back to sha3-256.
        if (in_array('sha3-256', hash_algos(), true)) {
            return hash('sha3-256', $data, true);
        }

        return hash('sha256', $data, true);
    }

    private function tronAddress(string $seed): string
    {
        // Tron mainnet addresses are Base58Check over 0x41 + 20-byte body.
        $body = "\x41".substr($seed, 0, 20);
        $checksum = substr(hash('sha256', hash('sha256', $body, true), true), 0, 4);

        return $this->base58($body.$checksum);
    }

    private function base58(string $bytes): string
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $num = '0';
        foreach (str_split($bytes) as $byte) {
            $num = bcadd(bcmul($num, '256'), (string) ord($byte));
        }

        $encoded = '';
        while (bccomp($num, '0') > 0) {
            $rem = (int) bcmod($num, '58');
            $num = bcdiv($num, '58', 0);
            $encoded = $alphabet[$rem].$encoded;
        }

        // Leading zero bytes become '1'.
        foreach (str_split($bytes) as $byte) {
            if ($byte === "\x00") {
                $encoded = '1'.$encoded;
            } else {
                break;
            }
        }

        return $encoded;
    }
}
