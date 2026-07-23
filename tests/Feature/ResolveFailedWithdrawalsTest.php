<?php

declare(strict_types=1);

use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Bip32;
use App\Domain\Custody\TronAddressDeriver;
use App\Domain\Ledger\LedgerService;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Domain\Withdrawal\ResolveFailedWithdrawalsAction;
use App\Enums\ChainType;
use App\Enums\KycTier;
use App\Enums\OnchainTxStatus;
use App\Enums\WithdrawalStatus;
use App\Models\OnchainTx;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    config([
        'poisapay.custody_simulated' => false,
        'poisapay.custody.seed' => '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f',
        'poisapay.custody.tron.usdt_contract' => $this->asset->contract_address,
    ]);

    $this->user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $this->user->forceFill(['created_at' => now()->subMonth()])->save();
    creditUser($this->user, $this->asset, '5000000');

    $xpub = app(SignerKeyProvider::class)->accountXpub(ChainType::Tron);
    $dest = (new TronAddressDeriver(new Bip32))->derive(ChainType::Tron, $xpub, 99);

    $this->w = app(RequestWithdrawalAction::class)->execute(
        $this->user, $this->asset, Money::ofBase('2000000', 6, 'USDT'), $dest, 'wd:resolve:1'
    );
    // Simulate a signer rejection: Failed, and never broadcast (no onchain_tx).
    $this->w->update(['status' => WithdrawalStatus::Failed, 'failure_reason' => 'Broadcast rejected']);
});

it('leaves the reserve locked while the flag is off (default, backward compatible)', function () {
    expect($this->ledger->lockedBalance($this->user, $this->asset->id)->isPositive())->toBeTrue();

    $released = app(ResolveFailedWithdrawalsAction::class)->execute();

    expect($released)->toBe(0)
        ->and($this->ledger->lockedBalance($this->user, $this->asset->id)->isPositive())->toBeTrue()
        ->and($this->w->fresh()->reserve_released_at)->toBeNull();
});

it('releases the reserve on a never-broadcast failed withdrawal when the flag is on', function () {
    updateSetting('withdrawal_auto_release_failed', true, 'features');

    $released = app(ResolveFailedWithdrawalsAction::class)->execute();

    expect($released)->toBe(1)
        ->and($this->ledger->lockedBalance($this->user, $this->asset->id)->baseString())->toBe('0')
        ->and($this->w->fresh()->reserve_released_at)->not->toBeNull();
});

it('never releases a post-broadcast failure that carries an on-chain tx (stays for reconciliation)', function () {
    updateSetting('withdrawal_auto_release_failed', true, 'features');

    $tx = OnchainTx::create([
        'chain_id' => $this->asset->chain_id, 'tx_hash' => str_repeat('a', 64), 'log_index' => 0,
        'from_address' => 'from', 'to_address' => 'to', 'asset_id' => $this->asset->id, 'amount' => '2000000',
        'confirmations' => 0, 'status' => OnchainTxStatus::Orphaned, 'direction' => 'out',
    ]);
    $this->w->update(['onchain_tx_id' => $tx->id]);

    $released = app(ResolveFailedWithdrawalsAction::class)->execute();

    expect($released)->toBe(0)
        ->and($this->ledger->lockedBalance($this->user, $this->asset->id)->isPositive())->toBeTrue();
});
