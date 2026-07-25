<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Builder\BuilderDocument;
use App\Shop\Builder\RenderContext;
use App\Shop\Builder\Renderer;
use App\Shop\Builder\SchemaMigrator;
use App\Shop\Builder\StyleCompiler;
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
