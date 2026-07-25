<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Shop\Actions\Coupon\CreateCoupon;
use App\Shop\Actions\Order\RefundOrder;
use App\Shop\Actions\Order\SendMessage;
use App\Shop\Actions\Order\SetOrderStatus;
use App\Shop\Actions\Product\CreateProduct;
use App\Shop\Actions\Product\SetProductStatus;
use App\Shop\Actions\Product\UpdateProduct;
use App\Shop\Actions\Refund\ResolveRefundRequest;
use App\Shop\Actions\Review\ReplyToReview;
use App\Shop\Actions\SalesPage\CreateSalesPage;
use App\Shop\Actions\SalesPage\SetSalesPageStatus;
use App\Shop\Actions\SalesPage\UpdateSalesPage;
use App\Shop\Actions\Seller\SubmitSellerApplication;
use App\Shop\DTOs\ProductData;
use App\Shop\DTOs\SalesPageData;
use App\Shop\DTOs\SellerApplicationData;
use App\Shop\Enums\CouponType;
use App\Shop\Enums\OrderItemKind;
use App\Shop\Enums\OrderStatus;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\RefundRequestStatus;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\AnalyticsEvent;
use App\Shop\Models\Order;
use App\Shop\Models\OrderItem;
use App\Shop\Models\Product;
use App\Shop\Models\RefundRequest;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;
use App\Shop\Services\AnalyticsService;
use App\Shop\Services\SellerService;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Creator/seller onboarding — the "Become a Seller" application (funnel platform).
 *
 * The apply flow is wired to the Sell backend: submission persists via
 * {@see SubmitSellerApplication} (Seller → pending_review + audited application
 * trail) and the seller home reflects real status. Remaining seller-area pages
 * (products, orders, etc.) are still frontend-first previews.
 */
class SellerController extends Controller
{
    /** Product categories a creator can pick (display order matters). */
    private const CATEGORIES = [
        'ebook' => 'eBooks & PDFs',
        'software' => 'Software & SaaS',
        'templates' => 'Templates & Themes',
        'plugins' => 'Plugins & Extensions',
        'design' => 'Design Assets & UI Kits',
        'ai' => 'AI Prompts & Tools',
        'music' => 'Music & Audio',
        'physical' => 'Physical Products',
        'membership' => 'Memberships & Communities',
        'service' => 'Services',
    ];

    /**
     * Seller home. Approved sellers get a real dashboard: live product counts,
     * paid-order revenue and a first-run checklist until they've launched a
     * product. Non-sellers see the onboarding CTA.
     */
    public function index(Request $request, SellerService $sellers): View
    {
        $seller = $sellers->forUser($request->user());
        $isSeller = $seller?->canSell() ?? false;

        $counts = ['products' => 0, 'published' => 0, 'pages' => 0, 'sales' => 0];
        $stats = ['revenue' => '—', 'available' => '—', 'pending' => '—', 'sales' => 0];
        $attention = ['fulfil' => 0, 'unread' => 0, 'refunds' => 0];
        $recentOrders = [];
        $insights = null;

        if ($isSeller) {
            $counts['products'] = $seller->products()->count();
            $counts['published'] = $seller->products()->where('status', ProductStatus::Published->value)->count();
            $counts['pages'] = $seller->products()->has('salesPages')->count();
            [$stats, $counts['sales']] = $this->sellerStats($seller);

            // Actionable signals — surfaced as a "needs attention" strip.
            $attention['fulfil'] = $seller->orders()
                ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Processing->value])->count();
            $attention['unread'] = $seller->orders()->where('seller_unread', true)->count();
            $attention['refunds'] = RefundRequest::where('seller_id', $seller->id)
                ->whereIn('status', [
                    RefundRequestStatus::Requested->value,
                    RefundRequestStatus::Rejected->value,
                    RefundRequestStatus::Escalated->value,
                ])->count();

