<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Domain\P2p\ConfirmReleaseAction;
use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\MarkBuyerPaidAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\LedgerAccountType;
use App\Enums\P2pEscrowStatus;
use App\Enums\P2pOrderStatus;
use App\Models\P2pAd;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    updateSetting('p2p_taker_fee_bps', 100); // 1%

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->resolver = $this->ledger->resolver();

    $this->seller = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($this->seller, $this->usdt, '1000000000'); // 1000 USDT

    $this->ad = P2pAd::factory()->create([
        'user_id' => $this->seller->id,
        'asset_id' => $this->usdt->id,
    ]);
});

function p2pEscrowBase(User $seller, int $assetId): string
{
    return app(LedgerService::class)->resolver()
        ->forUser($seller, LedgerAccountType::UserP2pEscrow, $assetId)
        ->fresh('balance')->money()->baseString();
}

function p2pFeeIncomeBase(int $assetId): string
{
    return app(LedgerService::class)->resolver()
        ->system(LedgerAccountType::P2pFeeIncome, $assetId)
        ->fresh('balance')->money()->baseString();
}

it('escrows the seller USDT when an order opens', function () {
    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));

    expect($order->status)->toBe(P2pOrderStatus::WaitingPayment)
        ->and($order->seller_id)->toBe($this->seller->id)
        ->and($order->buyer_id)->toBe($this->buyer->id)
        ->and($this->ledger->availableBalance($this->seller, $this->usdt->id)->baseString())->toBe('900000000')
        ->and(p2pEscrowBase($this->seller, $this->usdt->id))->toBe('100000000')
        ->and($order->escrow->status)->toBe(P2pEscrowStatus::Locked)
        ->and($order->fee_amount)->toBe('1000000')
        ->and($order->net_amount)->toBe('99000000');

    // Ad inventory decremented.
    expect($this->ad->fresh()->available_amount)->toBe('900000000');
});

it('completes the trade: escrow → buyer (net) + fee income, seller down gross', function () {
    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));

    app(MarkBuyerPaidAction::class)->execute($order->refresh(), $this->buyer);
    app(ConfirmReleaseAction::class)->execute($order->refresh(), $this->seller);

    $order->refresh();
    expect($order->status)->toBe(P2pOrderStatus::Completed)
        ->and($order->escrow->status)->toBe(P2pEscrowStatus::Released)
        // buyer received net 99, seller settled at 900 (gross 100 left their wallet), fee income 1, escrow empty
        ->and($this->ledger->availableBalance($this->buyer, $this->usdt->id)->baseString())->toBe('99000000')
        ->and($this->ledger->availableBalance($this->seller, $this->usdt->id)->baseString())->toBe('900000000')
        ->and(p2pFeeIncomeBase($this->usdt->id))->toBe('1000000')
        ->and(p2pEscrowBase($this->seller, $this->usdt->id))->toBe('0');
});

it('never double-releases: a second confirm throws and balances are unchanged', function () {
    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));
    app(MarkBuyerPaidAction::class)->execute($order->refresh(), $this->buyer);
    app(ConfirmReleaseAction::class)->execute($order->refresh(), $this->seller);

    expect(fn () => app(ConfirmReleaseAction::class)->execute($order->refresh(), $this->seller))
        ->toThrow(RuntimeException::class);

    // Buyer still holds exactly the single net release.
    expect($this->ledger->availableBalance($this->buyer, $this->usdt->id)->baseString())->toBe('99000000')
        ->and(p2pFeeIncomeBase($this->usdt->id))->toBe('1000000');
});

it('blocks a non-buyer from marking paid and a non-seller from releasing', function () {
    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));

    expect(fn () => app(MarkBuyerPaidAction::class)->execute($order->refresh(), $this->seller))
        ->toThrow(RuntimeException::class);

    app(MarkBuyerPaidAction::class)->execute($order->refresh(), $this->buyer);

    expect(fn () => app(ConfirmReleaseAction::class)->execute($order->refresh(), $this->buyer))
        ->toThrow(RuntimeException::class);
});
