<?php

declare(strict_types=1);

namespace App\Shop\Actions\Refund;

use App\Domain\Notification\AdminNotifier;
use App\Shop\Enums\RefundRequestStatus;
use App\Shop\Events\RefundEscalated;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\RefundRequest;
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
                throw ShopException::refundRequestClosed();
            }

            $fresh->update(['status' => RefundRequestStatus::Escalated, 'escalated_at' => now()]);

            RefundEscalated::dispatch($fresh);
            $this->admins->notify(
                __('Refund escalated'),
                __('A refund on order :number needs operator review.', ['number' => $fresh->order->number]),
                '/admin/shop/refunds/'.$fresh->getKey(),
                'sell',
            );

            return $fresh;
        });
    }
}
