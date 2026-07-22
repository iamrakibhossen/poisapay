<?php

declare(strict_types=1);

namespace App\Domain\Transfer;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Enums\TransferKind;
use App\Enums\TransferStatus;
use App\Events\TransferCompleted;
use App\Models\Asset;
use App\Models\Transfer;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Internal user->user transfer (TDD §6.4 / §F4.1): a single balanced ledger
 * entry sender:available -> recipient:available. Atomic, idempotent, instant,
 * zero network fee. Supports crypto and fiat assets alike.
 */
class ExecuteTransferAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(User $sender, User $recipient, Asset $asset, Money $amount, string $idempotencyKey, ?string $memo = null): Transfer
    {
        if ($sender->is($recipient)) {
            throw new RuntimeException('Cannot transfer to yourself.');
        }
        if (! $amount->isPositive()) {
            throw new RuntimeException('Transfer amount must be positive.');
        }

        return DB::transaction(function () use ($sender, $recipient, $asset, $amount, $idempotencyKey, $memo): Transfer {
            // Collapse retries: if this key already produced a transfer, return it.
            $existing = Transfer::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $senderAcct = $this->accounts->forUser($sender, LedgerAccountType::UserAvailable, $asset->id);
            $recipientAcct = $this->accounts->forUser($recipient, LedgerAccountType::UserAvailable, $asset->id);

            // Guard available balance under a row lock.
            $balanceRow = DB::table('account_balances')->where('account_id', $senderAcct->id)->lockForUpdate()->first();
            $current = Money::ofBase($balanceRow->balance ?? '0', $asset->decimals, $asset->symbol);
            if ($current->isLessThan($amount)) {
                throw new RuntimeException('Insufficient balance for transfer.');
            }

            $entry = $this->ledger->post(new EntryData(
                type: 'transfer.internal',
                idempotencyKey: 'entry:'.$idempotencyKey,
                lines: [
                    PostingLine::debit($senderAcct->id, $asset->id, $amount),
                    PostingLine::credit($recipientAcct->id, $asset->id, $amount),
                ],
                memo: $memo,
                metadata: ['sender' => $sender->id, 'recipient' => $recipient->id],
            ));

            $transfer = Transfer::create([
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'asset_id' => $asset->id,
                'amount' => $amount->baseString(),
                'kind' => TransferKind::Internal,
                'status' => TransferStatus::Completed,
                'entry_id' => $entry->id,
                'idempotency_key' => $idempotencyKey,
                'memo' => $memo,
            ]);

            ActivityLogger::log('transfer.completed', $transfer);

            TransferCompleted::dispatch($transfer->id);

            return $transfer;
        });
    }
}
