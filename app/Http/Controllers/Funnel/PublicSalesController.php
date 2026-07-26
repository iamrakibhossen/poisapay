<?php

declare(strict_types=1);

namespace App\Http\Controllers\Funnel;

use App\Domain\Auth\RegisterUserAction;
use App\Domain\Ledger\LedgerService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shop\Actions\Order\PlaceOrder;
use App\Shop\Builder\RenderContext;
use App\Shop\Builder\Renderer;
use App\Shop\DTOs\CheckoutData;
use App\Shop\Enums\OrderItemKind;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\Order;
use App\Shop\Models\Product;
use App\Shop\Models\ProductVariant;
use App\Shop\Models\SalesPage;
use App\Shop\Services\AnalyticsService;
use App\Shop\Services\CouponService;
use App\Shop\Services\Domain\DomainResolver;
use App\Shop\Services\PricingService;
use App\Shop\Support\PlatformHost;
use App\Shop\Tracking\TrackingEvent;
use App\Shop\Tracking\TrackingEventType;
use App\Shop\Tracking\TrackingManager;
use App\Utilities\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public product sales page — /p/{slug}. Standalone, no app nav, conversion-first.
 *
 * Renders the seller's *published* page from its persisted builder config, and
 * runs the real money path: the buyer pays with their PoisaPay wallet and the
 * Ledger moves the money (see {@see PlaceOrder}). No guest checkout — a signed-in
 * PoisaPay account (and wallet balance) is required to pay.
 */
