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

/**
 * Return escrow to the seller on cancel/expire/force-cancel:
 * seller:p2p_escrow → seller:available. Idempotent under a row lock.
 */
class RefundEscrowAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(P2pOrder $order): P2pEscrow
    {
        return DB::transaction(function () use ($order): P2pEscrow {
            $escrow = P2pEscrow::where('order_id', $order->id)->lockForUpdate()->firstOrFail();
            if ($escrow->status !== P2pEscrowStatus::Locked) {
                return $escrow;   // already settled — idempotent
            }

            $order->loadMissing('asset');
            $assetId = (int) $order->asset_id;
            $gross = Money::ofBase($order->crypto_amount, $order->asset->decimals, $order->asset->symbol);

            $escrowAcct = $this->accounts->forUser($order->seller_id, LedgerAccountType::UserP2pEscrow, $assetId);
            $sellerAvail = $this->accounts->forUser($order->seller_id, LedgerAccountType::UserAvailable, $assetId);

            $entry = $this->ledger->post(new EntryData(
                type: 'p2p.escrow.refund',
                idempotencyKey: "p2p:escrow:refund:{$order->id}",
                lines: [
                    PostingLine::debit($escrowAcct->id, $assetId, $gross),
                    PostingLine::credit($sellerAvail->id, $assetId, $gross),
                ],
                memo: "P2P escrow refund {$order->ref}",
                metadata: ['order_id' => $order->id, 'seller_id' => $order->seller_id],
            ));

            $escrow->update([
                'status' => P2pEscrowStatus::Refunded,
                'release_entry_id' => $entry->id,
            ]);

            return $escrow;
        });
    }
}
