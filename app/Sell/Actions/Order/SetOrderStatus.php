<?php

declare(strict_types=1);

namespace App\Sell\Actions\Order;

use App\Sell\Enums\OrderStatus;
use App\Sell\Events\OrderStatusChanged;
use App\Sell\Exceptions\SellException;
use App\Sell\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Seller-driven fulfilment: advance an order along its lifecycle
 * (paid → processing → shipped → delivered → completed). Validates the
 * transition, stamps the matching timestamp, records an order event and fires
 * OrderStatusChanged (auto-audited).
 *
 * Money-reversal states (refunded / cancelled) are intentionally NOT handled
 * here — those move balances and belong to a dedicated refund path.
 */
class SetOrderStatus
{
    private const FULFILMENT = [
        OrderStatus::Processing,
        OrderStatus::Shipped,
        OrderStatus::Delivered,
        OrderStatus::Completed,
    ];

    /**
     * @param  array<string, mixed>  $meta  carrier/tracking/note stored on the event
     */
    public function execute(Order $order, OrderStatus $to, array $meta = []): Order
    {
        if (! in_array($to, self::FULFILMENT, true)) {
            throw SellException::invalidOrderTransition($order->status, $to);
        }
        if (! $order->status->canTransitionTo($to)) {
            throw SellException::invalidOrderTransition($order->status, $to);
        }

        return DB::transaction(function () use ($order, $to, $meta): Order {
            $from = $order->status;

            $order->status = $to;

            // Carrier/tracking live on the order's shipping address blob.
            if ($meta !== []) {
                $order->shipping_address = array_merge($order->shipping_address ?? [], array_filter([
                    'carrier' => $meta['carrier'] ?? null,
                    'tracking' => $meta['tracking'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''));
            }
            $order->save();

            $order->events()->create([
                'type' => $to->value,
                'actor_type' => 'seller',
                'actor_id' => $order->seller_id,
                'data' => $meta,
            ]);

            OrderStatusChanged::dispatch($order, $from, $to);

            return $order->refresh();
        });
    }
}
