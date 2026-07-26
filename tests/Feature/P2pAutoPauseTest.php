<?php

declare(strict_types=1);

use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\InsufficientEscrowFundsException;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\P2pAdStatus;
use App\Models\P2pAd;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    updateSetting('p2p_taker_fee_bps', 0);

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->owner = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->taker = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
});

it('auto-pauses a sell ad whose owner cannot cover the escrow', function () {
    creditUser($this->owner, $this->usdt, '50000000'); // 50 USDT — short for a 100 order
    $ad = P2pAd::factory()->create(['user_id' => $this->owner->id, 'asset_id' => $this->usdt->id]);

    expect(fn () => app(CreateOrderAction::class)->execute($this->taker, $ad, Money::ofDecimal('100', 6, 'USDT')))
        ->toThrow(InsufficientEscrowFundsException::class);

    expect($ad->fresh()->status)->toBe(P2pAdStatus::Paused);

    // The owner is alerted to top up.
    $alerted = $this->owner->notifications()->get()
        ->contains(fn ($n) => ($n->data['event'] ?? null) === 'p2p.ad.auto_paused');
    expect($alerted)->toBeTrue();
});

it('does not pause a buy ad when the short party is the taker, not the owner', function () {
    // Buy ad: the owner is the buyer; the taker sells USDT and must fund escrow.
    creditUser($this->taker, $this->usdt, '10000000'); // 10 USDT — short for a 100 order
    $ad = P2pAd::factory()->buy()->create(['user_id' => $this->owner->id, 'asset_id' => $this->usdt->id]);

    expect(fn () => app(CreateOrderAction::class)->execute($this->taker, $ad, Money::ofDecimal('100', 6, 'USDT')))
        ->toThrow(InsufficientEscrowFundsException::class);

    // The ad owner had funds; the ad stays live.
    expect($ad->fresh()->status)->toBe(P2pAdStatus::Active);
});

it('leaves a well-funded ad active', function () {
    creditUser($this->owner, $this->usdt, '1000000000'); // 1000 USDT
    $ad = P2pAd::factory()->create(['user_id' => $this->owner->id, 'asset_id' => $this->usdt->id]);

    $order = app(CreateOrderAction::class)->execute($this->taker, $ad, Money::ofDecimal('100', 6, 'USDT'));

    expect($order->status->value)->toBe('waiting_payment')
        ->and($ad->fresh()->status)->toBe(P2pAdStatus::Active);
});
