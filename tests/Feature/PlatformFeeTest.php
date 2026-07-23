<?php

declare(strict_types=1);

use App\Domain\Custody\AllocateDepositAddressAction;
use App\Domain\Deposit\CreditDepositAction;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\LedgerService;
use App\Enums\DepositStatus;
use App\Enums\KycTier;
use App\Enums\LedgerAccountType;
use App\Models\CustodyXpub;
use App\Models\Deposit;
use App\Models\OnchainTx;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->chain = $this->asset->chain;
    $this->ledger = app(LedgerService::class);

    // Turn the platform fees on at 1% for these tests (test baseline is 0).
    updateSetting('deposit_fee_percent', '1', 'deposit');
    updateSetting('withdrawal_fee_percent', '1', 'withdrawal');
});

it('takes a 1% deposit fee and books it to fee:income', function () {
    $user = User::factory()->create();
    CustodyXpub::create([
        'chain_id' => $this->chain->id, 'label' => 'x', 'xpub' => 'xpub-fee',
        'derivation_path' => 'm', 'next_index' => 0, 'purpose' => 'deposit',
    ]);
    $address = app(AllocateDepositAddressAction::class)->execute($user, $this->chain);

    $tx = OnchainTx::create([
        'chain_id' => $this->chain->id, 'tx_hash' => '0xfee', 'log_index' => 0,
        'to_address' => $address->address, 'asset_id' => $this->asset->id,
        'amount' => '100000000', 'confirmations' => 20, 'status' => 'confirmed', 'direction' => 'in',
    ]);
    $deposit = Deposit::create([
        'user_id' => $user->id, 'deposit_address_id' => $address->id, 'asset_id' => $this->asset->id,
        'onchain_tx_id' => $tx->id, 'amount' => '100000000', 'confirmations' => 20, // 100 USDT
        'required_confirmations' => 19, 'status' => DepositStatus::Detected,
    ]);

    app(CreditDepositAction::class)->execute($deposit);

    // User credited 99 USDT; 1 USDT booked to fee:income.
    $feeIncome = app(AccountResolver::class)->system(LedgerAccountType::FeeIncome, $this->asset->id);

    expect($this->ledger->availableBalance($user, $this->asset->id)->baseString())->toBe('99000000')
        ->and($deposit->fresh()->fee)->toBe('1000000')
        ->and($feeIncome->fresh('balance')->money()->baseString())->toBe('1000000');
});

it('adds a 1% platform fee to a withdrawal', function () {
    $user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $user->forceFill(['created_at' => now()->subMonth()])->save();
    creditUser($user, $this->asset, '100000000'); // 100 USDT

    // Withdraw 50 USDT via the page.
    actingAs($user)->post(route('withdraw.submit'), [
        'assetId' => $this->asset->id, 'toAddress' => 'TdestFeeAddr', 'amount' => '50',
    ])->assertRedirect(route('withdraw.index'))->assertSessionHas('success');

    // Fee = flat (0) + 1% of 50 = 0.5 USDT. Locked = 50.5; available = 100 − 50.5.
    $withdrawal = $user->withdrawals()->latest()->first();
    expect($withdrawal->fee)->toBe('500000')
        ->and($this->ledger->availableBalance($user, $this->asset->id)->baseString())->toBe('49500000');
})->group('fee');
