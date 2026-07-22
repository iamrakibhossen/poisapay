<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\CardDispute;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Resolve a card dispute (TDD §F3.6). A win closes the case with no money moved.
 * A loss (chargeback) reimburses the cardholder from the card program loss
 * account: debit card_program:loss, credit user:available. Idempotent per dispute.
 */
class ResolveCardDisputeAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    /** @param  'won'|'lost'  $outcome */
    public function execute(CardDispute $dispute, string $outcome): CardDispute
    {
        if (! in_array($outcome, ['won', 'lost'], true)) {
            throw new RuntimeException('Dispute outcome must be won or lost.');
        }

        return DB::transaction(function () use ($dispute, $outcome): CardDispute {
            $dispute = CardDispute::whereKey($dispute->id)->lockForUpdate()->firstOrFail();
            if (in_array($dispute->status, ['won', 'lost'], true)) {
                return $dispute;
            }

            if ($outcome === 'lost') {
                $auth = $dispute->authorization;
                $asset = Asset::findOrFail($auth->funding_asset_id);
                $amount = Money::ofBase($auth->held_amount, $asset->decimals, $asset->symbol);

                $available = $this->accounts->forUser($auth->card->user_id, LedgerAccountType::UserAvailable, $asset->id);
                $loss = $this->accounts->system(LedgerAccountType::CardProgramLoss, $asset->id);

                $entry = $this->ledger->post(new EntryData(
                    type: 'card.chargeback',
                    idempotencyKey: "card:chargeback:{$dispute->id}",
                    lines: [
                        PostingLine::debit($loss->id, $asset->id, $amount),
                        PostingLine::credit($available->id, $asset->id, $amount),
                    ],
                    memo: "Chargeback {$auth->merchant}",
                    metadata: ['dispute_id' => $dispute->id],
                ));

                $dispute->entry_id = $entry->id;
            }

            $dispute->status = $outcome;
            $dispute->save();

            ActivityLogger::log("card.dispute.{$outcome}", $dispute, ['authorization_id' => $dispute->authorization_id]);

            return $dispute->refresh();
        });
    }
}
