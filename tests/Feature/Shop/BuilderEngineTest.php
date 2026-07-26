<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Actions\Product\CreateProduct;
use App\Shop\Builder\BlockRegistry;
use App\Shop\Builder\BuilderDocument;
use App\Shop\Builder\BuilderNode;
use App\Shop\Builder\RenderContext;
use App\Shop\Builder\Renderer;
use App\Shop\Builder\SchemaMigrator;
use App\Shop\Builder\StyleCompiler;
use App\Shop\Builder\Templates\TemplateLibrary;
use App\Shop\DTOs\ProductData;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Product;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;

// ─── engine unit coverage ──────────────────────────────────────────────────────

it('migrates a legacy v1 document into the v2 block tree', function () {
    $doc = (new SchemaMigrator)->toDocument([
        ['type' => 'hero', 'enabled' => true, 'content' => ['headline' => 'Hi']],
        ['type' => 'stats', 'enabled' => true, 'content' => [['value' => '9k', 'label' => 'Users']]],
        ['type' => 'faq', 'enabled' => false, 'content' => ['Q one?', 'Q two?']],
    ], ['accent' => '#111827', 'btn' => 'pill', 'font' => 'Poppins']);

    expect($doc->schema)->toBe(2)
        ->and($doc->root()->children)->toHaveCount(3)
        ->and($doc->globals()['colors']['brand'])->toBe('#111827')
        ->and($doc->globals()['buttons']['radius'])->toBe('pill');

    $children = $doc->root()->children;
    // list-typed legacy section normalised into { items: [...] }
    expect($children[1]->prop('items'))->toHaveCount(1);
    // disabled section carries visibility=false on every device
    expect($children[2]->visibility)->toMatchArray(['desktop' => false, 'tablet' => false, 'mobile' => false]);
});

it('passes a v2 document through unchanged', function () {
    $v2 = BuilderDocument::blank()->toArray();
    $v2['root']['children'][] = ['id' => 'b_keepme01', 'type' => 'heading', 'props' => ['text' => 'Kept']];

    $doc = (new SchemaMigrator)->toDocument($v2);
    expect($doc->schema)->toBe(2)
        ->and($doc->root()->children[0]->id)->toBe('b_keepme01');
});

it('compiles scoped, responsive css from node styles', function () {
    $doc = BuilderDocument::fromArray([
        'schema' => 2,
        'globals' => ['colors' => ['brand' => '#2563eb']],
        'root' => ['id' => 'root', 'type' => 'page', 'children' => [[
            'id' => 'b_style0001', 'type' => 'section',
            'style' => ['base' => ['padY' => 40], 'mobile' => ['padY' => 12]],
            'visibility' => ['desktop' => true, 'tablet' => false, 'mobile' => true],
        ]]],
    ]);

    $css = (new StyleCompiler)->rootVariables($doc->globals()).(new StyleCompiler)->compile($doc->root());

    expect($css)->toContain('--pp-brand:#2563eb')
        ->and($css)->toContain('#b_style0001{padding-top:40px;padding-bottom:40px}')
        ->and($css)->toContain('@media(max-width:640px)')          // mobile override present
        ->and($css)->toContain('@media(max-width:1024px)');        // tablet hide present
});

it('renders known blocks and safely skips unknown ones', function () {
    $doc = BuilderDocument::fromArray([
        'schema' => 2, 'globals' => BuilderDocument::defaultGlobals(),
        'root' => ['id' => 'root', 'type' => 'page', 'children' => [
            ['id' => 'b_head00001', 'type' => 'heading', 'props' => ['text' => 'Real heading', 'level' => 'h2']],
            ['id' => 'b_ghost0001', 'type' => 'not_a_real_block', 'props' => []],
        ]],
    ]);
    $ctx = new RenderContext('demo', ['name' => 'X', 'price' => '$1'], ['name' => 'S', 'initials' => 'S']);

    $out = app(Renderer::class)->render($doc, $ctx);
    expect((string) $out['html'])->toContain('Real heading')
        ->and((string) $out['html'])->not->toContain('not_a_real_block');
});

