<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal\Tron;

use App\Domain\Chain\Tron\TronGridClient;
use App\Domain\Withdrawal\SettleWithdrawalAction;
use App\Enums\OnchainTxStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Chain;
use App\Models\OnchainTx;
use App\Models\Withdrawal;

/**
 * Advances broadcast TRON withdrawals from real chain state and settles them in
 * the ledger once the broadcast tx reaches the required confirmations (TDD §6.3
 * step 6). A reverted or reorged-out tx is marked Failed for manual reconciliation
 * (funds remain locked — never silently released here). Settlement is delegated
 * to {@see SettleWithdrawalAction} (idempotent), so re-runs never double-settle.
 */
class AdvanceTronWithdrawalsAction
{
    public function __construct(
        private readonly TronGridClient $client,
        private readonly SettleWithdrawalAction $settle,
    ) {}

    public function execute(): void
    {
        $chain = Chain::where('key', 'tron')->first();
        if (! $chain) {
            return;
        }

        $latest = $this->client->latestBlock();
        if ($latest <= 0) {
            return;
        }

        Withdrawal::where('status', WithdrawalStatus::Broadcast->value)
            ->whereNotNull('onchain_tx_id')
            ->get()
            ->each(function (Withdrawal $withdrawal) use ($chain, $latest) {
                $tx = OnchainTx::where('id', $withdrawal->onchain_tx_id)->where('chain_id', $chain->id)->first();
                if (! $tx) {
                    return;
                }
                $this->advance($withdrawal, $tx, $latest);
            });
    }

    private function advance(Withdrawal $withdrawal, OnchainTx $tx, int $latest): void
    {
        $info = $this->client->transactionInfo($tx->tx_hash);

        if ($info === null) {
            if ($tx->block_number) {
                $this->fail($withdrawal, $tx, 'Broadcast transaction dropped from the chain (possible reorg).');
            }

            return;
        }

        if (! $info['success']) {
            $this->fail($withdrawal, $tx, 'On-chain transfer reverted.');

            return;
        }

        $required = $withdrawal->asset->requiredConfirmations();
        $confs = max(0, min($latest - $info['blockNumber'] + 1, $required));

        $tx->update([
            'block_number' => $info['blockNumber'],
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
