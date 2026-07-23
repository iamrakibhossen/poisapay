<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Enums\P2pEscrowStatus;
use App\Models\P2pEscrow;
use App\Models\P2pOrder;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lock the seller's gross USDT into escrow (available → user:p2p_escrow) — the
 * P2P analog of a card hold. Idempotent per order; balance checked under a row
 * lock so concurrent orders can't over-commit the same funds.
 */
class PlaceEscrowAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(P2pOrder $order): P2pEscrow
    {
        return DB::transaction(function () use ($order): P2pEscrow {
            $existing = P2pEscrow::where('order_id', $order->id)->first();
            if ($existing) {
                return $existing;   // idempotent
            }

            $order->loadMissing('asset');
            $assetId = (int) $order->asset_id;
            $gross = Money::ofBase($order->crypto_amount, $order->asset->decimals, $order->asset->symbol);

            $available = $this->accounts->forUser($order->seller_id, LedgerAccountType::UserAvailable, $assetId);
            $escrowAcct = $this->accounts->forUser($order->seller_id, LedgerAccountType::UserP2pEscrow, $assetId);

            $this->assertSufficient($available->id, $gross);

            $entry = $this->ledger->post(new EntryData(
                type: 'p2p.escrow.lock',
                idempotencyKey: "p2p:escrow:lock:{$order->id}",
                lines: [
                    PostingLine::debit($available->id, $assetId, $gross),
                    PostingLine::credit($escrowAcct->id, $assetId, $gross),
                ],
                memo: "P2P escrow lock {$order->ref}",
                metadata: ['order_id' => $order->id, 'seller_id' => $order->seller_id],
            ));

            return P2pEscrow::create([
                'order_id' => $order->id,
                'user_id' => $order->seller_id,
                'asset_id' => $assetId,
                'amount' => $gross->baseString(),
                'status' => P2pEscrowStatus::Locked,
                'lock_entry_id' => $entry->id,
            ]);
        });
    }

    private function assertSufficient(string $accountId, Money $amount): void
    {
        $row = DB::table('account_balances')->where('account_id', $accountId)->lockForUpdate()->first();
        $current = Money::ofBase($row->balance ?? '0', $amount->decimals, $amount->symbol);

        if ($current->isLessThan($amount)) {
            throw new RuntimeException("Insufficient balance to escrow: have {$current->toDecimal()}, need {$amount->toDecimal()}.");
        }
    }
}
