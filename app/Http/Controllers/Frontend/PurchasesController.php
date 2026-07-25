<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Sell\Actions\Order\SendMessage;
use App\Sell\Actions\Review\SubmitReview;
use App\Sell\Enums\OrderStatus;
use App\Sell\Enums\ProductType;
use App\Sell\Models\Order;
use App\Sell\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Customer portal — "My Purchases" (funnel platform). Buyers re-download files,
 * track orders, view license keys and invoices.
 *
 * Wired to real Sell orders: each paid order line the signed-in user bought
 * becomes a purchase card, typed by the product (digital download, physical
 * shipment, or license key). Files are served through {@see self::download()},
 * which re-checks ownership on every request.
 */
class PurchasesController extends Controller
{
    /** Heroicon per product type. */
    private const ICONS = [
        'digital' => 'cube', 'physical' => 'truck', 'license' => 'key',
        'membership' => 'user-group', 'subscription' => 'arrow-path',
        'service' => 'briefcase', 'bundle' => 'squares-2x2',
    ];

    public function index(\Illuminate\Http\Request $request): View
    {
        $items = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('buyer_user_id', $request->user()->getKey())
                ->whereNotIn('status', [OrderStatus::Pending->value, OrderStatus::Cancelled->value]))
            ->with([
                'order.asset', 'order.seller.user',
                'product.files', 'product.reviews', 'variant', 'licenses',
            ])
            ->latest()
            ->get();

        $purchases = $items->map(fn (OrderItem $item) => $this->present($item))->all();

