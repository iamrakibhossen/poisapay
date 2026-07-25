<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Models\Admin;
use App\Models\User;
use App\Notifications\OperatorNotification;
use App\Notifications\UserNotification;
use App\Sell\Actions\Order\PlaceOrder;
use App\Sell\Actions\Refund\CancelRefundRequest;
use App\Sell\Actions\Refund\EscalateRefundRequest;
use App\Sell\Actions\Refund\RequestRefund;
use App\Sell\Actions\Refund\ResolveRefundRequest;
use App\Sell\DTOs\CheckoutData;
use App\Sell\Enums\OrderStatus;
use App\Sell\Enums\ProductStatus;
use App\Sell\Enums\ProductType;
use App\Sell\Enums\RefundRequestStatus;
use App\Sell\Enums\SellerStatus;
use App\Sell\Exceptions\SellException;
use App\Sell\Models\Product;
use App\Sell\Models\RefundRequest;
use App\Sell\Models\Seller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    updateSetting('sell_enabled', true);
    updateSetting('sell_commission_bps', 1000); // 10%
    $this->ledger = app(LedgerService::class);
    $this->asset = testAsset('USDT', 6, 'tron');

    $this->sellerUser = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->sellerUser->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Acme', 'categories' => [], 'settlement_asset_id' => $this->asset->id,
    ]);
    $this->product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital, 'name' => 'Toolkit',
        'slug' => 'toolkit', 'status' => ProductStatus::Published, 'price_amount' => 50_000000, 'price_asset_id' => $this->asset->id,
    ]);

    $this->buyer = User::factory()->create();
    creditUser($this->buyer, $this->asset, '100000000'); // 100 USDT

    $this->order = fn () => app(PlaceOrder::class)->execute($this->buyer, CheckoutData::fromArray([
        'product_id' => $this->product->id, 'quantity' => 1, 'idempotency_key' => 'rr-'.uniqid(),
    ]));

    $this->makeAdmin = function () {
        Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
        $admin = Admin::create([
            'name' => 'Op', 'email' => 'op-'.uniqid().'@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
        ]);
        $admin->syncRoles(['super-admin']);

        return $admin;
    };

    $this->avail = fn (User $u) => $this->ledger->availableBalance($u, $this->asset->id)->baseString();
});

it('lets a buyer request a full refund and the seller approve it', function () {
    Notification::fake();
    $order = ($this->order)();

    $req = app(RequestRefund::class)->execute($order, $this->buyer, 'full', null, 'not needed');
    expect($req->status)->toBe(RefundRequestStatus::Requested)
        ->and($req->amount_requested)->toBe(50_000000);
    Notification::assertSentTo($this->sellerUser, UserNotification::class); // seller told

    app(ResolveRefundRequest::class)->approve($req->fresh(), $this->sellerUser, 'ok');

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and(($this->avail)($this->buyer))->toBe('100000000')          // fully repaid
        ->and(($this->avail)($this->sellerUser))->toBe('0')             // net clawed back
        ->and($req->fresh()->status)->toBe(RefundRequestStatus::Refunded)
        ->and($req->fresh()->amount_refunded)->toBe(50_000000)
        ->and($req->fresh()->ledger_entry_id)->not->toBeNull();
    Notification::assertSentTo($this->buyer, UserNotification::class);   // buyer told
});

it('splits partial refunds pro-rata and completes the order over two partials', function () {
    $order = ($this->order)();

    // First partial: 20 USDT of 50 → commission_back = 20×5/50 = 2, seller_back = 18.
    $r1 = app(RequestRefund::class)->execute($order, $this->buyer, 'partial', 20_000000, '');
    app(ResolveRefundRequest::class)->approve($r1->fresh(), $this->sellerUser);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::PartiallyRefunded)
        ->and((int) $order->refunded_amount)->toBe(20_000000)
        ->and(($this->avail)($this->buyer))->toBe('70000000')          // 50 spent, 20 back
        ->and(($this->avail)($this->sellerUser))->toBe('27000000');    // 45 net − 18 back

    // Second partial: the remaining 30 → completes the order.
    $r2 = app(RequestRefund::class)->execute($order, $this->buyer, 'partial', 30_000000, '');
    app(ResolveRefundRequest::class)->approve($r2->fresh(), $this->sellerUser);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Refunded)
        ->and((int) $order->refunded_amount)->toBe(50_000000)
        ->and(($this->avail)($this->buyer))->toBe('100000000')          // fully repaid
        ->and(($this->avail)($this->sellerUser))->toBe('0');
});

it('blocks a second open request while one is pending', function () {
    $order = ($this->order)();
    app(RequestRefund::class)->execute($order, $this->buyer, 'partial', 10_000000, '');

    app(RequestRefund::class)->execute($order, $this->buyer, 'partial', 10_000000, '');
})->throws(SellException::class);

