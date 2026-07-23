<?php

declare(strict_types=1);

namespace App\Domain\Chain\Tron;

use App\Domain\Custody\Crypto\TronAddress;

/**
 * TRC20 ABI calldata helpers shared by the withdrawal signer and the sweep engine.
 */
class Trc20
{
    /** ABI-encode transfer(address,uint256): 32-byte padded to-address + 32-byte padded amount. */
    public static function transferCalldata(string $toAddress, string $baseAmount): string
    {
        $to20 = substr(TronAddress::decode($toAddress), 1); // drop the 0x41 prefix → 20 bytes
        $toWord = str_pad(bin2hex($to20), 64, '0', STR_PAD_LEFT);
        $amountWord = str_pad(gmp_strval(gmp_init($baseAmount, 10), 16), 64, '0', STR_PAD_LEFT);

        return $toWord.$amountWord;
    }
}