it('renders every layout variant of the upgraded sections without error', function () {
    $sections = [
        'hero' => ['centered', 'split', 'minimal', 'gradient', 'showcase'],
        'features' => ['cards', 'iconTop', 'iconLeft', 'alternating'],
        'cta-banner' => ['gradient', 'simple', 'dark', 'card', 'split'],
        'faq' => ['accordion', 'cards', 'split'],
        'pricing' => ['cards', 'minimal', 'compact'],
        'testimonials' => ['cards', 'carousel', 'minimal', 'single'],
    ];
    $registry = app(BlockRegistry::class);
    $ctx = new RenderContext('demo', ['name' => 'X', 'price' => '$49', 'comparePrice' => '$99'], ['name' => 'S', 'initials' => 'S']);

    foreach ($sections as $type => $variants) {
        foreach ($variants as $variant) {
            $node = BuilderNode::fromArray(['id' => 'b_v'.substr(md5($type.$variant), 0, 8), 'type' => $type, 'props' => ['variant' => $variant, 'dark' => true, 'billingToggle' => true]]);
            $html = $registry->get($type)->render($node, $ctx, '');
            expect($html)->toBeString()->not->toBe('');
            expect($html)->toContain($node->id); // rendered its own root
        }
    }
});

it('renders an uploaded logo + brand social icons in the header and footer', function () {
    $doc = BuilderDocument::fromArray([
        'schema' => 2, 'globals' => BuilderDocument::defaultGlobals(),
        'root' => ['id' => 'root', 'type' => 'page', 'children' => [
            ['id' => 'b_header0001', 'type' => 'header', 'props' => [
                'logo' => '/storage/media/s/logo.png',
                'socialLinks' => [['platform' => 'instagram', 'url' => 'https://instagram.com/store']],
            ]],
            ['id' => 'b_footer0001', 'type' => 'footer', 'props' => [
                'logo' => '/storage/media/s/logo.png',
                'socialLinks' => [['platform' => 'x', 'url' => 'https://x.com/store']],
            ]],
        ]],
    ]);
    $ctx = new RenderContext('demo', ['name' => 'X', 'price' => '$1'], ['name' => 'S', 'initials' => 'S']);
    $html = (string) app(Renderer::class)->render($doc, $ctx)['html'];

    expect($html)->toContain('/storage/media/s/logo.png')        // uploaded logo used, not initials
        ->and($html)->toContain('https://instagram.com/store')   // header social link
        ->and($html)->toContain('https://x.com/store')           // footer social link
        ->and(substr_count($html, '<svg'))->toBeGreaterThanOrEqual(2); // brand glyphs rendered
});

it('compiles the extended visual control set into scoped css', function () {
    $node = BuilderNode::fromArray([
        'id' => 'b_ctrl00001', 'type' => 'section',
        'style' => ['base' => [
            'padTop' => 24, 'padLeft' => 12, 'marginBottom' => 8,
            'width' => 'full', 'maxWidth' => 720, 'minHeight' => 400,
            'borderWidth' => 2, 'borderStyle' => 'dashed', 'borderColor' => '#111',
            'radius' => 18, 'shadow' => 'lg', 'opacity' => 50, 'zIndex' => 5,
        ]],
    ]);
    $css = (new StyleCompiler)->compile($node);

    expect($css)
        ->toContain('padding-top:24px')
        ->toContain('padding-left:12px')
        ->toContain('margin-bottom:8px')
        ->toContain('width:100%')
        ->toContain('max-width:720px')
        ->toContain('min-height:400px')
        ->toContain('border:2px dashed #111')
        ->toContain('border-radius:18px')
        ->toContain('box-shadow:0 20px 48px')
        ->toContain('opacity:0.5')
        ->toContain('position:relative;z-index:5');
});

