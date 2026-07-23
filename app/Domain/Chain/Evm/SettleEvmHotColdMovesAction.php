<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Tron\SettleTronHotColdMovesAction;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Enums\OnchainTxStatus;
use App\Models\Asset;
use App\Models\OnchainTx;
use App\Models\TreasuryMove;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Settle broadcast EVM hot→cold moves once their tx confirms to the required depth.
 * Posts treasury:hot → treasury:cold (debit cold / credit hot) ONLY after confirmation.
 * Idempotent (keyed entry); a reverted move is marked failed. EVM sibling of
 * {@see SettleTronHotColdMovesAction}.
 */
class SettleEvmHotColdMovesAction
{
    public function __construct(
        private readonly BlockchainProvider $chain,
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(): int
    {
        $settled = 0;

        TreasuryMove::where('status', 'broadcast')->where('direction', 'hot_to_cold')->get()->each(function (TreasuryMove $move) use (&$settled) {
            $tx = OnchainTx::find($move->onchain_tx_id);
            $asset = Asset::find($move->asset_id);
            if ($tx === null || $asset === null) {
                return;
            }

            $chain = $asset->chain;
            if ($chain === null || ! $chain->is_evm) {
                return; // TRON moves settle elsewhere
            }
            $chainType = $chain->key;

            $receipt = $this->chain->getTransactionReceipt($chainType, $tx->tx_hash);
            if ($receipt === null) {
                return; // not yet mined
            }

            if (! $receipt['status']) {
                $move->update(['status' => 'failed']);
                $tx->update(['status' => OnchainTxStatus::Orphaned]);

                return;
            }

            $confirmations = $this->chain->blockNumber($chainType) - $receipt['blockNumber'] + 1;
            if ($confirmations < $asset->requiredConfirmations()) {
                return; // wait for depth
            }

            DB::transaction(function () use ($move, $tx, $asset, $confirmations) {
                $amount = Money::ofBase($move->amount, $asset->decimals, $asset->symbol);

                $hot = $this->accounts->system(LedgerAccountType::TreasuryHot, $asset->id);
                $cold = $this->accounts->system(LedgerAccountType::TreasuryCold, $asset->id);

                $entry = $this->ledger->post(new EntryData(
                    type: 'treasury.move',
                    idempotencyKey: "move:settle:{$move->id}",
                    lines: [
                        PostingLine::debit($cold->id, $asset->id, $amount),
                        PostingLine::credit($hot->id, $asset->id, $amount),
                    ],
                    memo: "Move {$asset->symbol} hot → cold",
                    metadata: ['treasury_move_id' => $move->id],
                ));

                $move->update(['status' => 'settled', 'settle_entry_id' => $entry->id]);
                $tx->update(['status' => OnchainTxStatus::Confirmed, 'confirmations' => $confirmations]);
            });

            $settled++;
        });

        return $settled;
    }
}
