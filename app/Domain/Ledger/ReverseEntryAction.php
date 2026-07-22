<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Enums\EntryStatus;
use App\Enums\LedgerSide;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reverse a journal entry (TDD §5.3). Corrections are never mutations — a new
 * balanced entry mirrors the original with every line's side flipped, links
 * back via reverses_entry_id, and the original is marked reversed. Idempotent:
 * an entry can only be reversed once.
 */
class ReverseEntryAction
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function execute(JournalEntry $original, ?string $reason = null): JournalEntry
    {
        return DB::transaction(function () use ($original, $reason): JournalEntry {
            $original = JournalEntry::with('lines')->lockForUpdate()->findOrFail($original->id);

            if ($original->status === EntryStatus::Reversed) {
                throw new RuntimeException('This entry has already been reversed.');
            }
            if (JournalEntry::where('reverses_entry_id', $original->id)->exists()) {
                throw new RuntimeException('A reversal for this entry already exists.');
            }

            $lines = $original->lines->map(fn ($line) => new PostingLine(
                accountId: $line->account_id,
                assetId: $line->asset_id,
                side: $line->side === LedgerSide::Debit ? LedgerSide::Credit : LedgerSide::Debit,
                amount: $line->amount,
            ))->all();

            $reversal = $this->ledger->post(new EntryData(
                type: $original->type.'.reversal',
                idempotencyKey: 'reversal:'.$original->id,
                lines: $lines,
                memo: $reason ?? "Reversal of {$original->id}",
                metadata: ['reverses' => $original->id, 'reason' => $reason],
                reversesEntryId: $original->id,
            ));

            $original->update(['status' => EntryStatus::Reversed]);

            ActivityLogger::log('ledger.entry.reversed', $reversal, [
                'original_entry' => $original->id,
                'reason' => $reason,
            ], "Reversed entry {$original->id}");

            return $reversal;
        });
    }
}