it('folds image + overlay into a single layered background, keeps legacy solid shorthand', function () {
    $layered = (new StyleCompiler)->compile(BuilderNode::fromArray([
        'id' => 'b_bgimg0001', 'type' => 'section',
        'style' => ['base' => ['bgImage' => 'https://cdn.test/hero.jpg', 'overlay' => 'rgba(0,0,0,.5)', 'bg' => '#000', 'parallax' => 1]],
    ]));
    expect($layered)
        ->toContain('background-image:linear-gradient(rgba(0,0,0,.5),rgba(0,0,0,.5)),url("https://cdn.test/hero.jpg")')
        ->toContain('background-color:#000')
        ->toContain('background-size:cover')
        ->toContain('background-attachment:fixed');

    // A plain solid colour still emits the compact shorthand (byte-compatible with v2 docs).
    $solid = (new StyleCompiler)->compile(BuilderNode::fromArray([
        'id' => 'b_bgsolid01', 'type' => 'section', 'style' => ['base' => ['bg' => '#f8fafc']],
    ]));
    expect($solid)->toContain('#b_bgsolid01{background:#f8fafc!important}');
});

it('compiles a progressive-enhancement scroll reveal for entrance animations', function () {
    $still = (new StyleCompiler)->compile(BuilderNode::fromArray([
        'id' => 'b_still0001', 'type' => 'section', 'style' => ['base' => ['padY' => 10]],
    ]));
    expect($still)->not->toContain('pp-anim')->and($still)->not->toContain('pp-in');

    $moving = (new StyleCompiler)->compile(BuilderNode::fromArray([
        'id' => 'b_anim00001', 'type' => 'section', 'style' => ['base' => ['anim' => 'up', 'animDur' => 800, 'animDelay' => 120]],
    ]));
    expect($moving)
        // Hidden only under html.pp-anim (JS present) — no-JS keeps content visible.
        ->toContain('html.pp-anim #b_anim00001{opacity:0;transform:translateY(24px);transition:opacity 800ms ease,transform 800ms ease;transition-delay:120ms}')
        ->toContain('#b_anim00001.pp-in{opacity:1;transform:none}')
        ->toContain('@media(prefers-reduced-motion:reduce)');
});

it('marks animated blocks with data-anim so the scroll observer can find them', function () {
    $doc = BuilderDocument::fromArray([
        'schema' => 2, 'globals' => BuilderDocument::defaultGlobals(),
        'root' => ['id' => 'root', 'type' => 'page', 'children' => [
            ['id' => 'b_reveal001', 'type' => 'heading', 'props' => ['text' => 'Hi', 'level' => 'h2'], 'style' => ['base' => ['anim' => 'fade']]],
            ['id' => 'b_static001', 'type' => 'heading', 'props' => ['text' => 'Bye', 'level' => 'h2']],
        ]],
    ]);
    $ctx = new RenderContext('demo', ['name' => 'X', 'price' => '$1'], ['name' => 'S', 'initials' => 'S']);
    $html = (string) app(Renderer::class)->render($doc, $ctx)['html'];

    expect($html)
        ->toContain('data-anim="" id="b_reveal001"')          // injected on the animated node
        ->and($html)->not->toContain('data-anim="" id="b_static001"'); // not on the static one
});

it('scopes custom css and refuses to break out of the rule', function () {
    $css = (new StyleCompiler)->compile(BuilderNode::fromArray([
        'id' => 'b_custom001', 'type' => 'section',
        'style' => ['base' => ['customCss' => 'letter-spacing:.1em} body{display:none} .x{']],
    ]));
    // Braces/angle-brackets stripped → the rule can only ever touch #b_custom001.
    expect($css)
        ->toContain('#b_custom001{letter-spacing:.1em bodydisplay:none .x}')
        ->not->toContain('body{display:none}');
});

it('scrubs a css-injection attempt in a style value', function () {
    $css = (new StyleCompiler)->compile(BuilderNode::fromArray([
        'id' => 'b_evil00001', 'type' => 'section',
        'style' => ['base' => ['bg' => 'red}html{opacity:0}']],
    ]));
    expect($css)->toContain('background:redhtmlopacity:0')
        ->and($css)->not->toContain('html{opacity:0}');
});

