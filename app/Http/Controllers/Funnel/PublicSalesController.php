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
use App\Sell\Models\SalesPage;
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
    ) {}

    public function show(string $slug): View
    {
        return view('funnel.sales', $this->pageViewModel($this->publishedPage($slug)));
    }

    /** Buy → shipping step for physical goods, else straight to the payment page. */
    public function checkout(Request $request, string $slug): RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        $page = $this->publishedPage($slug);

        return $page->product->requires_shipping
            ? redirect()->route('funnel.shipping', ['slug' => $slug])
            : redirect()->route('funnel.pay', ['slug' => $slug]);
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

        return view('funnel.shipping', [
            'slug' => $slug,
            'page' => $page,
            'product' => $page->product,
            'seller' => $page->seller,
            'address' => $request->session()->get($this->shipKey($page), []),
            'countries' => $this->countries(),
        ]);
    }

    /** Persist the address to the session, then continue to payment. */
    public function shippingSave(Request $request, string $slug): RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        $page = $this->publishedPage($slug);
        if (! $page->product->requires_shipping) {
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

        // Physical goods must have a delivery address before payment.
        $shipping = $request->session()->get($this->shipKey($page));
        if ($product->requires_shipping && empty($shipping)) {
            return redirect()->route('funnel.shipping', ['slug' => $slug]);
        }

        $break = $this->pricing->line($product, null, 1, $seller);
        $subtotal = (int) $break['line_total'];

        // Optional discount code (?coupon=CODE) — previewed, never charged here.
        $couponCode = $request->query('coupon');
        $coupon = $this->coupons->preview($seller, $product, $couponCode, $subtotal, $request->user());
        $discount = $coupon?->discountFor($subtotal) ?? 0;
        $totalAmount = $subtotal - $discount;

        $total = $asset->money((string) $totalAmount);
        $balance = $this->ledger->availableBalance($request->user(), (int) $product->price_asset_id);

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
            'total' => $total->format(2),
            'couponCode' => $coupon?->code,
            'couponInvalid' => $couponCode !== null && trim((string) $couponCode) !== '' && $coupon === null,
            'balance' => $balance->format(2),
            'sufficient' => ! $balance->isLessThan($total),
            'ownProduct' => $seller->user_id === $request->user()->getKey(),
            'idempotencyKey' => $idempotencyKey,
            'shipping' => $shipping,
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
        ]);

        // Physical goods can't be paid for without the address captured earlier.
        $shipping = $request->session()->get($this->shipKey($page));
        if ($page->product->requires_shipping && empty($shipping)) {
            return redirect()->route('funnel.shipping', ['slug' => $slug]);
        }

        try {
            $order = $placeOrder->execute($request->user(), CheckoutData::fromArray([
                'product_id' => $page->product_id,
                'quantity' => 1,
                'sales_page_id' => $page->getKey(),
                'idempotency_key' => $validated['idempotency_key'],
                'coupon_code' => $validated['coupon_code'] ?? null,
                'shipping_address' => $shipping,
            ]));
        } catch (SellException $e) {
            return back()->withErrors(['pay' => $e->getMessage()]);
        }

        $request->session()->forget(["sell:idem:{$page->getKey()}", $this->shipKey($page)]);

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
        }

        return view('funnel.thank-you', [
            'slug' => $slug,
            'page' => $page,
            'product' => $page->product,
            'seller' => $page->seller,
            'order' => $order,
            'total' => $order ? $page->product->priceAsset->money((string) $order->total_amount)->format(2) : null,
        ]);
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
        $page = SalesPage::with(['product.priceAsset', 'seller.user'])
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
            ],
        ];
    }
}
