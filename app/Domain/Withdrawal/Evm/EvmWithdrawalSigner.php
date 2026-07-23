<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Evm;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Chain\Evm\Abi;
use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Evm\Eip1559Transaction;
use App\Domain\Chain\Evm\Evm;
use App\Domain\Chain\Evm\GasEstimationService;
use App\Domain\Chain\Evm\NonceManager;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Secp256k1Signer;
use App\Enums\OnchainTxStatus;
use App\Enums\WithdrawalStatus;
use App\Models\OnchainTx;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * EVM withdrawal signer + broadcaster (Wave 2, mirrors TronWithdrawalSigner).
 * Builds a signed EIP-1559 ERC-20 transfer from the hot wallet, broadcasts it via
 * the {@see BlockchainProvider}, and records the on-chain tx. Nonce + gas come from
 * the dedicated services; the private key never leaves {@see SignerKeyProvider}.
 * Only approved USDT-on-EVM withdrawals are handled here (others are left untouched).
 */
class EvmWithdrawalSigner
{
    public function __construct(
        private readonly BlockchainProvider $chain,
        private readonly SignerKeyProvider $keys,
        private readonly Secp256k1Signer $signer,
        private readonly NonceManager $nonces,
        private readonly GasEstimationService $gas,
    ) {}

    public function execute(Withdrawal $withdrawal): Withdrawal
    {
        if ($withdrawal->status !== WithdrawalStatus::Approved) {
            return $withdrawal;
        }

        $withdrawal->loadMissing('asset.chain');
        $asset = $withdrawal->asset;
        $chain = $asset->chain;
        if (! $chain || ! $asset->chain_id) {
            return $withdrawal;
        }

        $chainType = $chain->key;
        $contract = (string) $asset->contract_address;
        // Handle any EVM ERC-20 (USDT, USDC, …); native + non-EVM settle elsewhere.
        if (! $chainType->isEvm() || $contract === '') {
            return $withdrawal;
        }

        try {
            $hotAddress = $this->keys->hotWalletAddress($chainType);
            $privateKey = $this->keys->hotWalletPrivateKey($chainType);
            $nonce = $this->nonces->next($chainType, $hotAddress);
            $head = $this->chain->blockNumber($chainType);
            $gas = $this->gas->suggest($chainType);
            $chainId = (int) config("poisapay.custody.{$chainType->value}.chain_id");

            // Scale the ledger amount up to the token's on-chain precision (e.g. 6 -> BSC 18).
            $tokenDecimals = (int) config("poisapay.custody.{$chainType->value}.token_decimals", 6);
            $onchainAmount = Evm::scaleDecimals($withdrawal->amount, $asset->decimals, $tokenDecimals);

            $tx = new Eip1559Transaction(
                chainId: $chainId,
                nonce: (string) $nonce,
                maxPriorityFeePerGas: $gas['maxPriorityFeePerGas'],
                maxFeePerGas: $gas['maxFeePerGas'],
                gasLimit: $gas['gasLimit'],
                to: $contract,
                value: '0',
                data: Abi::erc20Transfer($withdrawal->to_address, $onchainAmount),
            );

            $signature = $this->signer->sign($tx->signingHash(), $privateKey);
            $raw = $tx->serialize(
                substr($signature, 0, 64),
                substr($signature, 64, 64),
                (int) hexdec(substr($signature, 128, 2)),
            );

            $txHash = $this->chain->sendRawTransaction($chainType, $raw);
        } catch (Throwable $e) {
            return $this->fail($withdrawal, $e->getMessage());
        }

        if (! str_starts_with($txHash, '0x')) {
            return $this->fail($withdrawal, 'Broadcast returned no transaction hash.');
        }

        return DB::transaction(function () use ($withdrawal, $asset, $txHash, $hotAddress, $nonce, $head): Withdrawal {
            $onchain = OnchainTx::create([
                'chain_id' => $asset->chain_id,
                'tx_hash' => strtolower($txHash),
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
                'broadcast_nonce' => $nonce,
                'broadcast_block' => $head,
                'broadcast_attempts' => $withdrawal->broadcast_attempts + 1,
            ]);

            ActivityLogger::log('withdrawal.broadcast', $withdrawal, ['tx' => $txHash]);

            return $withdrawal->refresh();
        });
    }

    private function fail(Withdrawal $withdrawal, string $reason): Withdrawal
    {
        $withdrawal->update(['status' => WithdrawalStatus::Failed, 'failure_reason' => $reason]);
        ActivityLogger::log('withdrawal.broadcast.failed', $withdrawal, ['reason' => $reason]);

        return $withdrawal->refresh();
    }
}
