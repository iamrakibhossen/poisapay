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
 * Apply the terminal outcome of a fiat off-ramp once the PSP confirms (TDD §F1.3).
 * Success: the reserved funds leave the platform — user:locked -> ramp:clearing.
 * Failure: the reservation is released — user:locked -> user:available.
 * Both are idempotent so webhook retries are safe.
 */
class SettleOffRampAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function settle(RampOrder $order): RampOrder
    {
        if ($order->status === RampStatus::Credited) {
            return $order;
        }

        return DB::transaction(function () use ($order): RampOrder {
            $order->loadMissing('fiatAsset');
            $assetId = $order->fiat_asset_id;

            $locked = $this->accounts->forUser($order->user_id, LedgerAccountType::UserLocked, $assetId);
            $clearing = $this->accounts->system(LedgerAccountType::RampClearing, $assetId);

            $entry = $this->ledger->post(new EntryData(
                type: 'ramp.off.settle',
                idempotencyKey: "ramp:off:settle:{$order->id}",
                lines: [
                    PostingLine::debit($locked->id, $assetId, $order->fiat_amount),
                    PostingLine::credit($clearing->id, $assetId, $order->fiat_amount),
                ],
                memo: 'Fiat off-ramp payout',
                metadata: ['ramp_order_id' => $order->id],
            ));

            $order->update(['status' => RampStatus::Credited, 'entry_id' => $entry->id]);

            return $order->refresh();
        });
    }

    public function fail(RampOrder $order): RampOrder
    {
        if (in_array($order->status, [RampStatus::Failed, RampStatus::Credited], true)) {
            return $order;
        }

        return DB::transaction(function () use ($order): RampOrder {
            $order->loadMissing('fiatAsset');
            $amount = $order->fiatAsset->money($order->fiat_amount);

            $this->ledger->unlock(
                user: $order->user_id,
                assetId: $order->fiat_asset_id,
                amount: $amount,
                idempotencyKey: "offramp:unlock:{$order->id}",
                type: 'ramp.off.unlock',
                metadata: ['ramp_order_id' => $order->id],
            );

            $order->update(['status' => RampStatus::Failed]);

            return $order->refresh();
        });
    }
}
