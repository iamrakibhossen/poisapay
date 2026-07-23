<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Deposit\CreditDepositAction;
use App\Enums\ChainType;
use App\Enums\DepositStatus;
use App\Enums\OnchainTxStatus;
use App\Models\Chain;
use App\Models\Deposit;
use App\Models\OnchainTx;

/**
 * EVM confirmation engine (Wave 2, mirrors AdvanceTronDepositsAction). Advances
 * detected/confirming deposits against the chain head via the transaction receipt,
 * orphans reorged/reverted transactions, and credits (idempotently) once a deposit
 * reaches its required depth — reusing the shared {@see CreditDepositAction}.
 */
class AdvanceEvmDepositsAction
{
    public function __construct(
        private readonly BlockchainProvider $chain,
        private readonly CreditDepositAction $credit,
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

        $deposits = Deposit::whereIn('status', [DepositStatus::Detected, DepositStatus::Confirming])
            ->whereHas('onchainTx', fn ($q) => $q->where('chain_id', $chain->id))
            ->with('onchainTx')
            ->get();

        foreach ($deposits as $deposit) {
            $this->advance($chainType, $deposit, $head);
        }
    }

    private function advance(ChainType $chainType, Deposit $deposit, int $head): void
    {
        $tx = $deposit->onchainTx;
        if (! $tx) {
            return;
        }

        $receipt = $this->chain->getTransactionReceipt($chainType, $tx->tx_hash);

        // Reorg: previously mined, now unknown.
        if ($receipt === null) {
            if ($tx->block_number) {
                $this->orphan($deposit, $tx);
            }

            return;
        }

        // Reverted on-chain.
        if ($receipt['status'] === false) {
            $this->orphan($deposit, $tx);

            return;
        }

        $required = (int) $deposit->required_confirmations;
        $confs = max(0, min($head - $receipt['blockNumber'] + 1, $required));
        $deep = $confs >= $required;

        $tx->update([
            'block_number' => $receipt['blockNumber'],
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
            return; // never un-credit
        }
        $tx->update(['status' => OnchainTxStatus::Orphaned]);
        $deposit->update(['status' => DepositStatus::Orphaned]);
    }
}
