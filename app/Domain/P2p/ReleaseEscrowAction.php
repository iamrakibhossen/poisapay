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
 * Release escrow to the buyer on a completed trade: seller:p2p_escrow (gross) →
 * buyer:available (net) + p2p:fee_income (taker fee). Idempotent under a row
 * lock on the escrow record — a duplicate release is a no-op.
 */
class ReleaseEscrowAction
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
            $decimals = $order->asset->decimals;
            $symbol = $order->asset->symbol;

            $gross = Money::ofBase($order->crypto_amount, $decimals, $symbol);
            $fee = Money::ofBase($order->fee_amount, $decimals, $symbol);
            $net = Money::ofBase($order->net_amount, $decimals, $symbol);

            $escrowAcct = $this->accounts->forUser($order->seller_id, LedgerAccountType::UserP2pEscrow, $assetId);
            $buyerAvail = $this->accounts->forUser($order->buyer_id, LedgerAccountType::UserAvailable, $assetId);

            $lines = [
                PostingLine::debit($escrowAcct->id, $assetId, $gross),
                PostingLine::credit($buyerAvail->id, $assetId, $net),
            ];
            if ($fee->isPositive()) {
                $feeAcct = $this->accounts->system(LedgerAccountType::P2pFeeIncome, $assetId);
                $lines[] = PostingLine::credit($feeAcct->id, $assetId, $fee);
            }

            $entry = $this->ledger->post(new EntryData(
                type: 'p2p.escrow.release',
                idempotencyKey: "p2p:escrow:release:{$order->id}",
                lines: $lines,
                memo: "P2P escrow release {$order->ref}",
                metadata: ['order_id' => $order->id, 'buyer_id' => $order->buyer_id],
            ));

            $escrow->update([
                'status' => P2pEscrowStatus::Released,
                'release_entry_id' => $entry->id,
            ]);

            return $escrow;
        });
    }
}