it('injects a custom class + anchor onto the block root', function () {
    $doc = BuilderDocument::fromArray([
        'schema' => 2, 'globals' => BuilderDocument::defaultGlobals(),
        'root' => ['id' => 'root', 'type' => 'page', 'children' => [
            ['id' => 'b_chrome001', 'type' => 'heading', 'props' => ['text' => 'Hi', 'level' => 'h2'],
                'meta' => ['className' => 'promo big', 'anchor' => 'Pricing!!']],
        ]],
    ]);
    $ctx = new RenderContext('demo', ['name' => 'X', 'price' => '$1'], ['name' => 'S', 'initials' => 'S']);
    $html = (string) app(Renderer::class)->render($doc, $ctx)['html'];

    expect($html)
        ->toContain('data-anchor="pricing"')   // scrubbed to a slug
        ->toContain('promo big')               // merged into existing class attr
        ->toContain('id="b_chrome001"');
});

it('renders every registered block from its defaults without error', function () {
    $registry = app(BlockRegistry::class);
    $ctx = new RenderContext(
        'demo',
        ['name' => 'Kit', 'summary' => 'A great kit', 'price' => '$49', 'comparePrice' => '$99', 'type' => 'digital'],
        ['name' => 'Acme', 'initials' => 'A'],
        offers: [
            'bump' => ['headline' => 'Add this', 'price' => '$9'],
            'upsell' => ['headline' => 'One more', 'price' => '$19'],
        ],
        editing: true,
    );

    foreach ($registry->all() as $type => $block) {
        $child = ['id' => 'b_smoke0001', 'type' => $type, 'props' => $block->defaults()];
        // Give containers a heading child so their `{!! $children !!}` slot is exercised.
        if ($block->isContainer()) {
            $child['children'] = [['id' => 'b_smoke0002', 'type' => 'heading', 'props' => ['text' => 'Hi', 'level' => 'h2']]];
        }
        $doc = BuilderDocument::fromArray([
            'schema' => 2, 'globals' => BuilderDocument::defaultGlobals(),
            'root' => ['id' => 'root', 'type' => 'page', 'children' => [$child]],
        ]);

        // The render call itself throws on a bad Blade partial or an invalid icon
        // name — which is exactly what this smoke test is here to catch.
        $html = (string) app(Renderer::class)->render($doc, $ctx)['html'];
        expect($html)->toBeString();
    }

    // Forms (48), footer builder (49), product-grid (50).
    expect(count($registry->all()))->toBeGreaterThanOrEqual(50)
        ->and($registry->has('pricing'))->toBeTrue()
        ->and($registry->has('comparison'))->toBeTrue()
        ->and($registry->has('lead-capture'))->toBeTrue()
        ->and($registry->has('footer'))->toBeTrue()
        ->and($registry->has('product-grid'))->toBeTrue();
});

it('renders the product-grid block from the store catalog', function () {
    $ctx = new RenderContext('demo', ['name' => 'Kit', 'price' => '$1'], ['name' => 'Store', 'initials' => 'S'], catalog: [
        ['name' => 'Alpha Kit', 'summary' => 'The first one', 'image' => 'https://cdn.test/a.jpg', 'price' => '$49', 'comparePrice' => '$99', 'url' => 'https://x/p/alpha'],
        ['name' => 'Beta Kit', 'summary' => 'The second one', 'image' => null, 'price' => '$19', 'comparePrice' => null, 'url' => 'https://x/p/beta'],
    ]);
    $doc = BuilderDocument::fromArray([
        'schema' => 2, 'globals' => BuilderDocument::defaultGlobals(),
        'root' => ['id' => 'root', 'type' => 'page', 'children' => [
            ['id' => 'b_pgrid0001', 'type' => 'product-grid', 'props' => ['heading' => 'Our shop', 'cols' => 3, 'limit' => 6]],
        ]],
    ]);
    $html = (string) app(Renderer::class)->render($doc, $ctx)['html'];

    expect($html)
        ->toContain('Our shop')
        ->toContain('Alpha Kit')->toContain('$49')->toContain('$99')->toContain('cdn.test/a.jpg')
        ->toContain('Beta Kit')->toContain('https://x/p/beta');
});

