<?php

declare(strict_types=1);

use App\Domain\Chain\SimulateInboundDepositAction;
use App\Domain\Chain\SweepDepositAction;
use App\Domain\Custody\AllocateDepositAddressAction;
use App\Domain\Deposit\CreditDepositAction;
use App\Domain\Ledger\LedgerService;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Domain\Withdrawal\SettleWithdrawalAction;
use App\Enums\DepositStatus;
use App\Enums\KycTier;
use App\Enums\WithdrawalStatus;
use App\Jobs\ChainTickJob;
use App\Models\CustodyXpub;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->chain = $this->asset->chain;
    $this->ledger = app(LedgerService::class);
    CustodyXpub::create([
        'chain_id' => $this->chain->id, 'label' => 'x', 'xpub' => 'xpub-sim',
        'derivation_path' => 'm', 'next_index' => 0, 'purpose' => 'deposit',
    ]);
    $this->user = User::factory()->create(['kyc_tier' => KycTier::Full]);
});

function tick(): void
{
    (new ChainTickJob(confPerTick: 100))->handle(
        app(CreditDepositAction::class),
        app(SettleWithdrawalAction::class),
        app(SweepDepositAction::class),
    );
}

it('detects a simulated deposit and credits it after confirmations', function () {
    $address = app(AllocateDepositAddressAction::class)->execute($this->user, $this->chain);
    $deposit = app(SimulateInboundDepositAction::class)->execute($address, $this->asset, Money::ofBase('7000000', 6, 'USDT'));

    expect($deposit->status)->toBe(DepositStatus::Detected)
        ->and($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('0');

    tick(); // advance confirmations past the threshold -> credit

    expect($deposit->fresh()->status)->toBe(DepositStatus::Credited)
        ->and($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('7000000');
});

it('broadcasts and settles an approved withdrawal on tick', function () {
    creditUser($this->user, $this->asset, '5000000');
    $withdrawal = app(RequestWithdrawalAction::class)->execute(
        $this->user, $this->asset, Money::ofBase('2000000', 6, 'USDT'), 'Tdest', 'wd:sim'
    );

    // Funds are reserved (locked) immediately regardless of the review decision.
    expect($this->ledger->lockedBalance($this->user, $this->asset->id)->isPositive())->toBeTrue();

    // Operator approves (a fresh account is risk-flagged into review by design).
    $withdrawal->update(['status' => WithdrawalStatus::Approved]);

    tick();

    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Completed)
        ->and($this->ledger->lockedBalance($this->user, $this->asset->id)->baseString())->toBe('0');
});
