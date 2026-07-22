<?php

declare(strict_types=1);

namespace App\Domain\Chain\Tron;

use App\Enums\DepositStatus;
use App\Enums\OnchainTxStatus;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\Deposit;
use App\Models\DepositAddress;
use App\Models\OnchainTx;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Real Blockchain Monitor for TRON (TDD §6.1 step 4): scans every watched TRC20
 * deposit address for inbound USDT transfers and records an onchain_tx + deposit
 * (status=detected, 0 confs) for each new one. Deduplicated by
 * (chain, tx_hash, log_index) so overlapping/paginated scans never double-record.
 * Confirmations are advanced (and the deposit credited) by {@see AdvanceTronDepositsAction}.
 */
class ScanTronDepositsAction
{
    public function __construct(private readonly TronGridClient $client) {}

    /** @return int number of newly detected deposits */
    public function execute(): int
    {
        $chain = Chain::where('key', 'tron')->first();
        $contract = (string) config('poisapay.custody.tron.usdt_contract');
        if (! $chain || $contract === '') {
            return 0;
        }

        $asset = Asset::where('chain_id', $chain->id)
            ->where('contract_address', $contract)
            ->where('is_active', true)->first();
        if (! $asset) {
            return 0;
        }

        $detected = 0;

        DepositAddress::where('chain_id', $chain->id)->where('is_watched', true)->cursor()
            ->each(function (DepositAddress $address) use ($asset, $contract, &$detected) {
                foreach ($this->client->inboundTrc20($address->address, $contract) as $transfer) {
                    if ($transfer['to'] !== $address->address) {
                        continue;
                    }
                    if ($transfer['contract'] && strcasecmp($transfer['contract'], $contract) !== 0) {
                        continue;
                    }
                    if ($this->record($address, $asset, $transfer)) {
                        $detected++;
                    }
                }
            });

        return $detected;
    }

    /**
     * @param  array{txid: string, from: ?string, to: ?string, value: string, contract: ?string}  $transfer
     */
    private function record(DepositAddress $address, Asset $asset, array $transfer): bool
    {
        $exists = OnchainTx::where('chain_id', $address->chain_id)
            ->where('tx_hash', $transfer['txid'])
            ->where('log_index', 0)
            ->exists();
        if ($exists) {
            return false;
        }

        try {
            return DB::transaction(function () use ($address, $asset, $transfer): bool {
                $tx = OnchainTx::create([
                    'chain_id' => $address->chain_id,
                    'tx_hash' => $transfer['txid'],
                    'log_index' => 0,
                    'from_address' => $transfer['from'],
                    'to_address' => $address->address,
                    'asset_id' => $asset->id,
                    'amount' => $transfer['value'],
                    'confirmations' => 0,
                    'status' => OnchainTxStatus::Detected,
                    'direction' => 'in',
                ]);

                Deposit::create([
                    'user_id' => $address->user_id,
                    'deposit_address_id' => $address->id,
                    'asset_id' => $asset->id,
                    'source' => 'onchain',
                    'onchain_tx_id' => $tx->id,
                    'amount' => $transfer['value'],
                    'confirmations' => 0,
                    'required_confirmations' => $asset->requiredConfirmations(),
                    'status' => DepositStatus::Detected,
                ]);

                return true;
            });
        } catch (QueryException) {
            // Lost a race on the (chain, tx_hash, log_index) unique index — already recorded.
            return false;
        }
    }
}