it('persists a product cover image url through the create action', function () {
    updateSetting('shop_enabled', true);
    $asset = testAsset('USDT', 6, 'tron');
    $user = User::factory()->create();
    $seller = Seller::create(['user_id' => $user->id, 'status' => SellerStatus::Approved, 'brand_name' => 'Imaged', 'categories' => []]);

    $product = app(CreateProduct::class)->execute($seller, ProductData::fromArray([
        'type' => 'digital', 'name' => 'Imaged', 'price_amount' => 1000000, 'price_asset_id' => $asset->id,
        'image' => 'https://cdn.test/cover.jpg', 'attributes' => [], 'variants' => [],
    ]));

    expect($product->fresh()->image)->toBe('https://cdn.test/cover.jpg');
});

it('renders a minimal footer and honours renamed + legacy prop keys', function () {
    $ctx = new RenderContext('demo', ['name' => 'X', 'price' => '$1'], ['name' => 'Acme', 'initials' => 'A']);
    $render = fn (array $props) => (string) app(Renderer::class)->render(BuilderDocument::fromArray([
        'schema' => 2, 'globals' => BuilderDocument::defaultGlobals(),
        'root' => ['id' => 'root', 'type' => 'page', 'children' => [['id' => 'b_ftr000001', 'type' => 'footer', 'props' => $props]]],
    ]), $ctx)['html'];

    // Always single-column — the multi-column marketing footer was removed.
    expect($render([]))->not->toContain('md:grid-cols-4');
    // New prop keys render.
    expect($render(['brandName' => 'Newco', 'links' => [['label' => 'Docs', 'url' => '#docs']]]))
        ->toContain('Newco')->toContain('Docs')->toContain('#docs');
    // Legacy keys still resolve (brand → brandName, columns → links, dark → darkMode).
    expect($render(['brand' => 'Oldco', 'columns' => [['title' => 'X', 'links' => 'Legacy|#leg']], 'dark' => true]))
        ->toContain('Oldco')->toContain('Legacy')->toContain('bg-neutral-900');
});

it('lets a header/footer block replace the default sales-page chrome', function () {
    [$user, , , $page] = makeBuilderPage();
    $doc = BuilderDocument::blank()->toArray();
    $doc['root']['children'][] = ['id' => 'b_hdr00001', 'type' => 'header', 'props' => ['brand' => 'Acme', 'preset' => 'left', 'links' => []]];
    $doc['root']['children'][] = ['id' => 'b_ftr00001', 'type' => 'footer', 'props' => ['brand' => 'Acme', 'tagline' => 'My custom footer here', 'columns' => [], 'social' => []]];
    $page->update(['draft' => $doc]);
    $this->actingAs($user)->post(route('shop.sales-page.publish', ['slug' => 'main-page']));

    $this->get(route('funnel.sales', ['slug' => 'main-page']))
        ->assertOk()
        ->assertSee('My custom footer here')   // the seller's footer block rendered
        ->assertDontSee('Powered by PoisaHub'); // default chrome footer suppressed
});

it('builds a renderable document for every starter template', function () {
    $registry = app(BlockRegistry::class);
    $meta = TemplateLibrary::meta();
    expect($meta)->toHaveCount(15);

    $ctx = new RenderContext('demo', ['name' => 'Kit', 'price' => '$49', 'comparePrice' => '$99'], ['name' => 'Store', 'initials' => 'S'], editing: true);

    foreach ($meta as $t) {
        $doc = BuilderDocument::fromArray(TemplateLibrary::document($t['id']));

        // Every template may only reference real, registered block types.
        foreach ($doc->root()->flatten() as $node) {
            expect($node->type === 'page' || $registry->has($node->type))->toBeTrue("template {$t['id']} uses unknown block {$node->type}");
        }

        $html = (string) app(Renderer::class)->render($doc, $ctx)['html'];
        expect(strlen($html))->toBeGreaterThan(50);
    }
});

// ─── draft / publish / preview flow ─────────────────────────────────────────────

