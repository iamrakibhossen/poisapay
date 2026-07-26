<?php

declare(strict_types=1);

use App\Domain\P2p\CreateOrderAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\AmlAlert;
use App\Models\P2pAd;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\Money;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    updateSetting('p2p_taker_fee_bps', 0);
    updateSetting('p2p_high_value_usdt', 50);   // a 100-USDT trade is "high value" (+40)

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->seller = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($this->seller, $this->usdt, '1000000000');
    $this->ad = P2pAd::factory()->create(['user_id' => $this->seller->id, 'asset_id' => $this->usdt->id]);

    // Shared device with the counterparty (+50) → 90 → Critical.
    UserDevice::create(['user_id' => $this->seller->id, 'fingerprint' => 'wash-fp', 'ip_address' => '203.0.113.9']);
});

function criticalOrder(): mixed
{
    return app(CreateOrderAction::class)->execute(
        test()->buyer, test()->ad, Money::ofDecimal('100', 6, 'USDT'), fingerprint: 'wash-fp',
    );
}

it('auto-freezes the taker on a critical trade when the flag is on', function () {
    updateSetting('p2p_auto_freeze', true);

    expect(fn () => criticalOrder())->toThrow(RuntimeException::class, 'under review');

    expect($this->buyer->fresh()->is_frozen)->toBeTrue()
        ->and(AmlAlert::where('user_id', $this->buyer->id)->where('type', 'p2p_auto_freeze')->exists())->toBeTrue();
});

it('a frozen taker is then blocked from any further order', function () {
    updateSetting('p2p_auto_freeze', true);

    try {
        criticalOrder();
    } catch (RuntimeException) {
    }

    // Now frozen — AccountGuard blocks the next attempt outright.
    expect(fn () => app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('10', 6, 'USDT')))
        ->toThrow(RuntimeException::class, 'frozen');
});

it('does not freeze when the flag is off (default)', function () {
    // p2p_auto_freeze not set → default off.
    $order = criticalOrder();

    expect($order->status->value)->toBe('waiting_payment')
        ->and($this->buyer->fresh()->is_frozen)->toBeFalse()
        ->and($order->meta['risk']['level'])->toBe('critical'); // still scored critical, just not frozen
});
