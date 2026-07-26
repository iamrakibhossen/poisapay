<?php

declare(strict_types=1);

use App\Models\NotificationPreference;
use App\Models\User;
use App\Shop\Enums\RefundRequestStatus;
use App\Shop\Enums\SellerStatus;
use App\Shop\Events\OrderPlaced;
use App\Shop\Events\RefundApproved;
use App\Shop\Events\RefundRequested;
use App\Shop\Events\SellerStatusChanged;
use App\Shop\Models\Order;
use App\Shop\Models\RefundRequest;
use App\Shop\Models\Seller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    Artisan::call('db:seed', ['--class' => 'ShopNotificationTemplateSeeder', '--force' => true]);

    $this->asset = testAsset('USDT', 6, 'tron');
    $this->sellerUser = User::factory()->create();
    $this->seller = Seller::create(['user_id' => $this->sellerUser->id, 'status' => SellerStatus::Approved, 'categories' => []]);
    $this->buyer = User::factory()->create();
});

function shopOrder(): Order
{
    return Order::create([
        'number' => 'ORD-'.Str::random(6),
        'idempotency_key' => 'idem-'.Str::random(8),
        'seller_id' => test()->seller->id,
        'buyer_user_id' => test()->buyer->id,
        'asset_id' => test()->asset->id,
        'total_amount' => 50000,
        'seller_net_amount' => 45000,
        'commission_amount' => 5000,
    ]);
}

it('notifies both the buyer and the seller when an order is placed', function () {
    OrderPlaced::dispatch(shopOrder());

    expect($this->buyer->notifications()->count())->toBe(1)
        ->and($this->sellerUser->notifications()->count())->toBe(1);

    $sellerNote = $this->sellerUser->notifications()->first();
    expect($sellerNote->data['title'])->toBe('New sale')
        ->and($sellerNote->data['category'])->toBe('money');
});

it('respects a per-category notification opt-out', function () {
    // Buyer opts out of the "money" category entirely.
    NotificationPreference::create(['user_id' => $this->buyer->id, 'category' => 'money', 'in_app' => false, 'email' => false]);

    OrderPlaced::dispatch(shopOrder());

    // Buyer suppressed; seller (no opt-out) still notified.
    expect($this->buyer->notifications()->count())->toBe(0)
        ->and($this->sellerUser->notifications()->count())->toBe(1);
});

it('notifies the seller of a refund request and the buyer of the outcome', function () {
    $order = shopOrder();
    $req = RefundRequest::create([
        'order_id' => $order->id, 'seller_id' => $this->seller->id, 'buyer_user_id' => $this->buyer->id,
        'type' => 'full', 'amount_requested' => 50000, 'status' => RefundRequestStatus::Requested,
    ]);

    RefundRequested::dispatch($req);
    expect($this->sellerUser->notifications()->get()->pluck('data.title'))->toContain('Refund requested');

    $req->update(['amount_refunded' => 50000, 'status' => RefundRequestStatus::Refunded]);
    RefundApproved::dispatch($req);
    expect($this->buyer->notifications()->get()->pluck('data.title'))->toContain('Refund approved');
});

it('notifies the seller when their shop is approved or suspended', function () {
    SellerStatusChanged::dispatch($this->seller, SellerStatus::PendingReview, SellerStatus::Approved, null, null);
    SellerStatusChanged::dispatch($this->seller, SellerStatus::Approved, SellerStatus::Suspended, null, 'policy');

    $titles = $this->sellerUser->notifications()->get()->pluck('data.title');
    expect($titles)->toContain('Shop approved')->toContain('Shop suspended');
});

it('is idempotent — a replayed event never duplicates notifications', function () {
    $order = shopOrder();

    OrderPlaced::dispatch($order);
    OrderPlaced::dispatch($order); // replay / retry

    expect($this->buyer->notifications()->count())->toBe(1)
        ->and($this->sellerUser->notifications()->count())->toBe(1);
});

it('renders the template with payload tokens', function () {
    OrderPlaced::dispatch(shopOrder());

    $note = $this->buyer->notifications()->first();
    expect($note->data['title'])->toBe('Order confirmed')
        ->and($note->data['body'])->toContain('is confirmed'); // {{tokens}} substituted, not literal
});
