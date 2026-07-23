<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Enums\ChainType;

/**
 * In-memory EVM provider for deterministic tests + local development (Wave 2).
 * Implements the exact JSON-RPC contract; tests drive chain state via the control
 * methods (setBlock/pushTransferLog/confirm/setNonce/setBalance) and inspect what
 * was broadcast via {@see $sent}. Registered as a singleton so a test and the
 * pipeline under test share one instance.
 */
final class FakeBlockchainProvider implements BlockchainProvider
{
    /** @var array<string, int> */
    private array $head = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $logs = [];

    /** @var array<string, array{status: bool, blockNumber: int}> */
    private array $receipts = [];

    /** @var array<string, int> */
    private array $nonces = [];

    /** @var array<string, string> */
    private array $balances = [];

    /** @var array<string, string> */
    private array $callResults = [];

    /** @var array<int, array{chain: string, raw: string, hash: string}> */
    public array $sent = [];

    public string $priorityFee = '1000000000';   // 1 gwei

    public string $baseFee = '20000000000';       // 20 gwei

    // ---- test controls -----------------------------------------------------

    public function setBlock(ChainType $chain, int $number): void
    {
        $this->head[$chain->value] = $number;
    }

    public function pushTransferLog(ChainType $chain, string $contract, string $from, string $to, string $amount, string $txHash, int $logIndex = 0, int $block = 1): void
    {
        $this->head[$chain->value] = max($this->head[$chain->value] ?? 0, $block);
        $this->logs[$chain->value][] = [
            'address' => $contract,
            'topics' => [
                Abi::transferEventTopic(),
                '0x'.Evm::pad32(Evm::strip0x($from)),
                '0x'.Evm::pad32(Evm::strip0x($to)),
            ],
            'data' => '0x'.Evm::pad32(gmp_strval(gmp_init($amount, 10), 16)),
            'transactionHash' => $txHash,
            'logIndex' => Evm::intToHex((string) $logIndex),
            'blockNumber' => Evm::intToHex((string) $block),
        ];
    }

    public function confirm(string $txHash, int $block, bool $success = true): void
    {
        $this->receipts[$txHash] = ['status' => $success, 'blockNumber' => $block];
    }

    public function setNonce(ChainType $chain, string $address, int $nonce): void
    {
        $this->nonces[$chain->value.':'.strtolower($address)] = $nonce;
    }

    public function setBalance(ChainType $chain, string $address, string $wei): void
    {
        $this->balances[$chain->value.':'.strtolower($address)] = $wei;
    }

    public function setCallResult(string $data, string $result): void
    {
        $this->callResults[$data] = $result;
    }

    // ---- BlockchainProvider ------------------------------------------------

    public function blockNumber(ChainType $chain): int
    {
        return $this->head[$chain->value] ?? 0;
    }

    public function getLogs(ChainType $chain, array $filter): array
    {
        $addresses = isset($filter['address']) ? array_map('strtolower', (array) $filter['address']) : null;
        $topics = $filter['topics'] ?? [];
        $from = isset($filter['fromBlock']) ? (int) Evm::hexToInt((string) $filter['fromBlock']) : 0;
        $to = (isset($filter['toBlock']) && $filter['toBlock'] !== 'latest')
            ? (int) Evm::hexToInt((string) $filter['toBlock']) : PHP_INT_MAX;

        return array_values(array_filter($this->logs[$chain->value] ?? [], function (array $log) use ($addresses, $topics, $from, $to) {
            if ($addresses !== null && ! in_array(strtolower((string) $log['address']), $addresses, true)) {
                return false;
            }
            $block = (int) Evm::hexToInt((string) $log['blockNumber']);
            if ($block < $from || $block > $to) {
                return false;
            }
            foreach ($topics as $i => $topic) {
                if ($topic === null) {
                    continue;
                }
                $wanted = array_map('strtolower', (array) $topic);
                if (! in_array(strtolower((string) ($log['topics'][$i] ?? '')), $wanted, true)) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function getTransactionReceipt(ChainType $chain, string $txHash): ?array
    {
        return $this->receipts[$txHash] ?? null;
    }

    public function call(ChainType $chain, string $to, string $data): string
    {
        return $this->callResults[$data] ?? '0x'.str_repeat('0', 64);
    }

    public function getBalance(ChainType $chain, string $address): string
    {
        return $this->balances[$chain->value.':'.strtolower($address)] ?? '0';
    }

    public function getTransactionCount(ChainType $chain, string $address): int
    {
        return $this->nonces[$chain->value.':'.strtolower($address)] ?? 0;
    }

    public function maxPriorityFeePerGas(ChainType $chain): string
    {
        return $this->priorityFee;
    }

    public function baseFeePerGas(ChainType $chain): string
    {
        return $this->baseFee;
    }

    public function estimateGas(ChainType $chain, array $tx): string
    {
        return '65000';
    }

    public function sendRawTransaction(ChainType $chain, string $rawTx): string
    {
        $hash = '0x'.bin2hex(Evm::keccak((string) hex2bin(Evm::strip0x($rawTx))));
        $this->sent[] = ['chain' => $chain->value, 'raw' => $rawTx, 'hash' => $hash];

        return $hash;
    }
}
