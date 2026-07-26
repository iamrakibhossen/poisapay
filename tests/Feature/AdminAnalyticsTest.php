<?php

declare(strict_types=1);

use App\Domain\Analytics\Period;
use App\Models\Admin;
use App\Models\AnalyticsDailyMetric;
use App\Models\User;
use App\Shop\Actions\Order\PlaceOrder;
use App\Shop\DTOs\CheckoutData;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Product;
use App\Shop\Models\Seller;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    $this->asset = testAsset('USDT', 6, 'tron');

    $this->admin = Admin::create([
        'name' => 'Analyst', 'email' => 'analyst@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $this->admin->syncRoles(['super-admin']);
});

$sections = ['deposits', 'withdrawals', 'exchange', 'revenue', 'pnl', 'loss', 'wallet', 'treasury', 'cards', 'shop', 'compliance'];

it('renders the executive overview', function () {
    User::factory()->count(3)->create();

    actingAs($this->admin, 'admin')->get(route('admin.analytics'))
        ->assertOk()
        ->assertSee('Executive Overview')
        ->assertSee('Total Users');
});

it('renders every analytics section', function () use ($sections) {
    foreach ($sections as $section) {
        actingAs($this->admin, 'admin')->get(route('admin.analytics.section', $section))
            ->assertOk();
    }
});

it('renders the shop section with real commission after a sale', function () {
    updateSetting('shop_enabled', true);

    $buyer = User::factory()->create();
    creditUser($buyer, $this->asset, '100000000');
    $seller = Seller::create([
        'user_id' => User::factory()->create()->id, 'status' => SellerStatus::Approved,
        'categories' => [], 'commission_bps' => 1000,
    ]);
    $product = Product::create([
        'seller_id' => $seller->id, 'type' => ProductType::Digital,
        'name' => 'Kit', 'slug' => 'kit', 'status' => ProductStatus::Published,
        'price_amount' => 10_000000, 'price_asset_id' => $this->asset->id,
    ]);
    app(PlaceOrder::class)->execute($buyer, CheckoutData::fromArray([
        'product_id' => $product->id, 'quantity' => 1, 'idempotency_key' => 'ana-1',
    ]));

    actingAs($this->admin, 'admin')->get(route('admin.analytics.section', 'shop'))
        ->assertOk()
        ->assertSee('Shop Analytics')
        ->assertSee('Commission')
        ->assertSee('GMV');
});

it('404s an unknown section and the overview alias', function () {
    actingAs($this->admin, 'admin')->get(route('admin.analytics.section', 'nope'))->assertNotFound();
    actingAs($this->admin, 'admin')->get(route('admin.analytics.section', 'overview'))->assertNotFound();
});

it('honours every date-range preset without error', function () {
    foreach (array_keys(Period::PRESETS) as $preset) {
        actingAs($this->admin, 'admin')
            ->get(route('admin.analytics', ['period' => $preset, 'from' => '2026-01-01', 'to' => '2026-07-01']))
            ->assertOk();
    }
});

it('supports comparison mode on and off', function () {
    actingAs($this->admin, 'admin')->get(route('admin.analytics', ['compare' => 1]))->assertOk()->assertSee('vs previous');
    actingAs($this->admin, 'admin')->get(route('admin.analytics', ['compare' => 0]))->assertOk();
});

it('exports a section as CSV', function () {
    $res = actingAs($this->admin, 'admin')->get(route('admin.analytics.export', 'revenue'));
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('text/csv');
    expect($res->streamedContent())->toContain('Revenue Analytics');
});

it('forbids admins without the reporting permission', function () {
    $plain = Admin::create(['name' => 'Plain', 'email' => 'plain@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);

    actingAs($plain, 'admin')->get(route('admin.analytics'))->assertForbidden();
});

it('redirects guests to the operator login', function () {
    $this->get(route('admin.analytics'))->assertRedirect(route('admin.login'));
});

it('materialises daily rollups and flushes the cache', function () {
    User::factory()->count(4)->create();

    Artisan::call('poisapay:analytics-rollup', ['--days' => 1]);

    $newUsers = AnalyticsDailyMetric::where('metric', 'new_users')->where('day', today()->toDateString())->value('value');
    expect((float) $newUsers)->toBeGreaterThanOrEqual(4.0);

    // Every rolled-up metric key should be present for today.
    $metrics = AnalyticsDailyMetric::where('day', today()->toDateString())->pluck('metric');
    expect($metrics)->toContain('revenue_usd', 'deposit_volume_usd', 'withdrawal_volume_usd', 'swap_volume_usd');
});
