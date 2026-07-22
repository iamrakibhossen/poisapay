<?php

declare(strict_types=1);

namespace App\Domain\Chain\Tron;

use App\Domain\Deposit\CreditDepositAction;
use App\Enums\DepositStatus;
use App\Enums\OnchainTxStatus;
use App\Models\Chain;
use App\Models\Deposit;
use App\Models\OnchainTx;

/**
 * Advances confirmations on detected TRON deposits from real chain state and
 * credits them once they reach the required depth (TDD §6.1 step 6). Handles the
 * two failure modes:
 *  - reorg: a tx that previously had a block number is no longer found → orphaned
 *  - reverted: the transfer's contract result is not SUCCESS → orphaned
 * Crediting itself is delegated to {@see CreditDepositAction} (idempotent), so a
 * re-run never double-credits.
 */
class AdvanceTronDepositsAction
{
    public function __construct(
        private readonly TronGridClient $client,
        private readonly CreditDepositAction $credit,
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

        Deposit::with('onchainTx')
            ->whereIn('status', [DepositStatus::Detected->value, DepositStatus::Confirming->value])
            ->whereHas('onchainTx', fn ($q) => $q->where('chain_id', $chain->id))
            ->get()
            ->each(fn (Deposit $deposit) => $this->advance($deposit, $latest));
    }

    private function advance(Deposit $deposit, int $latest): void
    {
        $tx = $deposit->onchainTx;
        if (! $tx) {
            return;
        }

        $info = $this->client->transactionInfo($tx->tx_hash);

        if ($info === null) {
            // Dropped after having been mined → reorged out. Not yet mined → wait.
            if ($tx->block_number) {
                $this->orphan($deposit, $tx);
            }

            return;
        }

        if (! $info['success']) {
            $this->orphan($deposit, $tx);

            return;
        }

        $confs = max(0, min($latest - $info['blockNumber'] + 1, $deposit->required_confirmations));
        $deep = $confs >= $deposit->required_confirmations;

        $tx->update([
            'block_number' => $info['blockNumber'],
            'confirmations' => $confs,
            'status' => $deep ? OnchainTxStatus::Confirmed : OnchainTxStatus::Confirming,
        ]);
        $deposit->update([
            'confirmations' => $confs,
            'status' => $deep ? DepositStatus::Confirming : DepositStatus::Detected,
        ]);

        if ($deep) {
            $this->credit->execute($deposit->fresh());
        }
    }

    private function orphan(Deposit $deposit, OnchainTx $tx): void
    {
        if ($deposit->status === DepositStatus::Credited) {
            return; // never un-credit here; that is a manual reconciliation case
        }

        $tx->update(['status' => OnchainTxStatus::Orphaned]);
        $deposit->update(['status' => DepositStatus::Orphaned]);
    }
}
