<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

/**
 * Minimal RLP encoder (Ethereum) for building typed transactions (Wave 2).
 * Inputs are raw byte strings (already canonical/minimal) or nested arrays of them.
 */
final class Rlp
{
    /** @param string|array<int, string|array<mixed>> $input */
    public static function encode(string|array $input): string
    {
        if (is_array($input)) {
            $payload = '';
            foreach ($input as $item) {
                $payload .= self::encode($item);
            }

            return self::encodeLength(strlen($payload), 0xC0).$payload;
        }

        if (strlen($input) === 1 && ord($input[0]) < 0x80) {
            return $input;
        }

        return self::encodeLength(strlen($input), 0x80).$input;
    }

    private static function encodeLength(int $length, int $offset): string
    {
        if ($length < 56) {
            return chr($offset + $length);
        }

        $hex = dechex($length);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0'.$hex;
        }
        $lengthBytes = (string) hex2bin($hex);

        return chr($offset + 55 + strlen($lengthBytes)).$lengthBytes;
    }
}
