<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\CustodyXpub;
use App\Models\Faq;
use App\Models\Page;
use App\Models\RpcEndpoint;
use App\Models\TradingPair;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Roles/permissions live on the admin guard; the registry provides chains + assets.
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);

    $this->admin = Admin::create([
        'name' => 'Op', 'email' => 'crud@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $this->admin->syncRoles(['super-admin']);

    $this->chain = Chain::first();
    $this->asset = Asset::where('is_active', true)->orderBy('id')->first();
    $this->asset2 = Asset::where('is_active', true)->where('id', '!=', $this->asset->id)->orderBy('id')->first();
});

/*
|--------------------------------------------------------------------------
| Pages load (controller + Blade, not Livewire)
|--------------------------------------------------------------------------
*/

it('loads each converted admin page for an operator', function () {
    foreach (['rpc-endpoints', 'custody', 'exchange', 'faqs', 'pages'] as $route) {
        actingAs($this->admin, 'admin')->get(route("admin.{$route}"))->assertOk();
    }
});

/*
|--------------------------------------------------------------------------
| RPC endpoints
|--------------------------------------------------------------------------
*/

it('creates, updates, toggles and deletes an RPC endpoint', function () {
    // Create
    actingAs($this->admin, 'admin')->post(route('admin.rpc-endpoints.save'), [
        'chain_id' => $this->chain->id, 'name' => 'Ankr primary', 'url' => 'https://rpc.example.com',
        'priority' => 1, 'weight' => 100, 'is_active' => '1',
    ])->assertRedirect(route('admin.rpc-endpoints'))->assertSessionHas('success');

    $e = RpcEndpoint::where('name', 'Ankr primary')->firstOrFail();
    expect($e->url)->toBe('https://rpc.example.com');

    // Update via hidden id
    actingAs($this->admin, 'admin')->post(route('admin.rpc-endpoints.save'), [
        'id' => $e->id, 'chain_id' => $this->chain->id, 'name' => 'Ankr renamed', 'url' => 'https://rpc2.example.com',
        'priority' => 2, 'weight' => 50, 'is_active' => '1',
    ])->assertRedirect(route('admin.rpc-endpoints'));

    expect($e->fresh()->name)->toBe('Ankr renamed');

    // Toggle
    $before = $e->fresh()->is_active;
    actingAs($this->admin, 'admin')->post(route('admin.rpc-endpoints.toggle', $e->id))->assertRedirect();
    expect($e->fresh()->is_active)->not->toBe($before);

    // Delete
    actingAs($this->admin, 'admin')->delete(route('admin.rpc-endpoints.delete', $e->id))->assertRedirect();
    expect(RpcEndpoint::find($e->id))->toBeNull();
});

it('rejects an invalid RPC endpoint payload', function () {
    actingAs($this->admin, 'admin')->post(route('admin.rpc-endpoints.save'), [
        'chain_id' => $this->chain->id, 'name' => '', 'url' => 'not-a-url', 'priority' => 1, 'weight' => 100,
    ])->assertSessionHasErrors(['name', 'url']);
});

/*
|--------------------------------------------------------------------------
| Custody (xpub — PUBLIC keys only)
|--------------------------------------------------------------------------
*/

it('creates, updates, toggles and deletes a custody xpub', function () {
    $xpub = 'xpub6'.str_repeat('A', 100);

    actingAs($this->admin, 'admin')->post(route('admin.custody.save'), [
        'chain_id' => $this->chain->id, 'label' => 'Hot vault', 'xpub' => $xpub,
        'derivation_path' => "m/44'/60'/0'/0", 'purpose' => 'deposit', 'is_active' => '1',
    ])->assertRedirect(route('admin.custody'))->assertSessionHas('success');

    $x = CustodyXpub::where('label', 'Hot vault')->firstOrFail();
    expect($x->getAttributes()['xpub'])->toBe($xpub);

    // Update
    actingAs($this->admin, 'admin')->post(route('admin.custody.save'), [
        'id' => $x->id, 'chain_id' => $this->chain->id, 'label' => 'Cold vault', 'xpub' => $xpub,
        'derivation_path' => "m/44'/60'/0'/0", 'purpose' => 'cold-watch', 'is_active' => '1',
    ])->assertRedirect(route('admin.custody'));
    expect($x->fresh()->label)->toBe('Cold vault');

    // Toggle
    $before = $x->fresh()->is_active;
    actingAs($this->admin, 'admin')->post(route('admin.custody.toggle', $x->id))->assertRedirect();
    expect($x->fresh()->is_active)->not->toBe($before);

    // Delete
    actingAs($this->admin, 'admin')->delete(route('admin.custody.delete', $x->id))->assertRedirect();
    expect(CustodyXpub::find($x->id))->toBeNull();
});

