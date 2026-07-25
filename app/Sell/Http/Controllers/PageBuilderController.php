<?php

declare(strict_types=1);

namespace App\Sell\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sell\Builder\BlockRegistry;
use App\Sell\Builder\BuilderDocument;
use App\Sell\Builder\DocumentSanitizer;
use App\Sell\Builder\RenderContext;
use App\Sell\Builder\Renderer;
use App\Sell\Enums\ProductStatus;
use App\Sell\Enums\SalesPageStatus;
use App\Sell\Models\PageRevision;
use App\Sell\Models\Product;
use App\Sell\Models\SalesPage;
use App\Sell\Models\Seller;
use App\Sell\Services\SalesPageService;
use App\Sell\Services\SellerService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The visual block-tree builder. The editor is a rich Alpine island that keeps the
 * document in a client store and talks to these JSON endpoints:
 *   - GET  edit      → boots the editor (schemas + palette + working draft)
 *   - PATCH save     → debounced autosave of the working draft
 *   - POST preview   → server-renders the posted draft to HTML+CSS for the iframe
 *   - POST publish   → copies the draft to the live document + snapshots a revision
 *   - POST duplicate / restore → page + version-history operations
 *
 * Editing writes only `draft`; the live page keeps rendering `sections` until the
 * seller publishes, so in-progress edits never touch a live URL.
 */
class PageBuilderController extends Controller
{
    public function __construct(
        private readonly SellerService $sellers,
        private readonly BlockRegistry $registry,
        private readonly Renderer $renderer,
        private readonly DocumentSanitizer $sanitizer,
    ) {}

    public function edit(Request $request, string $slug): View|RedirectResponse
    {
        $page = $this->ownedPage($request, $slug);
        if (! $page instanceof SalesPage) {
            return $page;
        }

        return view('frontend.seller.builder', [
            'page' => $page,
            'slug' => $page->slug,
            'name' => $page->name,
            'published' => $page->status === SalesPageStatus::Published,
            'document' => $page->draftDocument()->toArray(),
            'schemas' => $this->registry->schemas(),
            'palette' => $this->registry->palette(),
            'products' => Product::where('seller_id', $page->seller_id)->orderBy('name')->pluck('name', 'id')->all(),
            'productId' => $page->product_id,
            'revisions' => $page->revisions()->limit(20)->get(['id', 'version', 'label', 'created_at']),
            'publicUrl' => route('funnel.sales', ['slug' => $page->slug]),
            'seo' => [
                'title' => $page->seo['title'] ?? '',
                'description' => $page->seo['description'] ?? '',
                'og_image' => $page->seo['og_image'] ?? '',
                'noindex' => (bool) ($page->seo['noindex'] ?? false),
            ],
            'offers' => $this->offerFields($page),
            'endpoints' => [
                'save' => route('sell.sales-page.document', ['slug' => $page->slug]),
                'preview' => route('sell.sales-page.preview', ['slug' => $page->slug]),
                'publish' => route('sell.sales-page.publish', ['slug' => $page->slug]),
                'duplicate' => route('sell.sales-page.duplicate', ['slug' => $page->slug]),
                'settings' => route('sell.sales-page.update', ['slug' => $page->slug]),
            ],
        ]);
    }

    /**
     * Offer fields for the settings panel — prices shown in the product's currency
     * (decimal), matching the applyOffers() round-trip.
     *
     * @return array<string, mixed>
     */
    private function offerFields(SalesPage $page): array
    {
        $asset = $page->product?->priceAsset;
        $toDec = fn (?int $minor) => $minor === null ? '' : rtrim(rtrim(number_format($minor / (10 ** ($asset?->decimals ?? 2)), $asset?->decimals ?? 2, '.', ''), '0'), '.');

        return [
            'bump_product_id' => (string) $page->bump_product_id,
            'bump_price' => $toDec($page->bump_price_amount),
            'bump_headline' => $page->bump_headline ?? '',
            'bump_description' => $page->bump_description ?? '',
            'upsell_product_id' => (string) $page->upsell_product_id,
            'upsell_price' => $toDec($page->upsell_price_amount),
            'upsell_headline' => $page->upsell_headline ?? '',
            'currency' => $asset?->symbol ?? '',
        ];
    }

