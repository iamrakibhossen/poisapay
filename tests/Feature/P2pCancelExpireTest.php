<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Domain\P2p\CancelOrderAction;
use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\ExpireOrderAction;
use App\Domain\P2p\MarkBuyerPaidAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\P2pEscrowStatus;
use App\Enums\P2pOrderStatus;
use App\Models\P2pAd;
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

it('cancel refunds the seller and restocks the ad', function () {
    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));
    expect($this->ledger->availableBalance($this->seller, $this->usdt->id)->baseString())->toBe('900000000');

    app(CancelOrderAction::class)->execute($order->refresh(), $this->buyer, 'changed_mind');

    $order->refresh();
    expect($order->status)->toBe(P2pOrderStatus::Cancelled)
        ->and($order->escrow->status)->toBe(P2pEscrowStatus::Refunded)
        ->and($this->ledger->availableBalance($this->seller, $this->usdt->id)->baseString())->toBe('1000000000')
        ->and($this->ad->fresh()->available_amount)->toBe('1000000000');
});

it('expires an unpaid order, refunds escrow and restocks; a second expiry is a no-op', function () {
    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));

    app(ExpireOrderAction::class)->execute($order->refresh());
    $order->refresh();

    expect($order->status)->toBe(P2pOrderStatus::Expired)
        ->and($this->ledger->availableBalance($this->seller, $this->usdt->id)->baseString())->toBe('1000000000')
        ->and($this->ad->fresh()->available_amount)->toBe('1000000000');

    // Idempotent — running expiry again changes nothing.
    app(ExpireOrderAction::class)->execute($order->refresh());
    expect($this->ledger->availableBalance($this->seller, $this->usdt->id)->baseString())->toBe('1000000000');
});

it('will not cancel an order once the buyer has paid', function () {
    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));
    app(MarkBuyerPaidAction::class)->execute($order->refresh(), $this->buyer);

    expect(fn () => app(CancelOrderAction::class)->execute($order->refresh(), $this->buyer))
        ->toThrow(RuntimeException::class);
});
