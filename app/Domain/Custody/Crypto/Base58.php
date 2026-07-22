<?php

declare(strict_types=1);

namespace App\Domain\Custody\Crypto;

use RuntimeException;

/**
 * Base58 + Base58Check (the Bitcoin/TRON alphabet). Used for extended-key
 * serialization (BIP32 xpub/xprv) and TRON address encoding. Pure gmp big-int
 * math — no external dependency.
 */
final class Base58
{
    private const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public static function encode(string $bytes): string
    {
        $zeros = strlen($bytes) - strlen(ltrim($bytes, "\x00"));

        $num = strlen($bytes) === 0 ? gmp_init(0) : gmp_import($bytes);
        $out = '';
        while (gmp_cmp($num, 0) > 0) {
            $rem = gmp_intval(gmp_mod($num, 58));
            $num = gmp_div_q($num, 58);
            $out .= self::ALPHABET[$rem];
        }

        return str_repeat('1', $zeros).strrev($out);
    }

    public static function decode(string $string): string
    {
        $num = gmp_init(0);
        $len = strlen($string);
        for ($i = 0; $i < $len; $i++) {
            $idx = strpos(self::ALPHABET, $string[$i]);
            if ($idx === false) {
                throw new RuntimeException('Invalid Base58 character.');
            }
            $num = gmp_add(gmp_mul($num, 58), $idx);
        }

        $bytes = gmp_cmp($num, 0) === 0 ? '' : gmp_export($num);
        $zeros = strlen($string) - strlen(ltrim($string, '1'));

        return str_repeat("\x00", $zeros).$bytes;
    }

    /** Append a 4-byte double-SHA256 checksum and Base58-encode. */
    public static function encodeCheck(string $payload): string
    {
        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        return self::encode($payload.$checksum);
    }

    /** Base58-decode and verify+strip the 4-byte checksum. */
    public static function decodeCheck(string $string): string
    {
        $full = self::decode($string);
        if (strlen($full) < 4) {
            throw new RuntimeException('Base58Check string too short.');
        }
        $payload = substr($full, 0, -4);
        $checksum = substr($full, -4);
        $expected = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        if (! hash_equals($expected, $checksum)) {
            throw new RuntimeException('Invalid Base58Check checksum.');
        }

        return $payload;
    }
}
