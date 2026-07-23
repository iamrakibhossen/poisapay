<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Tron\SettleTronSweepsAction;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Enums\OnchainTxStatus;
use App\Enums\SweepStatus;
use App\Events\SweepConfirmed;
use App\Events\SweepFailed;
use App\Models\Asset;
use App\Models\OnchainTx;
use App\Models\Sweep;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Settle broadcast EVM sweeps once their tx confirms to the required depth. The ledger
 * move (treasury:pending → treasury:hot) happens ONLY after confirmation, so the books
 * follow the chain. Idempotent (keyed entry). A reverted sweep tx is marked Failed. The
 * EVM sibling of {@see SettleTronSweepsAction}.
 */
class SettleEvmSweepsAction
{
    public function __construct(
        private readonly BlockchainProvider $chain,
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(): int
    {
        $settled = 0;

        Sweep::where('status', SweepStatus::Broadcast->value)->get()->each(function (Sweep $sweep) use (&$settled) {
            $tx = OnchainTx::find($sweep->onchain_tx_id);
            $asset = Asset::find($sweep->asset_id);
            if ($tx === null || $asset === null) {
                return;
            }

            $chain = $asset->chain;
            if ($chain === null || ! $chain->is_evm) {
                return; // handled by the TRON settler
            }
            $chainType = $chain->key;

            $receipt = $this->chain->getTransactionReceipt($chainType, $tx->tx_hash);
            if ($receipt === null) {
                return; // not yet mined
            }

            if (! $receipt['status']) {
                $sweep->update(['status' => SweepStatus::Failed]);
                $tx->update(['status' => OnchainTxStatus::Orphaned]);
                SweepFailed::dispatch($sweep->id, 'on-chain revert');

                return;
            }

            $confirmations = $this->chain->blockNumber($chainType) - $receipt['blockNumber'] + 1;
            if ($confirmations < $asset->requiredConfirmations()) {
                return; // wait for depth
            }

            DB::transaction(function () use ($sweep, $tx, $asset, $confirmations) {
                $amount = Money::ofBase($sweep->amount, $asset->decimals, $asset->symbol);

                $pending = $this->accounts->system(LedgerAccountType::TreasuryPending, $asset->id);
                $hot = $this->accounts->system(LedgerAccountType::TreasuryHot, $asset->id);

                $entry = $this->ledger->post(new EntryData(
                    type: 'sweep.settle',
                    idempotencyKey: "sweep:settle:{$sweep->id}",
                    lines: [
                        PostingLine::debit($hot->id, $asset->id, $amount),
                        PostingLine::credit($pending->id, $asset->id, $amount),
                    ],
                    memo: "Sweep {$asset->symbol} to hot wallet",
                    metadata: ['sweep_id' => $sweep->id],
                ));

                $sweep->update(['status' => SweepStatus::Swept, 'settle_entry_id' => $entry->id]);
                $tx->update(['status' => OnchainTxStatus::Confirmed, 'confirmations' => $confirmations]);
            });

            SweepConfirmed::dispatch($sweep->id);

            $settled++;
        });

        return $settled;
    }
}