it('routes a rejected request through buyer escalation to an admin', function () {
    Notification::fake();
    $order = ($this->order)();
    $req = app(RequestRefund::class)->execute($order, $this->buyer, 'full', null, '');

    app(ResolveRefundRequest::class)->reject($req->fresh(), $this->sellerUser, 'no refunds');
    expect($req->fresh()->status)->toBe(RefundRequestStatus::Rejected);

    app(EscalateRefundRequest::class)->execute($req->fresh());
    expect($req->fresh()->status)->toBe(RefundRequestStatus::Escalated);

    // Admin approves the escalated request.
    $admin = ($this->makeAdmin)();
    app(ResolveRefundRequest::class)->approve($req->fresh(), $admin, 'buyer is right');

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($req->fresh()->resolver_type)->toBe('admin')
        ->and(($this->avail)($this->buyer))->toBe('100000000');
});

it('lets a buyer cancel their own pending request', function () {
    $order = ($this->order)();
    $req = app(RequestRefund::class)->execute($order, $this->buyer, 'full', null, '');

    app(CancelRefundRequest::class)->execute($req);

    expect($req->fresh()->status)->toBe(RefundRequestStatus::Cancelled)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid); // untouched
});

it('auto-escalates a request the seller ignores past the SLA and notifies operators', function () {
    Notification::fake();
    updateSetting('sell_refund_sla_days', 3);
    $admin = ($this->makeAdmin)();
    $order = ($this->order)();
    $req = app(RequestRefund::class)->execute($order, $this->buyer, 'full', null, '');

    $this->travel(4)->days();
    $this->artisan('poisapay:sell-escalate-refunds')->assertSuccessful();

    expect($req->fresh()->status)->toBe(RefundRequestStatus::Escalated);
    Notification::assertSentTo($admin, OperatorNotification::class);
});

it('approving from the held balance is earnings-hold compatible', function () {
    updateSetting('sell_earnings_hold', true);
    $order = ($this->order)();

    // Net is in the seller's LOCKED balance while held.
    expect($this->ledger->lockedBalance($this->sellerUser, $this->asset->id)->baseString())->toBe('45000000');

    $req = app(RequestRefund::class)->execute($order, $this->buyer, 'full', null, '');
    app(ResolveRefundRequest::class)->approve($req->fresh(), $this->sellerUser);

    expect($this->ledger->lockedBalance($this->sellerUser, $this->asset->id)->baseString())->toBe('0')
        ->and(($this->avail)($this->buyer))->toBe('100000000');
});

it('cannot approve an already-resolved request', function () {
    $order = ($this->order)();
    $req = app(RequestRefund::class)->execute($order, $this->buyer, 'full', null, '');
    app(ResolveRefundRequest::class)->approve($req->fresh(), $this->sellerUser);

    app(ResolveRefundRequest::class)->approve($req->fresh(), $this->sellerUser);
})->throws(SellException::class);

// ─── HTTP / policy ──────────────────────────────────────────────────────────────

it('exposes the buyer + seller refund API with correct authorization', function () {
    $order = ($this->order)();

    // Buyer opens a request via the API.
    $res = $this->actingAs($this->buyer)
        ->postJson(route('sell.api.refund-requests.store'), ['order_id' => $order->id, 'type' => 'full'])
        ->assertCreated()->json('data');
    $reqId = $res['id'];

    // A stranger may not view it.
    $stranger = User::factory()->create();
    $this->actingAs($stranger)->getJson(route('sell.api.refund-requests.show', $reqId))->assertForbidden();

    // A stranger may not approve it; the seller can.
    $this->actingAs($stranger)->postJson(route('sell.api.refund-requests.approve', $reqId))->assertForbidden();
    $this->actingAs($this->sellerUser)->postJson(route('sell.api.refund-requests.approve', $reqId))->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded);
});

it('drives the buyer → seller refund flow over the Blade routes', function () {
    $order = ($this->order)();

    // The buyer's purchase page renders with the refund panel.
    $this->actingAs($this->buyer)->get(route('purchases.show', $order->id))
        ->assertOk()->assertSee('Request a refund');

    // Buyer opens a partial request from their purchase page.
    $this->actingAs($this->buyer)
        ->post(route('purchases.refund', ['order' => $order->id]), ['type' => 'partial', 'amount' => '20', 'reason' => 'meh'])
        ->assertRedirect(route('purchases.show', $order->id));

    $req = RefundRequest::where('order_id', $order->id)->firstOrFail();

    // Seller approves from the order page.
    $this->actingAs($this->sellerUser)
        ->post(route('sell.order.refund-request.approve', ['id' => $order->id, 'refundRequest' => $req->id]), ['note' => 'ok'])
        ->assertRedirect(route('sell.order', ['id' => $order->id]));

    expect($order->fresh()->status)->toBe(OrderStatus::PartiallyRefunded)
        ->and(($this->avail)($this->buyer))->toBe('70000000');
});

it('lets an operator resolve an escalated request over the admin routes', function () {
    $order = ($this->order)();
    $req = app(RequestRefund::class)->execute($order, $this->buyer, 'full', null, '');
    app(EscalateRefundRequest::class)->execute($req->fresh());
    $admin = ($this->makeAdmin)();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.sell-refunds.approve', $req->id), ['note' => 'valid claim'])
        ->assertRedirect(route('admin.sell-refunds.show', $req->id));

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($req->fresh()->resolver_type)->toBe('admin');
});
