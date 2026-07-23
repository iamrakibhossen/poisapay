<?php

declare(strict_types=1);

use App\Domain\Chain\Evm\Abi;
use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Evm\EvmSweepAction;
use App\Domain\Chain\Evm\FakeBlockchainProvider;
use App\Domain\Chain\Evm\SettleEvmSweepsAction;
use App\Domain\Custody\Contracts\AddressDeriver;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Ledger\AccountResolver;
use App\Enums\ChainType;
use App\Enums\SweepStatus;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\Currency;
use App\Models\CustodyXpub;
use App\Models\DepositAddress;
use App\Models\OnchainTx;
use App\Models\User;

beforeEach(function () {
    config([
        'poisapay.custody_simulated' => false,
        'poisapay.custody.seed' => str_repeat('a1', 32),
        'providers.blockchain.driver' => 'fake',
    ]);
    app()->forgetInstance(BlockchainProvider::class);
    $this->fake = app(BlockchainProvider::class);
    expect($this->fake)->toBeInstanceOf(FakeBlockchainProvider::class);
    updateSetting('onchain_sweep_enabled', true, 'features'); // gas sponsoring stays OFF → direct broadcast

    $this->chain = Chain::create([
        'key' => 'ethereum', 'name' => 'Ethereum', 'native_symbol' => 'ETH',
        'min_confirmations' => 12, 'is_evm' => true, 'is_active' => true,
    ]);
    $currency = Currency::firstOrCreate(['symbol' => 'USDT'], ['name' => 'Tether', 'kind' => 'crypto', 'is_stablecoin' => true, 'is_active' => true]);
    $this->contract = strtolower((string) config('poisapay.custody.ethereum.usdt_contract'));
    $this->asset = Asset::create([
        'currency_id' => $currency->id, 'symbol' => 'USDT', 'name' => 'USDT', 'kind' => 'crypto',
        'chain_id' => $this->chain->id, 'contract_address' => $this->contract, 'decimals' => 6,
        'is_stablecoin' => true, 'is_active' => true, 'withdrawal_min' => '0', 'withdrawal_fee' => '0',
    ]);
    app(AccountResolver::class)->ensureSystemAccounts($this->asset->id);

    $xpub = app(SignerKeyProvider::class)->accountXpub(ChainType::Ethereum);
    $depositAddr = app(AddressDeriver::class)->derive(ChainType::Ethereum, $xpub, 0);
    $xpubRow = CustodyXpub::create([
        'chain_id' => $this->chain->id, 'label' => 'eth', 'xpub' => $xpub,
        'derivation_path' => "m/44'/60'/0'/0", 'next_index' => 1, 'purpose' => 'deposit', 'is_active' => true,
    ]);
    $this->address = DepositAddress::create([
        'user_id' => User::factory()->create()->id, 'chain_id' => $this->chain->id, 'xpub_id' => $xpubRow->id,
        'derivation_index' => 0, 'address' => $depositAddr, 'is_watched' => true,
    ]);
});

it('broadcasts an EVM sweep and settles the ledger only after confirmation', function () {
    // 3 USDT on-chain at the deposit address.
    $this->fake->setCallResult(Abi::erc20BalanceOf($this->address->address), '0x'.str_pad(dechex(3000000), 64, '0', STR_PAD_LEFT));
    $this->fake->setBlock(ChainType::Ethereum, 100);

    $sweep = app(EvmSweepAction::class)->execute($this->address, $this->asset);

    expect($sweep->status)->toBe(SweepStatus::Broadcast)
        ->and($sweep->amount)->toBe('3000000')
        ->and(treasuryHotBase($this->asset->id))->toBe('0'); // ledger untouched pre-confirmation

    $tx = OnchainTx::find($sweep->onchain_tx_id);
    $this->fake->confirm($tx->tx_hash, 100, true);
    $this->fake->setBlock(ChainType::Ethereum, 100 + 12); // reach required depth

    $settled = app(SettleEvmSweepsAction::class)->execute();

    expect($settled)->toBe(1)
        ->and($sweep->fresh()->status)->toBe(SweepStatus::Swept)
        ->and(treasuryHotBase($this->asset->id))->toBe('3000000'); // moved to hot only after confirmation
});

it('does nothing when the sweep flag is off', function () {
    updateSetting('onchain_sweep_enabled', false, 'features');

    expect(app(EvmSweepAction::class)->execute($this->address, $this->asset))->toBeNull();
});
