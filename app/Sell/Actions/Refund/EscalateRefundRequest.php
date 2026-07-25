<?php

declare(strict_types=1);

namespace App\Sell\Actions\Refund;

use App\Domain\Notification\AdminNotifier;
use App\Sell\Enums\RefundRequestStatus;
use App\Sell\Events\RefundEscalated;
use App\Sell\Exceptions\SellException;
use App\Sell\Models\RefundRequest;
use Illuminate\Support\Facades\DB;

/**
 * Escalates a refund request to operators — either the buyer pushing a rejected
 * request forward, or the SLA job auto-escalating one the seller ignored. Notifies
 * the operator team so it surfaces in the admin refund queue.
 */
class EscalateRefundRequest
{
    public function __construct(private readonly AdminNotifier $admins) {}

    public function execute(RefundRequest $request): RefundRequest
    {
        return DB::transaction(function () use ($request): RefundRequest {
            /** @var RefundRequest $fresh */
            $fresh = RefundRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            if (! $fresh->status->canTransitionTo(RefundRequestStatus::Escalated)) {
                throw SellException::refundRequestClosed();
            }

            $fresh->update(['status' => RefundRequestStatus::Escalated, 'escalated_at' => now()]);

            RefundEscalated::dispatch($fresh);
            $this->admins->notify(
                __('Refund escalated'),
                __('A refund on order :number needs operator review.', ['number' => $fresh->order->number]),
                '/admin/sell/refunds/'.$fresh->getKey(),
                'sell',
            );

            return $fresh;
        });
    }
}
