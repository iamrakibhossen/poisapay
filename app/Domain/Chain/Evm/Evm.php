<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

use kornrunner\Keccak;

/**
 * Low-level EVM primitives (Wave 2): keccak-256, EIP-55 checksum addresses, ABI
 * function selectors, and minimal big-endian integer <-> byte helpers used by RLP
 * and EIP-1559 transaction building. Uses the real kornrunner/keccak (same library
 * the TRON address codec relies on).
 */
final class Evm
{
    /** Keccak-256 over binary input, returns raw 32 bytes. */
    public static function keccak(string $binary): string
    {
        return Keccak::hash($binary, 256, true);
    }

    /** First 4 bytes of keccak(signature) as lowercase hex, e.g. transfer(address,uint256) -> a9059cbb. */
    public static function selector(string $signature): string
    {
        return substr(bin2hex(self::keccak($signature)), 0, 8);
    }

    /** 0x-prefixed, EIP-55 mixed-case checksummed address from a 20-byte hex (with/without 0x). */
    public static function toChecksumAddress(string $address): string
    {
        $hex = strtolower(self::strip0x($address));
        $hash = bin2hex(self::keccak($hex));

        $out = '';
        for ($i = 0; $i < strlen($hex); $i++) {
            $char = $hex[$i];
            $out .= (ctype_alpha($char) && hexdec($hash[$i]) >= 8) ? strtoupper($char) : $char;
        }

        return '0x'.$out;
    }

    public static function isValidAddress(string $address): bool
    {
        return (bool) preg_match('/^0x[0-9a-fA-F]{40}$/', $address);
    }

    public static function strip0x(string $hex): string
    {
        return str_starts_with($hex, '0x') || str_starts_with($hex, '0X') ? substr($hex, 2) : $hex;
    }

    /** Minimal big-endian bytes of a non-negative decimal integer string ('' for zero — RLP canonical). */
    public static function intToBytes(string $decimal): string
    {
        $gmp = gmp_init($decimal === '' ? '0' : $decimal, 10);
        if (gmp_cmp($gmp, 0) === 0) {
            return '';
        }
        $hex = gmp_strval($gmp, 16);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0'.$hex;
        }

        return (string) hex2bin($hex);
    }

    /** Decimal string from big-endian bytes. */
    public static function bytesToInt(string $bytes): string
    {
        if ($bytes === '') {
            return '0';
        }

        return gmp_strval(gmp_init(bin2hex($bytes), 16), 10);
    }

    /** Decimal string from a hex quantity (e.g. an eth_ JSON-RPC 0x… result). */
    public static function hexToInt(string $hex): string
    {
        $hex = self::strip0x($hex);
        if ($hex === '' || $hex === '0') {
            return '0';
        }

        return gmp_strval(gmp_init($hex, 16), 10);
    }

    /** 0x-prefixed hex quantity from a decimal string (minimal, e.g. '0x0' for zero). */
    public static function intToHex(string $decimal): string
    {
        return '0x'.gmp_strval(gmp_init($decimal === '' ? '0' : $decimal, 10), 16);
    }

    /** Left-pad a hex string to 32 bytes (64 hex chars). */
    public static function pad32(string $hex): string
    {
        return str_pad(self::strip0x($hex), 64, '0', STR_PAD_LEFT);
    }

    /**
     * Rescale an integer base-unit amount between two decimal precisions — used to
     * normalise on-chain token amounts (e.g. BSC's 18-decimal USDT) to the ledger's
     * uniform stablecoin precision, and back for withdrawals. Scaling down truncates
     * sub-precision dust.
     */
    public static function scaleDecimals(string $amount, int $from, int $to): string
    {
        if ($from === $to) {
            return $amount;
        }

        return $from > $to
            ? bcdiv($amount, bcpow('10', (string) ($from - $to)), 0)
            : bcmul($amount, bcpow('10', (string) ($to - $from)));
    }
}
