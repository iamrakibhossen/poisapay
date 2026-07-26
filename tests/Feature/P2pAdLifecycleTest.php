<?php

declare(strict_types=1);

use App\Domain\P2p\ConfirmReleaseAction;
use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\DuplicateAdAction;
use App\Domain\P2p\MarkBuyerPaidAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\P2pAdStatus;
use App\Enums\P2pMessageType;
use App\Models\P2pAd;
use App\Models\P2pPaymentMethod;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    updateSetting('p2p_taker_fee_bps', 0);

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->owner = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($this->owner, $this->usdt, '1000000000');
});

it('duplicates an ad as a draft with fresh inventory and the same rails', function () {
    $ad = P2pAd::factory()->create([
        'user_id' => $this->owner->id,
        'asset_id' => $this->usdt->id,
        'available_amount' => '400000000',   // partly consumed
        'total_amount' => '1000000000',
        'status' => P2pAdStatus::Active,
    ]);
    $ad->paymentMethods()->sync(P2pPaymentMethod::where('key', 'bkash')->pluck('id')->all());

    $copy = app(DuplicateAdAction::class)->execute($this->owner, $ad);

    expect($copy->id)->not->toBe($ad->id)
        ->and($copy->status)->toBe(P2pAdStatus::Draft)
        ->and($copy->available_amount)->toBe('1000000000')      // reset to total
        ->and($copy->paymentMethods()->count())->toBe(1);
});

it('blocks duplicating an ad you do not own', function () {
    $ad = P2pAd::factory()->create(['user_id' => $this->owner->id, 'asset_id' => $this->usdt->id]);
    $stranger = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);

    expect(fn () => app(DuplicateAdAction::class)->execute($stranger, $ad))
        ->toThrow(RuntimeException::class, 'your own ads');
});

it('soft-deletes an ad and hides it from the owner list, but orders still resolve it', function () {
    $buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $ad = P2pAd::factory()->create(['user_id' => $this->owner->id, 'asset_id' => $this->usdt->id]);

    // A completed order so the ad has history but no OPEN orders.
    $order = app(CreateOrderAction::class)->execute($buyer, $ad, Money::ofDecimal('100', 6, 'USDT'));
    app(MarkBuyerPaidAction::class)->execute($order->refresh(), $buyer);
    app(ConfirmReleaseAction::class)->execute($order->refresh(), $this->owner);

    $this->actingAs($this->owner)
        ->delete(route('p2p.ads.delete', $ad))
        ->assertRedirect(route('p2p.ads'))
        ->assertSessionHas('success');

    expect(P2pAd::find($ad->id))->toBeNull()                    // hidden by soft-delete scope
        ->and(P2pAd::withTrashed()->find($ad->id))->not->toBeNull();

    // The historical order still resolves its (trashed) ad.
    expect($order->refresh()->ad)->not->toBeNull()
        ->and($order->ad->id)->toBe($ad->id);
});

it('refuses to delete an ad with open orders', function () {
    $buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $ad = P2pAd::factory()->create(['user_id' => $this->owner->id, 'asset_id' => $this->usdt->id]);
    app(CreateOrderAction::class)->execute($buyer, $ad, Money::ofDecimal('100', 6, 'USDT')); // waiting_payment

    $this->actingAs($this->owner)
        ->delete(route('p2p.ads.delete', $ad))
        ->assertSessionHas('error');

    expect(P2pAd::find($ad->id))->not->toBeNull(); // still there
});

it('posts the ad auto-reply into the order chat when an order opens', function () {
    $buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $ad = P2pAd::factory()->create([
        'user_id' => $this->owner->id,
        'asset_id' => $this->usdt->id,
        'auto_reply' => 'Please pay to my bKash and send a screenshot.',
    ]);

    $order = app(CreateOrderAction::class)->execute($buyer, $ad, Money::ofDecimal('100', 6, 'USDT'));

    $autoMsg = $order->messages()
        ->where('sender_type', 'user')
        ->where('sender_id', $this->owner->id)
        ->where('type', P2pMessageType::Text->value)
        ->first();

    expect($autoMsg)->not->toBeNull()
        ->and($autoMsg->body)->toBe('Please pay to my bKash and send a screenshot.');
});