function makeBuilderPage(): array
{
    updateSetting('shop_enabled', true);
    $asset = testAsset('USDT', 6, 'tron');
    $user = User::factory()->create();
    $seller = Seller::create(['user_id' => $user->id, 'status' => SellerStatus::Approved, 'brand_name' => 'Acme', 'categories' => []]);
    $product = Product::create(['seller_id' => $seller->id, 'type' => ProductType::Digital, 'name' => 'Kit', 'slug' => 'kit', 'status' => ProductStatus::Draft, 'price_amount' => 4900000, 'price_asset_id' => $asset->id]);
    $page = SalesPage::create([
        'seller_id' => $seller->id, 'product_id' => $product->id, 'name' => 'Main', 'slug' => 'main-page',
        'status' => SalesPageStatus::Draft, 'version' => 1, 'sections' => [], 'theme' => [],
    ]);

    return [$user, $seller, $product, $page];
}

it('autosaves the working draft without touching the live document', function () {
    [$user, , , $page] = makeBuilderPage();
    $doc = BuilderDocument::blank()->toArray();
    $doc['root']['children'][] = ['id' => 'b_draftnode', 'type' => 'heading', 'props' => ['text' => 'Draft only']];

    $this->actingAs($user)
        ->patchJson(route('shop.sales-page.document', ['slug' => 'main-page']), ['document' => $doc, 'name' => 'Renamed'])
        ->assertOk()->assertJson(['ok' => true]);

    $page->refresh();
    expect($page->draft['root']['children'][0]['id'])->toBe('b_draftnode')
        ->and($page->name)->toBe('Renamed')
        ->and($page->sections)->toBe([]); // live document untouched until publish
});

it('publishes by copying the draft into the live document + snapshots a revision', function () {
    [$user, , $product, $page] = makeBuilderPage();
    $doc = BuilderDocument::blank()->toArray();
    $doc['root']['children'][] = ['id' => 'b_livenode1', 'type' => 'heading', 'props' => ['text' => 'Now live']];
    $page->update(['draft' => $doc]);

    $this->actingAs($user)
        ->post(route('shop.sales-page.publish', ['slug' => 'main-page']))
        ->assertRedirect(route('shop.sales-page.edit', ['slug' => 'main-page']));

    $page->refresh();
    expect($page->status)->toBe(SalesPageStatus::Published)
        ->and($page->sections['schema'])->toBe(2)
        ->and($page->sections['root']['children'][0]['id'])->toBe('b_livenode1')
        ->and($page->revisions()->count())->toBe(1)
        ->and($product->fresh()->status)->toBe(ProductStatus::Published); // publishing takes the product live
});

it('renders the preview through the same renderer for the owner only', function () {
    [$user] = makeBuilderPage();
    $doc = BuilderDocument::blank()->toArray();
    $doc['root']['children'][] = ['id' => 'b_preview01', 'type' => 'heading', 'props' => ['text' => 'Preview me']];

    $this->actingAs($user)
        ->post(route('shop.sales-page.preview', ['slug' => 'main-page']), ['document' => $doc])
        ->assertOk()
        ->assertJsonStructure(['html', 'css'])
        ->assertSee('Preview me'); // the rendered fragment travels inside the JSON payload

    // A different seller cannot preview someone else's page.
    $other = User::factory()->create();
    Seller::create(['user_id' => $other->id, 'status' => SellerStatus::Approved, 'brand_name' => 'Other', 'categories' => []]);
    $this->actingAs($other)
        ->post(route('shop.sales-page.preview', ['slug' => 'main-page']), ['document' => $doc])
        ->assertNotFound();
});

it('applies a starter template to the working draft', function () {
    [$user, , , $page] = makeBuilderPage();

    $this->actingAs($user)
        ->postJson(route('shop.sales-page.template', ['slug' => 'main-page']), ['template' => 'saas'])
        ->assertOk()
        ->assertJsonStructure(['document', 'savedAt']);

    $types = collect(BuilderDocument::fromArray($page->fresh()->draft)->root()->flatten())->pluck('type');
    expect($types)->toContain('hero')->toContain('pricing')->toContain('footer');

    // Unknown template id → 404, draft untouched.
    $this->actingAs($user)
        ->postJson(route('shop.sales-page.template', ['slug' => 'main-page']), ['template' => 'does-not-exist'])
        ->assertNotFound();
});

