<?php

declare(strict_types=1);

use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\MarkBuyerPaidAction;
use App\Domain\P2p\MarkMessagesReadAction;
use App\Domain\P2p\SendMessageAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\P2pMessageType;
use App\Events\P2pMessageSent;
use App\Models\P2pAd;
use App\Models\P2pOrderMessage;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    updateSetting('p2p_taker_fee_bps', 0);

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->seller = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($this->seller, $this->usdt, '1000000000');
    $this->ad = P2pAd::factory()->create(['user_id' => $this->seller->id, 'asset_id' => $this->usdt->id]);
    $this->order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));
});

it('lets a counterparty post a message and broadcasts it', function () {
    Event::fake([P2pMessageSent::class]);

    $message = app(SendMessageAction::class)->execute($this->order, $this->buyer, P2pMessageType::Text, 'Payment sent, please check.');

    expect($message->sender_type)->toBe('user')
        ->and($message->sender_id)->toBe($this->buyer->id)
        ->and($message->type)->toBe(P2pMessageType::Text)
        ->and($message->body)->toBe('Payment sent, please check.');

    Event::assertDispatched(P2pMessageSent::class, fn ($e) => $e->messageId === $message->id);
});

it('blocks a non-party from posting to the thread', function () {
    $stranger = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);

    expect(fn () => app(SendMessageAction::class)->execute($this->order, $stranger, P2pMessageType::Text, 'let me in'))
        ->toThrow(RuntimeException::class);
});

it('emits a system message when the order is created and when the buyer pays', function () {
    // Created in beforeEach — the subscriber posted the opening system message.
    expect(P2pOrderMessage::where('order_id', $this->order->id)->where('type', P2pMessageType::System->value)->count())->toBe(1);

    app(MarkBuyerPaidAction::class)->execute($this->order->refresh(), $this->buyer);

    $system = P2pOrderMessage::where('order_id', $this->order->id)
        ->where('type', P2pMessageType::System->value)
        ->orderByDesc('created_at')->first();

    expect(P2pOrderMessage::where('order_id', $this->order->id)->where('type', P2pMessageType::System->value)->count())->toBe(2)
        ->and($system->body)->toContain('marked the payment');
});

it('stores an attachment on the private disk, never in the payload', function () {
    Storage::fake('local');

    $message = app(SendMessageAction::class)->execute(
        $this->order,
        $this->buyer,
        P2pMessageType::Receipt,
        null,
        UploadedFile::fake()->image('receipt.jpg'),
    );

    expect($message->attachment_path)->not->toBeNull()
        ->and($message->type)->toBe(P2pMessageType::Receipt);
    Storage::disk('local')->assertExists($message->attachment_path);
});

it('marks the other party messages as read', function () {
    // Buyer sends; seller reads → the buyer message is marked, the system message too.
    app(SendMessageAction::class)->execute($this->order, $this->buyer, P2pMessageType::Text, 'hi');

    $updated = app(MarkMessagesReadAction::class)->execute($this->order->refresh(), $this->seller);

    expect($updated)->toBeGreaterThanOrEqual(1);
    // The buyer's own message is unread from the buyer's perspective.
    expect(app(MarkMessagesReadAction::class)->execute($this->order->refresh(), $this->buyer))->toBe(0);
});
