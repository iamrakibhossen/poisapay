<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\MarkBuyerPaidAction;
use App\Domain\P2p\ReleaseEscrowAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\P2pAd;
use App\Models\P2pOrder;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    updateSetting('p2p_taker_fee_bps', 0);

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->seller = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($this->seller, $this->usdt, '1000000000'); // 1000 USDT
    $this->ad = P2pAd::factory()->create(['user_id' => $this->seller->id, 'asset_id' => $this->usdt->id]);
});

it('rejects an order that exceeds the ad remaining inventory', function () {
    app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('600', 6, 'USDT'));
    expect($this->ad->fresh()->available_amount)->toBe('400000000');

    $buyer2 = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);

    expect(fn () => app(CreateOrderAction::class)->execute($buyer2, $this->ad, Money::ofDecimal('600', 6, 'USDT')))
        ->toThrow(RuntimeException::class);

    expect($this->ad->fresh()->available_amount)->toBe('400000000'); // unchanged
});

it('rolls back the order and ad decrement when the seller has insufficient balance', function () {
    $poor = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($poor, $this->usdt, '500000000'); // only 500 USDT
    $ad = P2pAd::factory()->create(['user_id' => $poor->id, 'asset_id' => $this->usdt->id]); // advertises 1000

    expect(fn () => app(CreateOrderAction::class)->execute($this->buyer, $ad, Money::ofDecimal('600', 6, 'USDT')))
        ->toThrow(RuntimeException::class);

    expect($this->ledger->availableBalance($poor, $this->usdt->id)->baseString())->toBe('500000000')
        ->and($ad->fresh()->available_amount)->toBe('1000000000')  // decrement rolled back
        ->and(P2pOrder::count())->toBe(0);                         // order rolled back
});

it('release escrow is idempotent — a second release never double-pays the buyer', function () {
    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));
    app(MarkBuyerPaidAction::class)->execute($order->refresh(), $this->buyer);

    app(ReleaseEscrowAction::class)->execute($order->refresh());
    app(ReleaseEscrowAction::class)->execute($order->refresh()); // no-op

    expect($this->ledger->availableBalance($this->buyer, $this->usdt->id)->baseString())->toBe('100000000');
});
