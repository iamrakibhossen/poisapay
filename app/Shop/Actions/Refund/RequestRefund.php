<?php

declare(strict_types=1);

namespace App\Shop\Actions\Refund;

use App\Models\User;
use App\Notifications\UserNotification;
use App\Shop\Enums\RefundRequestStatus;
use App\Shop\Events\RefundRequested;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\Order;
use App\Shop\Models\RefundRequest;
use Illuminate\Support\Facades\DB;

/**
 * A buyer opens a refund request against a paid order — full (the whole remaining
 * amount) or partial. Guards ownership, that money remains refundable, and that no
 * request is already open. Notifies the seller and starts the response SLA clock.
 */
class RequestRefund
{
    public function execute(Order $order, User $buyer, string $type, ?int $amount, string $reason): RefundRequest
    {
        if ((string) $order->buyer_user_id !== (string) $buyer->getKey() || ! $order->status->isPaid()) {
            throw ShopException::notRefundable();
        }

        return DB::transaction(function () use ($order, $buyer, $type, $amount, $reason): RefundRequest {
            $remaining = RefundRequest::remainingRefundable($order);
            if ($remaining <= 0) {
                throw ShopException::notRefundable();
            }

            $open = RefundRequest::where('order_id', $order->getKey())
                ->whereIn('status', [RefundRequestStatus::Requested->value, RefundRequestStatus::Escalated->value])
                ->lockForUpdate()->exists();
            if ($open) {
                throw ShopException::refundRequestOpen();
            }

            $requested = $type === 'partial' ? (int) $amount : $remaining;
            if ($requested <= 0 || $requested > $remaining) {
                throw ShopException::invalidRefundAmount();
            }

            $request = RefundRequest::create([
                'order_id' => $order->getKey(),
                'seller_id' => $order->seller_id,
                'buyer_user_id' => $buyer->getKey(),
                'type' => $type === 'partial' ? 'partial' : 'full',
                'amount_requested' => $requested,
                'reason' => $reason ?: null,
                'status' => RefundRequestStatus::Requested,
                'sla_due_at' => now()->addDays((int) getSetting('sell_refund_sla_days', 3)),
            ]);

            RefundRequested::dispatch($request);

            $order->seller?->user?->notify(new UserNotification(
                __('New refund request'),
                __('A buyer requested a refund on order :number.', ['number' => $order->number]),
                route('shop.order', ['id' => $order->getKey()]),
                'product',
            ));

            return $request;
        });
    }
}
