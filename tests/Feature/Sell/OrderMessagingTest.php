<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Sell\Actions\Order\PlaceOrder;
use App\Sell\Actions\Order\SendMessage;
use App\Sell\DTOs\CheckoutData;
use App\Sell\Enums\ProductStatus;
use App\Sell\Enums\ProductType;
use App\Sell\Enums\SellerStatus;
use App\Sell\Models\Product;
use App\Sell\Models\Seller;

beforeEach(function () {
    updateSetting('sell_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');

    $this->buyer = User::factory()->create(['name' => 'Aisha Karim']);
    creditUser($this->buyer, $this->asset, '100000000');

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
    $this->order = app(PlaceOrder::class)->execute($this->buyer, CheckoutData::fromArray([
        'product_id' => $this->product->id, 'quantity' => 1, 'idempotency_key' => 'msg-1',
    ]));
});

it('flags the counterparty unread and audits each message', function () {
    app(SendMessage::class)->execute($this->order, 'buyer', $this->buyer->id, 'When does it ship?');
    $order = $this->order->fresh();

    expect($order->seller_unread)->toBeTrue()
        ->and($order->buyer_unread)->toBeFalse()
        ->and($order->last_message_at)->not->toBeNull()
        ->and($order->messages()->count())->toBe(1)
        ->and(AuditLog::where('action', 'sell.message.sent')->exists())->toBeTrue();

    app(SendMessage::class)->execute($order, 'seller', $this->sellerUser->id, 'Right away!');
    $order = $order->fresh();
    expect($order->buyer_unread)->toBeTrue()->and($order->seller_unread)->toBeFalse();
});

it('lets the buyer send and see the conversation, clearing their unread', function () {
    app(SendMessage::class)->execute($this->order, 'seller', $this->sellerUser->id, 'Thanks for buying!');
    expect($this->order->fresh()->buyer_unread)->toBeTrue();

    $this->actingAs($this->buyer)
        ->post(route('purchases.messages.send', ['order' => $this->order->id]), ['body' => 'Cheers!'])
        ->assertRedirect(route('purchases.messages', ['order' => $this->order->id]));

    $this->actingAs($this->buyer)->get(route('purchases.messages', ['order' => $this->order->id]))
        ->assertOk()->assertSee('Thanks for buying!')->assertSee('Cheers!');

    expect($this->order->fresh()->buyer_unread)->toBeFalse(); // cleared on view
});

it('lets the seller reply from the order page and shows the thread', function () {
    app(SendMessage::class)->execute($this->order, 'buyer', $this->buyer->id, 'Any docs?');

    $this->actingAs($this->sellerUser)
        ->post(route('sell.order.message', ['id' => $this->order->id]), ['body' => 'Yes, in the zip.'])
        ->assertRedirect(route('sell.order', ['id' => $this->order->id]));

    $this->actingAs($this->sellerUser)->get(route('sell.order', ['id' => $this->order->id]))
        ->assertOk()->assertSee('Any docs?')->assertSee('Yes, in the zip.');
});

it('shows the order in the seller inbox once it has messages', function () {
    app(SendMessage::class)->execute($this->order, 'buyer', $this->buyer->id, 'Hello there');

    $this->actingAs($this->sellerUser)->get(route('sell.inbox'))
        ->assertOk()->assertSee('Aisha Karim')->assertSee('Hello there');
});

it('forbids messaging an order you are not part of', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post(route('purchases.messages.send', ['order' => $this->order->id]), ['body' => 'hi'])
        ->assertRedirect(route('purchases'));

    expect($this->order->fresh()->messages()->count())->toBe(0);
});
