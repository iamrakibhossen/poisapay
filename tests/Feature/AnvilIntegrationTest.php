<?php

declare(strict_types=1);

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Evm\HttpBlockchainProvider;
use App\Enums\ChainType;
use Illuminate\Support\Facades\Http;

/**
 * Real JSON-RPC integration against a local Ethereum node (Foundry Anvil).
 * SKIPPED unless a node is reachable — start one with `anvil` (or set ANVIL_RPC),
 * then this proves HttpBlockchainProvider speaks the wire protocol end-to-end.
 * CI runs it only when an Anvil service is provided.
 */
function anvilRpc(): ?string
{
    $url = env('ANVIL_RPC', 'http://127.0.0.1:8545');
    try {
        $res = Http::timeout(2)->asJson()->post($url, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_blockNumber', 'params' => []]);

        return ($res->ok() && isset($res->json()['result'])) ? $url : null;
    } catch (Throwable) {
        return null;
    }
}

it('talks real JSON-RPC to a local Anvil node', function () {
    $url = anvilRpc();
    if (! $url) {
        $this->markTestSkipped('Anvil not reachable at 127.0.0.1:8545 — run `anvil` (or set ANVIL_RPC) to enable.');
    }

    config(['providers.blockchain.driver' => 'http', 'poisapay.custody.ethereum.rpc' => $url]);
    app()->forgetInstance(BlockchainProvider::class);
    $provider = app(BlockchainProvider::class);

    expect($provider)->toBeInstanceOf(HttpBlockchainProvider::class)
        ->and($provider->blockNumber(ChainType::Ethereum))->toBeGreaterThanOrEqual(0)
        ->and($provider->maxPriorityFeePerGas(ChainType::Ethereum))->toBeNumeric();

    // Anvil's default funded account has a non-negative balance and nonce.
    $account = '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266';
    expect($provider->getBalance(ChainType::Ethereum, $account))->toBeNumeric()
        ->and($provider->getTransactionCount(ChainType::Ethereum, $account))->toBeGreaterThanOrEqual(0);
});