    /** Debounced autosave: persist the working draft (never the live page). */
    public function save(Request $request, string $slug): JsonResponse
    {
        $page = $this->ownedPage($request, $slug);
        if (! $page instanceof SalesPage) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'document' => ['required', 'array'],
            'productId' => ['sometimes', 'nullable', 'string'],
        ]);

        $update = ['draft' => $this->sanitizer->clean($validated['document']), 'version' => $page->version + 1];

        if (isset($validated['name'])) {
            $update['name'] = $validated['name'];
        }
        if (! empty($validated['productId']) && $validated['productId'] !== $page->product_id
            && Product::where('seller_id', $page->seller_id)->whereKey($validated['productId'])->exists()) {
            $update['product_id'] = $validated['productId'];
        }

        $page->update($update);

        return response()->json(['ok' => true, 'version' => $page->version, 'savedAt' => now()->toIso8601String()]);
    }

    /**
     * Render the posted draft with the ONE shared renderer, so the editor's live
     * canvas is byte-identical to the public page. Rendered in "editing" mode (buy
     * buttons inert, empty blocks show placeholders). Returned as an HTML fragment
     * + compiled CSS so the client can patch it into the canvas IN PLACE — no
     * reload, so scroll position and the current selection are preserved.
     */
    public function preview(Request $request, string $slug): JsonResponse
    {
        $page = $this->ownedPage($request, $slug);
        if (! $page instanceof SalesPage) {
            abort(404);
        }

        $validated = $request->validate(['document' => ['required', 'array']]);
        $document = BuilderDocument::fromArray($this->sanitizer->clean($validated['document']));
        $context = RenderContext::fromSalesPage($page, $document->globals(), editing: true);
        $rendered = $this->renderer->render($document, $context);

        return response()->json([
            'html' => (string) $rendered['html'],
            'css' => (string) $rendered['css'],
        ]);
    }

    /** Publish: copy the working draft to the live document + snapshot a revision. */
    public function publish(Request $request, string $slug): RedirectResponse
    {
        $page = $this->ownedPage($request, $slug);
        if (! $page instanceof SalesPage) {
            abort(404);
        }

        $goLive = $page->status !== SalesPageStatus::Published;

        DB::transaction(function () use ($page, $goLive) {
            // The draft becomes the published document. Store it under `sections` as
            // a v2 document so the public renderer walks the tree (SalesPageService
            // cache is dropped by the model's saved observer).
            $draft = $page->draftDocument()->toArray();

            $page->update([
                'sections' => $draft,
                'version' => $page->version + 1,
                'status' => $goLive ? SalesPageStatus::Published : SalesPageStatus::Draft,
                'published_at' => $goLive ? now() : $page->published_at,
            ]);

            if ($goLive && $page->product && $page->product->status !== ProductStatus::Published) {
                $page->product->update(['status' => ProductStatus::Published]);
            }

            PageRevision::create([
                'sales_page_id' => $page->id,
                'author_user_id' => request()->user()?->id,
                'version' => $page->version,
                'label' => $goLive ? __('Published') : __('Unpublished'),
                'document' => $draft,
            ]);
        });

        return redirect()->route('sell.sales-page.edit', ['slug' => $page->slug])
            ->with('success', $goLive ? __('Your page is live.') : __('Page unpublished.'));
    }

    /**
     * Page settings — name, which product it sells, SEO/social overrides, and the
     * order-bump + 1-click upsell offers. These live outside the block document
     * (they're server-authoritative money/routing config), so they persist through
     * a plain form POST rather than the document autosave.
     */
    public function settings(Request $request, string $slug): RedirectResponse
    {
        $page = $this->ownedPage($request, $slug);
        if (! $page instanceof SalesPage) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'product_id' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:200'],
            'seo_og_image' => ['nullable', 'url', 'max:300'],
            'seo_noindex' => ['nullable', 'boolean'],
            'bump_product_id' => ['nullable', 'string'],
            'bump_price' => ['nullable', 'numeric', 'min:0'],
            'bump_headline' => ['nullable', 'string', 'max:160'],
            'bump_description' => ['nullable', 'string', 'max:400'],
            'upsell_product_id' => ['nullable', 'string'],
            'upsell_price' => ['nullable', 'numeric', 'min:0'],
            'upsell_headline' => ['nullable', 'string', 'max:160'],
            'upsell_description' => ['nullable', 'string', 'max:400'],
        ]);

        if (! empty($validated['name'])) {
            $page->name = $validated['name'];
        }
        if (! empty($validated['product_id']) && $validated['product_id'] !== $page->product_id
            && Product::where('seller_id', $page->seller_id)->whereKey($validated['product_id'])->exists()) {
            $page->product_id = $validated['product_id'];
        }
        $page->save();

        $this->applySeo($page->fresh(), $request);
        $this->applyOffers($page->fresh(), $request);

        return redirect()->route('sell.sales-page.edit', ['slug' => $page->slug])
            ->with('success', __('Settings saved.'));
    }

    /** Persist per-page SEO/social overrides into the `seo` jsonb. */
    private function applySeo(SalesPage $page, Request $request): void
    {
        $page->update(['seo' => array_filter([
            'title' => $request->input('seo_title'),
            'description' => $request->input('seo_description'),
            'og_image' => $request->input('seo_og_image'),
            'noindex' => $request->boolean('seo_noindex') ?: null,
        ], fn ($v) => $v !== null && $v !== '')]);
    }

    /**
     * Persist the order-bump + upsell offers. Prices are entered in the main
     * product's currency (decimal) → stored as minor units. Offer products must
     * belong to the seller and share the main product's currency.
     */
    private function applyOffers(SalesPage $page, Request $request): void
    {
        $asset = $page->product?->priceAsset;
        $decimals = $asset?->decimals ?? 2;
        $toMinor = fn ($v) => ($v === null || $v === '') ? null
            : (int) Money::ofDecimal((string) $v, $decimals, $asset?->symbol ?? '')->baseString();

        $resolve = function (?string $id) use ($page, $asset): ?string {
            if (! $id) {
                return null;
            }
            $p = Product::where('seller_id', $page->seller_id)->whereKey($id)->first();

            return ($p && (int) $p->price_asset_id === (int) $asset?->id && $p->getKey() !== $page->product_id)
                ? $p->getKey() : null;
        };

        $bumpId = $resolve($request->input('bump_product_id'));
        $upsellId = $resolve($request->input('upsell_product_id'));

        $page->update([
            'bump_product_id' => $bumpId,
            'bump_price_amount' => $bumpId ? $toMinor($request->input('bump_price')) : null,
            'bump_headline' => $bumpId ? $request->input('bump_headline') : null,
            'bump_description' => $bumpId ? $request->input('bump_description') : null,
            'upsell_product_id' => $upsellId,
            'upsell_price_amount' => $upsellId ? $toMinor($request->input('upsell_price')) : null,
            'upsell_headline' => $upsellId ? $request->input('upsell_headline') : null,
            'upsell_description' => $upsellId ? $request->input('upsell_description') : null,
        ]);
    }

    /** Clone the page (draft + design) under a new slug. */
    public function duplicate(Request $request, string $slug): RedirectResponse
    {
        $page = $this->ownedPage($request, $slug);
        if (! $page instanceof SalesPage) {
            abort(404);
        }

        $copy = $page->replicate(['published_at']);
        $copy->name = $page->name.' '.__('(copy)');
        $copy->slug = app(SalesPageService::class)->uniqueSlug($page->name.'-copy');
        $copy->status = SalesPageStatus::Draft;
        $copy->draft = $page->draftDocument()->toArray();
        $copy->save();

        return redirect()->route('sell.sales-page.edit', ['slug' => $copy->slug])
            ->with('success', __('Page duplicated.'));
    }

    /** Restore a revision's document back into the working draft. */
    public function restore(Request $request, string $slug, string $revision): RedirectResponse
    {
        $page = $this->ownedPage($request, $slug);
        if (! $page instanceof SalesPage) {
            abort(404);
        }

        $rev = $page->revisions()->whereKey($revision)->firstOrFail();
        $page->update(['draft' => $this->sanitizer->clean((array) $rev->document), 'version' => $page->version + 1]);

        return redirect()->route('sell.sales-page.edit', ['slug' => $page->slug])
            ->with('success', __('Version restored to your draft.'));
    }

    /** Resolve a page the current user owns, or a redirect to the list. */
    private function ownedPage(Request $request, string $slug): SalesPage|RedirectResponse
    {
        $seller = $this->sellers->forUser($request->user());
        abort_unless($seller instanceof Seller && $seller->canSell(), 403);

        $page = $seller->salesPages()->with(['product.priceAsset', 'seller', 'bumpProduct', 'upsellProduct'])
            ->where('slug', $slug)->first();

        return $page ?? redirect()->route('sell.sales-pages');
    }
}
