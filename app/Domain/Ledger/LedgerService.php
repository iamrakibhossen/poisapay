<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Enums\LedgerAccountType;
use App\Enums\LedgerSide;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\LedgerLine;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The ONLY path through which value moves (TDD §5, D1/D2).
 *
 * Every call posts an append-only, balanced journal entry inside a single
 * transaction, locks the affected balance rows FOR UPDATE, and applies the
 * signed delta to each materialised balance. Idempotency keys make retries
 * safe (D5); the DB trigger is the final guarantor of Σdebit = Σcredit.
 */
class LedgerService
{
    public function __construct(private readonly AccountResolver $accounts) {}

    /**
     * Post a balanced entry. If an entry with the same idempotency key already
     * exists, it is returned unchanged (no double-posting).
     */
    public function post(EntryData $data): JournalEntry
    {
        $data->assertBalanced();

        return DB::transaction(function () use ($data): JournalEntry {
            $existing = JournalEntry::where('idempotency_key', $data->idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $entry = JournalEntry::create([
                'type' => $data->type,
                'status' => $data->status,
                'idempotency_key' => $data->idempotencyKey,
                'reverses_entry_id' => $data->reversesEntryId,
                'memo' => $data->memo,
                'metadata' => $data->metadata ?: null,
                'posted_at' => now(),
            ]);

            // Lock balance rows in a deterministic order (by account id) to avoid deadlocks.
            $lines = collect($data->lines)->sortBy('accountId')->values();

            foreach ($lines as $line) {
                /** @var PostingLine $line */
                LedgerLine::create([
                    'entry_id' => $entry->id,
                    'account_id' => $line->accountId,
                    'asset_id' => $line->assetId,
                    'side' => $line->side,
                    'amount' => (string) $line->amount,
                ]);

                $this->applyToBalance($line);
            }

            return $entry->refresh();
        });
    }

    /**
     * Move funds available -> locked for a user+asset (reserve step, §6.3 / §5.2).
     * Returns the posted entry. Throws if insufficient available balance.
     */
    public function lock(User|string $user, int $assetId, Money $amount, string $idempotencyKey, string $type = 'balance.lock', array $metadata = []): JournalEntry
    {
        $available = $this->accounts->forUser($user, LedgerAccountType::UserAvailable, $assetId);
        $locked = $this->accounts->forUser($user, LedgerAccountType::UserLocked, $assetId);

        $this->assertSufficient($available, $amount);

        return $this->post(new EntryData(
            type: $type,
            idempotencyKey: $idempotencyKey,
            lines: [
                // available (credit-normal) decreases via a debit; locked increases via a credit.
                PostingLine::debit($available->id, $assetId, $amount),
                PostingLine::credit($locked->id, $assetId, $amount),
            ],
            metadata: $metadata,
        ));
    }

    /** Reverse a lock: locked -> available (cancel/fail before broadcast, §6.3 step 7). */
    public function unlock(User|string $user, int $assetId, Money $amount, string $idempotencyKey, string $type = 'balance.unlock', array $metadata = []): JournalEntry
    {
        $available = $this->accounts->forUser($user, LedgerAccountType::UserAvailable, $assetId);
        $locked = $this->accounts->forUser($user, LedgerAccountType::UserLocked, $assetId);

        return $this->post(new EntryData(
            type: $type,
            idempotencyKey: $idempotencyKey,
            lines: [
                PostingLine::debit($locked->id, $assetId, $amount),
                PostingLine::credit($available->id, $assetId, $amount),
            ],
            metadata: $metadata,
        ));
    }

    /** Available balance of a user+asset as a Money VO. */
    public function availableBalance(User|string $user, int $assetId): Money
    {
        return $this->accounts->forUser($user, LedgerAccountType::UserAvailable, $assetId)->fresh('balance')->money();
    }

    public function lockedBalance(User|string $user, int $assetId): Money
    {
        return $this->accounts->forUser($user, LedgerAccountType::UserLocked, $assetId)->fresh('balance')->money();
    }

    public function resolver(): AccountResolver
    {
        return $this->accounts;
    }

    private function assertSufficient(LedgerAccount $account, Money $amount): void
    {
        // Read the balance under a row lock so concurrent spends can't both pass.
        $balanceRow = DB::table('account_balances')->where('account_id', $account->id)->lockForUpdate()->first();
        $current = Money::ofBase($balanceRow->balance ?? '0', $amount->decimals, $amount->symbol);

        if ($current->isLessThan($amount)) {
            throw new RuntimeException("Insufficient available balance: have {$current->toDecimal()}, need {$amount->toDecimal()}.");
        }
    }

    /**
     * Apply a single line's signed delta to its materialised balance under a
     * row lock. A line on the account's normal side increases it; the opposite
     * side decreases it.
     */
    private function applyToBalance(PostingLine $line): void
    {
        $account = LedgerAccount::findOrFail($line->accountId);

        $row = DB::table('account_balances')->where('account_id', $line->accountId)->lockForUpdate()->first();
        $current = BigInteger::of($row->balance ?? '0');

        // A line on the account's normal side increases it; the opposite decreases it.
        $delta = $this->isNormalSide($account, $line->side) ? $line->amount : $line->amount->negated();

        $new = $current->plus($delta);

        DB::table('account_balances')->where('account_id', $line->accountId)->update([
            'balance' => (string) $new,
            'version' => ($row->version ?? 0) + 1,
            'updated_at' => now(),
        ]);
    }

    private function isNormalSide(LedgerAccount $account, LedgerSide $side): bool
    {
        return $account->normal_side === $side;
    }
}
