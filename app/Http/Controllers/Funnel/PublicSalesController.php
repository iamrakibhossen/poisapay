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
    ) {}

    public function show(string $slug): View
    {
        return view('funnel.sales', $this->pageViewModel($this->publishedPage($slug)));
    }

    /** Buy → hand off to the PoisaPay-hosted payment page (buyer must be signed in). */
    public function checkout(Request $request, string $slug): RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

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

        $break = $this->pricing->line($product, null, 1, $seller);
        $total = $asset->money((string) $break['line_total']);
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
            'total' => $total->format(2),
            'balance' => $balance->format(2),
            'sufficient' => ! $balance->isLessThan($total),
            'ownProduct' => $seller->user_id === $request->user()->getKey(),
            'idempotencyKey' => $idempotencyKey,
        ]);
    }

    /** Place the real order: debit buyer, credit seller + platform commission via the Ledger. */
    public function payConfirm(Request $request, string $slug, PlaceOrder $placeOrder): RedirectResponse
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        $page = $this->publishedPage($slug);
        $validated = $request->validate(['idempotency_key' => ['required', 'string', 'max:190']]);

        try {
            $order = $placeOrder->execute($request->user(), CheckoutData::fromArray([
                'product_id' => $page->product_id,
                'quantity' => 1,
                'sales_page_id' => $page->getKey(),
                'idempotency_key' => $validated['idempotency_key'],
            ]));
        } catch (SellException $e) {
            return back()->withErrors(['pay' => $e->getMessage()]);
        }

        $request->session()->forget("sell:idem:{$page->getKey()}");

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
