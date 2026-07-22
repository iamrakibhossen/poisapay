<?php

declare(strict_types=1);

use App\Domain\Custody\AllocateDepositAddressAction;
use App\Domain\Deposit\CreditDepositAction;
use App\Domain\Ledger\LedgerService;
use App\Domain\Transfer\ExecuteTransferAction;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Enums\DepositStatus;
use App\Enums\KycTier;
use App\Models\CustodyXpub;
use App\Models\Deposit;
use App\Models\DepositAddress;
use App\Models\OnchainTx;
use App\Models\User;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->chain = $this->asset->chain;
    $this->ledger = app(LedgerService::class);
});

it('allocates a unique deterministic deposit address and reuses it', function () {
    CustodyXpub::create([
        'chain_id' => $this->chain->id,
        'label' => 'Tron deposits',
        'xpub' => 'xpub-tron-test-0001',
        'derivation_path' => "m/44'/195'/0'/0",
        'next_index' => 0,
        'purpose' => 'deposit',
    ]);

    $user = User::factory()->create();
    $action = app(AllocateDepositAddressAction::class);

    $first = $action->execute($user, $this->chain);
    $second = $action->execute($user, $this->chain); // reuse

    expect($first->address)->toStartWith('T')
        ->and($first->id)->toBe($second->id)
        ->and(DepositAddress::count())->toBe(1);
});

it('credits a confirmed deposit exactly once (idempotent, no double-credit)', function () {
    $user = User::factory()->create();
    CustodyXpub::create([
        'chain_id' => $this->chain->id, 'label' => 'x', 'xpub' => 'xpub-a',
        'derivation_path' => 'm', 'next_index' => 0, 'purpose' => 'deposit',
    ]);
    $address = app(AllocateDepositAddressAction::class)->execute($user, $this->chain);

    $tx = OnchainTx::create([
        'chain_id' => $this->chain->id, 'tx_hash' => '0xdeadbeef', 'log_index' => 0,
        'to_address' => $address->address, 'asset_id' => $this->asset->id,
        'amount' => '5000000', 'confirmations' => 20, 'status' => 'confirmed', 'direction' => 'in',
    ]);
    $deposit = Deposit::create([
        'user_id' => $user->id, 'deposit_address_id' => $address->id, 'asset_id' => $this->asset->id,
        'onchain_tx_id' => $tx->id, 'amount' => '5000000', 'confirmations' => 20,
        'required_confirmations' => 19, 'status' => DepositStatus::Detected,
    ]);

    $action = app(CreditDepositAction::class);
    $action->execute($deposit);
    $action->execute($deposit->fresh()); // replay

    expect($this->ledger->availableBalance($user, $this->asset->id)->baseString())->toBe('5000000')
        ->and($deposit->fresh()->status)->toBe(DepositStatus::Credited);
});

it('performs an instant internal transfer between users', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    creditUser($sender, $this->asset, '3000000');

    app(ExecuteTransferAction::class)->execute(
        $sender, $recipient, $this->asset, Money::ofBase('1200000', 6, 'USDT'), 'tf:1', 'lunch'
    );

    expect($this->ledger->availableBalance($sender, $this->asset->id)->baseString())->toBe('1800000')
        ->and($this->ledger->availableBalance($recipient, $this->asset->id)->baseString())->toBe('1200000');
});

it('blocks a transfer that exceeds the sender balance', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    creditUser($sender, $this->asset, '100000');

    app(ExecuteTransferAction::class)->execute(
        $sender, $recipient, $this->asset, Money::ofBase('500000', 6, 'USDT'), 'tf:over'
    );
})->throws(RuntimeException::class);

it('reserves funds first on withdrawal (available -> locked)', function () {
    $user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    creditUser($user, $this->asset, '2000000');

    $withdrawal = app(RequestWithdrawalAction::class)->execute(
        $user, $this->asset, Money::ofBase('800000', 6, 'USDT'), 'TdestAddr123', 'wd:1'
    );

    expect($this->ledger->availableBalance($user, $this->asset->id)->baseString())->toBe('1200000')
        ->and($this->ledger->lockedBalance($user, $this->asset->id)->baseString())->toBe('800000')
        ->and($withdrawal->lock_entry_id)->not->toBeNull();
});

it('refuses withdrawal for an unverified (view-only) tier', function () {
    $user = User::factory()->create(['kyc_tier' => KycTier::Unverified]);
    creditUser($user, $this->asset, '2000000');

    app(RequestWithdrawalAction::class)->execute(
        $user, $this->asset, Money::ofBase('800000', 6, 'USDT'), 'TestAddr', 'wd:blocked'
    );
})->throws(ValidationException::class);
