<?php

declare(strict_types=1);

namespace App\Http\Controllers\Funnel;

use App\Domain\Ledger\LedgerService;
use App\Http\Controllers\Controller;
use App\Sell\Actions\Order\PlaceOrder;
use App\Sell\DTOs\CheckoutData;
use App\Sell\Enums\SalesPageStatus;
use App\Sell\Exceptions\SellException;
use App\Sell\Models\Order;
use App\Sell\Models\Product;
use App\Sell\Models\ProductVariant;
use App\Sell\Models\SalesPage;
use App\Sell\Services\AnalyticsService;
use App\Sell\Services\CouponService;
use App\Sell\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('funnel.sales', $this->pageViewModel($page));
    }

    /** Buy → shipping step for physical goods, else straight to the payment page. */
    public function checkout(Request $request, string $slug): RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        $page = $this->publishedPage($slug);
        $product = $page->product;

        if ($product->has_variants) {
            // Keep any variation picked on the sales page. Physical goods can also
            // pick/change it on the shipping step; digital must choose here.
            $variant = $this->resolveVariant($product, (array) $request->input('options', []));
            if ($variant) {
                $request->session()->put($this->variantKey($page), $variant->getKey());
            } elseif (! $product->requires_shipping) {
                return redirect()->route('funnel.sales', ['slug' => $slug])
                    ->withErrors(['buy' => __('Please choose an option before continuing.')]);
            }
        } else {
            $request->session()->forget($this->variantKey($page));
        }

        return $product->requires_shipping
            ? redirect()->route('funnel.shipping', ['slug' => $slug])
            : redirect()->route('funnel.pay', ['slug' => $slug]);
    }

    /** Session key holding the chosen variant for a page's in-progress checkout. */
    private function variantKey(SalesPage $page): string
    {
        return "sell:variant:{$page->getKey()}";
    }

    /**
     * Variant option catalog for a product (Size => [S, M, L], …) — powers the
     * buyer's variation selectors on both the sales and checkout pages.
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

    /** The variant chosen earlier in this checkout (or null for simple products). */
    private function selectedVariant(SalesPage $page, Request $request): ?ProductVariant
    {
        if (! $page->product->has_variants) {
            return null;
        }

        $id = $request->session()->get($this->variantKey($page));

        return $id
            ? ProductVariant::where('product_id', $page->product_id)->where('is_active', true)->find($id)
            : null;
    }

    /** Shipping-address step (physical products only). Pre-fills any saved address. */
    public function shipping(Request $request, string $slug): View|RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        $page = $this->publishedPage($slug);
        if (! $page->product->requires_shipping) {
            return redirect()->route('funnel.pay', ['slug' => $slug]);
        }

        // Variation is chosen here for physical goods — pre-select the current one.
        $variant = $this->selectedVariant($page, $request);

        return view('funnel.shipping', [
            'slug' => $slug,
            'page' => $page,
            'product' => $page->product,
            'seller' => $page->seller,
            'address' => $request->session()->get($this->shipKey($page), []),
            'countries' => $this->countries(),
            'variantOptions' => $this->variantOptionCatalog($page->product),
            'selectedOptions' => $variant?->options ?? [],
            'summary' => $this->orderSummary($page, $variant),
        ]);
    }

    /**
     * Formatted order summary for a page (product price, shipping, total) — shown
     * beside the shipping form and on the payment page. Priced for the chosen variant.
     *
     * @return array{product:string, variation:?string, subtotal:string, shipping:string, shippingFree:bool, total:string}
     */
    private function orderSummary(SalesPage $page, ?ProductVariant $variant = null): array
    {
        $product = $page->product;
        $asset = $product->priceAsset;

        $subtotal = (int) $this->pricing->line($product, $variant, 1, $page->seller)['line_total'];
        $shipping = $this->pricing->shippingFee($product);

        return [
            'product' => $product->name,
            'variation' => $variant ? implode(' · ', array_values($variant->options ?? [])) : null,
            'subtotal' => $asset->money((string) $subtotal)->format(2),
            'shipping' => $shipping > 0 ? $asset->money((string) $shipping)->format(2) : __('Free'),
            'shippingFree' => $shipping === 0,
            'total' => $asset->money((string) ($subtotal + $shipping))->format(2),
        ];
    }

    /** Persist the address to the session, then continue to payment. */
    public function shippingSave(Request $request, string $slug): RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        $page = $this->publishedPage($slug);
        $product = $page->product;
        if (! $product->requires_shipping) {
            return redirect()->route('funnel.pay', ['slug' => $slug]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'line1' => ['required', 'string', 'max:190'],
            'line2' => ['nullable', 'string', 'max:190'],
            'city' => ['required', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:24'],
            'country' => ['required', 'string', 'size:2'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Capture the chosen variation here (for variant products).
        if ($product->has_variants) {
            $variant = $this->resolveVariant($product, (array) $request->input('options', []));
            if (! $variant) {
                return back()->withInput()->withErrors(['options' => __('Please choose a variation.')]);
            }
            $request->session()->put($this->variantKey($page), $variant->getKey());
        }

        $request->session()->put($this->shipKey($page), $validated);

        return redirect()->route('funnel.pay', ['slug' => $slug]);
    }

    /** PoisaPay-hosted payment page — the buyer confirms with their wallet balance. */
    public function pay(Request $request, string $slug): View|RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        $page = $this->publishedPage($slug);
        $product = $page->product;
        $seller = $page->seller;
        $asset = $product->priceAsset;

        // Variant products need a variation chosen — physical picks it on the
        // shipping step, digital on the sales page.
        $variant = $this->selectedVariant($page, $request);
        if ($product->has_variants && ! $variant) {
            return $product->requires_shipping
                ? redirect()->route('funnel.shipping', ['slug' => $slug])
                : redirect()->route('funnel.sales', ['slug' => $slug])->withErrors(['buy' => __('Please choose an option before continuing.')]);
        }

        // Physical goods must have a delivery address before payment.
        $shipping = $request->session()->get($this->shipKey($page));
        if ($product->requires_shipping && empty($shipping)) {
            return redirect()->route('funnel.shipping', ['slug' => $slug]);
        }

        $this->analytics->track($page, AnalyticsService::CHECKOUT_START, $request, dedupeOncePerSession: true);

        $break = $this->pricing->line($product, $variant, 1, $seller);
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
        if ($bp && (int) $bp->price_asset_id === (int) $product->price_asset_id
            && $bp->getKey() !== $product->getKey() && $bp->status->isBuyable()) {
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
        $sessionKey = "sell:idem:{$page->getKey()}";
        $idempotencyKey = $request->session()->get($sessionKey);
        if (! $idempotencyKey) {
            $idempotencyKey = (string) Str::uuid();
            $request->session()->put($sessionKey, $idempotencyKey);
        }

        return view('funnel.pay', [
            'slug' => $slug,
            'page' => $page,
            'product' => $product,
            'seller' => $seller,
            'asset' => $asset,
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
            'shipping' => $shipping,
            'variation' => $variant ? implode(' · ', array_values($variant->options ?? [])) : null,
            // Raw minor-unit amounts + asset meta for live (bump) total math in the browser.
            'bump' => $bump,
            'totalRaw' => $totalAmount,
            'balanceRaw' => (int) $balance->baseString(),
            'assetDecimals' => (int) $asset->decimals,
            'assetSymbol' => $asset->symbol,
        ]);
    }

    /** Place the real order: debit buyer, credit seller + platform commission via the Ledger. */
    public function payConfirm(Request $request, string $slug, PlaceOrder $placeOrder): RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        $page = $this->publishedPage($slug);
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:190'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'bump' => ['nullable', 'boolean'],
        ]);

        // Variant products need a variation chosen — physical picks it on the
        // shipping step, digital on the sales page.
        $variant = $this->selectedVariant($page, $request);
        if ($page->product->has_variants && ! $variant) {
            return $page->product->requires_shipping
                ? redirect()->route('funnel.shipping', ['slug' => $slug])
                : redirect()->route('funnel.sales', ['slug' => $slug])->withErrors(['buy' => __('Please choose an option before continuing.')]);
        }

        // Physical goods can't be paid for without the address captured earlier.
        $shipping = $request->session()->get($this->shipKey($page));
        if ($page->product->requires_shipping && empty($shipping)) {
            return redirect()->route('funnel.shipping', ['slug' => $slug]);
        }

        try {
            $order = $placeOrder->execute($request->user(), CheckoutData::fromArray([
                'product_id' => $page->product_id,
                'variant_id' => $variant?->getKey(),
                'quantity' => 1,
                'sales_page_id' => $page->getKey(),
                'idempotency_key' => $validated['idempotency_key'],
                'coupon_code' => $validated['coupon_code'] ?? null,
                'shipping_address' => $shipping,
                'bump' => (bool) ($validated['bump'] ?? false),
            ]));
        } catch (SellException $e) {
            return back()->withErrors(['pay' => $e->getMessage()]);
        }

        $request->session()->forget(["sell:idem:{$page->getKey()}", $this->shipKey($page), $this->variantKey($page)]);
        $this->analytics->track($page, AnalyticsService::PURCHASE, $request, orderId: $order->getKey());

        return redirect()->route('funnel.thankyou', ['slug' => $slug])->with('order_id', $order->getKey());
    }

    public function thankYou(Request $request, string $slug): View
    {
        $page = $this->publishedPage($slug);

        $order = null;
        if (($orderId = $request->session()->get('order_id')) && $request->user()) {
            $order = Order::with('items')
                ->where('buyer_user_id', $request->user()->getKey())
                ->find($orderId);
            $request->session()->keep('order_id'); // survive a refresh / the upsell POST
        }

        return view('funnel.thank-you', [
            'slug' => $slug,
            'page' => $page,
            'product' => $page->product,
            'seller' => $page->seller,
            'order' => $order,
            'total' => $order ? $page->product->priceAsset->money((string) $order->total_amount)->format(2) : null,
            'upsell' => $order ? $this->upsellOffer($page, $order) : null,
        ]);
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

        if (! $up || ! $up->status->isBuyable()
            || (int) $up->price_asset_id !== (int) $page->product->price_asset_id
            || $up->getKey() === $page->product_id) {
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
            return redirect()->guest(route('login'));
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
                'kind' => \App\Sell\Enums\OrderItemKind::Upsell,
                'override_amount' => $page->upsellAmount(),
                'idempotency_key' => 'sell:upsell:'.$order->getKey(), // one upsell per order
            ]));
        } catch (SellException $e) {
            return redirect()->route('funnel.thankyou', ['slug' => $slug])->withErrors(['upsell' => $e->getMessage()]);
        }

        $this->analytics->track($page, AnalyticsService::PURCHASE, $request, orderId: $upsellOrder->getKey());

        return redirect()->route('funnel.thankyou', ['slug' => $slug])
            ->with('order_id', $order->getKey())
            ->with('success', __('Added to your order — enjoy!'));
    }

    /** Session key holding the in-progress shipping address for a page's checkout. */
    private function shipKey(SalesPage $page): string
    {
        return "sell:ship:{$page->getKey()}";
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
    private function pageViewModel(SalesPage $page): array
    {
        $product = $page->product;
        $seller = $page->seller;
        $asset = $product->priceAsset;

        $theme = $page->theme ?? [];
        $sections = collect($page->sections ?? [])
            ->filter(fn ($s) => (bool) ($s['enabled'] ?? true))
            ->map(fn ($s) => ['type' => $s['type'], 'content' => $s['content'] ?? null])
            ->values()
            ->all();

        $sellerName = $seller->displayName();

        return [
            'slug' => $page->slug,
            'name' => $page->name,
            // Physical variant products pick the variation on the shipping step, so
            // only surface the picker on the sales page for digital variant goods.
            'variantOptions' => $product->requires_shipping ? [] : $this->variantOptionCatalog($product),
            'theme' => [
                'accent' => $theme['accent'] ?? '#2563eb',
                'btn' => $theme['btn'] ?? 'rounded',
                'font' => $theme['font'] ?? 'Inter',
            ],
            'sections' => $sections,
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

        // FAQPage from the first enabled FAQ section with q/a pairs.
        $faq = collect($page->sections ?? [])->firstWhere('type', 'faq')['content'] ?? [];
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
