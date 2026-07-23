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
use App\Enums\WithdrawalStatus;
use App\Models\OnchainTx;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Replace-By-Fee for stuck EVM withdrawals. A withdrawal that has been Broadcast but
 * has no receipt after `withdrawal_stuck_blocks` is rebroadcast with the SAME nonce
 * (so it replaces, not duplicates) and a fee bumped by 25% per prior attempt (each
 * attempt strictly exceeds the last, satisfying node replacement rules). After
 * `withdrawal_max_broadcast_attempts` it is left for the dead-letter path.
 *
 * Opt-in and default OFF via `withdrawal_batching_enabled`; the nonce comes from the
 * same shared {@see NonceManager}-allocated value recorded at
 * first broadcast, so RBF never collides with new withdrawals or gas top-ups.
 */
class RebroadcastStuckWithdrawalsAction
{
    public function __construct(
        private readonly BlockchainProvider $chain,
        private readonly SignerKeyProvider $keys,
        private readonly Secp256k1Signer $signer,
        private readonly GasEstimationService $gas,
    ) {}

    public function execute(): int
    {
        if (! feature('withdrawal_batching_enabled', false)) {
            return 0;
        }

        $stuckAfter = (int) config('poisapay.custody.withdrawal_stuck_blocks', 30);
        $maxAttempts = (int) config('poisapay.custody.withdrawal_max_broadcast_attempts', 3);
        $replaced = 0;

        Withdrawal::where('status', WithdrawalStatus::Broadcast->value)
            ->whereNotNull('broadcast_nonce')
            ->whereNotNull('broadcast_block')
            ->with('asset.chain')
            ->get()
            ->each(function (Withdrawal $withdrawal) use ($stuckAfter, $maxAttempts, &$replaced) {
                $chain = $withdrawal->asset->chain;
                if ($chain === null || ! $chain->key->isEvm() || $withdrawal->broadcast_attempts >= $maxAttempts) {
                    return;
                }
                $chainType = $chain->key;

                $onchain = OnchainTx::find($withdrawal->onchain_tx_id);
                if ($onchain === null) {
                    return;
                }

                // Already mined? Not stuck — the advance/settle path owns it.
                if ($this->chain->getTransactionReceipt($chainType, $onchain->tx_hash) !== null) {
                    return;
                }

                $head = $this->chain->blockNumber($chainType);
                if ($head - (int) $withdrawal->broadcast_block < $stuckAfter) {
                    return; // still within the wait window
                }

                try {
                    $gas = $this->gas->suggest($chainType);
                    $factor = (string) (100 + 25 * $withdrawal->broadcast_attempts); // strictly increasing per attempt
                    $tokenDecimals = (int) config("poisapay.custody.{$chainType->value}.token_decimals", 6);

                    $tx = new Eip1559Transaction(
                        chainId: (int) config("poisapay.custody.{$chainType->value}.chain_id"),
                        nonce: (string) $withdrawal->broadcast_nonce, // SAME nonce → replacement
                        maxPriorityFeePerGas: bcdiv(bcmul($gas['maxPriorityFeePerGas'], $factor), '100'),
                        maxFeePerGas: bcdiv(bcmul($gas['maxFeePerGas'], $factor), '100'),
                        gasLimit: $gas['gasLimit'],
                        to: (string) $withdrawal->asset->contract_address,
                        value: '0',
                        data: Abi::erc20Transfer($withdrawal->to_address, Evm::scaleDecimals($withdrawal->amount, $withdrawal->asset->decimals, $tokenDecimals)),
                    );
                    $signature = $this->signer->sign($tx->signingHash(), $this->keys->hotWalletPrivateKey($chainType));
                    $raw = $tx->serialize(substr($signature, 0, 64), substr($signature, 64, 64), (int) hexdec(substr($signature, 128, 2)));
                    $newHash = $this->chain->sendRawTransaction($chainType, $raw);
                } catch (Throwable $e) {
                    $withdrawal->increment('broadcast_attempts');
                    ActivityLogger::log('withdrawal.rbf.failed', $withdrawal, ['reason' => $e->getMessage()]);

                    return;
                }

                DB::transaction(function () use ($withdrawal, $onchain, $newHash, $head) {
                    $onchain->update(['tx_hash' => strtolower($newHash)]); // track the replacement
                    $withdrawal->update([
                        'broadcast_block' => $head,
                        'broadcast_attempts' => $withdrawal->broadcast_attempts + 1,
                    ]);
                });

                ActivityLogger::log('withdrawal.rbf', $withdrawal, ['tx' => $newHash, 'nonce' => $withdrawal->broadcast_nonce, 'attempt' => $withdrawal->broadcast_attempts]);
                $replaced++;
            });

        return $replaced;
    }
}
