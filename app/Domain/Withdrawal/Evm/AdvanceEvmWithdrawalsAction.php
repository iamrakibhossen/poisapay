<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Evm;

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Withdrawal\SettleWithdrawalAction;
use App\Enums\ChainType;
use App\Enums\OnchainTxStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Chain;
use App\Models\OnchainTx;
use App\Models\Withdrawal;

/**
 * EVM withdrawal confirmation engine (Wave 2, mirrors AdvanceTronWithdrawalsAction).
 * Advances broadcast withdrawals against the chain head via the receipt, fails
 * reorged/reverted ones, and settles the ledger (idempotently) once confirmed via
 * the shared {@see SettleWithdrawalAction}.
 */
class AdvanceEvmWithdrawalsAction
{
    public function __construct(
        private readonly BlockchainProvider $chain,
        private readonly SettleWithdrawalAction $settle,
    ) {}

    public function execute(ChainType $chainType): void
    {
        $chain = Chain::where('key', $chainType->value)->first();
        if (! $chain) {
            return;
        }

        $head = $this->chain->blockNumber($chainType);
        if ($head <= 0) {
            return;
        }

        $withdrawals = Withdrawal::where('status', WithdrawalStatus::Broadcast)
            ->whereNotNull('onchain_tx_id')
            ->whereHas('asset', fn ($q) => $q->where('chain_id', $chain->id))
            ->with('asset')
            ->get();

        foreach ($withdrawals as $withdrawal) {
            $tx = OnchainTx::find($withdrawal->onchain_tx_id);
            if ($tx) {
                $this->advance($chainType, $withdrawal, $tx, $head);
            }
        }
    }

    private function advance(ChainType $chainType, Withdrawal $withdrawal, OnchainTx $tx, int $head): void
    {
        $receipt = $this->chain->getTransactionReceipt($chainType, $tx->tx_hash);

        if ($receipt === null) {
            if ($tx->block_number) {
                $this->fail($withdrawal, $tx, 'Transaction dropped from the chain (reorg).');
            }

            return;
        }

        if ($receipt['status'] === false) {
            $this->fail($withdrawal, $tx, 'Transaction reverted on-chain.');

            return;
        }

        $required = $withdrawal->asset->requiredConfirmations();
        $confs = max(0, min($head - $receipt['blockNumber'] + 1, $required));

        $tx->update([
            'block_number' => $receipt['blockNumber'],
            'confirmations' => $confs,
            'status' => $confs >= $required ? OnchainTxStatus::Confirmed : OnchainTxStatus::Confirming,
        ]);

        if ($confs >= $required) {
            $this->settle->execute($withdrawal->fresh(), $tx->tx_hash);
        }
    }

    private function fail(Withdrawal $withdrawal, OnchainTx $tx, string $reason): void
    {
        $tx->update(['status' => OnchainTxStatus::Failed]);
        $withdrawal->update(['status' => WithdrawalStatus::Failed, 'failure_reason' => $reason]);
    }
}
