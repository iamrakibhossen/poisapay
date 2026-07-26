<?php

declare(strict_types=1);

use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\MarkBuyerPaidAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Events\P2pOrderStatusChanged;
use App\Models\P2pAd;
use App\Models\User;
use App\Support\Money;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    updateSetting('p2p_taker_fee_bps', 0);

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->seller = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($this->seller, $this->usdt, '1000000000');
    $this->ad = P2pAd::factory()->create(['user_id' => $this->seller->id, 'asset_id' => $this->usdt->id]);
});

it('broadcasts a status change on the shared order channel', function () {
    Event::fake([P2pOrderStatusChanged::class]);

    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));

    // Opening the order is not a guarded transition — no status broadcast yet.
    Event::assertNotDispatched(P2pOrderStatusChanged::class);

    app(MarkBuyerPaidAction::class)->execute($order->refresh(), $this->buyer);

    Event::assertDispatched(
        P2pOrderStatusChanged::class,
        fn (P2pOrderStatusChanged $e) => $e->orderId === $order->id && $e->status === 'buyer_paid',
    );
});

it('addresses the correct private channel and payload', function () {
    $event = new P2pOrderStatusChanged('order-123', 'completed');

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->afterCommit)->toBeTrue()
        ->and($event->broadcastAs())->toBe('p2p.status')
        ->and($event->broadcastWith())->toBe(['order_id' => 'order-123', 'status' => 'completed']);

    $channels = $event->broadcastOn();
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-p2p.order.order-123');
});
