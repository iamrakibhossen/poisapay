<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Models\User;
use App\Sell\Actions\Order\PlaceOrder;
use App\Sell\DTOs\CheckoutData;
use App\Sell\Enums\OrderItemKind;
use App\Sell\Enums\ProductStatus;
use App\Sell\Enums\ProductType;
use App\Sell\Enums\SalesPageStatus;
use App\Sell\Enums\SellerStatus;
use App\Sell\Models\Product;
use App\Sell\Models\SalesPage;
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

    $this->product = mkProduct($this->seller, $this->asset, 'Main', 10_000000);   // 10 USDT
    $this->bumpP = mkProduct($this->seller, $this->asset, 'Bump', 5_000000);       // 5 USDT
    $this->upsellP = mkProduct($this->seller, $this->asset, 'Upsell', 20_000000);  // 20 USDT

    $this->page = SalesPage::create([
        'seller_id' => $this->seller->id, 'product_id' => $this->product->id,
        'name' => 'Main', 'slug' => 'main-page', 'status' => SalesPageStatus::Published,
        'sections' => [], 'theme' => [], 'version' => 1, 'published_at' => now(),
        'bump_product_id' => $this->bumpP->id, 'bump_price_amount' => 3_000000, // bump at $3
        'upsell_product_id' => $this->upsellP->id, 'upsell_price_amount' => 12_000000, // upsell at $12
    ]);
});

function mkProduct(Seller $seller, $asset, string $name, int $price): Product
{
    return Product::create([
        'seller_id' => $seller->id, 'type' => ProductType::Digital, 'name' => $name,
        'slug' => strtolower($name).'-'.uniqid(), 'status' => ProductStatus::Published,
        'price_amount' => $price, 'price_asset_id' => $asset->id,
    ]);
}

$co = fn (array $extra = []) => CheckoutData::fromArray(array_merge([
    'product_id' => test()->product->id, 'quantity' => 1, 'sales_page_id' => test()->page->id,
    'idempotency_key' => 'k-'.uniqid(),
], $extra));

it('adds the order bump as a second line and charges the combined total', function () use ($co) {
    $order = app(PlaceOrder::class)->execute($this->buyer, $co(['bump' => true]));

    expect($order->items()->count())->toBe(2)
        ->and((int) $order->total_amount)->toBe(13_000000)          // 10 + 3
        ->and((int) $order->commission_amount)->toBe(1_300000)      // 10% of 13
        ->and((int) $order->seller_net_amount)->toBe(11_700000)
        ->and((int) $order->items()->where('kind', OrderItemKind::OrderBump->value)->value('line_total_amount'))->toBe(3_000000)
        ->and($this->ledger->availableBalance($this->buyer, $this->asset->id)->baseString())->toBe('87000000')  // 100 - 13
        ->and($this->ledger->availableBalance($this->sellerUser, $this->asset->id)->baseString())->toBe('11700000');
});

it('places a single line when the bump is declined', function () use ($co) {
    $order = app(PlaceOrder::class)->execute($this->buyer, $co(['bump' => false]));

    expect($order->items()->count())->toBe(1)->and((int) $order->total_amount)->toBe(10_000000);
});

it('ignores a bump in a different currency', function () use ($co) {
    $other = testAsset('USDC', 6, 'tron');
    $this->bumpP->update(['price_asset_id' => $other->id]);

    $order = app(PlaceOrder::class)->execute($this->buyer, $co(['bump' => true]));

    expect($order->items()->count())->toBe(1)->and((int) $order->total_amount)->toBe(10_000000);
});

it('grants digital delivery for both the main and bump lines', function () use ($co) {
    App\Sell\Models\ProductFile::create(['product_id' => $this->product->id, 'disk' => 'local', 'path' => 'm.zip', 'original_name' => 'm.zip', 'is_current' => true]);
    App\Sell\Models\ProductFile::create(['product_id' => $this->bumpP->id, 'disk' => 'local', 'path' => 'b.zip', 'original_name' => 'b.zip', 'is_current' => true]);

    $order = app(PlaceOrder::class)->execute($this->buyer, $co(['bump' => true]));

    expect(App\Sell\Models\Download::where('buyer_user_id', $this->buyer->id)->count())->toBe(2);
});

it('places a 1-click upsell as a new order at the override price, linked to the parent', function () use ($co) {
    $parent = app(PlaceOrder::class)->execute($this->buyer, $co());

    $upsell = app(PlaceOrder::class)->execute($this->buyer, CheckoutData::fromArray([
        'product_id' => $this->upsellP->id, 'quantity' => 1, 'sales_page_id' => $this->page->id,
        'idempotency_key' => 'up-1', 'override_amount' => 12_000000,
        'kind' => OrderItemKind::Upsell, 'parent_order_id' => $parent->id,
    ]));

    expect((int) $upsell->total_amount)->toBe(12_000000)              // override, not 20
        ->and($upsell->parent_order_id)->toBe($parent->id)
        ->and($upsell->items()->first()->kind)->toBe(OrderItemKind::Upsell)
        // 100 - 10 (parent) - 12 (upsell) = 78
        ->and($this->ledger->availableBalance($this->buyer, $this->asset->id)->baseString())->toBe('78000000');
});