class PublicSalesController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PricingService $pricing,
        private readonly CouponService $coupons,
        private readonly AnalyticsService $analytics,
    ) {}

    public function show(Request $request, string $slug): View
    {
        $page = $this->publishedPage($slug);
        $this->analytics->track($page, AnalyticsService::PAGE_VIEW, $request, dedupeOncePerSession: true);

        return view('funnel.sales', $this->pageViewModel($page) + $this->tracking($page, [
            TrackingEvent::of(TrackingEventType::PageView),
            TrackingEvent::of(TrackingEventType::ViewContent, $this->trackingProduct($page->product)),
        ]));
    }

    /** Buy → the single-page checkout. Variation + shipping are captured there. */
    public function checkout(Request $request, string $slug): RedirectResponse
    {
        $this->publishedPage($slug); // 404 guard
        $pay = route('funnel.pay', ['slug' => $slug]);

        // Cold traffic: don't bounce to the app login — take a quick, on-brand
        // account step, then land straight on checkout.
        return $request->user()
            ? redirect()->to($pay)
            : $this->guestToAccount($request, $slug, $pay);
    }

    /** Send a signed-out visitor to the on-funnel express-account step (not the app login). */
    private function guestToAccount(Request $request, string $slug, ?string $intended = null): RedirectResponse
    {
        $request->session()->put('url.intended', $intended
            ?? ($request->isMethod('get') ? url()->current() : route('funnel.pay', ['slug' => $slug])));

        return redirect()->route('funnel.account', ['slug' => $slug]);
    }

    /** Express account step — create an account or sign in, right inside the funnel. */
    public function account(Request $request, string $slug): View|RedirectResponse
    {
        $page = $this->publishedPage($slug);

        if ($request->user()) {
            return redirect()->intended(route('funnel.pay', ['slug' => $slug]));
        }

        return view('funnel.account', [
            'slug' => $slug,
            'page' => $page,
            'seller' => $page->seller,
            'product' => $page->product,
        ]);
    }

    /** Create the account (or sign in) and resume checkout. */
    public function accountSubmit(Request $request, string $slug, RegisterUserAction $register): RedirectResponse
    {
        $this->publishedPage($slug); // 404 guard

        $mode = $request->input('mode') === 'existing' ? 'existing' : 'new';
        $key = 'funnel-account:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => __('Too many attempts. Please try again shortly.')])->withInput();
        }

        if ($mode === 'existing') {
            $validated = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
            $user = User::where('email', $validated['email'])->first();
            if (! $user || ! Auth::getProvider()->validateCredentials($user, ['password' => $validated['password']])) {
                RateLimiter::hit($key);

                return back()->withErrors(['password' => __('Those credentials don’t match our records.')])->withInput();
            }
            Auth::login($user, remember: true);
        } else {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:180', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
            ]);
            $user = $register->execute([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            Auth::login($user, remember: true);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('funnel.pay', ['slug' => $slug]));
    }

    /**
     * Variant option catalog for a product (Size => [S, M, L], …) — powers the
     * buyer's variation selectors on the checkout page.
     *
     * @return array<string, list<string>>
     */
    private function variantOptionCatalog(Product $product): array
    {
        if (! $product->has_variants) {
            return [];
        }

        $catalog = [];
        foreach ($product->variants()->where('is_active', true)->orderBy('position')->get() as $v) {
            foreach ($v->options ?? [] as $name => $val) {
                $catalog[$name] ??= [];
                if (! in_array($val, $catalog[$name], true)) {
                    $catalog[$name][] = $val;
                }
            }
        }

        return $catalog;
    }

    /** Match submitted options ({Size:M, Color:Black}) to one active variant. */
    private function resolveVariant(Product $product, array $options): ?ProductVariant
    {
        $options = array_filter($options, fn ($v) => $v !== null && $v !== '');

        foreach ($product->variants()->where('is_active', true)->get() as $variant) {
            $vo = $variant->options ?? [];
            if (count($vo) === count($options) && empty(array_diff_assoc($vo, $options))) {
                return $variant;
            }
        }

        return null;
    }

    /** Funnel pay page (/p/{slug}/checkout) — delegates to the shared renderer. */
    public function pay(Request $request, string $slug): View|RedirectResponse
    {
        if (! $request->user()) {
            return $this->guestToAccount($request, $slug);
        }

        return $this->payPage($request, $this->publishedPage($slug), [
            'confirm' => route('funnel.pay.confirm', ['slug' => $slug]),
            'coupon' => route('funnel.pay', ['slug' => $slug]),
        ]);
    }

    /**
     * PoisaPay-hosted payment page — the buyer confirms with their wallet balance.
     * The form endpoints are injected so the SAME page renders identically for the
     * funnel (/p/{slug}/checkout) and the central checkout (/checkout/{product}).
     *
     * @param  array{confirm: string, coupon: string}  $urls
     */
    private function payPage(Request $request, SalesPage $page, array $urls): View
    {
        $product = $page->product;
        $seller = $page->seller;
        $asset = $product->priceAsset;

        $this->analytics->track($page, AnalyticsService::CHECKOUT_START, $request, dedupeOncePerSession: true);

        // Variation is chosen on this page (for variant products). Price from the
        // first active variant seeds the display; the browser updates it live.
        $variants = $product->has_variants
            ? $product->variants()->where('is_active', true)->orderBy('position')->get()
            : collect();
        $baseVariant = $variants->first();

        $break = $this->pricing->line($product, $baseVariant, 1, $seller);
        $subtotal = (int) $break['line_total'];

        // Optional discount code (?coupon=CODE) — previewed, never charged here.
        $couponCode = $request->query('coupon');
        $coupon = $this->coupons->preview($seller, $product, $couponCode, $subtotal, $request->user());
        $discount = $coupon?->discountFor($subtotal) ?? 0;

        // Shipping is charged for physical goods (matches PlaceOrder).
        $shipFee = $this->pricing->shippingFee($product);
        $totalAmount = $subtotal - $discount + $shipFee;

        $total = $asset->money((string) $totalAmount);
        $balance = $this->ledger->availableBalance($request->user(), (int) $product->price_asset_id);

        // Order bump — an optional add-on the buyer can accept at checkout.
        $bump = null;
        $bp = $page->bumpProduct;
        if (
            $bp && (int) $bp->price_asset_id === (int) $product->price_asset_id
            && $bp->getKey() !== $product->getKey() && $bp->status->isBuyable()
        ) {
            $bumpAmt = (int) $page->bumpAmount();
            $listAmt = (int) $bp->price_amount;
            $bump = [
                'name' => $bp->name,
                'headline' => $page->bump_headline ?: __('Add :name', ['name' => $bp->name]),
                'desc' => $page->bump_description,
                'amount' => $asset->money((string) $bumpAmt)->format(2),
                'compare' => ($page->bump_price_amount !== null && $listAmt > $bumpAmt) ? $asset->money((string) $listAmt)->format(2) : null,
                'amountRaw' => $bumpAmt,
            ];
        }

        // One idempotency key per buyer+page so a refresh/double-submit never double-charges.
        $sessionKey = "shop:idem:{$page->getKey()}";
        $idempotencyKey = $request->session()->get($sessionKey);
        if (! $idempotencyKey) {
            $idempotencyKey = (string) Str::uuid();
            $request->session()->put($sessionKey, $idempotencyKey);
        }

        // Conversion signals: real product image, social proof (published reviews),
        // delivery reassurance, and the true money-back window.
        $rev = $product->reviews()->where('status', 'published')
            ->selectRaw('avg(rating) as avg, count(*) as c')->first();
        $rating = ($rev && (int) $rev->c > 0)
            ? ['avg' => round((float) $rev->avg, 1), 'count' => (int) $rev->c]
            : null;

        $delivery = $product->requires_shipping
            ? ['icon' => 'truck', 'text' => __('Ships to your address after payment')]
            : ($product->type->isDigitalDelivery()
                ? ['icon' => 'bolt', 'text' => __('Instant access — delivered to your account right after payment')]
                : ['icon' => 'check-badge', 'text' => __('Delivered right after payment')]);

        return view('funnel.pay', [
            'slug' => $page->slug,
            'page' => $page,
            'product' => $product,
            'seller' => $seller,
            'asset' => $asset,
            'backUrl' => $this->storefrontBackUrl($request, $page),
            'confirmUrl' => $urls['confirm'],
            'couponUrl' => $urls['coupon'],
            'productImage' => Asset::url($product->image),
            'rating' => $rating,
            'delivery' => $delivery,
            'refundDays' => (int) getSetting('shop_refund_window_days', 14),
            'subtotal' => $asset->money((string) $subtotal)->format(2),
            'discount' => $discount > 0 ? $asset->money((string) $discount)->format(2) : null,
            'shipFee' => $shipFee > 0 ? $asset->money((string) $shipFee)->format(2) : null,
            'total' => $total->format(2),
            'couponCode' => $coupon?->code,
            'couponInvalid' => $couponCode !== null && trim((string) $couponCode) !== '' && $coupon === null,
            'balance' => $balance->format(2),
            'sufficient' => ! $balance->isLessThan($total),
            'ownProduct' => $seller->user_id === $request->user()->getKey(),
            'idempotencyKey' => $idempotencyKey,
            // Inline variation + shipping (single-page checkout).
            'requiresShipping' => $product->requires_shipping,
            'countries' => $product->requires_shipping ? $this->countries() : [],
            'variantCatalog' => $this->variantOptionCatalog($product),
            'variantPrices' => $variants->map(fn ($v) => [
                'options' => $v->options ?? [],
                'price' => (int) ($v->price_amount ?? $product->price_amount),
            ])->values()->all(),
            // Raw minor-unit amounts + asset meta for live (variant/bump/quantity) total math.
            'bump' => $bump,
            'productRaw' => $subtotal,
            'unitRaw' => (int) $break['unit'],
            'maxQty' => 99,
            'couponType' => $coupon?->type->value,
            'couponValue' => $coupon ? (int) $coupon->value : 0,
            'discountRaw' => $discount,
            'shipFeeRaw' => $shipFee,
            'totalRaw' => $totalAmount,
            'balanceRaw' => (int) $balance->baseString(),
            'assetDecimals' => (int) $asset->decimals,
            'assetSymbol' => $asset->symbol,
        ] + $this->tracking($page, [
            TrackingEvent::of(TrackingEventType::PageView),
            TrackingEvent::of(TrackingEventType::InitiateCheckout, $this->trackingProduct($product, $totalAmount)),
        ]));
    }

    /** Funnel order placement (POST /p/{slug}/checkout) — delegates to the shared path. */
    public function payConfirm(Request $request, string $slug, PlaceOrder $placeOrder): RedirectResponse
    {
        if (! $request->user()) {
            return $this->guestToAccount($request, $slug);
        }

        return $this->placeOrderFor($request, $this->publishedPage($slug), $placeOrder, route('funnel.thankyou', ['slug' => $slug]));
    }

    /**
     * Place the real order: debit buyer, credit seller + platform commission via the
     * Ledger. Shared by the funnel and the central checkout; $thankYouUrl keeps the
     * buyer on whichever surface they came through.
     */
    private function placeOrderFor(Request $request, SalesPage $page, PlaceOrder $placeOrder, string $thankYouUrl): RedirectResponse
    {
        $product = $page->product;

        $rules = [
            'idempotency_key' => ['required', 'string', 'max:190'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'bump' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
        // Shipping fields are captured inline here for physical goods.
        if ($product->requires_shipping) {
            $rules += [
                'name' => ['required', 'string', 'max:120'],
                'phone' => ['required', 'string', 'max:32'],
                'line1' => ['required', 'string', 'max:190'],
                'line2' => ['nullable', 'string', 'max:190'],
                'city' => ['required', 'string', 'max:120'],
                'postcode' => ['nullable', 'string', 'max:24'],
                'country' => ['required', 'string', 'size:2'],
                'notes' => ['nullable', 'string', 'max:500'],
            ];
        }
        $validated = $request->validate($rules);

        // Resolve the variation chosen on this page.
        $variant = $product->has_variants
            ? $this->resolveVariant($product, (array) $request->input('options', []))
            : null;
        if ($product->has_variants && ! $variant) {
            return back()->withInput()->withErrors(['options' => __('Please choose a variation.')]);
        }

        $shipping = $product->requires_shipping ? Arr::only($validated, [
            'name',
            'phone',
            'line1',
            'line2',
            'city',
            'postcode',
            'country',
            'notes',
        ]) : null;

        try {
            $order = $placeOrder->execute($request->user(), CheckoutData::fromArray([
                'product_id' => $page->product_id,
                'variant_id' => $variant?->getKey(),
                'quantity' => (int) ($validated['quantity'] ?? 1),
                'sales_page_id' => $page->getKey(),
                'idempotency_key' => $validated['idempotency_key'],
                'coupon_code' => $validated['coupon_code'] ?? null,
                'shipping_address' => $shipping,
                'bump' => (bool) ($validated['bump'] ?? false),
            ]));
        } catch (ShopException $e) {
            return back()->withInput()->withErrors(['pay' => $e->getMessage()]);
        }

        $request->session()->forget("shop:idem:{$page->getKey()}");
        $this->analytics->track($page, AnalyticsService::PURCHASE, $request, orderId: $order->getKey());

        return redirect()->to($thankYouUrl)->with('order_id', $order->getKey());
    }

    /**
     * Central checkout entry (platform host). A storefront's Buy form posts here —
     * possibly cross-origin from a custom domain — carrying only the page id (a
     * selection, never a price). We hand off to the SAME funnel on THIS platform
     * host, so payment always happens centrally with one PoisaPay session and one
     * trusted domain. CSRF-exempt by design (cross-origin handoff); the actual
     * order placement downstream is same-origin + CSRF-protected + price is
     * re-resolved server-side, so a tampered post can't change what's charged.
     */
    public function enter(Request $request): RedirectResponse
    {
        if (! feature('shop_enabled', false)) {
            abort(404);
        }

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:200'],
            'coupon' => ['nullable', 'string', 'max:40'],
            'return_url' => ['nullable', 'string', 'max:500'],
        ]);

        // publishedPage() 404s an unknown/unpublished slug (host-header/takeover safe).
        $page = $this->publishedPage($data['slug']);

        // Remember the originating storefront (custom domain) so the pay page's
        // "Back to the page" link returns there, not to the platform funnel URL.
        if (! empty($data['return_url']) && $this->isSafeStorefrontUrl($data['return_url'])) {
            $request->session()->put('shop:return_url', $data['return_url']);
        }

        return $this->handoffTo($request, $page, empty($data['coupon']) ? null : $data['coupon']);
    }

    /** The central checkout PAGE — /checkout/{product}. Renders the shared pay page. */
    public function directCheckout(Request $request, string $product): View|RedirectResponse
    {
        if (! feature('shop_enabled', false)) {
            abort(404);
        }

        $page = $this->productPage($product);

        if (! $request->user()) {
            return $this->guestToAccount($request, $page->slug, route('checkout.show', ['product' => $product]));
        }

        return $this->payPage($request, $page, [
            'confirm' => route('checkout.pay', ['product' => $product]),
            'coupon' => route('checkout.show', ['product' => $product]),
        ]);
    }

    /** Place the order from the central checkout (POST /checkout/{product}). */
    public function confirmDirect(Request $request, string $product, PlaceOrder $placeOrder): RedirectResponse
    {
        if (! feature('shop_enabled', false)) {
            abort(404);
        }

        $page = $this->productPage($product);

        if (! $request->user()) {
            return $this->guestToAccount($request, $page->slug, route('checkout.show', ['product' => $product]));
        }

        return $this->placeOrderFor($request, $page, $placeOrder, route('checkout.thankyou', ['product' => $product]));
    }

    /** Central thank-you — /checkout/{product}/thank-you. */
    public function centralThankYou(Request $request, string $product): View
    {
        return $this->thankYouView($request, $this->productPage($product));
    }

    /** Resolve a product to its primary published sales page (404 if none). */
    private function productPage(string $product): SalesPage
    {
        $found = Product::find($product);
        abort_if($found === null, 404);

        $page = SalesPage::where('product_id', $found->getKey())
            ->where('status', SalesPageStatus::Published)->latest('published_at')->first();
        abort_if($page === null, 404);

        return $page;
    }

    /** Route the buyer to the central checkout page (guest → account step first). */
    private function handoffTo(Request $request, SalesPage $page, ?string $coupon): RedirectResponse
    {
        $checkout = route('checkout.show', ['product' => $page->product_id])
            .($coupon !== null && trim($coupon) !== '' ? '?coupon='.urlencode($coupon) : '');

        if ($request->user()) {
            return redirect()->to($checkout);
        }

        // Guest: the on-funnel express-account step, then resume the central checkout.
        $request->session()->put('url.intended', $checkout);

        return redirect()->route('funnel.account', ['slug' => $page->slug]);
    }

    /** "Back to the page" target — the originating storefront, else the funnel page. */
    private function storefrontBackUrl(Request $request, SalesPage $page): string
    {
        $ret = (string) $request->session()->get('shop:return_url', '');

        return $ret !== '' && $this->isSafeStorefrontUrl($ret)
            ? $ret
            : route('funnel.sales', ['slug' => $page->slug]);
    }

    /** Anti open-redirect: only a platform host or serviceable custom domain is trusted. */
    private function isSafeStorefrontUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || ! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return PlatformHost::is($host)
            || app(DomainResolver::class)->resolve($host) !== null;
    }

    public function thankYou(Request $request, string $slug): View
    {
        return $this->thankYouView($request, $this->publishedPage($slug));
    }

    private function thankYouView(Request $request, SalesPage $page): View
    {
        $order = null;
        if (($orderId = $request->session()->get('order_id')) && $request->user()) {
            $order = Order::with('items')
                ->where('buyer_user_id', $request->user()->getKey())
                ->find($orderId);
            $request->session()->keep('order_id'); // survive a refresh / the upsell POST
        }

        $events = [TrackingEvent::of(TrackingEventType::PageView)];
        if ($order) {
            $events[] = TrackingEvent::of(TrackingEventType::Purchase,
                $this->trackingProduct($page->product, (int) $order->total_amount) + ['order_id' => (string) $order->getKey()],
            );
        }

        return view('funnel.thank-you', [
            'slug' => $page->slug,
            'page' => $page,
            'product' => $page->product,
            'seller' => $page->seller,
            'order' => $order,
            'total' => $order ? $page->product->priceAsset->money((string) $order->total_amount)->format(2) : null,
            'upsell' => $order ? $this->upsellOffer($page, $order) : null,
        ] + $this->tracking($page, $events));
    }

    /**
     * The 1-click upsell offer for a just-placed order, or null if there's no
     * upsell, it's the same product, currency mismatches, or it was already taken.
     *
     * @return array<string, mixed>|null
     */
    private function upsellOffer(SalesPage $page, Order $order): ?array
    {
        $up = $page->upsellProduct;
        $asset = $page->product->priceAsset;

        if (
            ! $up || ! $up->status->isBuyable()
            || (int) $up->price_asset_id !== (int) $page->product->price_asset_id
            || $up->getKey() === $page->product_id
        ) {
            return null;
        }
        // Already upsold from this order? Don't offer again.
        if (Order::where('parent_order_id', $order->getKey())->exists()) {
            return null;
        }

        $amount = (int) $page->upsellAmount();
        $list = (int) $up->price_amount;

        return [
            'headline' => $page->upsell_headline ?: __('One-time offer: add :name', ['name' => $up->name]),
            'description' => $page->upsell_description,
            'name' => $up->name,
            'price' => $asset->money((string) $amount)->format(2),
            'compare' => ($page->upsell_price_amount !== null && $list > $amount) ? $asset->money((string) $list)->format(2) : null,
        ];
    }

    /** Accept the 1-click upsell — a new order at the page's upsell price, charged instantly. */
    public function upsellAccept(Request $request, string $slug, PlaceOrder $placeOrder): RedirectResponse
    {
        if (! $request->user()) {
            return $this->guestToAccount($request, $slug);
        }

        $page = $this->publishedPage($slug);
        $orderId = $request->input('order_id') ?: $request->session()->get('order_id');
        $order = $orderId
            ? Order::where('buyer_user_id', $request->user()->getKey())->find($orderId)
            : null;

        // Nothing to accept, or the offer no longer applies → back to thank-you.
        if (! $order || ! $this->upsellOffer($page, $order)) {
            return redirect()->route('funnel.thankyou', ['slug' => $slug])->with('order_id', $orderId);
        }

        try {
            $upsellOrder = $placeOrder->execute($request->user(), CheckoutData::fromArray([
                'product_id' => $page->upsell_product_id,
                'quantity' => 1,
                'sales_page_id' => $page->getKey(),
                'parent_order_id' => $order->getKey(),
                'kind' => OrderItemKind::Upsell,
                'override_amount' => $page->upsellAmount(),
                'idempotency_key' => 'shop:upsell:'.$order->getKey(), // one upsell per order
            ]));
        } catch (ShopException $e) {
            return redirect()->route('funnel.thankyou', ['slug' => $slug])->withErrors(['upsell' => $e->getMessage()]);
        }

        $this->analytics->track($page, AnalyticsService::PURCHASE, $request, orderId: $upsellOrder->getKey());

        return redirect()->route('funnel.thankyou', ['slug' => $slug])
            ->with('order_id', $order->getKey())
            ->with('success', __('Added to your order — enjoy!'));
    }

    /** @return array<string, string> ISO2 => name (short, common-first list). */
    private function countries(): array
    {
        return [
            'BD' => 'Bangladesh',
            'IN' => 'India',
            'PK' => 'Pakistan',
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'MY' => 'Malaysia',
            'SG' => 'Singapore',
            'ID' => 'Indonesia',
            'NG' => 'Nigeria',
            'DE' => 'Germany',
            'FR' => 'France',
            'NL' => 'Netherlands',
            'OT' => 'Other',
        ];
    }

    /** The published page for this slug, or a 404 (also 404 if its product isn't buyable). */
    private function publishedPage(string $slug): SalesPage
    {
        $page = SalesPage::with(['product.priceAsset', 'seller.user', 'bumpProduct', 'upsellProduct'])
            ->where('slug', $slug)
            ->where('status', SalesPageStatus::Published)
            ->first();

        abort_if($page === null || $page->product === null || ! $page->product->status->isBuyable(), 404);

        return $page;
    }

    /**
     * View-model for the public page: real product + seller, live price, and the
     * seller's builder sections (theme + enabled content), so what they built is
     * exactly what buyers see.
     *
     * @return array<string, mixed>
     */
    /**
     * Per-page pixel config + the load-time events to fire, merged into a view model
     * and forwarded to the sales layout (see {@see TrackingManager}).
     *
     * @param  list<TrackingEvent>  $events
     * @return array{tracking: array<string, mixed>, trackingEvents: list<TrackingEvent>}
     */
    private function tracking(SalesPage $page, array $events): array
    {
        return [
            'tracking' => $page->tracking,
            'trackingEvents' => $events,
        ];
    }

    /**
     * Canonical (provider-agnostic) product payload for a tracking event. Each
     * adapter reshapes these keys into its own required format.
     *
     * @return array<string, mixed>
     */
    private function trackingProduct(?Product $product, ?int $valueMinor = null, int $quantity = 1): array
    {
        if (! $product) {
            return [];
        }

        $asset = $product->priceAsset;

        return array_filter([
            'product_id' => (string) $product->getKey(),
            'product_name' => $product->name,
            'currency' => $asset->currency_code ?: $asset->symbol,
            'value' => $asset->money((string) ($valueMinor ?? $product->price_amount))->toDecimal(),
            'quantity' => $quantity,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /** @return array<string, mixed> */
    private function pageViewModel(SalesPage $page): array
    {
        $product = $page->product;
        $seller = $page->seller;
        $asset = $product->priceAsset;

        // Render the published block-tree document with the one shared renderer —
        // byte-identical to the editor's iframe preview.
        $document = $page->publishedDocument();
        $context = RenderContext::fromSalesPage($page, $document->globals());
        $rendered = app(Renderer::class)->render($document, $context);

        // When the seller builds their own header/footer block, the default sales
        // chrome header/footer steps aside so their block is the page's real one.
        $types = array_map(static fn ($n) => $n->type, $document->root()->flatten());

        $sellerName = $seller->displayName();

        return [
            'slug' => $page->slug,
            'name' => $page->name,
            'bodyHtml' => $rendered['html'],
            'headCss' => $rendered['css'],
            'hasHeader' => in_array('header', $types, true),
            'hasFooter' => in_array('footer', $types, true),
            'product' => [
                'name' => $product->name,
                'summary' => $product->summary,
                'description' => $product->description,
                'price' => $asset->money($product->price_amount)->format(2),
                'comparePrice' => $product->compare_price_amount
                    ? $asset->money($product->compare_price_amount)->format(2)
                    : null,
            ],
            'seller' => [
                'name' => $sellerName,
                'initials' => Str::of($sellerName)->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode(''),
                'logo' => $seller->logoUrl(),
            ],
            'meta' => $this->buildMeta($page, $sellerName),
            'schema' => $this->buildSchema($page, $sellerName),
        ];
    }

    /**
     * SEO/social meta for the page head — seller overrides (seo jsonb) first, then
     * sensible product-derived fallbacks. Canonical is the connected domain when
     * one exists, else the platform /p/{slug} URL.
     *
     * @return array<string, mixed>
     */
    private function buildMeta(SalesPage $page, string $sellerName): array
    {
        $seo = $page->seo ?? [];
        $product = $page->product;

        $canonical = $page->domain?->host
            ? 'https://'.$page->domain->host
            : route('funnel.sales', ['slug' => $page->slug]);

        $ogImage = $seo['og_image'] ?? $page->seller->logoUrl();
        if ($ogImage && ! str_starts_with($ogImage, 'http')) {
            $ogImage = url($ogImage);
        }

        $description = $seo['description'] ?? Str::limit(strip_tags((string) ($product->summary ?: $product->description)), 155);

        return [
            'title' => $seo['title'] ?? ($product->name.' · '.$sellerName),
            'description' => $description ?: null,
            'canonical' => $canonical,
            'robots' => ! empty($seo['noindex']) ? 'noindex,nofollow' : 'index,follow',
            'ogImage' => $ogImage ?: null,
        ];
    }

    /**
     * schema.org JSON-LD: Product (+ Offer, AggregateRating, Review) and, when the
     * page has an FAQ section, a FAQPage — both eligible for Google rich results.
     *
     * @return list<array<string, mixed>>
     */
    private function buildSchema(SalesPage $page, string $sellerName): array
    {
        $product = $page->product;
        $asset = $product->priceAsset;
        $canonical = route('funnel.sales', ['slug' => $page->slug]);
        $priceDecimal = number_format($product->price_amount / (10 ** $asset->decimals), $asset->decimals, '.', '');

        $reviews = $product->reviews()->where('status', 'published')->with('buyer')->latest()->get();

        $productSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => Str::limit(strip_tags((string) ($product->summary ?: $product->description)), 300) ?: $product->name,
            'brand' => ['@type' => 'Brand', 'name' => $sellerName],
            'offers' => [
                '@type' => 'Offer',
                'price' => $priceDecimal,
                'priceCurrency' => $asset->symbol,
                'availability' => 'https://schema.org/InStock',
                'url' => $canonical,
            ],
        ];

        if ($og = $page->seo['og_image'] ?? $page->seller->logoUrl()) {
            $productSchema['image'] = str_starts_with($og, 'http') ? $og : url($og);
        }

        if ($reviews->isNotEmpty()) {
            $productSchema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $reviews->avg('rating'), 1),
                'reviewCount' => $reviews->count(),
            ];
            $productSchema['review'] = $reviews->take(5)->map(fn ($r) => array_filter([
                '@type' => 'Review',
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int) $r->rating, 'bestRating' => 5],
                'author' => ['@type' => 'Person', 'name' => $r->buyer?->name ?? 'Verified buyer'],
                'reviewBody' => $r->body ?: null,
            ]))->values()->all();
        }

        $schemas = [$productSchema];

        // FAQPage from the first FAQ block with q/a pairs (walks the block tree).
        $faqNode = collect($page->publishedDocument()->root()->flatten())->firstWhere('type', 'faq');
        $faq = $faqNode?->prop('items', []) ?? [];
        $questions = collect($faq)
            ->filter(fn ($q) => is_array($q) && ! empty($q['q']) && ! empty($q['a']))
            ->map(fn ($q) => [
                '@type' => 'Question',
                'name' => $q['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q['a']],
            ])->values()->all();

        if ($questions !== []) {
            $schemas[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $questions];
        }

        return $schemas;
    }
}
