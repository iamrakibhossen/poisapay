<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Sell\Actions\Order\SetOrderStatus;
use App\Sell\Actions\Product\CreateProduct;
use App\Sell\Actions\Product\SetProductStatus;
use App\Sell\Actions\Product\UpdateProduct;
use App\Sell\Actions\SalesPage\CreateSalesPage;
use App\Sell\Actions\SalesPage\SetSalesPageStatus;
use App\Sell\Actions\SalesPage\UpdateSalesPage;
use App\Sell\Actions\Seller\SubmitSellerApplication;
use App\Sell\DTOs\ProductData;
use App\Sell\DTOs\SalesPageData;
use App\Sell\DTOs\SellerApplicationData;
use App\Sell\Enums\OrderStatus;
use App\Sell\Enums\ProductStatus;
use App\Sell\Enums\SalesPageStatus;
use App\Sell\Enums\SellerStatus;
use App\Sell\Exceptions\SellException;
use App\Sell\Models\Order;
use App\Sell\Models\SalesPage;
use App\Sell\Models\Seller;
use App\Sell\Services\SellerService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        if ($isSeller) {
            $counts['products'] = $seller->products()->count();
            $counts['published'] = $seller->products()->where('status', ProductStatus::Published->value)->count();
            $counts['pages'] = $seller->products()->has('salesPages')->count();
            [$stats, $counts['sales']] = $this->sellerStats($seller);
        }

        return view('frontend.seller.index', [
            'seller' => $seller,
            'isSeller' => $isSeller,
            'status' => $seller?->status,
            'counts' => $counts,
            'hasProducts' => $counts['products'] > 0,
            'stats' => $stats,
        ]);
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

        $fmt = fn (int $base) => $asset ? $asset->money((string) $base)->format(2) : number_format($base / 100, 2);

        return [[
            'revenue' => $fmt($sum($paid)),
            'available' => $fmt($sum($paid->whereIn('status', $settled))),
            'pending' => $fmt($sum($paid->whereNotIn('status', $settled))),
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
                'events' => $order->events->sortBy('created_at')->map(fn ($e) => [
                    'label' => ucwords(str_replace('_', ' ', $e->type)),
                    'at' => $e->created_at->format('M j · g:i A'),
                ])->values()->all(),
            ],
        ]);
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
        } catch (SellException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('sell.order', ['id' => $order->id])
            ->with('success', __('Order updated.'));
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
    public function inbox(Request $request): View
    {
        return view('frontend.seller.inbox', [
            'threads' => [
                [
                    'id' => 1, 'buyer' => 'Aisha Karim', 'initials' => 'AK', 'product' => 'LaunchKit',
                    'subject' => 'Question about my order', 'time' => '2h', 'unread' => true,
                    'messages' => [
                        ['from' => 'buyer', 'body' => 'Hi! Does LaunchKit include future updates?', 'at' => '10:20 AM'],
                        ['from' => 'seller', 'body' => 'Yes — lifetime updates are included with your purchase.', 'at' => '10:45 AM'],
                        ['from' => 'buyer', 'body' => 'Perfect, thank you! One more — is there Livewire support?', 'at' => '10:47 AM'],
                    ],
                ],
                [
                    'id' => 2, 'buyer' => 'Tanvir Hasan', 'initials' => 'TH', 'product' => 'PoisaHub Dev Tee',
                    'subject' => 'Shipping / delivery', 'time' => '1d', 'unread' => false,
                    'messages' => [
                        ['from' => 'buyer', 'body' => 'When will my order ship? Order PH-10427.', 'at' => 'Yesterday'],
                        ['from' => 'seller', 'body' => 'Shipped today via Pathao — tracking BD-7712-9920.', 'at' => 'Yesterday'],
                    ],
                ],
                [
                    'id' => 3, 'buyer' => 'Maria Lopez', 'initials' => 'ML', 'product' => 'Premium UI Kit',
                    'subject' => 'Problem with the product', 'time' => '3d', 'unread' => false,
                    'messages' => [
                        ['from' => 'buyer', 'body' => 'The Figma file link seems broken.', 'at' => 'Jul 22'],
                        ['from' => 'seller', 'body' => 'Sorry about that — fixed and re-sent the link.', 'at' => 'Jul 22'],
                        ['from' => 'buyer', 'body' => 'Got it, works now. Thanks!', 'at' => 'Jul 22'],
                    ],
                ],
            ],
        ]);
    }

    public function reviews(Request $request): View
    {
        return view('frontend.seller.reviews', [
            'summary' => ['avg' => '4.9', 'count' => 214],
            'reviews' => [
                ['buyer' => 'Aisha K.', 'product' => 'LaunchKit', 'rating' => 5, 'body' => 'I launched my MVP in 4 days. Paid for itself instantly.', 'date' => '2 days ago', 'reply' => null],
                ['buyer' => 'Tanvir H.', 'product' => 'Premium UI Kit', 'rating' => 5, 'body' => 'Clean components, saved me hours.', 'date' => '4 days ago', 'reply' => 'Thanks Tanvir — glad it helped!'],
                ['buyer' => 'Maria L.', 'product' => 'LaunchKit', 'rating' => 4, 'body' => 'Great starter. Docs could be a bit deeper.', 'date' => '1 week ago', 'reply' => null],
            ],
        ]);
    }

    public function customers(Request $request): View
    {
        return view('frontend.seller.customers', [
            'customers' => [
                ['name' => 'Aisha Karim', 'email' => 'aisha@example.com', 'orders' => 4, 'spent' => '$246', 'since' => 'Jan 2026'],
                ['name' => 'Tanvir Hasan', 'email' => 'tanvir@example.com', 'orders' => 2, 'spent' => '$88', 'since' => 'Mar 2026'],
                ['name' => 'Maria Lopez', 'email' => 'maria@example.com', 'orders' => 1, 'spent' => '$49', 'since' => 'Jul 2026'],
                ['name' => 'Karim Ahmed', 'email' => 'karim@example.com', 'orders' => 3, 'spent' => '$154', 'since' => 'Feb 2026'],
            ],
        ]);
    }

    public function coupons(Request $request): View
    {
        return view('frontend.seller.coupons', [
            'coupons' => [
                ['code' => 'LAUNCH20', 'type' => '20% off', 'used' => 142, 'limit' => 500, 'status' => 'Active', 'color' => 'success', 'expires' => 'Aug 31, 2026'],
                ['code' => 'BLACKFRIDAY', 'type' => '$15 off', 'used' => 0, 'limit' => 1000, 'status' => 'Scheduled', 'color' => 'warning', 'expires' => 'Nov 29, 2026'],
                ['code' => 'EARLYBIRD', 'type' => '30% off', 'used' => 200, 'limit' => 200, 'status' => 'Expired', 'color' => 'gray', 'expires' => 'Jun 1, 2026'],
            ],
        ]);
    }

    public function analytics(Request $request): View
    {
        return view('frontend.seller.analytics', [
            'kpis' => [
                ['Visitors · 30d', '12,480', 'eye', 'text-neutral-900', '+18%'],
                ['Conversion', '3.2%', 'cursor-arrow-rays', 'text-emerald-600', '+0.4pt'],
                ['Revenue · 30d', '$6,120', 'banknotes', 'text-neutral-900', '+24%'],
                ['Avg. order value', '$71.40', 'shopping-cart', 'text-neutral-900', '+$6'],
            ],
            'funnel' => [
                ['Page views', 12480, 100],
                ['Checkout started', 1090, 9],
                ['Purchased', 399, 3],
                ['Upsell accepted', 88, 1],
            ],
            'sources' => [
                ['Facebook Ads', 46], ['Direct / link', 24], ['Google', 16], ['YouTube', 9], ['Other', 5],
            ],
        ]);
    }

    public function earnings(Request $request): View
    {
        return view('frontend.seller.earnings', [
            'balances' => ['available' => '$1,842.50', 'pending' => '$430.00', 'withdrawn' => '$14,120.00', 'revenue' => '$16,392.50'],
            'payouts' => [
                ['amount' => '$1,200.00', 'method' => 'Bank transfer', 'status' => 'Paid', 'color' => 'success', 'date' => 'Jul 15, 2026'],
                ['amount' => '$900.00', 'method' => 'USDT wallet', 'status' => 'Paid', 'color' => 'success', 'date' => 'Jun 30, 2026'],
                ['amount' => '$430.00', 'method' => 'Bank transfer', 'status' => 'Processing', 'color' => 'warning', 'date' => 'Jul 25, 2026'],
            ],
        ]);
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
        } catch (SellException $e) {
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

        return view('frontend.seller.sales-pages', [
            'page' => $page,
            'slug' => $page->slug,
            'product' => $page->product?->name ?? __('Product'),
            'published' => $page->status === SalesPageStatus::Published,
            'seed' => $this->builderSeed($page),
            'themes' => ['#2563eb' => 'Blue', '#7c3aed' => 'Violet', '#059669' => 'Emerald', '#e11d48' => 'Rose', '#ea580c' => 'Orange', '#0f172a' => 'Slate'],
        ]);
    }

    /** Persist the builder (name, sections, theme) without changing status. */
    public function saveSalesPage(Request $request, SellerService $sellers, UpdateSalesPage $action, string $slug): RedirectResponse
    {
        $page = $this->ownedPage($sellers, $request, $slug);
        if (! $page instanceof SalesPage) {
            return $page;
        }

        $action->execute($page, $this->salesPageDataFromRequest($request, $page));

        return redirect()->route('sell.sales-page.edit', ['slug' => $page->slug])
            ->with('success', __('Changes saved.'));
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

        $goLive = $page->status !== SalesPageStatus::Published;

        try {
            if ($goLive && $page->product && $page->product->status !== ProductStatus::Published) {
                $setProductStatus->execute($page->product, ProductStatus::Published);
            }
            $setStatus->execute($page->fresh(), $goLive ? SalesPageStatus::Published : SalesPageStatus::Draft);
        } catch (SellException $e) {
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
            'builder' => ['nullable', 'json'],
        ]);

        $doc = json_decode($validated['builder'] ?? '{}', true) ?: [];

        return SalesPageData::fromArray([
            'product_id' => $page->product_id,
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
     * @return array<string, mixed>
     */
    private function builderSeed(SalesPage $page): array
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
                    $seed['content'][$s['type']] = $s['content'];
                }
            }
            if ($seed['content'] === []) {
                unset($seed['content']); // keep the builder's rich default content
            }
        }

        return $seed;
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
        } catch (SellException $e) {
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

        return view('frontend.seller.product-create', [
            'types' => self::PRODUCT_TYPES,
            'assets' => $this->pricingAssets(),
            'product' => $seller->products()->with('priceAsset')->findOrFail($id),
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
        } catch (SellException $e) {
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
            'cod' => ['nullable', 'boolean'],
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
        if (\Brick\Math\BigInteger::of($base)->isGreaterThan(PHP_INT_MAX)) {
            throw ValidationException::withMessages(['price' => __('That price is too large.')]);
        }

        // Physical delivery details ride in attributes until per-variant stock lands.
        $attributes = [];
        if ($validated['type'] === 'physical') {
            $attributes = array_filter([
                'weight' => $validated['weight'] ?? null,
                'sku' => $validated['sku'] ?? null,
                'shipping_fee' => $validated['shipping_fee'] ?? null,
                'stock' => $validated['stock'] ?? null,
                'cod' => $request->boolean('cod') ?: null,
            ], fn ($v) => $v !== null);
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
        } catch (SellException $e) {
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
     * @return \Illuminate\Support\Collection<int, array{id:int,symbol:string,name:string}>
     */
    private function pricingAssets(): \Illuminate\Support\Collection
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
