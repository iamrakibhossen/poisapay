<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\PlaceEscrowAction;
use App\Domain\P2p\RefundEscrowAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\P2pEscrowStatus;
use App\Models\JournalEntry;
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
    creditUser($this->seller, $this->usdt, '1000000000');
    $this->ad = P2pAd::factory()->create(['user_id' => $this->seller->id, 'asset_id' => $this->usdt->id]);
});

it('records a locked escrow linked to a balanced lock entry', function () {
    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));
    $escrow = $order->escrow;

    expect($escrow->status)->toBe(P2pEscrowStatus::Locked)
        ->and($escrow->amount)->toBe('100000000')
        ->and($escrow->lock_entry_id)->not->toBeNull();

    $entry = JournalEntry::find($escrow->lock_entry_id);
    expect($entry->type)->toBe('p2p.escrow.lock')
        ->and($entry->lines()->count())->toBe(2); // balanced two-legged hold
});

it('place escrow is idempotent per order — no second hold', function () {
    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));

    $again = app(PlaceEscrowAction::class)->execute($order->refresh());

    expect($again->id)->toBe($order->escrow->id)
        ->and($this->ledger->availableBalance($this->seller, $this->usdt->id)->baseString())->toBe('900000000');
});

it('refunds a locked escrow back to the seller', function () {
    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));

    app(RefundEscrowAction::class)->execute($order->refresh());

    expect($order->escrow->refresh()->status)->toBe(P2pEscrowStatus::Refunded)
        ->and($this->ledger->availableBalance($this->seller, $this->usdt->id)->baseString())->toBe('1000000000');
});
