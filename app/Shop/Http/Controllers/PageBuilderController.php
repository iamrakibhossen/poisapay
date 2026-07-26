<?php

declare(strict_types=1);

namespace App\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Shop\Builder\BlockRegistry;
use App\Shop\Builder\BuilderDocument;
use App\Shop\Builder\DocumentSanitizer;
use App\Shop\Builder\RenderContext;
use App\Shop\Builder\Renderer;
use App\Shop\Builder\Templates\TemplateLibrary;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Models\PageRevision;
use App\Shop\Models\Product;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;
use App\Shop\Services\SalesPageService;
use App\Shop\Services\SellerService;
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
            'templates' => TemplateLibrary::meta(),
            'products' => Product::where('seller_id', $page->seller_id)->orderBy('name')->pluck('name', 'id')->all(),
            // Offer products must share the front product's currency and can't be the
            // front product itself — otherwise applyOffers() would reject them on save.
            'offerProducts' => Product::where('seller_id', $page->seller_id)
                ->when($page->product?->price_asset_id, fn ($q, $assetId) => $q->where('price_asset_id', $assetId))
                ->whereKeyNot($page->product_id)
                ->orderBy('name')->pluck('name', 'id')->all(),
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
                'save' => route('shop.sales-page.document', ['slug' => $page->slug]),
                'preview' => route('shop.sales-page.preview', ['slug' => $page->slug]),
                'publish' => route('shop.sales-page.publish', ['slug' => $page->slug]),
                'duplicate' => route('shop.sales-page.duplicate', ['slug' => $page->slug]),
                'settings' => route('shop.sales-page.update', ['slug' => $page->slug]),
                'template' => route('shop.sales-page.template', ['slug' => $page->slug]),
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
            'upsell_description' => $page->upsell_description ?? '',
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

        return redirect()->route('shop.sales-page.edit', ['slug' => $page->slug])
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
        $rejected = $this->applyOffers($page->fresh(), $request);

        $redirect = redirect()->route('shop.sales-page.edit', ['slug' => $page->slug])
            ->with('success', __('Settings saved.'));

        if ($rejected !== []) {
            $redirect->with('warning', __(
                'The :offers wasn’t applied — an offer product must use the same currency as this page and can’t be the page’s own product.',
                ['offers' => implode(' & ', $rejected)]
            ));
        }

        return $redirect;
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
     * belong to the seller and share the main product's currency. A blank select
     * clears the offer; a non-blank but ineligible pick is *rejected* (the stored
     * offer is left untouched) so a bad choice never silently wipes a good offer.
     *
     * @return array<int, string> human labels of the offers that were rejected
     */
    private function applyOffers(SalesPage $page, Request $request): array
    {
        $asset = $page->product?->priceAsset;
        $decimals = $asset?->decimals ?? 2;
        $toMinor = fn ($v) => ($v === null || $v === '') ? null
            : (int) Money::ofDecimal((string) $v, $decimals, $asset?->symbol ?? '')->baseString();

        // ['id' => ?string, 'rejected' => bool] — id null means "clear this offer".
        $resolve = function (?string $id) use ($page, $asset): array {
            if ($id === null || $id === '') {
                return ['id' => null, 'rejected' => false];
            }
            $p = Product::where('seller_id', $page->seller_id)->whereKey($id)->first();
            $ok = $p && (int) $p->price_asset_id === (int) $asset?->id && $p->getKey() !== $page->product_id;

            return ['id' => $ok ? $p->getKey() : null, 'rejected' => ! $ok];
        };

        $rejected = [];
        $update = [];

        $bump = $resolve($request->input('bump_product_id'));
        if ($bump['rejected']) {
            $rejected[] = __('order bump');
        } else {
            $id = $bump['id'];
            $update += [
                'bump_product_id' => $id,
                'bump_price_amount' => $id ? $toMinor($request->input('bump_price')) : null,
                'bump_headline' => $id ? $request->input('bump_headline') : null,
                'bump_description' => $id ? $request->input('bump_description') : null,
            ];
        }

        $upsell = $resolve($request->input('upsell_product_id'));
        if ($upsell['rejected']) {
            $rejected[] = __('1-click upsell');
        } else {
            $id = $upsell['id'];
            $update += [
                'upsell_product_id' => $id,
                'upsell_price_amount' => $id ? $toMinor($request->input('upsell_price')) : null,
                'upsell_headline' => $id ? $request->input('upsell_headline') : null,
                'upsell_description' => $id ? $request->input('upsell_description') : null,
            ];
        }

        if ($update !== []) {
            $page->update($update);
        }

        return $rejected;
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

        return redirect()->route('shop.sales-page.edit', ['slug' => $copy->slug])
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

        return redirect()->route('shop.sales-page.edit', ['slug' => $page->slug])
            ->with('success', __('Version restored to your draft.'));
    }

    /**
     * Apply a premium starter template — replaces the working draft with the
     * template's document (sanitised). The client swaps its in-memory doc from the
     * response. Publishing is still an explicit, separate step.
     */
    public function applyTemplate(Request $request, string $slug): JsonResponse
    {
        $page = $this->ownedPage($request, $slug);
        if (! $page instanceof SalesPage) {
            abort(404);
        }

        $validated = $request->validate(['template' => ['required', 'string', 'max:40']]);
        $document = TemplateLibrary::document($validated['template']);
        abort_if($document === null, 404);

        // Templates store only the props they override (the renderer fills the rest
        // from schema defaults). But the EDITOR's property panel binds to each block's
        // full field set, so a template node's missing keys can't be edited. Hydrate
        // every node with its block defaults on apply → applied templates behave
        // exactly like hand-built blocks.
        $document = $this->hydrateDefaults($document);

        $clean = $this->sanitizer->clean($document);
        $page->update(['draft' => $clean, 'version' => $page->version + 1]);

        return response()->json(['document' => $clean, 'savedAt' => now()->toIso8601String()]);
    }

    /**
     * Merge each node's block-schema default props under its (override) props, so an
     * applied template carries the full, editable prop set — recursively.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function hydrateDefaults(array $document): array
    {
        $walk = function (array &$nodes) use (&$walk): void {
            foreach ($nodes as &$node) {
                $type = $node['type'] ?? null;
                if (is_string($type) && $this->registry->has($type)) {
                    $node['props'] = array_replace(
                        $this->registry->get($type)->defaults(),
                        is_array($node['props'] ?? null) ? $node['props'] : [],
                    );
                }
                if (! empty($node['children']) && is_array($node['children'])) {
                    $walk($node['children']);
                }
            }
            unset($node);
        };

        if (! empty($document['root']['children']) && is_array($document['root']['children'])) {
            $walk($document['root']['children']);
        }

        return $document;
    }

    /**
     * A live, self-contained render of a starter template — shown as a scaled-down
     * "screenshot" iframe in the editor's template gallery. Demo product/seller data,
     * buy buttons inert (editing mode).
     */
    public function templatePreview(Request $request, string $template): View
    {
        $seller = $this->sellers->forUser($request->user());
        abort_unless($seller instanceof Seller && $seller->canSell(), 403);

        $document = TemplateLibrary::document($template);
        abort_if($document === null, 404);

        $doc = BuilderDocument::fromArray($this->sanitizer->clean($document));
        $name = $seller->displayName() ?: 'Your store';
        $ctx = new RenderContext(
            'preview',
            ['name' => 'Your product', 'summary' => 'A short, punchy line about what you sell.', 'price' => '$49', 'comparePrice' => '$99', 'type' => 'digital'],
            ['name' => $name, 'initials' => mb_strtoupper(mb_substr($name, 0, 1)), 'logo' => $seller->logoUrl()],
            editing: true,
        );
        $rendered = $this->renderer->render($doc, $ctx);

        return view('frontend.seller.template-preview', [
            'html' => $rendered['html'],
            'css' => $rendered['css'],
        ]);
    }

    /** Resolve a page the current user owns, or a redirect to the list. */
    private function ownedPage(Request $request, string $slug): SalesPage|RedirectResponse
    {
        $seller = $this->sellers->forUser($request->user());
        abort_unless($seller instanceof Seller && $seller->canSell(), 403);

        $page = $seller->salesPages()->with(['product.priceAsset', 'seller', 'bumpProduct', 'upsellProduct'])
            ->where('slug', $slug)->first();

        return $page ?? redirect()->route('shop.sales-pages');
    }
}
