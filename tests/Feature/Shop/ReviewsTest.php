<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Shop\Actions\Order\PlaceOrder;
use App\Shop\Actions\Review\SubmitReview;
use App\Shop\DTOs\CheckoutData;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Product;
use App\Shop\Models\Review;
use App\Shop\Models\Seller;

beforeEach(function () {
    updateSetting('shop_enabled', true);
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
        'product_id' => $this->product->id, 'quantity' => 1, 'idempotency_key' => 'rv-1',
    ]));
});

it('lets a verified buyer review, one per purchase, and audits it', function () {
    app(SubmitReview::class)->execute($this->buyer, $this->order, $this->product->id, 5, 'Great', 'Loved it');

    $review = Review::where('order_id', $this->order->id)->first();
    expect($review->rating)->toBe(5)
        ->and($review->buyer_user_id)->toBe($this->buyer->id)
        ->and(AuditLog::where('action', 'shop.review.submitted')->exists())->toBeTrue();

    // Re-submit updates the same review (unique order+product).
    app(SubmitReview::class)->execute($this->buyer, $this->order, $this->product->id, 4, 'Good', 'Still good');
    expect(Review::where('order_id', $this->order->id)->count())->toBe(1)
        ->and(Review::where('order_id', $this->order->id)->first()->rating)->toBe(4);
});

it('rejects a review from someone who did not buy', function () {
    $stranger = User::factory()->create();

    expect(fn () => app(SubmitReview::class)->execute($stranger, $this->order, $this->product->id, 5, null, 'nope'))
        ->toThrow(App\Shop\Exceptions\ShopException::class);
});

it('submits a review over HTTP and shows it in the seller reviews page', function () {
    $this->actingAs($this->buyer)
        ->post(route('purchases.review', ['order' => $this->order->id]), [
            'product_id' => $this->product->id, 'rating' => 5, 'title' => 'Superb', 'body' => 'Worth it',
        ])
        ->assertRedirect(route('purchases'));

    $this->actingAs($this->sellerUser)->get(route('shop.reviews'))
        ->assertOk()->assertSee('Aisha Karim')->assertSee('Worth it')->assertSee('5.0');
});

it('lets the seller reply to a review', function () {
    app(SubmitReview::class)->execute($this->buyer, $this->order, $this->product->id, 5, null, 'nice');
    $review = Review::where('order_id', $this->order->id)->first();

    $this->actingAs($this->sellerUser)
        ->post(route('shop.reviews.reply', ['id' => $review->id]), ['reply' => 'Thank you!'])
        ->assertRedirect(route('shop.reviews'));

    expect($review->fresh()->seller_reply)->toBe('Thank you!')
        ->and(AuditLog::where('action', 'shop.review.replied')->exists())->toBeTrue();
});

it('forbids a foreign seller from replying', function () {
    app(SubmitReview::class)->execute($this->buyer, $this->order, $this->product->id, 5, null, 'nice');
    $review = Review::where('order_id', $this->order->id)->first();

    $other = User::factory()->create();
    Seller::create(['user_id' => $other->id, 'status' => SellerStatus::Approved, 'categories' => []]);

    $this->actingAs($other)
        ->post(route('shop.reviews.reply', ['id' => $review->id]), ['reply' => 'hijack'])
        ->assertRedirect(route('shop.reviews'));

    expect($review->fresh()->seller_reply)->toBeNull();
});
