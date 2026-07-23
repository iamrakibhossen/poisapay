<?php

declare(strict_types=1);

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Evm\Evm;
use App\Domain\Chain\Evm\FakeBlockchainProvider;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\LedgerService;
use App\Domain\Withdrawal\Evm\EvmWithdrawalSigner;
use App\Domain\Withdrawal\Evm\RebroadcastStuckWithdrawalsAction;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Enums\ChainType;
use App\Enums\KycTier;
use App\Enums\WithdrawalStatus;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\Currency;
use App\Models\OnchainTx;
use App\Models\User;

beforeEach(function () {
    config([
        'poisapay.custody_simulated' => false,
        'poisapay.custody.seed' => str_repeat('a1', 32),
        'providers.blockchain.driver' => 'fake',
        'poisapay.custody.withdrawal_stuck_blocks' => 10,
    ]);
    app()->forgetInstance(BlockchainProvider::class);
    $this->fake = app(BlockchainProvider::class);
    expect($this->fake)->toBeInstanceOf(FakeBlockchainProvider::class);
    updateSetting('withdrawal_batching_enabled', true, 'features');

    $this->chain = Chain::create(['key' => 'ethereum', 'name' => 'Ethereum', 'native_symbol' => 'ETH', 'min_confirmations' => 12, 'is_evm' => true, 'is_active' => true]);
    $currency = Currency::firstOrCreate(['symbol' => 'USDT'], ['name' => 'Tether', 'kind' => 'crypto', 'is_stablecoin' => true, 'is_active' => true]);
    $this->asset = Asset::create([
        'currency_id' => $currency->id, 'symbol' => 'USDT', 'name' => 'USDT', 'kind' => 'crypto',
        'chain_id' => $this->chain->id, 'contract_address' => strtolower((string) config('poisapay.custody.ethereum.usdt_contract')), 'decimals' => 6,
        'is_stablecoin' => true, 'is_active' => true, 'withdrawal_min' => '0', 'withdrawal_fee' => '0',
    ]);
    app(AccountResolver::class)->ensureSystemAccounts($this->asset->id);

    $this->user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $this->user->forceFill(['created_at' => now()->subMonth()])->save();
    creditUser($this->user, $this->asset, '5000000');

    $to = Evm::toChecksumAddress('0x'.str_repeat('33', 20));
    $w = app(RequestWithdrawalAction::class)->execute($this->user, $this->asset, $this->asset->money('1000000'), $to, 'rbf-wd-1');
    $w->update(['status' => WithdrawalStatus::Approved]);
    $this->fake->setBlock(ChainType::Ethereum, 100);
    $this->withdrawal = app(EvmWithdrawalSigner::class)->execute($w->fresh());
});

it('rebroadcasts a stuck EVM withdrawal with the same nonce and a bumped fee', function () {
    expect($this->withdrawal->status)->toBe(WithdrawalStatus::Broadcast)
        ->and($this->withdrawal->broadcast_attempts)->toBe(1);

    $firstHash = OnchainTx::find($this->withdrawal->onchain_tx_id)->tx_hash;
    $firstNonce = $this->withdrawal->broadcast_nonce;

    $this->fake->setBlock(ChainType::Ethereum, 100 + 15); // past the stuck window, still no receipt

    $replaced = app(RebroadcastStuckWithdrawalsAction::class)->execute();

    $fresh = $this->withdrawal->fresh();
    expect($replaced)->toBe(1)
        ->and($fresh->broadcast_attempts)->toBe(2)
        ->and($fresh->broadcast_nonce)->toBe($firstNonce)                              // SAME nonce → replacement
        ->and(OnchainTx::find($fresh->onchain_tx_id)->tx_hash)->not->toBe($firstHash)  // new (bumped-fee) tx
        ->and($this->fake->sent)->toHaveCount(2);
});

it('does not rebroadcast before the stuck window elapses', function () {
    $this->fake->setBlock(ChainType::Ethereum, 100 + 3); // < stuck threshold

    expect(app(RebroadcastStuckWithdrawalsAction::class)->execute())->toBe(0);
});

it('does nothing when the batching flag is off', function () {
    updateSetting('withdrawal_batching_enabled', false, 'features');
    $this->fake->setBlock(ChainType::Ethereum, 100 + 50);

    expect(app(RebroadcastStuckWithdrawalsAction::class)->execute())->toBe(0);
});

it('dead-letters a stuck withdrawal after exhausting RBF attempts (funds stay locked)', function () {
    $max = (int) config('poisapay.custody.withdrawal_max_broadcast_attempts', 3);
    $this->withdrawal->update(['broadcast_attempts' => $max]); // attempts already exhausted
    $this->fake->setBlock(ChainType::Ethereum, 100 + 15); // still stuck, no receipt

    app(RebroadcastStuckWithdrawalsAction::class)->execute();

    $fresh = $this->withdrawal->fresh();
    expect($fresh->status)->toBe(WithdrawalStatus::Failed)
        ->and($fresh->failure_reason)->toContain('Dead-lettered')
        ->and(app(LedgerService::class)->lockedBalance($this->user, $this->asset->id)->isPositive())->toBeTrue();
});
