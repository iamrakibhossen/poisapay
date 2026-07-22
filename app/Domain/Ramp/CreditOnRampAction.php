<?php

declare(strict_types=1);

namespace App\Domain\Ramp;

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Enums\RampStatus;
use App\Models\RampOrder;
use Illuminate\Support\Facades\DB;

/**
 * Credit a confirmed fiat on-ramp (TDD §F1.3 step 4): ramp:clearing ->
 * user:available in the fiat asset, once the PSP confirms cleared funds.
 * Idempotent by (rail, provider_ref) via the ramp_orders unique constraint and
 * the entry idempotency key. Never credit before the PSP confirms (§F1.3 warn).
 */
class CreditOnRampAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(RampOrder $order): RampOrder
    {
        if ($order->status === RampStatus::Credited) {
            return $order;
        }

        return DB::transaction(function () use ($order): RampOrder {
            $order->loadMissing('fiatAsset');
            $assetId = $order->fiat_asset_id;

            $clearing = $this->accounts->system(LedgerAccountType::RampClearing, $assetId);
            $available = $this->accounts->forUser($order->user_id, LedgerAccountType::UserAvailable, $assetId);

            $entry = $this->ledger->post(new EntryData(
                type: 'ramp.on.credit',
                idempotencyKey: "ramp:{$order->rail}:{$order->provider_ref}",
                lines: [
                    PostingLine::debit($clearing->id, $assetId, $order->fiat_amount),
                    PostingLine::credit($available->id, $assetId, $order->fiat_amount),
                ],
                memo: 'Fiat on-ramp',
                metadata: ['ramp_order_id' => $order->id],
            ));

            $order->update(['status' => RampStatus::Credited, 'entry_id' => $entry->id]);

            return $order->refresh();
        });
    }
}