it('REJECTS a private key on the custody form (security)', function () {
    // An xprv extended PRIVATE key must never be accepted or stored.
    actingAs($this->admin, 'admin')->post(route('admin.custody.save'), [
        'chain_id' => $this->chain->id, 'label' => 'Danger', 'xpub' => 'xprv9s'.str_repeat('B', 100),
        'derivation_path' => "m/44'/60'/0'/0", 'purpose' => 'deposit', 'is_active' => '1',
    ])->assertSessionHasErrors(['xpub']);

    expect(CustodyXpub::where('label', 'Danger')->exists())->toBeFalse();

    // A key literally containing "priv" is also rejected.
    actingAs($this->admin, 'admin')->post(route('admin.custody.save'), [
        'chain_id' => $this->chain->id, 'label' => 'Danger2', 'xpub' => 'xpubprivkey'.str_repeat('C', 90),
        'derivation_path' => "m/44'/60'/0'/0", 'purpose' => 'deposit', 'is_active' => '1',
    ])->assertSessionHasErrors(['xpub']);

    expect(CustodyXpub::where('label', 'Danger2')->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Exchange (trading pairs / rate config)
|--------------------------------------------------------------------------
*/

it('creates, updates, toggles and deletes a trading pair', function () {
    actingAs($this->admin, 'admin')->post(route('admin.exchange.save'), [
        'fromAssetId' => $this->asset->id, 'toAssetId' => $this->asset2->id, 'spreadBps' => 75,
        'minAmount' => '1.00', 'maxAmount' => '1000.00', 'sort' => 0, 'is_active' => '1',
    ])->assertRedirect(route('admin.exchange'))->assertSessionHas('success');

    $pair = TradingPair::where('from_asset_id', $this->asset->id)->where('to_asset_id', $this->asset2->id)->firstOrFail();
    expect($pair->spread_bps)->toBe(75);

    // Update
    actingAs($this->admin, 'admin')->post(route('admin.exchange.save'), [
        'id' => $pair->id, 'fromAssetId' => $this->asset->id, 'toAssetId' => $this->asset2->id, 'spreadBps' => 120,
        'minAmount' => '2.00', 'maxAmount' => '', 'sort' => 3, 'is_active' => '1',
    ])->assertRedirect(route('admin.exchange'));
    expect($pair->fresh()->spread_bps)->toBe(120)
        ->and($pair->fresh()->max_amount)->toBeNull();

    // Toggle
    $before = $pair->fresh()->is_active;
    actingAs($this->admin, 'admin')->post(route('admin.exchange.toggle', $pair->id))->assertRedirect();
    expect($pair->fresh()->is_active)->not->toBe($before);

    // Delete
    actingAs($this->admin, 'admin')->delete(route('admin.exchange.delete', $pair->id))->assertRedirect();
    expect(TradingPair::find($pair->id))->toBeNull();
});

it('rejects a same-asset trading pair', function () {
    actingAs($this->admin, 'admin')->post(route('admin.exchange.save'), [
        'fromAssetId' => $this->asset->id, 'toAssetId' => $this->asset->id, 'minAmount' => '1.00',
    ])->assertSessionHasErrors(['toAssetId']);
});

/*
|--------------------------------------------------------------------------
| FAQs
|--------------------------------------------------------------------------
*/

it('creates, updates and deletes a FAQ', function () {
    actingAs($this->admin, 'admin')->post(route('admin.faqs.save'), [
        'question' => 'How do I deposit?', 'answer' => 'Use the deposit page.', 'group' => 'Deposits',
        'sort_order' => 0, 'status' => 'published', 'show_on_homepage' => '1',
    ])->assertRedirect(route('admin.faqs'))->assertSessionHas('success');

    $faq = Faq::where('question', 'How do I deposit?')->firstOrFail();
    expect($faq->status)->toBe('published');

    actingAs($this->admin, 'admin')->post(route('admin.faqs.save'), [
        'id' => $faq->id, 'question' => 'How do I withdraw?', 'answer' => 'Use withdraw.',
        'sort_order' => 5, 'status' => 'draft',
    ])->assertRedirect(route('admin.faqs'));
    expect($faq->fresh()->question)->toBe('How do I withdraw?')
        ->and($faq->fresh()->show_on_homepage)->toBeFalse(); // unchecked -> false

    actingAs($this->admin, 'admin')->delete(route('admin.faqs.delete', $faq->id))->assertRedirect();
    expect(Faq::find($faq->id))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Pages (CMS)
|--------------------------------------------------------------------------
*/

it('creates, updates and deletes a CMS page', function () {
    actingAs($this->admin, 'admin')->post(route('admin.pages.save'), [
        'title' => 'About us', 'slug' => 'about-us', 'status' => 'published',
        'meta_description' => 'About PoisaPay', 'content' => '<h2>Hi</h2>',
    ])->assertRedirect(route('admin.pages'))->assertSessionHas('success');

    $page = Page::where('slug', 'about-us')->firstOrFail();

    actingAs($this->admin, 'admin')->post(route('admin.pages.save'), [
        'id' => $page->id, 'title' => 'About PoisaPay', 'slug' => 'about-us', 'status' => 'draft',
    ])->assertRedirect(route('admin.pages'));
    expect($page->fresh()->title)->toBe('About PoisaPay')
        ->and($page->fresh()->status)->toBe('draft');

    actingAs($this->admin, 'admin')->delete(route('admin.pages.delete', $page->id))->assertRedirect();
    expect(Page::find($page->id))->toBeNull();
});

it('auto-derives a page slug from the title when blank', function () {
    actingAs($this->admin, 'admin')->post(route('admin.pages.save'), [
        'title' => 'Terms & Conditions', 'status' => 'published',
    ])->assertRedirect(route('admin.pages'));

    expect(Page::where('slug', 'terms-conditions')->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Permission gates
|--------------------------------------------------------------------------
*/

it('forbids an operator without the required permission', function () {
    $plain = Admin::create(['name' => 'Plain', 'email' => 'plain-crud@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);

    foreach (['rpc-endpoints', 'custody', 'exchange', 'faqs', 'pages'] as $route) {
        actingAs($plain, 'admin')->get(route("admin.{$route}"))->assertForbidden();
    }
});
