<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Actions\Order\PlaceOrder;
use App\Shop\DTOs\CheckoutData;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Product;
use App\Shop\Models\ProductFile;
use App\Shop\Models\Seller;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');

    $this->buyer = User::factory()->create();
    creditUser($this->buyer, $this->asset, '100000000'); // 100 USDT

    $this->sellerUser = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->sellerUser->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Rahim Studios', 'categories' => [], 'commission_bps' => 1000,
    ]);
    $this->product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital,
        'name' => 'LaunchKit', 'slug' => 'launchkit', 'status' => ProductStatus::Published,
        'price_amount' => 10_000000, 'price_asset_id' => $this->asset->id,
    ]);
    ProductFile::create([
        'product_id' => $this->product->id, 'disk' => 'local', 'path' => 'sell/launchkit.zip',
        'original_name' => 'launchkit-v1.0.zip', 'is_current' => true, 'scan_status' => 'clean',
    ]);

    $this->order = app(PlaceOrder::class)->execute($this->buyer, CheckoutData::fromArray([
        'product_id' => $this->product->id, 'quantity' => 1, 'idempotency_key' => 'buy-1',
    ]));
});

it('lists the buyer real purchases', function () {
    $this->actingAs($this->buyer)->get('/purchases')
        ->assertOk()
        ->assertSee('LaunchKit')
        ->assertSee('Rahim Studios')
        ->assertSee('10.00 USDT');
});

it('streams a purchased file to its owner', function () {
    Storage::fake('local');
    Storage::disk('local')->put('sell/launchkit.zip', 'ZIPDATA');

    $item = $this->order->items->first();

    $res = $this->actingAs($this->buyer)->get(route('purchases.download', $item->id));
    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('launchkit-v1.0.zip');
});

it('forbids downloading a file the user did not buy', function () {
    Storage::fake('local');
    Storage::disk('local')->put('sell/launchkit.zip', 'ZIPDATA');

    $item = $this->order->items->first();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('purchases.download', $item->id))->assertNotFound();
});

it('shows nothing to a user with no purchases', function () {
    $this->actingAs(User::factory()->create())->get('/purchases')
        ->assertOk()
        ->assertDontSee('launchkit-v1.0.zip');
});
