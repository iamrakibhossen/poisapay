<?php

declare(strict_types=1);

namespace App\Domain\Chain\Tron;

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\ChainType;
use App\Enums\LedgerAccountType;
use App\Enums\OnchainTxStatus;
use App\Models\Asset;
use App\Models\OnchainTx;
use App\Models\TreasuryMove;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Settle broadcast TRON hot→cold moves once their tx confirms: post the ledger
 * treasury:hot → treasury:cold ONLY after confirmation (debit cold / credit hot —
 * both debit-normal, so cold rises and hot falls). Idempotent (keyed entry). A
 * reverted move is marked failed — the funds never left the hot wallet.
 */
class SettleTronHotColdMovesAction
{
    public function __construct(
        private readonly TronGridClient $client,
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
            if ($chain === null || $chain->key !== ChainType::Tron) {
                return; // EVM moves settle elsewhere
            }

            $info = $this->client->transactionInfo($tx->tx_hash);
            if ($info === null) {
                return; // not yet in a block
            }

            if (! $info['success']) {
                $move->update(['status' => 'failed']);
                $tx->update(['status' => OnchainTxStatus::Orphaned]);

                return;
            }

            DB::transaction(function () use ($move, $tx, $asset) {
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
                $tx->update(['status' => OnchainTxStatus::Confirmed, 'confirmations' => $asset->requiredConfirmations()]);
            });

            $settled++;
        });

        return $settled;
    }
}