it('hydrates applied-template nodes with full block defaults so every field is editable', function () {
    [$user, , , $page] = makeBuilderPage();
    $registry = app(BlockRegistry::class);

    $this->actingAs($user)
        ->postJson(route('shop.sales-page.template', ['slug' => 'main-page']), ['template' => 'saas'])
        ->assertOk();

    $flatten = function (array $nodes) use (&$flatten): array {
        $out = [];
        foreach ($nodes as $n) {
            $out[] = $n;
            if (! empty($n['children'])) {
                $out = array_merge($out, $flatten($n['children']));
            }
        }

        return $out;
    };

    $nodes = collect($flatten($page->fresh()->draft['root']['children'] ?? []))
        ->filter(fn ($n) => ($n['type'] ?? null) !== 'page' && $registry->has($n['type'] ?? ''));

    expect($nodes)->not->toBeEmpty();
    // Every default prop key is present on the applied node → the property panel
    // binds to real keys instead of undefined, so template values are editable.
    $nodes->each(function ($n) use ($registry) {
        $missing = array_diff(array_keys($registry->get($n['type'])->defaults()), array_keys($n['props'] ?? []));
        expect($missing)->toBe([], "block {$n['type']} is missing default props after applying the template");
    });
});

it('renders a live template preview for the gallery thumbnail', function () {
    [$user] = makeBuilderPage();

    $this->actingAs($user)
        ->get(route('shop.template.preview', ['template' => 'saas']))
        ->assertOk()
        ->assertSee('all-in-one platform', false); // the SaaS template hero headline

    $this->actingAs($user)
        ->get(route('shop.template.preview', ['template' => 'does-not-exist']))
        ->assertNotFound();
});

it('restores a revision back into the draft', function () {
    [$user, , , $page] = makeBuilderPage();
    $doc = BuilderDocument::blank()->toArray();
    $doc['root']['children'][] = ['id' => 'b_snapnode1', 'type' => 'heading', 'props' => ['text' => 'Snapshot']];
    $page->update(['draft' => $doc]);
    $this->actingAs($user)->post(route('shop.sales-page.publish', ['slug' => 'main-page']));

    // Mutate the draft, then restore the published revision.
    $page->refresh()->update(['draft' => BuilderDocument::blank()->toArray()]);
    $rev = $page->revisions()->first();

    $this->actingAs($user)
        ->post(route('shop.sales-page.restore', ['slug' => 'main-page', 'revision' => $rev->id]))
        ->assertRedirect(route('shop.sales-page.edit', ['slug' => 'main-page']));

    expect($page->fresh()->draft['root']['children'][0]['id'])->toBe('b_snapnode1');
});

it('caps document depth + node count when sanitising', function () {
    [$user, , , $page] = makeBuilderPage();

    // Deeply nested beyond MAX_DEPTH — should be pruned, never rejected.
    $node = ['id' => 'b_deep00000', 'type' => 'section', 'children' => []];
    $cursor = &$node;
    for ($i = 0; $i < 20; $i++) {
        $cursor['children'] = [['id' => 'b_deep'.str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'type' => 'section', 'children' => []]];
        $cursor = &$cursor['children'][0];
    }
    $doc = ['schema' => 2, 'root' => ['id' => 'root', 'type' => 'page', 'children' => [$node]], 'globals' => []];

    $this->actingAs($user)
        ->patchJson(route('shop.sales-page.document', ['slug' => 'main-page']), ['document' => $doc])
        ->assertOk();

    // Depth-capped: nesting stops well short of 20 levels.
    $depth = 0;
    $n = $page->fresh()->draft['root']['children'][0] ?? null;
    while ($n) {
        $depth++;
        $n = $n['children'][0] ?? null;
    }
    expect($depth)->toBeLessThanOrEqual(8);
});
