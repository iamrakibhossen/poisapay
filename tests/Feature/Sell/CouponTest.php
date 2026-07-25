<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Models\User;
use App\Sell\Actions\Coupon\CreateCoupon;
use App\Sell\Actions\Order\PlaceOrder;
use App\Sell\DTOs\CheckoutData;
use App\Sell\Enums\CouponType;
use App\Sell\Enums\ProductStatus;
use App\Sell\Enums\ProductType;
use App\Sell\Enums\SellerStatus;
use App\Sell\Exceptions\SellException;
use App\Sell\Models\Coupon;
use App\Sell\Models\Order;
use App\Sell\Models\Product;
use App\Sell\Models\Seller;

beforeEach(function () {
    updateSetting('sell_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);

    $this->buyer = User::factory()->create();
    creditUser($this->buyer, $this->asset, '100000000'); // 100 USDT

    $this->sellerUser = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->sellerUser->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Rahim Studios', 'categories' => [], 'commission_bps' => 1000, // 10%
    ]);
    $this->product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital,
        'name' => 'LaunchKit', 'slug' => 'launchkit', 'status' => ProductStatus::Published,
        'price_amount' => 10_000000, 'price_asset_id' => $this->asset->id, // 10 USDT
    ]);
});

$order = fn (array $extra = []) => CheckoutData::fromArray(array_merge([
    'product_id' => test()->product->id, 'quantity' => 1, 'idempotency_key' => 'cp-'.uniqid(),
], $extra));

it('applies a percentage coupon and recomputes commission on the discounted total', function () use ($order) {
    Coupon::create([
        'seller_id' => $this->seller->id, 'code' => 'SAVE20',
        'type' => CouponType::Percent, 'value' => 2000, 'is_active' => true, // 20%
    ]);

    $o = app(PlaceOrder::class)->execute($this->buyer, $order(['coupon_code' => 'save20'])); // case-insensitive

    expect((int) $o->subtotal_amount)->toBe(10_000000)
        ->and((int) $o->discount_amount)->toBe(2_000000)
        ->and((int) $o->total_amount)->toBe(8_000000)
        ->and((int) $o->commission_amount)->toBe(800000)     // 10% of 8 USDT
        ->and((int) $o->seller_net_amount)->toBe(7_200000)
        ->and($this->ledger->availableBalance($this->buyer, $this->asset->id)->baseString())->toBe('92000000')
        ->and($this->ledger->availableBalance($this->sellerUser, $this->asset->id)->baseString())->toBe('7200000')
        ->and((int) Coupon::first()->used_count)->toBe(1);
});

it('applies a fixed-amount coupon', function () use ($order) {
    Coupon::create([
        'seller_id' => $this->seller->id, 'code' => 'MINUS3',
        'type' => CouponType::Fixed, 'value' => 3_000000, 'is_active' => true, // 3 USDT off
    ]);

    $o = app(PlaceOrder::class)->execute($this->buyer, $order(['coupon_code' => 'MINUS3']));

    expect((int) $o->discount_amount)->toBe(3_000000)->and((int) $o->total_amount)->toBe(7_000000);
});

it('rejects invalid, expired, and over-limit coupons', function () use ($order) {
    // unknown
    expect(fn () => app(PlaceOrder::class)->execute($this->buyer, $order(['coupon_code' => 'NOPE'])))
        ->toThrow(SellException::class);

    // expired
    Coupon::create(['seller_id' => $this->seller->id, 'code' => 'OLD', 'type' => CouponType::Percent, 'value' => 1000, 'is_active' => true, 'ends_at' => now()->subDay()]);
    expect(fn () => app(PlaceOrder::class)->execute($this->buyer, $order(['coupon_code' => 'OLD'])))
        ->toThrow(SellException::class);

    // usage limit reached
    Coupon::create(['seller_id' => $this->seller->id, 'code' => 'MAXED', 'type' => CouponType::Percent, 'value' => 1000, 'is_active' => true, 'usage_limit' => 1, 'used_count' => 1]);
    expect(fn () => app(PlaceOrder::class)->execute($this->buyer, $order(['coupon_code' => 'MAXED'])))
        ->toThrow(SellException::class);
});

it('enforces a per-customer limit', function () use ($order) {
    $c = Coupon::create(['seller_id' => $this->seller->id, 'code' => 'ONCE', 'type' => CouponType::Percent, 'value' => 1000, 'is_active' => true, 'per_customer_limit' => 1]);

    app(PlaceOrder::class)->execute($this->buyer, $order(['coupon_code' => 'ONCE', 'idempotency_key' => 'k1']));

    expect(fn () => app(PlaceOrder::class)->execute($this->buyer, $order(['coupon_code' => 'ONCE', 'idempotency_key' => 'k2'])))
        ->toThrow(SellException::class);
});

it('scopes a product-specific coupon to that product', function () use ($order) {
    $other = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital, 'name' => 'Other',
        'slug' => 'other', 'status' => ProductStatus::Published, 'price_amount' => 10_000000, 'price_asset_id' => $this->asset->id,
    ]);
    Coupon::create(['seller_id' => $this->seller->id, 'product_id' => $other->id, 'code' => 'OTHERONLY', 'type' => CouponType::Percent, 'value' => 5000, 'is_active' => true]);

    // Applied to the wrong product → rejected.
    expect(fn () => app(PlaceOrder::class)->execute($this->buyer, $order(['coupon_code' => 'OTHERONLY'])))
        ->toThrow(SellException::class);
});

it('lets a seller create and list coupons over HTTP', function () {
    $this->actingAs($this->sellerUser)
        ->post(route('sell.coupons.store'), ['code' => 'WELCOME', 'type' => 'percent', 'value' => 15])
        ->assertRedirect(route('sell.coupons'));

    $c = Coupon::where('code', 'WELCOME')->first();
    expect($c)->not->toBeNull()->and((int) $c->value)->toBe(1500); // 15% → 1500 bps

    $this->actingAs($this->sellerUser)->get(route('sell.coupons'))->assertOk()->assertSee('WELCOME');
});

it('shows the discount on the public pay page via ?coupon', function () {
    // Publish a sales page so the public route resolves.
    \App\Sell\Models\SalesPage::create([
        'seller_id' => $this->seller->id, 'product_id' => $this->product->id, 'name' => 'Main',
        'slug' => 'launchkit-main', 'status' => \App\Sell\Enums\SalesPageStatus::Published,
        'sections' => [], 'theme' => [], 'version' => 1, 'published_at' => now(),
    ]);
    Coupon::create(['seller_id' => $this->seller->id, 'code' => 'TAKE20', 'type' => CouponType::Percent, 'value' => 2000, 'is_active' => true]);

    $this->actingAs($this->buyer)->get(route('funnel.pay', ['slug' => 'launchkit-main', 'coupon' => 'TAKE20']))
        ->assertOk()->assertSee('TAKE20')->assertSee('8.00 USDT'); // discounted total
});
