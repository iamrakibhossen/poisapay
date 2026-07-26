<?php

declare(strict_types=1);

namespace App\Shop\Listeners;

use App\Domain\Notification\NotificationService;
use App\Models\User;
use App\Shop\Enums\OrderStatus;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\SellerStatus;
use App\Shop\Events\OrderPlaced;
use App\Shop\Events\OrderStatusChanged;
use App\Shop\Events\ProductStatusChanged;
use App\Shop\Events\RefundApproved;
use App\Shop\Events\RefundRejected;
use App\Shop\Events\RefundRequested;
use App\Shop\Events\ReviewSubmitted;
use App\Shop\Events\SellerApplied;
use App\Shop\Events\SellerStatusChanged;
use App\Shop\Models\Order;
use App\Shop\Models\RefundRequest;

/**
 * Single source of Shop user-notifications. Every Shop domain event routes here
 * and is delivered via {@see NotificationService}, which resolves an admin-editable
 * template and honours each user's per-category channel preferences (in-app +
 * broadcast, email, sms/push). Replaces the old inline ->notify() calls that
 * hardcoded channels and ignored preferences.
 */
class ShopNotificationSubscriber
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            OrderPlaced::class => 'onOrderPlaced',
            OrderStatusChanged::class => 'onOrderStatusChanged',
            ReviewSubmitted::class => 'onReviewSubmitted',
            ProductStatusChanged::class => 'onProductStatusChanged',
            SellerStatusChanged::class => 'onSellerStatusChanged',
            SellerApplied::class => 'onSellerApplied',
            RefundRequested::class => 'onRefundRequested',
            RefundApproved::class => 'onRefundApproved',
            RefundRejected::class => 'onRefundRejected',
        ];
    }

    public function onOrderPlaced(OrderPlaced $event): void
    {
        $order = $event->order->loadMissing(['asset', 'seller.user', 'buyer']);
        $data = ['number' => $order->number, 'product' => $this->productLabel($order), 'amount' => $order->total()->format()];

        $this->to($order->buyer, 'shop.order.created', $data, route('purchases.show', $order));
        $this->to($order->seller?->user, 'shop.purchase.new', $data, $this->sellerOrderUrl($order));
    }

    public function onOrderStatusChanged(OrderStatusChanged $event): void
    {
        // Refund-driven transitions are announced by the refund events instead.
        $key = match ($event->to) {
            OrderStatus::Completed => 'shop.order.completed',
            OrderStatus::Cancelled => 'shop.order.cancelled',
            OrderStatus::Shipped => 'shop.order.shipped',
            default => null,
        };
        if ($key === null) {
            return;
        }

        $order = $event->order->loadMissing('buyer');
        $this->to($order->buyer, $key, ['number' => $order->number], route('purchases.show', $order));
    }

    public function onReviewSubmitted(ReviewSubmitted $event): void
    {
        $review = $event->review->loadMissing(['seller.user', 'product', 'buyer']);
        $this->to($review->seller?->user, 'shop.review.submitted', [
            'buyer' => $review->buyer?->name ?? 'A buyer',
            'rating' => (string) $review->rating,
            'product' => $review->product?->name ?? 'your product',
        ], route('shop.reviews'));
    }

    public function onProductStatusChanged(ProductStatusChanged $event): void
    {
        $key = match ($event->to) {
            ProductStatus::Published => 'shop.product.published',
            ProductStatus::Archived => 'shop.product.disabled',
            default => null,
        };
        if ($key === null) {
            return;
        }

        $product = $event->product->loadMissing('seller.user');
        $this->to($product->seller?->user, $key, ['product' => $product->name], route('shop.products.edit', $product->id));
    }

    public function onSellerStatusChanged(SellerStatusChanged $event): void
    {
        $key = match ($event->to) {
            SellerStatus::Approved => 'shop.seller.approved',
            SellerStatus::Rejected => 'shop.seller.rejected',
            SellerStatus::Suspended => 'shop.seller.suspended',
            default => null,
        };
        if ($key === null) {
            return;
        }

        $seller = $event->seller->loadMissing('user');
        $this->to($seller->user, $key, ['reason' => $event->reason ?? '—'], route('shop'));
    }

    public function onSellerApplied(SellerApplied $event): void
    {
        $seller = $event->seller->loadMissing('user');
        $this->to($seller->user, 'shop.seller.applied', [], route('shop'));
    }

    public function onRefundRequested(RefundRequested $event): void
    {
        $req = $event->request->loadMissing(['order.asset', 'order.seller.user']);
        $order = $req->order;
        $this->to($order?->seller?->user, 'shop.refund.requested', [
            'number' => $order?->number ?? '',
            'amount' => $this->amount($order, (int) $req->amount_requested),
        ], $order ? $this->sellerOrderUrl($order) : null);
    }

    public function onRefundApproved(RefundApproved $event): void
    {
        $this->refundOutcome($event->request, 'shop.refund.approved');
    }

    public function onRefundRejected(RefundRejected $event): void
    {
        $this->refundOutcome($event->request, 'shop.refund.rejected');
    }

    private function refundOutcome(RefundRequest $req, string $key): void
    {
        $req->loadMissing(['order.asset', 'buyer']);
        $order = $req->order;
        $this->to($req->buyer, $key, [
            'number' => $order?->number ?? '',
            'amount' => $this->amount($order, (int) ($req->amount_refunded ?: $req->amount_requested)),
        ], $order ? route('purchases.show', $order) : null);
    }

    /** Deliver one notification if the recipient exists. */
    private function to(?User $user, string $key, array $data, ?string $url = null): void
    {
        if ($user !== null) {
            $this->notifications->send($user, $key, $data, null, $url);
        }
    }

    private function amount(?Order $order, int $minor): string
    {
        return $order?->asset ? $order->asset->money((string) $minor)->format() : (string) $minor;
    }

    private function productLabel(Order $order): string
    {
        return (string) ($order->items()->value('name_snapshot') ?: 'your order');
    }

    private function sellerOrderUrl(Order $order): string
    {
        return route('shop.order', $order->id);
    }
}
