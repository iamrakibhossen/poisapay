<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

/**
 * Minimal ERC-20 ABI helpers (Wave 2): encode a `transfer(address,uint256)` call
 * and decode a `Transfer` event log. Sufficient for stablecoin deposits/withdrawals
 * without pulling in a full web3/ABI library.
 */
final class Abi
{
    /** 0x-prefixed calldata for transfer(to, amount). $amount is a decimal (base units) string. */
    public static function erc20Transfer(string $to, string $amount): string
    {
        $selector = Evm::selector('transfer(address,uint256)'); // a9059cbb
        $toWord = Evm::pad32(Evm::strip0x($to));
        $amountWord = Evm::pad32(gmp_strval(gmp_init($amount === '' ? '0' : $amount, 10), 16));

        return '0x'.$selector.$toWord.$amountWord;
    }

    /** 0x-prefixed calldata for balanceOf(owner). */
    public static function erc20BalanceOf(string $owner): string
    {
        return '0x'.Evm::selector('balanceOf(address)').Evm::pad32(Evm::strip0x($owner));
    }

    /** The keccak topic hash for the ERC-20 Transfer event. */
    public static function transferEventTopic(): string
    {
        return '0x'.bin2hex(Evm::keccak('Transfer(address,address,uint256)'));
    }

    /**
     * Decode an ERC-20 Transfer log into {from, to, amount}. Indexed from/to live in
     * topics[1]/topics[2] (last 20 bytes of a 32-byte word); the value is in data.
     *
     * @param  array{topics?: array<int, string>, data?: string}  $log
     * @return array{from: string, to: string, amount: string}
     */
    public static function decodeTransferLog(array $log): array
    {
        $topics = $log['topics'] ?? [];

        return [
            'from' => self::topicToAddress($topics[1] ?? ''),
            'to' => self::topicToAddress($topics[2] ?? ''),
            'amount' => Evm::hexToInt($log['data'] ?? '0x0'),
        ];
    }

    private static function topicToAddress(string $topic): string
    {
        $hex = Evm::strip0x($topic);

        return Evm::isValidAddress('0x'.substr($hex, -40))
            ? Evm::toChecksumAddress('0x'.substr($hex, -40))
            : '0x'.substr($hex, -40);
    }
}
