<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Enums\ChainType;
use App\Enums\DepositStatus;
use App\Enums\OnchainTxStatus;
use App\Events\DepositDetected;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\Deposit;
use App\Models\DepositAddress;
use App\Models\OnchainTx;
use Illuminate\Support\Facades\DB;

/**
 * EVM deposit watcher (Wave 2, multi-token). Queries ERC-20 `Transfer` logs to the
 * platform's watched deposit addresses across EVERY active token on the chain
 * (USDT, USDC, …) over a bounded block window, and records new detections as
 * OnchainTx + Deposit rows. Idempotent via the (chain_id, tx_hash, log_index)
 * unique key, so re-scanning an overlapping window is safe.
 */
class ScanEvmDepositsAction
{
    public function __construct(private readonly BlockchainProvider $chain) {}

    /** Returns the number of newly detected deposits for the chain. */
    public function execute(ChainType $chainType): int
    {
        $chain = Chain::where('key', $chainType->value)->first();
        if (! $chain) {
            return 0;
        }

        // Every active ERC-20 asset on this chain, keyed by lowercase contract.
        $assetsByContract = Asset::where('chain_id', $chain->id)
            ->whereNotNull('contract_address')
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (Asset $a) => strtolower((string) $a->contract_address));
        if ($assetsByContract->isEmpty()) {
            return 0;
        }

        $watched = DepositAddress::where('chain_id', $chain->id)
            ->where('is_watched', true)
            ->get()
            ->keyBy(fn (DepositAddress $a) => strtolower($a->address));
        if ($watched->isEmpty()) {
            return 0;
        }

        $head = $this->chain->blockNumber($chainType);
        if ($head <= 0) {
            return 0;
        }

        $range = (int) config('poisapay.custody.evm_scan_range', 500);
        $from = max(0, $head - $range);
        $tokenDecimals = (int) config("poisapay.custody.{$chainType->value}.token_decimals", 6);
        $addressTopics = $watched->keys()->map(fn ($a) => '0x'.Evm::pad32($a))->all();

        $logs = $this->chain->getLogs($chainType, [
            'fromBlock' => Evm::intToHex((string) $from),
            'toBlock' => Evm::intToHex((string) $head),
            'address' => $assetsByContract->keys()->all(), // all token contracts (eth_getLogs accepts an array)
            'topics' => [Abi::transferEventTopic(), null, $addressTopics],
        ]);

        $count = 0;
        foreach ($logs as $log) {
            $asset = $assetsByContract->get(strtolower((string) ($log['address'] ?? '')));
            $address = $watched->get(strtolower(Abi::decodeTransferLog($log)['to']));
            if (! $asset || ! $address) {
                continue;
            }

            $decoded = Abi::decodeTransferLog($log);
            // Normalise the on-chain amount to the asset's ledger precision (e.g. BSC 18 -> 6).
            $decoded['amount'] = Evm::scaleDecimals($decoded['amount'], $tokenDecimals, $asset->decimals);

            if ($this->record($chain, $address, $asset, $decoded, $log)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array{from: string, to: string, amount: string}  $decoded
     * @param  array<string, mixed>  $log
     */
    private function record(Chain $chain, DepositAddress $address, Asset $asset, array $decoded, array $log): bool
    {
        $txHash = strtolower((string) ($log['transactionHash'] ?? ''));
        $logIndex = (int) Evm::hexToInt((string) ($log['logIndex'] ?? '0x0'));
        $blockNumber = (int) Evm::hexToInt((string) ($log['blockNumber'] ?? '0x0'));

        $exists = OnchainTx::where('chain_id', $chain->id)
            ->where('tx_hash', $txHash)
            ->where('log_index', $logIndex)
            ->exists();
        if ($exists) {
            return false;
        }

        DB::transaction(function () use ($chain, $address, $asset, $decoded, $txHash, $logIndex, $blockNumber) {
            $tx = OnchainTx::create([
                'chain_id' => $chain->id,
                'tx_hash' => $txHash,
                'log_index' => $logIndex,
                'from_address' => $decoded['from'],
                'to_address' => $address->address,
                'asset_id' => $asset->id,
                'amount' => $decoded['amount'],
                'block_number' => $blockNumber,
                'confirmations' => 0,
                'status' => OnchainTxStatus::Detected,
                'direction' => 'in',
            ]);

            $deposit = Deposit::create([
                'user_id' => $address->user_id,
                'deposit_address_id' => $address->id,
                'asset_id' => $asset->id,
                'source' => 'onchain',
                'onchain_tx_id' => $tx->id,
                'amount' => $decoded['amount'],
                'confirmations' => 0,
                'required_confirmations' => $asset->requiredConfirmations(),
                'status' => DepositStatus::Detected,
            ]);

            DepositDetected::dispatch($deposit->id);
        });

        return true;
    }
}
