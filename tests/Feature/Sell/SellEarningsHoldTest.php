<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Models\User;
use App\Shop\Actions\Order\PlaceOrder;
use App\Shop\DTOs\CheckoutData;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Product;
use App\Shop\Models\Seller;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    $this->ledger = app(LedgerService::class);
    $this->asset = testAsset('USDT', 6, 'tron');

    $sellerUser = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $sellerUser->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Acme', 'categories' => [], 'settlement_asset_id' => $this->asset->id,
    ]);
    $this->sellerUser = $sellerUser;
    $this->product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital, 'name' => 'Toolkit',
        'slug' => 'toolkit', 'status' => ProductStatus::Published, 'price_amount' => 50_000000, 'price_asset_id' => $this->asset->id,
    ]);

    $this->buyer = User::factory()->create();
    creditUser($this->buyer, $this->asset, '100000000'); // 100 USDT

    $this->buy = fn (string $key = 'hold-1') => app(PlaceOrder::class)->execute($this->buyer, CheckoutData::fromArray([
        'product_id' => $this->product->id, 'quantity' => 1, 'idempotency_key' => $key,
    ]));
});

it('holds seller earnings in the locked balance when the flag is on', function () {
    updateSetting('shop_earnings_hold', true);

    $order = ($this->buy)();
    $net = (int) $order->seller_net_amount;

    expect($order->earnings_held)->toBeTrue()
        ->and($order->refund_window_ends_at)->not->toBeNull()
        ->and($this->ledger->lockedBalance($this->sellerUser, $this->asset->id)->baseString())->toBe((string) $net)
        ->and($this->ledger->availableBalance($this->sellerUser, $this->asset->id)->baseString())->toBe('0');
});

it('does not release earnings while still inside the refund window', function () {
    updateSetting('shop_earnings_hold', true);
    $order = ($this->buy)();

    $this->artisan('poisapay:shop-release-earnings')->assertSuccessful();

    expect($order->fresh()->earnings_released_at)->toBeNull()
        ->and($this->ledger->availableBalance($this->sellerUser, $this->asset->id)->baseString())->toBe('0')
        ->and($this->ledger->lockedBalance($this->sellerUser, $this->asset->id)->isPositive())->toBeTrue();
});

it('releases held earnings to spendable once the refund window passes', function () {
    updateSetting('shop_earnings_hold', true);
    $order = ($this->buy)();
    $net = (int) $order->seller_net_amount;

    $this->travel(15)->days();
    $this->artisan('poisapay:shop-release-earnings')->assertSuccessful();

    expect($order->fresh()->earnings_released_at)->not->toBeNull()
        ->and($this->ledger->lockedBalance($this->sellerUser, $this->asset->id)->baseString())->toBe('0')
        ->and($this->ledger->availableBalance($this->sellerUser, $this->asset->id)->baseString())->toBe((string) $net);

    // Idempotent — a second run must not double-release.
    $this->artisan('poisapay:shop-release-earnings')->assertSuccessful();
    expect($this->ledger->availableBalance($this->sellerUser, $this->asset->id)->baseString())->toBe((string) $net);
});

it('credits earnings instantly to spendable when the flag is off', function () {
    // Default (flag off): unchanged legacy behaviour.
    $order = ($this->buy)();
    $net = (int) $order->seller_net_amount;

    expect($order->earnings_held)->toBeFalse()
        ->and($this->ledger->availableBalance($this->sellerUser, $this->asset->id)->baseString())->toBe((string) $net)
        ->and($this->ledger->lockedBalance($this->sellerUser, $this->asset->id)->baseString())->toBe('0');

    $this->artisan('poisapay:shop-release-earnings')->assertSuccessful(); // no-op
});
