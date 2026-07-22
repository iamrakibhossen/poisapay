<?php

declare(strict_types=1);

namespace App\Domain\Chain\Tron;

use App\Models\RpcEndpoint;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin TronGrid / Tron full-node HTTP client. The base URL is taken from the
 * highest-priority active RpcEndpoint for the TRON chain, falling back to the
 * configured default (testnet Nile). Only the read + broadcast endpoints the
 * custody pipeline needs are exposed; all amounts are TRC20 base units (strings).
 */
class TronGridClient
{
    public function latestBlock(): int
    {
        return (int) $this->http()->post($this->url('/wallet/getnowblock'))
            ->json('block_header.raw_data.number', 0);
    }

    /**
     * Inbound TRC20 transfers of a specific token to an address (newest first).
     *
     * @return array<int, array{txid: string, from: ?string, to: ?string, value: string, contract: ?string}>
     */
    public function inboundTrc20(string $address, string $contract, int $limit = 50): array
    {
        $rows = $this->http()->get($this->url("/v1/accounts/{$address}/transactions/trc20"), [
            'only_to' => 'true',
            'only_confirmed' => 'true',
            'contract_address' => $contract,
            'limit' => $limit,
        ])->json('data', []);

        return collect($rows)->map(fn (array $e) => [
            'txid' => (string) ($e['transaction_id'] ?? ''),
            'from' => $e['from'] ?? null,
            'to' => $e['to'] ?? null,
            'value' => (string) ($e['value'] ?? '0'),
            'contract' => $e['token_info']['address'] ?? null,
        ])->filter(fn ($e) => $e['txid'] !== '')->values()->all();
    }

    /**
     * Block number + success flag for a transaction, or null if not yet in a block.
     *
     * @return array{blockNumber: int, success: bool}|null
     */
    public function transactionInfo(string $txid): ?array
    {
        $info = $this->http()->post($this->url('/wallet/gettransactioninfobyid'), ['value' => $txid])->json();

        if (! is_array($info) || empty($info['blockNumber'])) {
            return null;
        }

        // A TRC20 transfer's contract result; absent means a plain success.
        $result = $info['receipt']['result'] ?? 'SUCCESS';

        return [
            'blockNumber' => (int) $info['blockNumber'],
            'success' => $result === 'SUCCESS',
        ];
    }

    /**
     * Ask the node to build an unsigned TRC20 transfer (returns raw_data + txID).
     * The node does the protobuf assembly; we only sign the txID off-node.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function triggerSmartContract(array $params): array
    {
        return $this->http()->post($this->url('/wallet/triggersmartcontract'), $params)->json() ?? [];
    }

    /**
     * Broadcast a signed transaction.
     *
     * @param  array<string, mixed>  $signedTx
     * @return array<string, mixed>
     */
    public function broadcast(array $signedTx): array
    {
        return $this->http()->post($this->url('/wallet/broadcasttransaction'), $signedTx)->json() ?? [];
    }

    private function http(): PendingRequest
    {
        $request = Http::timeout(12)->acceptJson()->asJson();

        if ($key = config('poisapay.custody.tron.api_key')) {
            $request = $request->withHeaders(['TRON-PRO-API-KEY' => $key]);
        }

        return $request;
    }

    private function url(string $path): string
    {
        return $this->baseUrl().$path;
    }

    private function baseUrl(): string
    {
        $endpoint = RpcEndpoint::query()
            ->whereHas('chain', fn ($q) => $q->where('key', 'tron'))
            ->where('is_active', true)
            ->orderBy('priority')
            ->value('url');

        return rtrim($endpoint ?: (string) config('poisapay.custody.tron.rpc'), '/');
    }
}
