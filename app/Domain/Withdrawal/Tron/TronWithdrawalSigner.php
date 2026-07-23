<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Tron;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Chain\Tron\Trc20;
use App\Domain\Chain\Tron\TronGridClient;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Secp256k1Signer;
use App\Domain\Withdrawal\SettleWithdrawalAction;
use App\Enums\ChainType;
use App\Enums\OnchainTxStatus;
use App\Enums\WithdrawalStatus;
use App\Models\OnchainTx;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;

/**
 * Signs + broadcasts an approved TRON USDT withdrawal from the hot wallet.
 *
 * Flow (TDD §3.2, §6.3): the node builds the unsigned TRC20 transfer (protobuf
 * assembly stays server-side), we sign only the txID off-node with the hot
 * wallet key from {@see SignerKeyProvider}, then broadcast. Funds were already
 * reserved (user:available → user:locked) at request time; ledger settlement
 * happens later in {@see SettleWithdrawalAction} once the
 * broadcast tx confirms. Never re-derives or persists a private key.
 */
class TronWithdrawalSigner
{
    public function __construct(
        private readonly TronGridClient $client,
        private readonly SignerKeyProvider $keys,
        private readonly Secp256k1Signer $signer,
    ) {}

    public function execute(Withdrawal $withdrawal): Withdrawal
    {
        if ($withdrawal->status !== WithdrawalStatus::Approved) {
            return $withdrawal; // only approved withdrawals are broadcast
        }

        $withdrawal->loadMissing('asset.chain');
        $contract = (string) config('poisapay.custody.tron.usdt_contract');

        // Only TRON TRC20-USDT withdrawals are handled here.
        if ($withdrawal->asset->chain?->key?->value !== 'tron' || $withdrawal->asset->contract_address !== $contract) {
            return $withdrawal;
        }

        $hotAddress = $this->keys->hotWalletAddress(ChainType::Tron);

        $built = $this->client->triggerSmartContract([
            'owner_address' => $hotAddress,
            'contract_address' => $contract,
            'function_selector' => 'transfer(address,uint256)',
            'parameter' => Trc20::transferCalldata($withdrawal->to_address, $withdrawal->amount),
            'fee_limit' => 100_000_000,
            'call_value' => 0,
            'visible' => true,
        ]);

        $tx = $built['transaction'] ?? null;
        $txId = $tx['txID'] ?? null;
        if (! is_array($tx) || ! $txId) {
            return $this->fail($withdrawal, $built['result']['message'] ?? 'Could not build the transfer transaction.');
        }

        $signature = $this->signer->sign($txId, $this->keys->hotWalletPrivateKey(ChainType::Tron));
        $tx['signature'] = [$signature];

        $result = $this->client->broadcast($tx);
        if (! ($result['result'] ?? false)) {
            return $this->fail($withdrawal, $result['message'] ?? 'Broadcast rejected by the network.');
        }

        return DB::transaction(function () use ($withdrawal, $txId, $hotAddress): Withdrawal {
            $onchain = OnchainTx::create([
                'chain_id' => $withdrawal->asset->chain_id,
                'tx_hash' => $txId,
                'log_index' => 0,
                'from_address' => $hotAddress,
                'to_address' => $withdrawal->to_address,
                'asset_id' => $withdrawal->asset_id,
                'amount' => $withdrawal->amount,
                'confirmations' => 0,
                'status' => OnchainTxStatus::Detected,
                'direction' => 'out',
            ]);

            $withdrawal->update([
                'status' => WithdrawalStatus::Broadcast,
                'onchain_tx_id' => $onchain->id,
            ]);

            ActivityLogger::log('withdrawal.broadcast', $withdrawal, ['tx' => $txId], 'Withdrawal broadcast');

            return $withdrawal->refresh();
        });
    }

    private function fail(Withdrawal $withdrawal, string $reason): Withdrawal
    {
        $withdrawal->update(['status' => WithdrawalStatus::Failed, 'failure_reason' => $reason]);
        ActivityLogger::log('withdrawal.broadcast.failed', $withdrawal, ['reason' => $reason], 'Withdrawal broadcast failed');

        return $withdrawal->refresh();
    }
}
