<?php

declare(strict_types=1);

namespace App\Domain\Custody\Crypto;

use Elliptic\EC;
use kornrunner\Keccak;
use RuntimeException;

/**
 * TRON address encoding: address = Base58Check( 0x41 || last20( keccak256( pubXY ) ) ).
 * Same 20-byte hash as an EVM address, with the 0x41 mainnet prefix and Base58Check.
 */
final class TronAddress
{
    private const PREFIX = "\x41";

    /**
     * @param  string  $publicKey  binary compressed (33), uncompressed (65) or raw XY (64) public key
     */
    public static function fromPublicKey(string $publicKey): string
    {
        return Base58::encodeCheck(self::PREFIX.self::hash160Keccak($publicKey));
    }

    /** The 20-byte address hash as lowercase hex (equals the EVM address body) — used in tests. */
    public static function evmHex(string $publicKey): string
    {
        return bin2hex(self::hash160Keccak($publicKey));
    }

    /** Validate + normalise a Base58Check TRON address; returns the 21-byte (0x41+20) payload. */
    public static function decode(string $address): string
    {
        $payload = Base58::decodeCheck($address);
        if (strlen($payload) !== 21 || $payload[0] !== self::PREFIX) {
            throw new RuntimeException('Not a valid TRON address.');
        }

        return $payload;
    }

    /** last 20 bytes of keccak256 over the 64-byte uncompressed XY public key. */
    private static function hash160Keccak(string $publicKey): string
    {
        $xy = self::rawXy($publicKey);
        $hash = Keccak::hash($xy, 256, true);

        return substr($hash, -20);
    }

    private static function rawXy(string $publicKey): string
    {
        $len = strlen($publicKey);
        if ($len === 64) {
            return $publicKey;
        }
        if ($len === 65 && $publicKey[0] === "\x04") {
            return substr($publicKey, 1);
        }
        if ($len === 33) {
            // Decompress via secp256k1.
            $ec = new EC('secp256k1');
            $uncompressed = hex2bin($ec->keyFromPublic(bin2hex($publicKey), 'hex')->getPublic(false, 'hex'));

            return substr($uncompressed, 1);
        }

        throw new RuntimeException('Unexpected public key length: '.$len);
    }
}
