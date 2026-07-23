<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Enums\ChainType;
use App\Models\RpcEndpoint;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Production EVM provider: standard JSON-RPC over HTTP with per-request failover
 * across the chain's configured `rpc_endpoints` (ordered by priority) plus a short
 * retry. The active vendor is chosen purely by which URLs are configured, so
 * Infura/Alchemy/QuickNode/self-hosted swap with zero code change.
 */
final class HttpBlockchainProvider implements BlockchainProvider
{
    public function blockNumber(ChainType $chain): int
    {
        return (int) Evm::hexToInt((string) $this->rpc($chain, 'eth_blockNumber'));
    }

    public function getLogs(ChainType $chain, array $filter): array
    {
        return (array) $this->rpc($chain, 'eth_getLogs', [$filter]);
    }

    public function getTransactionReceipt(ChainType $chain, string $txHash): ?array
    {
        $receipt = $this->rpc($chain, 'eth_getTransactionReceipt', [$txHash]);
        if (! is_array($receipt) || ! isset($receipt['blockNumber'])) {
            return null;
        }

        return [
            'status' => ($receipt['status'] ?? '0x1') !== '0x0',
            'blockNumber' => (int) Evm::hexToInt((string) $receipt['blockNumber']),
        ];
    }

    public function call(ChainType $chain, string $to, string $data): string
    {
        return (string) $this->rpc($chain, 'eth_call', [['to' => $to, 'data' => $data], 'latest']);
    }

    public function getBalance(ChainType $chain, string $address): string
    {
        return Evm::hexToInt((string) $this->rpc($chain, 'eth_getBalance', [$address, 'latest']));
    }

    public function getTransactionCount(ChainType $chain, string $address): int
    {
        return (int) Evm::hexToInt((string) $this->rpc($chain, 'eth_getTransactionCount', [$address, 'pending']));
    }

    public function maxPriorityFeePerGas(ChainType $chain): string
    {
        return Evm::hexToInt((string) $this->rpc($chain, 'eth_maxPriorityFeePerGas'));
    }

    public function baseFeePerGas(ChainType $chain): string
    {
        $block = $this->rpc($chain, 'eth_getBlockByNumber', ['latest', false]);

        return Evm::hexToInt((string) (is_array($block) ? ($block['baseFeePerGas'] ?? '0x0') : '0x0'));
    }

    public function estimateGas(ChainType $chain, array $tx): string
    {
        return Evm::hexToInt((string) $this->rpc($chain, 'eth_estimateGas', [$tx]));
    }

    public function sendRawTransaction(ChainType $chain, string $rawTx): string
    {
        return (string) $this->rpc($chain, 'eth_sendRawTransaction', [$rawTx]);
    }

    /**
     * Call a JSON-RPC method, failing over across the chain's active endpoints
     * (priority order) with a short retry per endpoint.
     *
     * @param  array<int, mixed>  $params
     */
    private function rpc(ChainType $chain, string $method, array $params = []): mixed
    {
        $payload = ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params];
        $lastError = 'no endpoints configured';

        foreach ($this->endpoints($chain) as $url) {
            try {
                $response = Http::timeout(12)->acceptJson()->asJson()->retry(2, 200)->post($url, $payload);
                $json = $response->json();

                if (is_array($json) && isset($json['error'])) {
                    $lastError = (string) ($json['error']['message'] ?? 'rpc error');

                    continue;
                }

                return is_array($json) ? ($json['result'] ?? null) : null;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new RuntimeException("All RPC endpoints failed for {$chain->value} ({$method}): {$lastError}");
    }

    /** @return array<int, string> */
    private function endpoints(ChainType $chain): array
    {
        $urls = RpcEndpoint::query()
            ->whereHas('chain', fn ($q) => $q->where('key', $chain->value))
            ->where('is_active', true)
            ->orderBy('priority')
            ->pluck('url')
            ->all();

        if ($urls === []) {
            $fallback = config("poisapay.custody.{$chain->value}.rpc");

            return $fallback ? [(string) $fallback] : [];
        }

        return $urls;
    }
}
