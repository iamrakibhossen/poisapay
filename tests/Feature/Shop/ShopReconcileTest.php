<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Actions\Order\PlaceOrder;
use App\Shop\Actions\Order\RefundOrder;
use App\Shop\DTOs\CheckoutData;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Order;
use App\Shop\Models\Product;
use App\Shop\Models\Seller;
use App\Shop\Services\ShopReconciler;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');

    $this->buyer = User::factory()->create();
    creditUser($this->buyer, $this->asset, '100000000');

    $this->seller = Seller::create([
        'user_id' => User::factory()->create()->id, 'status' => SellerStatus::Approved,
        'categories' => [], 'commission_bps' => 1000,
    ]);
    $this->product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital,
        'name' => 'Kit', 'slug' => 'kit', 'status' => ProductStatus::Published,
        'price_amount' => 10_000000, 'price_asset_id' => $this->asset->id,
    ]);
});

$buy = function (string $key = 'rec-1') {
    return app(PlaceOrder::class)->execute(test()->buyer, CheckoutData::fromArray([
        'product_id' => test()->product->id, 'quantity' => 1, 'idempotency_key' => $key,
    ]));
};

it('reports clean when orders and ledger agree', function () use ($buy) {
    $order = $buy();
    app(RefundOrder::class)->execute($order, 4_000000, null, '', 'rec-partial');

    $report = app(ShopReconciler::class)->run();

    expect($report['issues'])->toBe([])
        ->and($report['stats']['orders'])->toBe(1);
});

it('flags a GMV mismatch when an order column is tampered', function () use ($buy) {
    $order = $buy();
    // Corrupt the denormalized column without touching the ledger.
    Order::whereKey($order->id)->update(['total_amount' => 999_000000]);

    $codes = collect(app(ShopReconciler::class)->run()['issues'])->pluck('code');

    expect($codes)->toContain('gmv_mismatch');
});

it('flags a captured order with no ledger entry', function () use ($buy) {
    $order = $buy();
    Order::whereKey($order->id)->update(['ledger_entry_id' => null]);

    $codes = collect(app(ShopReconciler::class)->run()['issues'])->pluck('code');

    expect($codes)->toContain('missing_ledger_entry');
});

it('flags a refund-amount mismatch', function () use ($buy) {
    $order = $buy();
    app(RefundOrder::class)->execute($order, 4_000000, null, '', 'rec-r');
    Order::whereKey($order->id)->update(['refunded_amount' => 123]);

    $codes = collect(app(ShopReconciler::class)->run()['issues'])->pluck('code');

    expect($codes)->toContain('refund_mismatch');
});

it('exits non-zero from the command on a critical discrepancy', function () use ($buy) {
    $order = $buy();
    Order::whereKey($order->id)->update(['total_amount' => 1]);

    expect(Artisan::call('shop:reconcile'))->toBe(1);
});