        return view('frontend.purchases', ['purchases' => $purchases]);
    }

    /** Re-download a purchased digital file. Ownership is re-verified here. */
    public function download(\Illuminate\Http\Request $request, string $item): StreamedResponse|RedirectResponse
    {
        $orderItem = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('buyer_user_id', $request->user()->getKey())
                ->whereNotIn('status', [OrderStatus::Pending->value, OrderStatus::Cancelled->value]))
            ->with('product.files')
            ->findOrFail($item);

        $file = $orderItem->product?->files->firstWhere('is_current', true)
            ?? $orderItem->product?->files->first();

        if (! $file || ! Storage::disk($file->disk)->exists($file->path)) {
            return back()->with('error', __('This file isn’t available for download yet.'));
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    /** Buyer's view of the order's shared conversation with the seller. */
    public function messages(Request $request, string $order): View|RedirectResponse
    {
        $model = $this->ownedOrder($request, $order);
        if (! $model instanceof Order) {
            return $model;
        }

        if ($model->buyer_unread) {
            $model->forceFill(['buyer_unread' => false])->save();
        }

        return view('frontend.purchase-messages', [
            'order' => $model,
            'seller' => $model->seller?->displayName() ?? __('Seller'),
            'messages' => $model->messages()->orderBy('created_at')->get()->map(fn ($m) => [
                'side' => $m->author_type === 'buyer' ? 'mine' : 'theirs',
                'author' => $m->author_type === 'buyer' ? __('You') : ($model->seller?->displayName() ?? __('Seller')),
                'body' => $m->body,
                'at' => $m->created_at->format('M j · g:i A'),
            ])->all(),
        ]);
    }

    /** Buyer posts a message to the order conversation. */
    public function sendMessage(Request $request, SendMessage $action, string $order): RedirectResponse
    {
        $model = $this->ownedOrder($request, $order);
        if (! $model instanceof Order) {
            return $model;
        }

        $validated = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $action->execute($model, 'buyer', $request->user()->getKey(), $validated['body']);

        return redirect()->route('purchases.messages', $model->id);
    }

    /** Buyer submits (or updates) a review for a product they purchased. */
    public function submitReview(Request $request, SubmitReview $action, string $order): RedirectResponse
    {
        $model = $this->ownedOrder($request, $order);
        if (! $model instanceof Order) {
            return $model;
        }

        $validated = $request->validate([
            'product_id' => ['required', 'string'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $action->execute(
                $request->user(), $model, $validated['product_id'],
                (int) $validated['rating'], $validated['title'] ?? null, $validated['body'] ?? null,
            );
        } catch (\App\Sell\Exceptions\SellException $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }

        return redirect()->route('purchases')->with('success', __('Thanks for your review!'));
    }

    /** Resolve an order the signed-in buyer owns, or a redirect back to purchases. */
    private function ownedOrder(Request $request, string $orderId): Order|RedirectResponse
    {
        $order = Order::with('seller')
            ->where('buyer_user_id', $request->user()->getKey())
            ->whereNotIn('status', [OrderStatus::Pending->value, OrderStatus::Cancelled->value])
            ->find($orderId);

        return $order ?? redirect()->route('purchases');
    }

    /** @return array<string, mixed> the view-model for one purchase card. */
    private function present(OrderItem $item): array
    {
        $order = $item->order;
        $type = $item->product?->type ?? ProductType::Digital;
        $placed = $order->paid_at ?? $order->created_at;

        $card = [
            'name' => $item->name_snapshot,
            'type' => $type->value,
            'icon' => self::ICONS[$type->value] ?? 'cube',
            'seller' => $order->seller?->displayName() ?? __('Seller'),
            'date' => $placed?->format('M j, Y') ?? '—',
            'price' => $order->asset->money((string) $item->line_total_amount)->format(2),
            'messagesUrl' => route('purchases.messages', $order->id),
            'unread' => (bool) $order->buyer_unread,
            'orderId' => $order->id,
            'productId' => $item->product_id,
            'canReview' => $order->status->isPaid() && $item->product_id !== null,
            'review' => ($rv = $item->product?->reviews->firstWhere('order_id', $order->id))
                ? ['rating' => (int) $rv->rating, 'title' => $rv->title, 'body' => $rv->body, 'reply' => $rv->seller_reply]
                : null,
        ];

        if ($type === ProductType::Digital) {
            $file = $item->product?->files->firstWhere('is_current', true) ?? $item->product?->files->first();
            $card['file'] = $file?->original_name;
            $card['downloadUrl'] = $file ? route('purchases.download', $item->id) : null;
            $card['action'] = __('Download');
        } elseif ($type === ProductType::License) {
            $card['licenseKey'] = optional($item->licenses->first())->key();
            $card['action'] = __('View key');
        } elseif ($type === ProductType::Physical) {
            $addr = $order->shipping_address ?? [];
            $card['shipStatus'] = $order->status->label();
            $card['carrier'] = $addr['carrier'] ?? $order->events->firstWhere('type', 'shipped')?->data['carrier'] ?? '—';
            $card['tracking'] = $addr['tracking'] ?? $order->events->firstWhere('type', 'shipped')?->data['tracking'] ?? '—';
            $card['address'] = $this->formatAddress($addr);
            $card['timeline'] = $this->shipmentTimeline($order->status, $placed);
            $card['action'] = __('Track order');
        } else {
            $card['action'] = __('View');
        }

        return $card;
    }

    /** @param array<string, mixed> $addr */
    private function formatAddress(array $addr): string
    {
        return implode(', ', array_filter([
            $addr['line1'] ?? null, $addr['city'] ?? null,
            $addr['postcode'] ?? null, $addr['country'] ?? null,
        ])) ?: __('No address on file');
    }

    /**
     * Buyer-facing shipment timeline derived from the order status — each step is
     * "done" once the order has reached at least that stage.
     *
     * @return list<array{0:string,1:string,2:bool}>
     */
    private function shipmentTimeline(OrderStatus $status, ?\Illuminate\Support\Carbon $placed): array
    {
        $rank = [
            OrderStatus::Paid->value => 1, OrderStatus::Processing->value => 1,
            OrderStatus::Shipped->value => 2, OrderStatus::Delivered->value => 3,
            OrderStatus::Completed->value => 3,
        ][$status->value] ?? 1;

        return [
            [__('Order placed'), $placed?->format('M j · g:i A') ?? '—', $rank >= 1],
            [__('Shipped'), $rank >= 2 ? __('In transit') : __('Pending'), $rank >= 2],
            [__('Out for delivery'), $rank >= 3 ? __('Delivered') : __('Pending'), $rank >= 3],
            [__('Delivered'), $rank >= 3 ? __('Complete') : __('Pending'), $rank >= 3],
        ];
    }
}
