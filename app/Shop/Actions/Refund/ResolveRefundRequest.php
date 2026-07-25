<?php

declare(strict_types=1);

namespace App\Shop\Actions\Refund;

use App\Models\Admin;
use App\Models\JournalEntry;
use App\Notifications\UserNotification;
use App\Shop\Actions\Order\RefundOrder;
use App\Shop\Enums\RefundRequestStatus;
use App\Shop\Events\RefundApproved;
use App\Shop\Events\RefundRejected;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\RefundRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Seller (or, once escalated, an operator) resolves a refund request. Approving
 * posts the ledger reversal via {@see RefundOrder} for the requested amount and
 * closes the request; rejecting records the reason. The buyer is notified either
 * way. Authorization (who may resolve which state) is enforced by the policy at
 * the controller; here we enforce the state machine.
 */
class ResolveRefundRequest
{
    public function __construct(private readonly RefundOrder $refunds) {}

    public function approve(RefundRequest $request, Model $actor, ?string $note = null): RefundRequest
    {
        return DB::transaction(function () use ($request, $actor, $note): RefundRequest {
            /** @var RefundRequest $fresh */
            $fresh = RefundRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            if (! $fresh->status->isOpen()) {
                throw ShopException::refundRequestClosed();
            }

            $order = $fresh->order;
            $applied = min((int) $fresh->amount_requested, RefundRequest::remainingRefundable($order));
            $idempotencyKey = 'sell:refund:req:'.$fresh->getKey();

            $this->refunds->execute($order, $applied, $actor, $note ?? (string) $fresh->reason, $idempotencyKey);

            $fresh->update([
                'status' => RefundRequestStatus::Refunded,
                'amount_refunded' => $applied,
                'ledger_entry_id' => JournalEntry::where('idempotency_key', $idempotencyKey)->value('id'),
                'resolver_type' => $actor instanceof Admin ? 'admin' : 'seller',
                'resolver_id' => $actor->getKey(),
                'resolution_note' => $note,
                'resolved_at' => now(),
            ]);

            RefundApproved::dispatch($fresh);
            $this->notifyBuyer($fresh, __('Refund approved'),
                __('Your refund on order :number was approved.', ['number' => $order->number]));

            return $fresh;
        });
    }

    public function reject(RefundRequest $request, Model $actor, ?string $note = null): RefundRequest
    {
        return DB::transaction(function () use ($request, $actor, $note): RefundRequest {
            /** @var RefundRequest $fresh */
            $fresh = RefundRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            if (! $fresh->status->isOpen()) {
                throw ShopException::refundRequestClosed();
            }

            $fresh->update([
                'status' => RefundRequestStatus::Rejected,
                'resolver_type' => $actor instanceof Admin ? 'admin' : 'seller',
                'resolver_id' => $actor->getKey(),
                'resolution_note' => $note,
                'resolved_at' => now(),
            ]);

            RefundRejected::dispatch($fresh);
            $this->notifyBuyer($fresh, __('Refund declined'),
                __('Your refund on order :number was declined.', ['number' => $fresh->order->number]));

            return $fresh;
        });
    }

    private function notifyBuyer(RefundRequest $request, string $title, string $body): void
    {
        $request->buyer?->notify(new UserNotification(
            $title, $body,
            route('purchases.show', ['order' => $request->order_id]),
            'product',
        ));
    }
}
