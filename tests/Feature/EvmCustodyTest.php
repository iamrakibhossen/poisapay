<?php

declare(strict_types=1);

use App\Domain\Chain\Evm\AdvanceEvmDepositsAction;
use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Evm\Evm;
use App\Domain\Chain\Evm\FakeBlockchainProvider;
use App\Domain\Chain\Evm\HotWalletManager;
use App\Domain\Chain\Evm\NonceManager;
use App\Domain\Chain\Evm\ScanEvmDepositsAction;
use App\Domain\Custody\Contracts\AddressDeriver;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\LedgerService;
use App\Domain\Withdrawal\Evm\AdvanceEvmWithdrawalsAction;
use App\Domain\Withdrawal\Evm\EvmWithdrawalSigner;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Enums\ChainType;
use App\Enums\DepositStatus;
use App\Enums\KycTier;
use App\Enums\WithdrawalStatus;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\Currency;
use App\Models\CustodyXpub;
use App\Models\Deposit;
use App\Models\DepositAddress;
use App\Models\GasWallet;
use App\Models\OnchainTx;
use App\Models\User;

beforeEach(function () {
    // Live custody with a deterministic test seed + the in-memory chain.
    config([
        'poisapay.custody_simulated' => false,
        'poisapay.custody.seed' => str_repeat('a1', 32), // 32-byte hex
        'providers.blockchain.driver' => 'fake',
    ]);
    app()->forgetInstance(BlockchainProvider::class);
    $this->fake = app(BlockchainProvider::class);
    expect($this->fake)->toBeInstanceOf(FakeBlockchainProvider::class);

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

    // Derive a real deposit address from the account xpub.
    $keys = app(SignerKeyProvider::class);
    $xpub = $keys->accountXpub(ChainType::Ethereum);
    $this->depositAddr = app(AddressDeriver::class)->derive(ChainType::Ethereum, $xpub, 0);

    $xpubRow = CustodyXpub::create([
        'chain_id' => $this->chain->id, 'label' => 'eth', 'xpub' => $xpub,
        'derivation_path' => "m/44'/60'/0'/0", 'next_index' => 1, 'purpose' => 'deposit', 'is_active' => true,
    ]);
    $this->user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $this->user->forceFill(['created_at' => now()->subMonth()])->save();
    DepositAddress::create([
        'user_id' => $this->user->id, 'chain_id' => $this->chain->id, 'xpub_id' => $xpubRow->id,
        'derivation_index' => 0, 'address' => $this->depositAddr, 'is_watched' => true,
    ]);
    $this->ledger = app(LedgerService::class);
});

it('derives a valid EIP-55 deposit + hot-wallet address', function () {
    expect(Evm::isValidAddress($this->depositAddr))->toBeTrue()
        ->and($this->depositAddr)->toBe(Evm::toChecksumAddress($this->depositAddr)) // already checksummed
        ->and(Evm::isValidAddress(app(SignerKeyProvider::class)->hotWalletAddress(ChainType::Ethereum)))->toBeTrue();
});

it('detects an ERC-20 deposit and credits it after confirmations', function () {
    $txHash = '0x'.str_repeat('ab', 32);
    $this->fake->pushTransferLog(ChainType::Ethereum, $this->contract, '0x'.str_repeat('11', 20), $this->depositAddr, '1000000', $txHash, 0, 100);

    expect(app(ScanEvmDepositsAction::class)->execute(ChainType::Ethereum))->toBe(1);
    $deposit = Deposit::where('user_id', $this->user->id)->first();
    expect($deposit->status)->toBe(DepositStatus::Detected);

    // Confirm the receipt and advance the head beyond the required depth.
    $this->fake->confirm($txHash, 100, true);
    $this->fake->setBlock(ChainType::Ethereum, 100 + 12);
    app(AdvanceEvmDepositsAction::class)->execute(ChainType::Ethereum);

    expect($deposit->fresh()->status)->toBe(DepositStatus::Credited)
        ->and($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('1000000'); // fees 0 in test env
});

it('orphans a deposit whose transaction reverts', function () {
    $txHash = '0x'.str_repeat('cd', 32);
    $this->fake->pushTransferLog(ChainType::Ethereum, $this->contract, '0x'.str_repeat('22', 20), $this->depositAddr, '500000', $txHash, 0, 50);
    app(ScanEvmDepositsAction::class)->execute(ChainType::Ethereum);

    $this->fake->confirm($txHash, 50, false); // reverted
    $this->fake->setBlock(ChainType::Ethereum, 100);
    app(AdvanceEvmDepositsAction::class)->execute(ChainType::Ethereum);

    expect(Deposit::where('user_id', $this->user->id)->first()->status)->toBe(DepositStatus::Orphaned)
        ->and($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('0');
});

it('signs, broadcasts and settles an EVM withdrawal', function () {
    creditUser($this->user, $this->asset, '5000000');
    $to = Evm::toChecksumAddress('0x'.str_repeat('33', 20));

    $withdrawal = app(RequestWithdrawalAction::class)->execute(
        $this->user, $this->asset, $this->asset->money('1000000'), $to, 'evm-wd-1',
    );
    expect($withdrawal->status)->toBe(WithdrawalStatus::Approved);

    // Sign + broadcast.
    $signed = app(EvmWithdrawalSigner::class)->execute($withdrawal->fresh());
    expect($signed->status)->toBe(WithdrawalStatus::Broadcast)
        ->and($signed->onchain_tx_id)->not->toBeNull()
        ->and($signed->broadcast_nonce)->not->toBeNull()       // recorded for RBF
        ->and($signed->broadcast_attempts)->toBe(1)
        ->and($this->fake->sent)->toHaveCount(1)
        ->and($this->fake->sent[0]['raw'])->toStartWith('0x02'); // typed EIP-1559 tx

    // Confirm the broadcast tx and settle.
    $broadcastHash = OnchainTx::find($signed->onchain_tx_id)->tx_hash;
    $this->fake->confirm($broadcastHash, 200, true);
    $this->fake->setBlock(ChainType::Ethereum, 200 + 12);
    app(AdvanceEvmWithdrawalsAction::class)->execute(ChainType::Ethereum);

    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Completed)
        ->and($this->ledger->lockedBalance($this->user, $this->asset->id)->baseString())->toBe('0')
        ->and($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('4000000');
});

it('reserves strictly increasing nonces from the manager', function () {
    $manager = app(NonceManager::class);
    $addr = '0x'.str_repeat('44', 20);
    expect($manager->next(ChainType::Ethereum, $addr))->toBe(0)
        ->and($manager->next(ChainType::Ethereum, $addr))->toBe(1)
        ->and($manager->next(ChainType::Ethereum, $addr))->toBe(2);
});

it('syncs the gas wallet balance and alerts when low', function () {
    $hot = app(SignerKeyProvider::class)->hotWalletAddress(ChainType::Ethereum);
    GasWallet::create([
        'chain_id' => $this->chain->id, 'address' => $hot, 'balance' => '0',
        'min_threshold' => '1000000000000000000', 'is_active' => true, // 1 ETH threshold
    ]);
    $this->fake->setBalance(ChainType::Ethereum, $hot, '500000000000000000'); // 0.5 ETH < threshold

    $wallet = app(HotWalletManager::class)->syncGas(ChainType::Ethereum);
    expect($wallet->balance)->toBe('500000000000000000')
        ->and($wallet->isLow())->toBeTrue();
});
