<?php

declare(strict_types=1);

use App\Domain\Revenue\RevenueService;
use App\Models\User;
use App\Shop\Actions\Order\PlaceOrder;
use App\Shop\Actions\Order\RefundOrder;
use App\Shop\DTOs\CheckoutData;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Product;
use App\Shop\Models\Seller;
use App\Shop\Services\ShopRevenueService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->revenue = app(ShopRevenueService::class);

    $this->buyer = User::factory()->create();
    creditUser($this->buyer, $this->asset, '100000000'); // 100 USDT

    $this->sellerUser = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->sellerUser->id, 'status' => SellerStatus::Approved,
        'categories' => [], 'commission_bps' => 1000, // 10%
    ]);

    $this->product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital,
        'name' => 'LaunchKit', 'slug' => 'launchkit', 'status' => ProductStatus::Published,
        'price_amount' => 10_000000, 'price_asset_id' => $this->asset->id, // 10 USDT
    ]);
});

$buy = function (string $key = 'rev-1') {
    return app(PlaceOrder::class)->execute(test()->buyer, CheckoutData::fromArray([
        'product_id' => test()->product->id, 'quantity' => 1, 'idempotency_key' => $key,
    ]));
};

it('derives GMV, commission and merchant earnings from the ledger', function () use ($buy) {
    $buy();

    expect($this->revenue->gmv($this->asset)->baseString())->toBe('10000000')        // 10 USDT paid
        ->and($this->revenue->commission($this->asset)->baseString())->toBe('1000000') // 10% cut
        ->and($this->revenue->merchantEarnings($this->asset)->baseString())->toBe('9000000')
        ->and($this->revenue->netSales($this->asset)->baseString())->toBe('10000000')
        ->and($this->revenue->refunded($this->asset)->baseString())->toBe('0');
});

it('nets refunds out of every figure', function () use ($buy) {
    $order = $buy();
    app(RefundOrder::class)->full($order);

    expect($this->revenue->gmv($this->asset)->baseString())->toBe('10000000')          // gross paid is unchanged
        ->and($this->revenue->refunded($this->asset)->baseString())->toBe('10000000')
        ->and($this->revenue->netSales($this->asset)->baseString())->toBe('0')
        ->and($this->revenue->commission($this->asset)->baseString())->toBe('0')        // clawed back
        ->and($this->revenue->merchantEarnings($this->asset)->baseString())->toBe('0');
});

it('sums GMV across multiple orders', function () use ($buy) {
    $buy('rev-a');
    $buy('rev-b');

    expect($this->revenue->gmv($this->asset)->baseString())->toBe('20000000')
        ->and($this->revenue->commission($this->asset)->baseString())->toBe('2000000');
});

it('surfaces shop commission in the platform revenue wallet', function () use ($buy) {
    $order = $buy();

    // The admin Revenue Wallet now counts shop:commission_income as platform revenue.
    expect(app(RevenueService::class)->balance($this->asset)->baseString())->toBe('1000000');

    app(RefundOrder::class)->full($order);

    expect(app(RevenueService::class)->balance($this->asset)->baseString())->toBe('0');
});

it('respects the date window', function () use ($buy) {
    $buy();

    $future = CarbonImmutable::now()->addDay();
    expect($this->revenue->gmv($this->asset, $future)->baseString())->toBe('0')
        ->and($this->revenue->gmv($this->asset, null, $future)->baseString())->toBe('10000000');
});
