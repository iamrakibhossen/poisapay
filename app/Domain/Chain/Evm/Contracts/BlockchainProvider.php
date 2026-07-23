<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm\Contracts;

use App\Domain\Chain\Evm\FakeBlockchainProvider;
use App\Enums\ChainType;

/**
 * EVM JSON-RPC provider (Wave 2). A thin, vendor-neutral surface over the standard
 * Ethereum JSON-RPC methods, so Infura / Alchemy / QuickNode / a self-hosted node
 * (or Anvil) connect by configuration only — the RPC URL(s) live in `rpc_endpoints`.
 * The in-memory {@see FakeBlockchainProvider} implements the
 * same contract for deterministic tests.
 */
interface BlockchainProvider
{
    /** eth_blockNumber — current head block height. */
    public function blockNumber(ChainType $chain): int;

    /**
     * eth_getLogs — raw logs matching the filter (fromBlock/toBlock/address/topics as
     * 0x-hex where applicable).
     *
     * @param  array<string, mixed>  $filter
     * @return array<int, array<string, mixed>>
     */
    public function getLogs(ChainType $chain, array $filter): array;

    /**
     * eth_getTransactionReceipt — null while pending/unknown.
     *
     * @return array{status: bool, blockNumber: int}|null
     */
    public function getTransactionReceipt(ChainType $chain, string $txHash): ?array;

    /** eth_call — returns the 0x-hex result (e.g. an ERC-20 balanceOf word). */
    public function call(ChainType $chain, string $to, string $data): string;

    /** eth_getBalance (latest) — native balance in wei as a decimal string. */
    public function getBalance(ChainType $chain, string $address): string;

    /** eth_getTransactionCount (pending) — the next nonce for $address. */
    public function getTransactionCount(ChainType $chain, string $address): int;

    /** eth_maxPriorityFeePerGas — suggested tip in wei (decimal). */
    public function maxPriorityFeePerGas(ChainType $chain): string;

    /** baseFeePerGas of the latest block in wei (decimal). */
    public function baseFeePerGas(ChainType $chain): string;

    /**
     * eth_estimateGas — gas units (decimal) for the given call.
     *
     * @param  array<string, mixed>  $tx
     */
    public function estimateGas(ChainType $chain, array $tx): string;

    /** eth_sendRawTransaction — broadcast a 0x raw signed tx; returns the tx hash. */
    public function sendRawTransaction(ChainType $chain, string $rawTx): string;
}
