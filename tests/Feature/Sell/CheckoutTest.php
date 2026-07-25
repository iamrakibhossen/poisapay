<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Models\AuditLog;
use App\Models\User;
use App\Shop\Actions\Order\PlaceOrder;
use App\Shop\DTOs\CheckoutData;
use App\Shop\Enums\OrderStatus;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SellerStatus;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\Download;
use App\Shop\Models\Order;
use App\Shop\Models\Product;
use App\Shop\Models\ProductFile;
use App\Shop\Models\Seller;

beforeEach(function () {
    updateSetting('sell_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);

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
    ProductFile::create([
        'product_id' => $this->product->id, 'disk' => 'local', 'path' => 'launchkit.zip',
        'original_name' => 'launchkit.zip', 'is_current' => true,
    ]);
});

$checkout = fn (array $extra = []) => CheckoutData::fromArray(array_merge([
    'product_id' => test()->product->id, 'quantity' => 1, 'idempotency_key' => 'order-key-1',
], $extra));

it('moves money through the ledger and marks the order paid', function () use ($checkout) {
    $order = app(PlaceOrder::class)->execute($this->buyer, $checkout());

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->ledger_entry_id)->not->toBeNull()
        ->and((int) $order->commission_amount)->toBe(1_000000)   // 10% of 10 USDT
        ->and((int) $order->seller_net_amount)->toBe(9_000000)
        // A balanced entry is enforced by the Ledger; verify both sides moved.
        ->and($this->ledger->availableBalance($this->buyer, $this->asset->id)->baseString())->toBe('90000000')
        ->and($this->ledger->availableBalance($this->sellerUser, $this->asset->id)->baseString())->toBe('9000000')
        ->and(AuditLog::where('action', 'shop.order.placed')->exists())->toBeTrue();
});

it('is idempotent — a replay returns the same order with no second charge', function () use ($checkout) {
    $first = app(PlaceOrder::class)->execute($this->buyer, $checkout());
    $again = app(PlaceOrder::class)->execute($this->buyer, $checkout()); // same key

    expect($again->id)->toBe($first->id)
        ->and(Order::count())->toBe(1)
        ->and($this->ledger->availableBalance($this->buyer, $this->asset->id)->baseString())->toBe('90000000');
});

it('grants a signed, count-limited download for a digital product', function () use ($checkout) {
    $order = app(PlaceOrder::class)->execute($this->buyer, $checkout());

    $grant = Download::where('buyer_user_id', $this->buyer->id)->first();
    expect($grant)->not->toBeNull()
        ->and($grant->isUsable())->toBeTrue()
        ->and(strlen($grant->token))->toBeGreaterThan(20);
});

it('declines when the buyer wallet balance is insufficient', function () {
    $poor = User::factory()->create();
    creditUser($poor, $this->asset, '1000000'); // 1 USDT

    expect(fn () => app(PlaceOrder::class)->execute($poor, CheckoutData::fromArray([
        'product_id' => $this->product->id, 'idempotency_key' => 'poor-1',
    ])))->toThrow(ShopException::class);

    expect(Order::count())->toBe(0); // nothing partially written
});

it('refuses buying your own product', function () {
    expect(fn () => app(PlaceOrder::class)->execute($this->sellerUser, CheckoutData::fromArray([
        'product_id' => $this->product->id, 'idempotency_key' => 'own-1',
    ])))->toThrow(ShopException::class);
});

it('refuses an unpublished product', function () {
    $this->product->update(['status' => ProductStatus::Draft]);

    expect(fn () => app(PlaceOrder::class)->execute($this->buyer, CheckoutData::fromArray([
        'product_id' => $this->product->id, 'idempotency_key' => 'draft-1',
    ])))->toThrow(ShopException::class);
});

it('checks out over the REST endpoint and returns a paid order', function () {
    $this->actingAs($this->buyer)
        ->postJson('/api/shop/checkout', [
            'product_id' => $this->product->id, 'quantity' => 1, 'idempotency_key' => 'http-1',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.amounts.total', 10_000000);
});

it('renders a domain guard as 422 (buying your own product)', function () {
    $this->actingAs($this->sellerUser)
        ->postJson('/api/shop/checkout', [
            'product_id' => $this->product->id, 'idempotency_key' => 'http-own',
        ])
        ->assertStatus(422);
});
