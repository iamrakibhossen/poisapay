<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Sell\Actions\Order\SendMessage;
use App\Sell\Actions\Refund\CancelRefundRequest;
use App\Sell\Actions\Refund\EscalateRefundRequest;
use App\Sell\Actions\Refund\RequestRefund;
use App\Sell\Actions\Review\SubmitReview;
use App\Sell\Enums\OrderStatus;
use App\Sell\Enums\ProductType;
use App\Sell\Enums\RefundRequestStatus;
use App\Sell\Exceptions\SellException;
use App\Sell\Models\Order;
use App\Sell\Models\OrderItem;
use App\Sell\Models\RefundRequest;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function index(Request $request): View
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

    /** A single purchase (order) in full: items, delivery, totals, tracking. */
    public function show(Request $request, string $order): View|RedirectResponse
    {
        $model = Order::query()
            ->where('buyer_user_id', $request->user()->getKey())
            ->whereNotIn('status', [OrderStatus::Pending->value, OrderStatus::Cancelled->value])
            ->with([
                'asset', 'seller.user',
                'items.product.files', 'items.product.reviews', 'items.variant', 'items.licenses',
            ])
            ->find($order);

        if (! $model) {
            return redirect()->route('purchases');
        }

        $money = fn ($base) => $model->asset?->money((string) (int) $base)->format(2) ?? '—';
        $physical = $model->items->first()?->product?->type?->requiresShipping() ?? false;
        $addr = $model->shipping_address ?? [];
        $placed = $model->paid_at ?? $model->created_at;

        return view('frontend.purchase-show', [
            'order' => [
                'id' => $model->id,
                'number' => $model->number,
                'status' => $model->status->label(),
                'statusColor' => $model->status->color(),
                'placedAt' => $placed?->format('M j, Y · g:i A') ?? '—',
                'seller' => $model->seller?->displayName() ?? __('Seller'),
                'physical' => $physical,
                'messagesUrl' => route('purchases.messages', $model->id),
                'unread' => (bool) $model->buyer_unread,
                'totals' => [
                    'subtotal' => $money($model->subtotal_amount),
                    'shipping' => $money($model->shipping_amount),
                    'total' => $money($model->total_amount),
                ],
                'shipping' => $physical ? [
                    'address' => $this->formatAddress($addr),
                    'carrier' => $addr['carrier'] ?? '—',
                    'tracking' => $addr['tracking'] ?? '—',
                    'timeline' => $this->shipmentTimeline($model->status, $placed),
                ] : null,
                'refund' => $this->refundPanel($model, $money),
            ],
            'items' => $model->items->map(fn (OrderItem $i) => $this->present($i->setRelation('order', $model)))->all(),
        ]);
    }

    /**
     * Refund state for the buyer's order page: whether they can open a request,
     * how much is still refundable, and the current request (with its next actions).
     *
     * @param  callable(int|string): string  $money
     * @return array<string, mixed>
     */
    private function refundPanel(Order $model, callable $money): array
    {
        $remaining = RefundRequest::remainingRefundable($model);
        $request = RefundRequest::where('order_id', $model->id)->latest()->first();
        $open = $request && $request->status->isOpen();

        return [
            'canRequest' => $model->status->isPaid() && $remaining > 0 && ! $open,
            'remaining' => $money($remaining),
            'remainingDecimal' => number_format($remaining / (10 ** ($model->asset?->decimals ?? 2)), $model->asset?->decimals ?? 2, '.', ''),
            'symbol' => $model->asset?->symbol,
            'request' => $request ? [
                'id' => $request->id,
                'status' => $request->status->label(),
                'statusColor' => $request->status->color(),
                'type' => $request->type,
                'amount' => $money($request->amount_requested),
                'reason' => $request->reason,
                'note' => $request->resolution_note,
                'canCancel' => $request->status === RefundRequestStatus::Requested,
                'canEscalate' => $request->status === RefundRequestStatus::Rejected,
            ] : null,
        ];
    }

    /** Buyer opens a refund request (full or partial) against their order. */
    public function requestRefund(Request $request, RequestRefund $action, string $order): RedirectResponse
    {
        $model = Order::where('buyer_user_id', $request->user()->getKey())->findOrFail($order);

        $validated = $request->validate([
            'type' => ['required', 'in:full,partial'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'required_if:type,partial'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $amount = null;
        if ($validated['type'] === 'partial') {
            $decimals = $model->asset?->decimals ?? 2;
            $amount = (int) Money::ofDecimal((string) $validated['amount'], $decimals, $model->asset?->symbol ?? '')->baseString();
        }

        try {
            $action->execute($model, $request->user(), $validated['type'], $amount, $validated['reason'] ?? '');
        } catch (SellException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        return redirect()->route('purchases.show', $model->id)->with('success', __('Your refund request was submitted.'));
    }

    /** Buyer withdraws their own pending request. */
    public function cancelRefund(Request $request, CancelRefundRequest $action, string $refundRequest): RedirectResponse
    {
        $req = RefundRequest::where('buyer_user_id', $request->user()->getKey())->findOrFail($refundRequest);

        try {
            $action->execute($req);
        } catch (SellException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        return redirect()->route('purchases.show', $req->order_id)->with('success', __('Refund request cancelled.'));
    }

    /** Buyer escalates a rejected request to support. */
    public function escalateRefund(Request $request, EscalateRefundRequest $action, string $refundRequest): RedirectResponse
    {
        $req = RefundRequest::where('buyer_user_id', $request->user()->getKey())->findOrFail($refundRequest);

        try {
            $action->execute($req);
        } catch (SellException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        return redirect()->route('purchases.show', $req->order_id)->with('success', __('Your request was escalated to support.'));
    }

    /** Re-download a purchased digital file. Ownership is re-verified here. */
    public function download(Request $request, string $item): StreamedResponse|RedirectResponse
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
        } catch (SellException $e) {
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
    private function shipmentTimeline(OrderStatus $status, ?Carbon $placed): array
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