            // Newest five real orders — a glanceable, clickable list.
            $recentOrders = $seller->orders()->with(['items', 'buyer', 'asset'])
                ->whereNotIn('status', [OrderStatus::Pending->value, OrderStatus::Cancelled->value])
                ->latest()->limit(5)->get()
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'number' => $o->number,
                    'buyer' => $o->buyer?->email ?? '—',
                    'product' => $o->items->first()?->name_snapshot ?? '—',
                    'amount' => $o->asset ? $o->asset->money((string) $o->total_amount)->format(2) : '—',
                    'status' => $o->status->label(),
                    'color' => $o->status->color(),
                    'date' => $o->created_at->diffForHumans(),
                    'unread' => (bool) $o->seller_unread,
                ])->all();

            $insights = $this->dashboardInsights($seller);
        }

        return view('frontend.seller.index', [
            'seller' => $seller,
            'isSeller' => $isSeller,
            'status' => $seller?->status,
            'counts' => $counts,
            'hasProducts' => $counts['products'] > 0,
            'stats' => $stats,
            'attention' => $attention,
            'recentOrders' => $recentOrders,
            'insights' => $insights,
        ]);
    }

    /**
     * Dashboard insights: a 30-day funnel (distinct sessions), rolling revenue /
     * average order value, and the seller's best-selling products — all from real
     * analytics events and paid orders. Mirrors the /sell/analytics maths.
     *
     * @return array{funnel:array<int,array{0:string,1:int,2:int}>, visitors:int, conversion:float, revenue30:string, orders30:int, aov:string, topProducts:array<int,array{name:string,units:int,net:string}>}
     */
    private function dashboardInsights(Seller $seller): array
    {
        $since = now()->subDays(30);

        $rows = AnalyticsEvent::query()
            ->where('seller_id', $seller->getKey())
            ->where('occurred_at', '>=', $since)
            ->selectRaw('type, count(distinct session_id) as sessions')
            ->groupBy('type')->pluck('sessions', 'type');

        $visitors = (int) ($rows[AnalyticsService::PAGE_VIEW] ?? 0);
        $checkouts = (int) ($rows[AnalyticsService::CHECKOUT_START] ?? 0);
        $purchases = (int) ($rows[AnalyticsService::PURCHASE] ?? 0);
        $conversion = $visitors > 0 ? round($purchases / $visitors * 100, 1) : 0.0;
        $pct = fn (int $n) => $visitors > 0 ? (int) round($n / $visitors * 100) : 0;

        $paid = $seller->orders()->with('asset')
            ->whereNotIn('status', [OrderStatus::Pending->value, OrderStatus::Cancelled->value])
            ->where('created_at', '>=', $since)->get();
        $asset = $seller->settlementAsset ?? $paid->first()?->asset;
        $revenue30 = (int) $paid->sum(fn ($o) => (int) $o->seller_net_amount);
        $orders30 = $paid->count();
        $aov = $orders30 > 0 ? intdiv($revenue30, $orders30) : 0;
        $fmt = fn (int $base) => $asset ? $asset->money((string) $base)->format(2) : number_format($base / 100, 2);

        // Best sellers by units sold across all paid orders.
        $topProducts = OrderItem::query()
            ->join('shop_orders', 'shop_orders.id', '=', 'shop_order_items.order_id')
            ->where('shop_orders.seller_id', $seller->id)
            ->whereNotIn('shop_orders.status', [OrderStatus::Pending->value, OrderStatus::Cancelled->value])
            ->selectRaw('name_snapshot, sum(quantity) as units, sum(shop_order_items.seller_net_amount) as net')
            ->groupBy('name_snapshot')
            ->orderByDesc('units')
            ->limit(5)->get()
            ->map(fn ($r) => ['name' => $r->name_snapshot ?? '—', 'units' => (int) $r->units, 'net' => $fmt((int) $r->net)])
            ->all();

        return [
            'funnel' => [
                [__('Visitors'), $visitors, 100],
                [__('Checkout'), $checkouts, $pct($checkouts)],
                [__('Purchased'), $purchases, $pct($purchases)],
            ],
            'visitors' => $visitors,
            'conversion' => $conversion,
            'revenue30' => $fmt($revenue30),
            'orders30' => $orders30,
            'aov' => $fmt($aov),
            'topProducts' => $topProducts,
        ];
    }

    /** Upload / replace the store logo (shown in the public storefront header). */
    public function uploadLogo(Request $request, SellerService $sellers): RedirectResponse
    {
        $seller = $sellers->forUser($request->user());
        if (! $seller) {
            return redirect()->route('sell');
        }

        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'], // 1 MB
        ]);

        // Replace any previous file.
        if ($seller->logo_path) {
            Storage::disk('public')->delete($seller->logo_path);
        }

        $path = $request->file('logo')->store('sell/logos', 'public');
        $seller->update(['logo_path' => $path]);

        return redirect()->route('sell')->with('success', __('Store logo updated.'));
    }

    /** Remove the store logo (falls back to the monogram). */
    public function deleteLogo(Request $request, SellerService $sellers): RedirectResponse
    {
        $seller = $sellers->forUser($request->user());
        if ($seller?->logo_path) {
            Storage::disk('public')->delete($seller->logo_path);
            $seller->update(['logo_path' => null]);
        }

        return redirect()->route('sell')->with('success', __('Store logo removed.'));
    }

    /**
     * Real seller money KPIs from paid orders, valued in the seller's settlement
     * asset (fallback: a fiat pricing asset). Revenue = all paid net; available =
     * completed/delivered net; pending = paid-but-not-yet-settled net.
     *
     * @return array{0: array{revenue:string,available:string,pending:string,sales:int}, 1: int}
     */
    private function sellerStats(Seller $seller): array
    {
        $asset = $seller->settlementAsset
            ?? Asset::where('is_active', true)->where('decimals', '<=', 8)->orderBy('id')->first();

        $paid = $seller->orders()
            ->when($asset, fn ($q) => $q->where('asset_id', $asset->id))
            ->whereNotIn('status', [OrderStatus::Pending->value, OrderStatus::Cancelled->value])
            ->get(['status', 'seller_net_amount']);

        $settled = [OrderStatus::Delivered->value, OrderStatus::Completed->value];
        $sum = fn ($rows) => array_sum($rows->pluck('seller_net_amount')->map(fn ($v) => (int) $v)->all());
        // status is an enum cast, so compare on ->value (whereIn against strings
        // would compare enum objects and never match).
        $isSettled = fn ($o) => in_array($o->status->value, $settled, true);

        $fmt = fn (int $base) => $asset ? $asset->money((string) $base)->format(2) : number_format($base / 100, 2);

        return [[
            'revenue' => $fmt($sum($paid)),
            'available' => $fmt($sum($paid->filter($isSettled))),
            'pending' => $fmt($sum($paid->reject($isSettled))),
            'sales' => $paid->count(),
        ], $paid->count()];
    }

    /** Product types a seller can create. */
    private const PRODUCT_TYPES = [
        'digital' => ['Digital download', 'cube', 'Files delivered instantly after payment.'],
        'physical' => ['Physical product', 'truck', 'Shipped to the buyer — t-shirts, gadgets, print.'],
        'license' => ['License key', 'key', 'Software + a unique key per sale.'],
        'membership' => ['Membership', 'user-group', 'Recurring access to gated content.'],
        'subscription' => ['Subscription', 'arrow-path', 'Recurring billing for a plan.'],
        'service' => ['Service', 'briefcase', 'A delivered outcome with an intake brief.'],
    ];

    /** Seller's real product list (newest first). */
    public function products(Request $request, SellerService $sellers): View
    {
        $seller = $sellers->forUser($request->user());

        $statusColor = [
            ProductStatus::Draft->value => 'gray',
            ProductStatus::Published->value => 'success',
            ProductStatus::Archived->value => 'warning',
        ];

        $products = $seller
            ? $seller->products()->with(['priceAsset', 'salesPages'])->latest()->get()->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type->label(),
                'price' => $p->priceAsset ? $p->priceAsset->money($p->price_amount)->format(2) : '—',
                'status' => ucfirst($p->status->value),
                'statusColor' => $statusColor[$p->status->value] ?? 'gray',
                'sales' => 0, // real sales counts land with the orders vertical
                'slug' => optional($p->salesPages->firstWhere('status', SalesPageStatus::Published))->slug,
            ])->all()
            : [];

        return view('frontend.seller.products', ['products' => $products]);
    }

    /** Custom domains — one per sales page. FRONTEND-FIRST: connect flow + DNS + verify. */
    public function domains(Request $request): View
    {
        return view('frontend.seller.domains', [
            'target' => 'cname.poisahub.com',
            // Sales pages that don't yet have a domain (selectable when connecting one).
            'availablePages' => ['LaunchKit — Black Friday', 'PoisaHub Dev Tee'],
            'domains' => [
                ['host' => 'shop.launchkit.dev', 'page' => 'LaunchKit — Main', 'slug' => 'launchkit', 'status' => 'Verified', 'color' => 'success', 'ssl' => true],
                ['host' => 'get.premiumkit.com', 'page' => 'Premium UI Kit', 'slug' => 'premium-ui-kit', 'status' => 'Pending DNS', 'color' => 'warning', 'ssl' => false],
            ],
        ]);
    }

    /** Seller's real orders (paid+), newest first, with headline stats. */
    public function orders(Request $request, SellerService $sellers): View
    {
        $seller = $sellers->forUser($request->user());

        $orders = $seller
            ? $seller->orders()->with(['items', 'buyer', 'asset'])
                ->whereNotIn('status', [OrderStatus::Pending->value, OrderStatus::Cancelled->value])
                ->latest()->limit(100)->get()
            : collect();

        $paidNet = (int) $orders->sum(fn ($o) => (int) $o->seller_net_amount);
        $settleAsset = $seller?->settlementAsset ?? $orders->first()?->asset;

        return view('frontend.seller.orders', [
            'stats' => [
                'total' => $orders->count(),
                'revenue' => $settleAsset ? $settleAsset->money((string) $paidNet)->format(2) : '—',
                'pending' => $orders->whereIn('status', [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped])->count(),
                'refunded' => $orders->whereIn('status', [OrderStatus::Refunded, OrderStatus::PartiallyRefunded])->count(),
            ],
            'orders' => $orders->map(fn ($o) => [
                'id' => $o->id,
                'number' => $o->number,
                'buyer' => $o->buyer?->email ?? '—',
                'product' => $o->items->first()?->name_snapshot ?? '—',
                'amount' => $o->asset ? $o->asset->money((string) $o->total_amount)->format(2) : '—',
                'status' => $o->status->label(),
                'color' => $o->status->color(),
                'date' => $o->created_at->format('M j, Y'),
            ])->all(),
        ]);
    }

    /** Order detail + fulfilment for the owning seller. */
    public function order(Request $request, SellerService $sellers, string $id): View|RedirectResponse
    {
        $order = $this->ownedOrder($sellers, $request, $id);
        if (! $order instanceof Order) {
            return $order;
        }

        // Opening the order clears the seller's unread flag.
        if ($order->seller_unread) {
            $order->forceFill(['seller_unread' => false])->save();
        }

        $asset = $order->asset;
        $fmt = fn ($base) => $asset ? $asset->money((string) (int) $base)->format(2) : '—';
        $addr = $order->shipping_address ?? [];
        $type = $order->items->first()?->product?->type;

        return view('frontend.seller.order', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status->label(),
                'statusColor' => $order->status->color(),
                'placedAt' => ($order->paid_at ?? $order->created_at)->format('M j, Y · g:i A'),
                'buyer' => ['name' => $order->buyer?->name ?? '—', 'email' => $order->buyer?->email ?? '—'],
                'type' => $type?->value ?? 'digital',
                'physical' => $type?->requiresShipping() ?? false,
                'items' => $order->items->map(fn ($i) => [
                    'name' => $i->name_snapshot,
                    'variant' => $i->variant?->name,
                    'qty' => (int) $i->quantity,
                    'price' => $fmt($i->line_total_amount),
                ])->all(),
                'shipping' => [
                    'name' => $addr['name'] ?? $order->buyer?->name,
                    'phone' => $addr['phone'] ?? null,
                    'line1' => $addr['line1'] ?? null, 'city' => $addr['city'] ?? null,
                    'postcode' => $addr['postcode'] ?? null, 'country' => $addr['country'] ?? null,
                    'carrier' => $addr['carrier'] ?? null, 'tracking' => $addr['tracking'] ?? null,
                ],
                'totals' => [
                    'subtotal' => $fmt($order->subtotal_amount),
                    'shipping' => $fmt($order->shipping_amount),
                    'total' => $fmt($order->total_amount),
                    'fee' => '−'.$fmt($order->commission_amount),
                    'net' => $fmt($order->seller_net_amount),
                ],
                // Valid next fulfilment steps for the status-advance control.
                'nextSteps' => array_values(array_map(
                    fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                    array_filter($order->status->allowedNext(), fn (OrderStatus $s) => in_array($s->value, ['processing', 'shipped', 'delivered', 'completed'], true)),
                )),
                'carriers' => ['Sundarban', 'Pathao', 'RedX', 'Steadfast', 'DHL', 'Other'],
                // Money can be reversed to the buyer while the order is paid & not final.
                'refundable' => $order->status->canTransitionTo(OrderStatus::Refunded),
                'refundedAt' => $order->refunded_at?->format('M j, Y'),
                'refundTotal' => $fmt((int) $order->seller_net_amount + (int) $order->commission_amount),
                'openRefund' => $this->openRefundFor($order, $fmt),
                'events' => $order->events->sortBy('created_at')->map(fn ($e) => [
                    'label' => ucwords(str_replace('_', ' ', $e->type)),
                    'at' => $e->created_at->format('M j · g:i A'),
                ])->values()->all(),
                'messages' => $this->conversation($order, 'seller'),
            ],
        ]);
    }

    /**
     * The order's shared conversation as a view-model, tagged from the given
     * viewer's perspective ('mine' vs 'theirs') so the bubble alignment is right.
     *
     * @return list<array{side:string,author:string,body:string,at:string}>
     */
    private function conversation(Order $order, string $viewer): array
    {
        return $order->messages()->orderBy('created_at')->get()->map(fn ($m) => [
            'side' => $m->author_type === $viewer ? 'mine' : 'theirs',
            'author' => match ($m->author_type) {
                'seller' => $order->seller?->displayName() ?? __('Seller'),
                'buyer' => $order->buyer?->name ?? __('Buyer'),
                'operator' => __('Support'),
                default => __('System'),
            },
            'body' => $m->body,
            'at' => $m->created_at->format('M j · g:i A'),
        ])->all();
    }

    /** Seller posts a message to the order conversation. */
    public function sendOrderMessage(Request $request, SellerService $sellers, SendMessage $action, string $id): RedirectResponse
    {
        $order = $this->ownedOrder($sellers, $request, $id);
        if (! $order instanceof Order) {
            return $order;
        }

        $validated = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $action->execute($order, 'seller', $sellers->forUser($request->user())?->getKey(), $validated['body']);

        return redirect()->route('sell.order', ['id' => $order->id]);
    }

    /** Advance an order along fulfilment (processing → shipped → delivered → completed). */
    public function fulfilOrder(Request $request, SellerService $sellers, SetOrderStatus $action, string $id): RedirectResponse
    {
        $order = $this->ownedOrder($sellers, $request, $id);
        if (! $order instanceof Order) {
            return $order;
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:processing,shipped,delivered,completed'],
            'carrier' => ['nullable', 'string', 'max:60'],
            'tracking' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $action->execute($order, OrderStatus::from($validated['status']), array_filter([
                'carrier' => $validated['carrier'] ?? null,
                'tracking' => $validated['tracking'] ?? null,
            ]));
        } catch (ShopException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('sell.order', ['id' => $order->id])
            ->with('success', __('Order updated.'));
    }

    /** Refund an order — returns the money to the buyer and reverses the earnings. */
    public function refundOrder(Request $request, SellerService $sellers, RefundOrder $action, string $id): RedirectResponse
    {
        $order = $this->ownedOrder($sellers, $request, $id);
        if (! $order instanceof Order) {
            return $order;
        }

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        try {
            $action->full($order, $request->user(), $validated['reason'] ?? '');
        } catch (ShopException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        return redirect()->route('sell.order', ['id' => $order->id])
            ->with('success', __('Order refunded — the buyer has been repaid.'));
    }

    /**
     * The open (buyer-raised) refund request on this order, for the seller to action.
     *
     * @param  callable(int|string): string  $fmt
     * @return array<string, mixed>|null
     */
    private function openRefundFor(Order $order, callable $fmt): ?array
    {
        $req = RefundRequest::where('order_id', $order->id)
            ->whereIn('status', ['requested', 'escalated'])->latest()->first();

        return $req ? [
            'id' => $req->id,
            'status' => $req->status->label(),
            'statusColor' => $req->status->color(),
            'type' => $req->type,
            'amount' => $fmt($req->amount_requested),
            'reason' => $req->reason,
            'escalated' => $req->status->value === 'escalated',
        ] : null;
    }

    /** Seller approves a buyer's refund request (posts the refund). */
    public function approveRefundRequest(Request $request, SellerService $sellers, ResolveRefundRequest $action, string $id, string $refundRequest): RedirectResponse
    {
        return $this->resolveRefundRequest($request, $sellers, $action, $id, $refundRequest, approve: true);
    }

    /** Seller rejects a buyer's refund request. */
    public function rejectRefundRequest(Request $request, SellerService $sellers, ResolveRefundRequest $action, string $id, string $refundRequest): RedirectResponse
    {
        return $this->resolveRefundRequest($request, $sellers, $action, $id, $refundRequest, approve: false);
    }

    private function resolveRefundRequest(Request $request, SellerService $sellers, ResolveRefundRequest $action, string $id, string $refundRequest, bool $approve): RedirectResponse
    {
        $order = $this->ownedOrder($sellers, $request, $id);
        if (! $order instanceof Order) {
            return $order;
        }

        $req = RefundRequest::where('order_id', $order->id)->findOrFail($refundRequest);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;

        try {
            $approve ? $action->approve($req, $request->user(), $note) : $action->reject($req, $request->user(), $note);
        } catch (ShopException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        return redirect()->route('sell.order', ['id' => $order->id])
            ->with('success', $approve ? __('Refund approved — the buyer has been repaid.') : __('Refund request declined.'));
    }

    /** Resolve an order the current seller owns, or a redirect away. */
    private function ownedOrder(SellerService $sellers, Request $request, string $id): Order|RedirectResponse
    {
        $seller = $sellers->forUser($request->user());
        $order = $seller
            ? $seller->orders()->with(['items.product', 'items.variant', 'buyer', 'asset', 'events'])->find($id)
            : null;

        return $order ?? redirect()->route('sell.orders');
    }

    /** Seller inbox — buyer message threads. FRONTEND-FIRST: sample conversations. */
    /** Seller inbox — orders that have a conversation, newest activity first. */
    public function inbox(Request $request, SellerService $sellers): View
    {
        $seller = $sellers->forUser($request->user());

        $threads = $seller
            ? $seller->orders()->with(['buyer', 'items'])->has('messages')
                ->orderByDesc('last_message_at')->limit(100)->get()
                ->map(function ($o) {
                    $last = $o->messages()->orderByDesc('created_at')->first();
                    $name = $o->buyer?->name ?? __('Buyer');

                    return [
                        'id' => $o->id,
                        'number' => $o->number,
                        'buyer' => $name,
                        'initials' => Str::of($name)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode(''),
                        'product' => $o->items->first()?->name_snapshot ?? '—',
                        'preview' => Str::limit($last?->body ?? '', 80),
                        'time' => $o->last_message_at?->diffForHumans(short: true) ?? '',
                        'unread' => (bool) $o->seller_unread,
                    ];
                })->all()
            : [];

        return view('frontend.seller.inbox', ['threads' => $threads]);
    }

    /** Real reviews across the seller's products, newest first, with an average. */
    public function reviews(Request $request, SellerService $sellers): View
    {
        $seller = $sellers->forUser($request->user());

        $reviews = $seller
            ? $seller->reviews()->with(['buyer', 'product'])->where('status', 'published')->latest()->limit(200)->get()
            : collect();

        return view('frontend.seller.reviews', [
            'summary' => [
                'avg' => $reviews->count() ? number_format((float) $reviews->avg('rating'), 1) : '—',
                'count' => $reviews->count(),
            ],
            'reviews' => $reviews->map(fn ($r) => [
                'id' => $r->id,
                'buyer' => $r->buyer?->name ?? __('Buyer'),
                'product' => $r->product?->name ?? '—',
                'rating' => (int) $r->rating,
                'title' => $r->title,
                'body' => $r->body,
                'date' => $r->created_at->diffForHumans(),
                'reply' => $r->seller_reply,
            ])->all(),
        ]);
    }

    /** Seller replies (once) to a review on one of their products. */
    public function replyReview(Request $request, SellerService $sellers, ReplyToReview $action, string $id): RedirectResponse
    {
        $seller = $sellers->forUser($request->user());
        $review = $seller ? $seller->reviews()->find($id) : null;
        if (! $review) {
            return redirect()->route('sell.reviews');
        }

        $validated = $request->validate(['reply' => ['required', 'string', 'max:2000']]);
        $action->execute($review, $validated['reply']);

        return redirect()->route('sell.reviews')->with('success', __('Reply posted.'));
    }

    /** Real customers: everyone who has placed a paid order, ranked by spend. */
    public function customers(Request $request, SellerService $sellers): View
    {
        $seller = $sellers->forUser($request->user());

        $orders = $seller
            ? $seller->orders()->with(['buyer', 'asset'])
                ->whereNotIn('status', [OrderStatus::Pending->value, OrderStatus::Cancelled->value])
                ->get()
            : collect();

        $asset = $seller?->settlementAsset ?? $orders->first()?->asset;
        $fmt = fn (int $base) => $asset ? $asset->money((string) $base)->format(2) : number_format($base / 100, 2);

        $customers = $orders->groupBy('buyer_user_id')->map(function ($group) use ($fmt) {
            $buyer = $group->first()->buyer;
            $spent = (int) $group->sum(fn ($o) => (int) $o->total_amount);

            return [
                'name' => $buyer?->name ?? __('Customer'),
                'email' => $buyer?->email ?? '—',
                'orders' => $group->count(),
                'spent' => $fmt($spent),
                'spentRaw' => $spent,
                'since' => $group->min('created_at')?->format('M Y') ?? '—',
            ];
        })->sortByDesc('spentRaw')->values()->all();

        return view('frontend.seller.customers', ['customers' => $customers]);
    }

    /** Real discount codes for the seller, plus their products for scoping. */
    public function coupons(Request $request, SellerService $sellers): View
    {
        $seller = $sellers->forUser($request->user());

        $coupons = $seller
            ? $seller->coupons()->with('product')->latest()->get()->map(function ($c) {
                $status = ! $c->is_active ? ['Disabled', 'gray']
                    : ($c->starts_at && $c->starts_at->isFuture() ? ['Scheduled', 'warning']
                    : ($c->ends_at && $c->ends_at->isPast() ? ['Expired', 'gray']
                    : (($c->usage_limit !== null && $c->used_count >= $c->usage_limit) ? ['Used up', 'gray']
                    : ['Active', 'success'])));

                return [
                    'id' => $c->id,
                    'code' => $c->code,
                    'type' => $c->type === CouponType::Percent
                        ? number_format($c->value / 100, ($c->value % 100 ? 1 : 0)).'% off'
                        : ($c->product?->priceAsset?->money((string) $c->value)->format(2) ?? $c->value).' off',
                    'scope' => $c->product?->name ?? __('All products'),
                    'used' => (int) $c->used_count,
                    'limit' => $c->usage_limit,
                    'status' => $status[0],
                    'color' => $status[1],
                    'active' => (bool) $c->is_active,
                    'expires' => $c->ends_at?->format('M j, Y') ?? '—',
                ];
            })->all()
            : [];

        $products = $seller ? $seller->products()->orderBy('name')->pluck('name', 'id')->all() : [];

        return view('frontend.seller.coupons', compact('coupons', 'products'));
    }

    /** Create a discount code. Fixed-amount values are entered in the seller's pricing currency. */
    public function storeCoupon(Request $request, SellerService $sellers, CreateCoupon $action): RedirectResponse
    {
        $seller = $sellers->forUser($request->user());
        if (! ($seller?->canSell() ?? false)) {
            return redirect()->route('sell');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0.01'],
            'product_id' => ['nullable', 'string'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'ends_at' => ['nullable', 'date'],
        ]);

        // Percent capped at 100; fixed amount → minor units of the product's (or a pricing) asset.
        $value = $validated['value'];
        if ($validated['type'] === 'percent') {
            $value = min(100, (float) $value);
        } else {
            $pid = $validated['product_id'] ?? null;
            if ($pid) {
                $asset = $seller->products()->find($pid)?->priceAsset;
            } else {
                $pricing = $this->pricingAssets();
                $asset = $pricing->isNotEmpty() ? Asset::find($pricing->first()['id']) : null;
            }
            $decimals = $asset?->decimals ?? 2;
            $value = (int) round(((float) $value) * (10 ** $decimals));
        }

        try {
            $action->execute($seller, [
                'code' => $validated['code'],
                'type' => $validated['type'],
                'value' => $value,
                'product_id' => ($validated['product_id'] ?? null) ?: null,
                'usage_limit' => $validated['usage_limit'] ?? null,
                'per_customer_limit' => $validated['per_customer_limit'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
            ]);
        } catch (ShopException $e) {
            return back()->withInput()->withErrors(['coupon' => $e->getMessage()]);
        }

        return redirect()->route('sell.coupons')->with('success', __('Coupon created.'));
    }

    /** Enable/disable a coupon. */
    public function toggleCoupon(Request $request, SellerService $sellers, string $id): RedirectResponse
    {
        $seller = $sellers->forUser($request->user());
        $coupon = $seller ? $seller->coupons()->find($id) : null;
        if ($coupon) {
            $coupon->update(['is_active' => ! $coupon->is_active]);
        }

        return redirect()->route('sell.coupons');
    }

    /** Soft-delete a coupon. */
    public function destroyCoupon(Request $request, SellerService $sellers, string $id): RedirectResponse
    {
        $seller = $sellers->forUser($request->user());
        $coupon = $seller ? $seller->coupons()->find($id) : null;
        $coupon?->delete();

        return redirect()->route('sell.coupons')->with('success', __('Coupon deleted.'));
    }

    /** Real 30-day funnel analytics from recorded events + paid orders. */
    public function analytics(Request $request, SellerService $sellers): View
    {
        $seller = $sellers->forUser($request->user());

        $since = now()->subDays(30);
        $blank = ['visitors' => 0, 'checkouts' => 0, 'purchases' => 0];

        if ($seller) {
            // Distinct sessions per funnel stage (a visitor counts once).
            $rows = AnalyticsEvent::query()
                ->where('seller_id', $seller->getKey())
                ->where('occurred_at', '>=', $since)
                ->selectRaw('type, count(distinct session_id) as sessions')
                ->groupBy('type')->pluck('sessions', 'type');

            $counts = [
                'visitors' => (int) ($rows[AnalyticsService::PAGE_VIEW] ?? 0),
                'checkouts' => (int) ($rows[AnalyticsService::CHECKOUT_START] ?? 0),
                'purchases' => (int) ($rows[AnalyticsService::PURCHASE] ?? 0),
            ];

            // Revenue (seller net) + order count from real paid orders in-window.
            $paid = $seller->orders()->with('asset')
                ->whereNotIn('status', [OrderStatus::Pending->value, OrderStatus::Cancelled->value])
                ->where('created_at', '>=', $since)->get();
            $asset = $seller->settlementAsset ?? $paid->first()?->asset;
            $revenue = (int) $paid->sum(fn ($o) => (int) $o->seller_net_amount);
            $ordersCount = $paid->count();

            $sources = AnalyticsEvent::query()
                ->where('seller_id', $seller->getKey())
                ->where('type', AnalyticsService::PAGE_VIEW)
                ->where('occurred_at', '>=', $since)
                ->get(['referrer', 'utm'])
                ->groupBy(fn ($e) => $this->trafficSource($e))
                ->map->count()->sortDesc();
            $sourceTotal = max(1, $sources->sum());
        } else {
            $counts = $blank;
            $asset = null;
            $revenue = $ordersCount = 0;
            $sources = collect();
            $sourceTotal = 1;
        }

        $fmt = fn (int $base) => $asset ? $asset->money((string) $base)->format(2) : number_format($base / 100, 2);
        $conversion = $counts['visitors'] > 0 ? round($counts['purchases'] / $counts['visitors'] * 100, 1) : 0.0;
        $aov = $ordersCount > 0 ? intdiv($revenue, $ordersCount) : 0;
        $pct = fn (int $n) => $counts['visitors'] > 0 ? (int) round($n / $counts['visitors'] * 100) : 0;

        return view('frontend.seller.analytics', [
            'kpis' => [
                ['Visitors · 30d', number_format($counts['visitors']), 'eye', 'text-neutral-900', null],
                ['Conversion', $conversion.'%', 'cursor-arrow-rays', 'text-emerald-600', null],
                ['Revenue · 30d', $fmt($revenue), 'banknotes', 'text-neutral-900', null],
                ['Avg. order value', $fmt($aov), 'shopping-cart', 'text-neutral-900', null],
            ],
            'funnel' => [
                ['Page views', $counts['visitors'], 100],
                ['Checkout started', $counts['checkouts'], $pct($counts['checkouts'])],
                ['Purchased', $counts['purchases'], $pct($counts['purchases'])],
            ],
            'sources' => $sources->take(6)->map(fn ($n, $name) => [$name, (int) round($n / $sourceTotal * 100)])->values()->all(),
        ]);
    }

    /** Bucket a page-view event into a human traffic source. */
    private function trafficSource(AnalyticsEvent $e): string
    {
        if (! empty($e->utm['source'])) {
            return ucfirst((string) $e->utm['source']);
        }
        if (empty($e->referrer)) {
            return 'Direct / link';
        }
        $host = strtolower((string) parse_url($e->referrer, PHP_URL_HOST));

        return match (true) {
            str_contains($host, 'facebook'), str_contains($host, 'fb.') => 'Facebook',
            str_contains($host, 'google') => 'Google',
            str_contains($host, 'youtube') => 'YouTube',
            str_contains($host, 't.co'), str_contains($host, 'twitter'), str_contains($host, 'x.com') => 'X / Twitter',
            str_contains($host, 'instagram') => 'Instagram',
            $host === '' => 'Direct / link',
            default => 'Other',
        };
    }

    public function earnings(Request $request, SellerService $sellers): View|RedirectResponse
    {
        $seller = $sellers->forUser($request->user());
        if (! $seller || ! $seller->canSell()) {
            return redirect()->route('sell');
        }

        [$balances, $recent] = $this->sellerEarnings($seller);

        return view('frontend.seller.earnings', [
            'balances' => $balances,
            'recent' => $recent,
            'assetCode' => ($seller->settlementAsset ?? $seller->orders()->first()?->asset)?->symbol,
            'withdrawUrl' => route('withdraw.index'),
        ]);
    }

    /**
     * Real seller earnings, computed from their orders. Net (price minus platform
     * commission) is credited to the seller's PoisaPay wallet at purchase; here we
     * split the lifetime total into what has cleared the refund window (Available)
     * vs. what is still inside it (Pending), and build a recent-earnings feed.
     *
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function sellerEarnings(Seller $seller): array
    {
        $asset = $seller->settlementAsset
            ?? Asset::where('is_active', true)->where('decimals', '<=', 8)->orderBy('id')->first();

        // Earning orders — everything paid that isn't cancelled or fully refunded.
        $orders = $seller->orders()
            ->when($asset, fn ($q) => $q->where('asset_id', $asset->id))
            ->whereNotIn('status', [OrderStatus::Pending->value, OrderStatus::Cancelled->value, OrderStatus::Refunded->value])
            ->with(['items' => fn ($q) => $q->where('kind', OrderItemKind::Main->value)])
            ->latest('paid_at')
            ->get(['id', 'number', 'status', 'seller_net_amount', 'asset_id', 'earnings_held', 'earnings_released_at', 'paid_at', 'created_at']);

        $fmt = fn (int $base) => $asset ? $asset->money((string) $base)->format(2) : number_format($base / 100, 2);

        // Available = spendable now (released, or credited instantly while the hold
        // flag is off). Pending = still held during the buyer's refund window.
        $isHeld = fn (Order $o) => $o->earnings_held && $o->earnings_released_at === null;
        $sum = fn ($rows) => (int) $rows->sum(fn (Order $o) => (int) $o->seller_net_amount);

        $pending = $sum($orders->filter($isHeld));
        $available = $sum($orders->reject($isHeld));

        $balances = [
            'available' => $fmt($available),
            'pending' => $fmt($pending),
            'revenue' => $fmt($available + $pending),
            'sales' => $orders->count(),
        ];

        $recent = $orders->take(10)->map(fn (Order $o) => [
            'number' => $o->number,
            'product' => $o->items->first()?->name_snapshot ?? __('Order'),
            'amount' => $fmt((int) $o->seller_net_amount),
            'status' => $isHeld($o) ? __('On hold') : __('Available'),
            'color' => $isHeld($o) ? 'warning' : 'success',
            'cleared' => ! $isHeld($o),
            'date' => optional($o->paid_at ?? $o->created_at)->format('M j, Y'),
        ])->values()->all();

        return [$balances, $recent];
    }

    /**
     * Sales pages. A seller can create many — a product can even have several
     * pages (e.g. different ad campaigns). Create one against a product, then
     * customize it in the builder.
     */
    /** Real sales-page list + the seller's products for the create picker. */
    public function salesPages(Request $request, SellerService $sellers): View
    {
        $seller = $sellers->forUser($request->user());

        $color = [
            SalesPageStatus::Draft->value => 'gray',
            SalesPageStatus::Published->value => 'success',
            SalesPageStatus::Archived->value => 'warning',
        ];

        $pages = $seller
            ? $seller->salesPages()->with(['product', 'domain'])->latest()->get()->map(fn ($pg) => [
                'name' => $pg->name,
                'product' => $pg->product?->name ?? '—',
                'slug' => $pg->slug,
                'status' => ucfirst($pg->status->value),
                'color' => $color[$pg->status->value] ?? 'gray',
                'domain' => $pg->domain?->host,
                'views' => '—', // real page views arrive with the analytics vertical
                'conv' => '—',
            ])->all()
            : [];

        // Products the seller can attach a page to (id => name).
        $products = $seller
            ? $seller->products()->orderBy('name')->pluck('name', 'id')->all()
            : [];

        return view('frontend.seller.sales-pages-index', compact('pages', 'products'));
    }

    /** Create a page for one of the seller's products, then jump to the builder. */
    public function storeSalesPage(Request $request, SellerService $sellers, CreateSalesPage $action): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $seller = $sellers->forUser($request->user());
        if (! ($seller?->canSell() ?? false)) {
            return redirect()->route('sell');
        }

        try {
            $page = $action->execute($seller, SalesPageData::fromArray([
                'product_id' => $validated['product_id'],
                'name' => $validated['name'],
            ]));
        } catch (ShopException $e) {
            return back()->withInput()->withErrors(['sales_page' => $e->getMessage()]);
        }

        return redirect()->route('sell.sales-page.edit', ['slug' => $page->slug])
            ->with('success', __('Page created — customize and publish it.'));
    }

    /** Sales-page builder — loads the real page and seeds the editor from persisted config. */
    public function editSalesPage(Request $request, SellerService $sellers, string $slug): View|RedirectResponse
    {
        $page = $this->ownedPage($sellers, $request, $slug);
        if (! $page instanceof SalesPage) {
            return $page; // redirect
        }

        $info = $this->productInfo($page);

        // The seller's products: names for the switcher, facts for live copy resync.
        $sellerProducts = Product::where('seller_id', $page->seller_id)->with('priceAsset')->orderBy('name')->get();

        // Offer prices shown to the seller in decimal (their pricing currency).
        $asset = $page->product?->priceAsset;
        $toDec = fn (?int $minor) => $minor === null ? '' : rtrim(rtrim(number_format($minor / (10 ** ($asset?->decimals ?? 2)), $asset?->decimals ?? 2, '.', ''), '0'), '.');

        return view('frontend.seller.sales-pages', [
            'page' => $page,
            'slug' => $page->slug,
            'product' => $page->product?->name ?? __('Product'),
            'productInfo' => $info,
            'products' => $sellerProducts->pluck('name', 'id')->all(),
            'productsInfo' => $sellerProducts->mapWithKeys(fn ($p) => [$p->id => $this->productFacts($p)])->all(),
            'published' => $page->status === SalesPageStatus::Published,
            'seed' => $this->builderSeed($page, $info),
            'themes' => ['#2563eb' => 'Blue', '#7c3aed' => 'Violet', '#059669' => 'Emerald', '#e11d48' => 'Rose', '#ea580c' => 'Orange', '#0f172a' => 'Slate'],
            'offers' => [
                'bump_product_id' => $page->bump_product_id,
                'bump_price' => $toDec($page->bump_price_amount),
                'bump_headline' => $page->bump_headline,
                'bump_description' => $page->bump_description,
                'upsell_product_id' => $page->upsell_product_id,
                'upsell_price' => $toDec($page->upsell_price_amount),
                'upsell_headline' => $page->upsell_headline,
                'upsell_description' => $page->upsell_description,
            ],
            'currencySymbol' => $asset?->symbol ?? '',
            'seo' => [
                'title' => $page->seo['title'] ?? '',
                'description' => $page->seo['description'] ?? '',
                'og_image' => $page->seo['og_image'] ?? '',
                'noindex' => (bool) ($page->seo['noindex'] ?? false),
            ],
            'publicUrl' => route('funnel.sales', ['slug' => $page->slug]),
        ]);
    }

    /**
     * The real product behind a sales page — used to seed the builder's Product
     * section with live name/price/summary instead of placeholder copy.
     *
     * @return array<string, string|null>
     */
    private function productInfo(SalesPage $page): array
    {
        return $this->productFacts($page->product) + [
            'seller' => $page->seller?->displayName() ?? $page->product?->seller?->displayName(),
        ];
    }

    /**
     * Display-ready facts for one product (name/type/summary/price…). Used both to
     * seed the builder and, keyed by id, to resync section copy when the seller
     * switches the page's product.
     *
     * @return array<string, string|null>
     */
    private function productFacts(?Product $product): array
    {
        $asset = $product?->priceAsset;

        return [
            'name' => $product?->name,
            'type' => $product?->type->label(),
            'summary' => $product?->summary,
            'description' => $product?->description,
            'price' => $product && $asset ? $asset->money((string) $product->price_amount)->format(2) : null,
            'compare' => $product && $asset && $product->compare_price_amount
                ? $asset->money((string) $product->compare_price_amount)->format(2)
                : null,
        ];
    }

    /** Persist the builder (name, sections, theme) without changing status. */
    public function saveSalesPage(Request $request, SellerService $sellers, UpdateSalesPage $action, string $slug): RedirectResponse
    {
        $page = $this->ownedPage($sellers, $request, $slug);
        if (! $page instanceof SalesPage) {
            return $page;
        }

        $action->execute($page, $this->salesPageDataFromRequest($request, $page));
        $this->applyOffers($page->fresh(), $request);
        $this->applySeo($page->fresh(), $request);

        return redirect()->route('sell.sales-page.edit', ['slug' => $page->slug])
            ->with('success', __('Changes saved.'));
    }

    /** Persist per-page SEO/social overrides into the `seo` jsonb. */
    private function applySeo(SalesPage $page, Request $request): void
    {
        $validated = $request->validate([
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:200'],
            'seo_og_image' => ['nullable', 'url', 'max:300'],
            'seo_noindex' => ['nullable', 'boolean'],
        ]);

        $page->update(['seo' => array_filter([
            'title' => $validated['seo_title'] ?? null,
            'description' => $validated['seo_description'] ?? null,
            'og_image' => $validated['seo_og_image'] ?? null,
            'noindex' => $request->boolean('seo_noindex') ?: null,
        ], fn ($v) => $v !== null && $v !== '')]);
    }

    /**
     * Persist the page's order-bump + upsell offers. Prices are entered in the
     * main product's currency (decimal) and stored as minor units. Offer products
     * must belong to the seller and share the main product's currency.
     */
    private function applyOffers(SalesPage $page, Request $request): void
    {
        $validated = $request->validate([
            'bump_product_id' => ['nullable', 'string'],
            'bump_price' => ['nullable', 'numeric', 'min:0'],
            'bump_headline' => ['nullable', 'string', 'max:160'],
            'bump_description' => ['nullable', 'string', 'max:400'],
            'upsell_product_id' => ['nullable', 'string'],
            'upsell_price' => ['nullable', 'numeric', 'min:0'],
            'upsell_headline' => ['nullable', 'string', 'max:160'],
            'upsell_description' => ['nullable', 'string', 'max:400'],
        ]);

        $asset = $page->product?->priceAsset;
        $decimals = $asset?->decimals ?? 2;
        $toMinor = fn ($v) => ($v === null || $v === '') ? null
            : (int) Money::ofDecimal((string) $v, $decimals, $asset?->symbol ?? '')->baseString();

        // A picked product only counts if the seller owns it and it's same-currency.
        $resolve = function (?string $id) use ($page, $asset): ?string {
            if (! $id) {
                return null;
            }
            $p = Product::where('seller_id', $page->seller_id)->whereKey($id)->first();

            return ($p && (int) $p->price_asset_id === (int) $asset?->id && $p->getKey() !== $page->product_id)
                ? $p->getKey() : null;
        };

        $bumpId = $resolve($validated['bump_product_id'] ?? null);
        $upsellId = $resolve($validated['upsell_product_id'] ?? null);

        $page->update([
            'bump_product_id' => $bumpId,
            'bump_price_amount' => $bumpId ? $toMinor($validated['bump_price'] ?? null) : null,
            'bump_headline' => $bumpId ? ($validated['bump_headline'] ?? null) : null,
            'bump_description' => $bumpId ? ($validated['bump_description'] ?? null) : null,
            'upsell_product_id' => $upsellId,
            'upsell_price_amount' => $upsellId ? $toMinor($validated['upsell_price'] ?? null) : null,
            'upsell_headline' => $upsellId ? ($validated['upsell_headline'] ?? null) : null,
            'upsell_description' => $upsellId ? ($validated['upsell_description'] ?? null) : null,
        ]);
    }

    /**
     * Publish (or unpublish) the page. Publishing also takes the product live so a
     * seller can go from draft to a shareable link in one click.
     */
    public function publishSalesPage(
        Request $request,
        SellerService $sellers,
        UpdateSalesPage $update,
        SetSalesPageStatus $setStatus,
        SetProductStatus $setProductStatus,
        string $slug,
    ): RedirectResponse {
        $page = $this->ownedPage($sellers, $request, $slug);
        if (! $page instanceof SalesPage) {
            return $page;
        }

        // Persist current builder edits alongside the publish toggle.
        $update->execute($page, $this->salesPageDataFromRequest($request, $page));
        $this->applyOffers($page->fresh(), $request);
        $this->applySeo($page->fresh(), $request);

        $goLive = $page->status !== SalesPageStatus::Published;

        try {
            if ($goLive && $page->product && $page->product->status !== ProductStatus::Published) {
                $setProductStatus->execute($page->product, ProductStatus::Published);
            }
            $setStatus->execute($page->fresh(), $goLive ? SalesPageStatus::Published : SalesPageStatus::Draft);
        } catch (ShopException $e) {
            return back()->withErrors(['publish' => $e->getMessage()]);
        }

        return redirect()->route('sell.sales-page.edit', ['slug' => $page->slug])
            ->with('success', $goLive ? __('Your page is live.') : __('Page unpublished.'));
    }

    /** Resolve a page the current user owns, or a redirect away. */
    private function ownedPage(SellerService $sellers, Request $request, string $slug): SalesPage|RedirectResponse
    {
        $seller = $sellers->forUser($request->user());
        $page = $seller
            ? $seller->salesPages()->with('product')->where('slug', $slug)->first()
            : null;

        return $page ?? redirect()->route('sell.sales-pages');
    }

    /** Decode the builder payload from the editor into a SalesPageData DTO. */
    private function salesPageDataFromRequest(Request $request, SalesPage $page): SalesPageData
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'product_id' => ['nullable', 'string'],
            'builder' => ['nullable', 'json'],
        ]);

        $doc = json_decode($validated['builder'] ?? '{}', true) ?: [];

        // The page can be re-pointed at another of the seller's products — but only
        // ever one they own (a foreign product id falls back to the current one).
        $productId = $page->product_id;
        if (! empty($validated['product_id']) && $validated['product_id'] !== $page->product_id
            && Product::where('seller_id', $page->seller_id)->whereKey($validated['product_id'])->exists()) {
            $productId = $validated['product_id'];
        }

        return SalesPageData::fromArray([
            'product_id' => $productId,
            'name' => $validated['name'],
            'sections' => $doc['sections'] ?? $page->sections ?? [],
            'theme' => $doc['theme'] ?? $page->theme ?? [],
            'seo' => $page->seo ?? [],
            'tracking' => $page->tracking ?? [],
        ]);
    }

    /**
     * Seed the Alpine builder: persisted layout/content if the page has any,
     * otherwise the default template so a fresh page opens ready to edit.
     *
     * @param  array<string, string|null>  $info  live product facts (see {@see productInfo()})
     * @return array<string, mixed>
     */
    private function builderSeed(SalesPage $page, array $info = []): array
    {
        $theme = $page->theme ?? [];
        $sections = $page->sections ?? [];

        $seed = [
            'name' => $page->name,
            'accent' => $theme['accent'] ?? '#2563eb',
            'btn' => $theme['btn'] ?? 'rounded',
            'font' => $theme['font'] ?? 'Inter',
        ];

        // Persisted layout → the editor's Alpine props (order + enabled-map + content).
        // The enabled-map is named `sections` in the builder, so we mirror that here.
        if ($sections !== []) {
            $seed['order'] = array_map(fn ($s) => $s['type'], $sections);
            $seed['sections'] = [];
            $seed['content'] = [];
            foreach ($sections as $s) {
                $seed['sections'][$s['type']] = (bool) ($s['enabled'] ?? true);
                if (array_key_exists('content', $s) && $s['content'] !== null) {
                    $seed['content'][$s['type']] = $this->normalizeSectionContent($s['type'], $s['content']);
                }
            }

            // The Product section is newer than some saved pages — guarantee it
            // exists (right after the hero) so every page shows the real product.
            if (! in_array('product', $seed['order'], true)) {
                $at = array_search('hero', $seed['order'], true);
                array_splice($seed['order'], $at === false ? 0 : $at + 1, 0, 'product');
                $seed['sections']['product'] = true;
            }
            if ($seed['content'] !== [] && ! isset($seed['content']['product'])) {
                $seed['content']['product'] = $this->productSectionContent($info);
            }
            if ($seed['content'] === []) {
                unset($seed['content']); // keep the builder's rich default content
            }
        }

        return $seed;
    }

    /**
     * Upgrade older persisted section content to the current editor shape so the
     * builder never binds to a missing key: FAQ strings → {q, a}, testimonials
     * gain a `role`. Anything already in the new shape passes through untouched.
     *
     * @param  mixed  $content
     * @return mixed
     */
    private function normalizeSectionContent(string $type, $content)
    {
        if ($type === 'faq' && is_array($content)) {
            return array_map(
                fn ($item) => is_array($item) ? $item : ['q' => (string) $item, 'a' => ''],
                $content,
            );
        }

        if ($type === 'testimonials' && is_array($content)) {
            return array_map(
                fn ($t) => is_array($t) ? array_merge(['name' => '', 'role' => '', 'quote' => ''], $t) : ['name' => '', 'role' => '', 'quote' => (string) $t],
                $content,
            );
        }

        return $content;
    }

    /**
     * Default copy for the Product section, seeded from the live product.
     *
     * @param  array<string, string|null>  $info
     * @return array<string, string>
     */
    private function productSectionContent(array $info): array
    {
        return [
            'name' => $info['name'] ?? __('Your product'),
            'type' => $info['type'] ?? __('Digital download'),
            'summary' => $info['summary'] ?? __('What this product includes and why it’s worth it.'),
            'price' => $info['price'] ?? '—',
            'compare' => $info['compare'] ?? '',
            'btn' => __('Buy now'),
            'note' => __('Instant, secure checkout — wallet, card, crypto, bank & mobile money.'),
        ];
    }

    /** Funnel builder. FRONTEND-FIRST: a sample funnel so the flow is reviewable. */
    public function funnels(Request $request): View
    {
        return view('frontend.seller.funnels', [
            'funnel' => [
                'name' => 'LaunchKit funnel',
                'product' => 'LaunchKit — Laravel SaaS Boilerplate',
                'price' => '$49',
                'steps' => [
                    ['kind' => 'bump', 'label' => 'Order bump', 'offer' => 'Premium UI Kit add-on', 'price' => '$19', 'rate' => 34, 'icon' => 'plus-circle', 'where' => 'Shown inside checkout'],
                    ['kind' => 'upsell', 'label' => 'Upsell #1', 'offer' => 'Extended Team License', 'price' => '$59', 'rate' => 22, 'icon' => 'arrow-trending-up', 'where' => 'One-click after payment'],
                    ['kind' => 'downsell', 'label' => 'Downsell', 'offer' => 'Single-site License', 'price' => '$29', 'rate' => 11, 'icon' => 'arrow-trending-down', 'where' => 'If the upsell is skipped'],
                ],
            ],
        ]);
    }

    public function createProduct(Request $request, SellerService $sellers): View|RedirectResponse
    {
        if (! ($sellers->forUser($request->user())?->canSell() ?? false)) {
            return redirect()->route('sell'); // only approved sellers can create
        }

        return view('frontend.seller.product-create', [
            'types' => self::PRODUCT_TYPES,
            'assets' => $this->pricingAssets(),
            'product' => null,
            'variantSeed' => $this->variantSeed(null),
        ]);
    }

    /** Persist a new product (Draft) through the Sell backend action. */
    public function storeProduct(Request $request, SellerService $sellers, CreateProduct $action): RedirectResponse
    {
        $validated = $request->validate($this->productRules());

        $seller = $sellers->forUser($request->user());
        if (! ($seller?->canSell() ?? false)) {
            return redirect()->route('sell');
        }

        try {
            $product = $action->execute($seller, $this->toProductData($validated, $request));
        } catch (ShopException $e) {
            return back()->withInput()->withErrors(['product' => $e->getMessage()]);
        }

        return redirect()->route('sell.products')
            ->with('success', __('“:name” created as a draft — publish it to generate its sales page.', ['name' => $product->name]));
    }

    /** Edit form for a product the seller owns. 404s on someone else's product. */
    public function editProduct(Request $request, SellerService $sellers, string $id): View|RedirectResponse
    {
        $seller = $sellers->forUser($request->user());
        if (! ($seller?->canSell() ?? false)) {
            return redirect()->route('sell');
        }

        $product = $seller->products()->with(['priceAsset', 'variants'])->findOrFail($id);

        return view('frontend.seller.product-create', [
            'types' => self::PRODUCT_TYPES,
            'assets' => $this->pricingAssets(),
            'product' => $product,
            'variantSeed' => $this->variantSeed($product),
        ]);
    }

    /** Persist edits to an owned product. Slug + status are untouched here. */
    public function updateProduct(Request $request, SellerService $sellers, UpdateProduct $action, string $id): RedirectResponse
    {
        $validated = $request->validate($this->productRules());

        $seller = $sellers->forUser($request->user());
        if (! ($seller?->canSell() ?? false)) {
            return redirect()->route('sell');
        }
        $product = $seller->products()->findOrFail($id);

        try {
            $action->execute($product, $this->toProductData($validated, $request));
        } catch (ShopException $e) {
            return back()->withInput()->withErrors(['product' => $e->getMessage()]);
        }

        return redirect()->route('sell.products')
            ->with('success', __('“:name” updated.', ['name' => $product->name]));
    }

    /** Validation rules shared by product create + update. */
    private function productRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(self::PRODUCT_TYPES))],
            'price' => ['required', 'numeric', 'min:0'],
            'price_asset_id' => ['required', 'integer', 'exists:assets,id'],
            'summary' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string'],
            // Physical extras (kept in attributes until the variant matrix lands)
            'weight' => ['nullable', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'max:64'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            // Physical variant matrix (Size/Color → per-variant price & stock)
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'string'],
            'variants.*.options' => ['nullable', 'array'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.sku' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * Reconstruct the variant-matrix editor state from a product's live variants
     * (or defaults for a fresh product) so editing shows real options/price/stock.
     *
     * @return array{has:bool, options:list<array{name:string,values:list<string>}>, rows:array<string,array<string,mixed>>}
     */
    private function variantSeed(?Product $product): array
    {
        $default = [
            ['name' => 'Size', 'values' => ['S', 'M', 'L']],
            ['name' => 'Color', 'values' => ['Black', 'White']],
        ];

        $variants = $product ? $product->variants->where('is_active', true)->sortBy('position') : collect();
        if ($variants->isEmpty()) {
            return ['has' => false, 'options' => $default, 'rows' => []];
        }

        $asset = $product->priceAsset;
        $names = [];
        $values = [];
        $rows = [];
        foreach ($variants as $var) {
            $opts = $var->options ?? [];
            foreach ($opts as $name => $val) {
                if (! in_array($name, $names, true)) {
                    $names[] = $name;
                    $values[$name] = [];
                }
                if (! in_array($val, $values[$name], true)) {
                    $values[$name][] = $val;
                }
            }
            $rows[implode(' / ', array_values($opts))] = [
                'id' => $var->id,
                'price' => $var->price_amount !== null && $asset ? $asset->money((string) $var->price_amount)->toDecimal() : '',
                'stock' => $var->stock,
                'sku' => $var->sku,
            ];
        }

        return [
            'has' => true,
            'options' => array_map(fn ($n) => ['name' => $n, 'values' => $values[$n]], $names),
            'rows' => $rows,
        ];
    }

    /**
     * Build ProductData from validated input, converting the decimal price to the
     * asset's minor units. Shared by create + update.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException when the asset/price can't back a listing.
     */
    private function toProductData(array $validated, Request $request): ProductData
    {
        $asset = Asset::findOrFail((int) $validated['price_asset_id']);
        if ($asset->decimals > 8) {
            throw ValidationException::withMessages(['price_asset_id' => __('Products can only be priced in fiat or stablecoins.')]);
        }
        $base = Money::ofDecimal($validated['price'], $asset->decimals, $asset->symbol)->baseString();
        if (BigInteger::of($base)->isGreaterThan(PHP_INT_MAX)) {
            throw ValidationException::withMessages(['price' => __('That price is too large.')]);
        }

        // Physical delivery details ride in attributes until per-variant stock lands.
        $attributes = [];
        $variants = [];
        if ($validated['type'] === 'physical') {
            $attributes = array_filter([
                'weight' => $validated['weight'] ?? null,
                'sku' => $validated['sku'] ?? null,
                'shipping_fee' => $validated['shipping_fee'] ?? null,
                'stock' => $validated['stock'] ?? null,
            ], fn ($v) => $v !== null);

            // Variant matrix: each row's decimal price → the asset's minor units.
            foreach ($validated['variants'] ?? [] as $v) {
                $opts = array_filter($v['options'] ?? [], fn ($x) => $x !== null && $x !== '');
                if ($opts === []) {
                    continue;
                }
                $variants[] = [
                    'id' => $v['id'] ?? null,
                    'options' => $opts,
                    'price_amount' => isset($v['price']) && $v['price'] !== '' && $v['price'] !== null
                        ? (int) Money::ofDecimal((string) $v['price'], $asset->decimals, $asset->symbol)->baseString()
                        : null,
                    'stock' => isset($v['stock']) && $v['stock'] !== '' ? (int) $v['stock'] : null,
                    'sku' => $v['sku'] ?? null,
                ];
            }
        }

        return ProductData::fromArray([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'summary' => $validated['summary'] ?? null,
            'description' => $validated['description'] ?? null,
            'price_amount' => (int) $base,
            'price_asset_id' => $asset->id,
            'requires_shipping' => $validated['type'] === 'physical',
            'attributes' => $attributes,
            'variants' => $variants,
        ]);
    }

    public function apply(Request $request, SellerService $sellers): View|RedirectResponse
    {
        // Already applied/approved/suspended → send them to the seller home (status shown there).
        $seller = $sellers->forUser($request->user());
        if ($seller && $seller->status->canApply() === false) {
            return redirect()->route('sell');
        }

        return view('frontend.seller.apply', [
            'categories' => self::CATEGORIES,
            'countries' => $this->countries(),
            'settlementAssets' => $this->pricingAssets(),
            'defaultCountry' => (string) ($request->user()->country ?? 'BD'),
        ]);
    }

    /** Persist the application through the Sell backend action. */
    public function submitApplication(Request $request, SubmitSellerApplication $action): RedirectResponse
    {
        $validated = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url', 'max:190'],
            'country' => ['required', 'string', 'size:2'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['string', 'max:32'],
            'settlement_asset_id' => ['nullable', 'integer'],
            'terms' => ['accepted'],
        ]);

        try {
            $action->execute($request->user(), SellerApplicationData::fromArray($validated));
        } catch (ShopException $e) {
            return back()->withInput()->withErrors(['apply' => $e->getMessage()]);
        }

        return redirect()->route('sell')
            ->with('success', 'Application submitted — we usually review within 1–2 business days.');
    }

    /**
     * Assets a product/settlement may be priced in: fiat + stablecoins only.
     * Volatile 18-decimal coins are excluded — they make no sense as a listing
     * price and their minor-unit amounts overflow the int64 price column.
     * One asset per currency (the canonical/lowest id).
     *
     * @return Collection<int, array{id:int,symbol:string,name:string}>
     */
    private function pricingAssets(): Collection
    {
        return Asset::where('is_active', true)
            ->where('decimals', '<=', 8)
            ->get()
            ->groupBy('currency_id')
            ->map(fn ($g) => $g->sortBy('id')->first())
            ->sortBy('symbol')
            ->map(fn ($a) => ['id' => $a->id, 'symbol' => $a->symbol, 'name' => (string) $a->name])
            ->values();
    }

    /** @return array<string, string> ISO2 => name (short, common-first list). */
    private function countries(): array
    {
        return [
            'BD' => 'Bangladesh', 'IN' => 'India', 'PK' => 'Pakistan', 'US' => 'United States',
            'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia', 'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia', 'MY' => 'Malaysia', 'SG' => 'Singapore', 'ID' => 'Indonesia',
            'NG' => 'Nigeria', 'DE' => 'Germany', 'FR' => 'France', 'NL' => 'Netherlands', 'OT' => 'Other',
        ];
    }
}
