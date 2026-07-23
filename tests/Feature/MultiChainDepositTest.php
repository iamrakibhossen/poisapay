<?php

declare(strict_types=1);

use App\Domain\Chain\Evm\AdvanceEvmDepositsAction;
use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Evm\Evm;
use App\Domain\Chain\Evm\ScanEvmDepositsAction;
use App\Domain\Custody\AllocateDepositAddressAction;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Ledger\LedgerService;
use App\Enums\ChainType;
use App\Enums\DepositStatus;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\CustodyXpub;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config(['poisapay.custody_simulated' => false, 'poisapay.custody.seed' => str_repeat('c3', 32), 'providers.blockchain.driver' => 'fake']);
    app()->forgetInstance(BlockchainProvider::class);
    $this->fake = app(BlockchainProvider::class);
    Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);
    $this->ledger = app(LedgerService::class);
});

// Helper: register a real deposit xpub for a chain + allocate the user's address.
function seedDepositAddress(User $user, ChainType $chainType): array
{
    $chain = Chain::where('key', $chainType->value)->first();
    $xpub = app(SignerKeyProvider::class)->accountXpub($chainType);
    CustodyXpub::where('chain_id', $chain->id)->where('purpose', 'deposit')->update(['xpub' => $xpub, 'next_index' => 0]);
    $addr = app(AllocateDepositAddressAction::class)->execute($user, $chain);

    return [$chain, $addr->address];
}

it('seeds the full RedotPay-style chain + token matrix', function () {
    // All EVM chains present + Tron.
    foreach (['ethereum', 'bsc', 'polygon', 'arbitrum', 'optimism', 'base', 'avalanche', 'tron'] as $key) {
        expect(Chain::where('key', $key)->exists())->toBeTrue();
    }
    // USDC exists on Polygon; USDT is absent on Base; USDC is absent on Tron.
    $polygon = Chain::where('key', 'polygon')->first();
    expect(Asset::where('chain_id', $polygon->id)->where('symbol', 'USDC')->exists())->toBeTrue();

    $base = Chain::where('key', 'base')->first();
    expect(Asset::where('chain_id', $base->id)->where('symbol', 'USDC')->exists())->toBeTrue()
        ->and(Asset::where('chain_id', $base->id)->where('symbol', 'USDT')->exists())->toBeFalse();

    $tron = Chain::where('key', 'tron')->first();
    expect(Asset::where('chain_id', $tron->id)->where('symbol', 'USDC')->exists())->toBeFalse();
});

it('credits a USDC deposit on Polygon (6-decimal)', function () {
    $user = User::factory()->create();
    [$chain, $address] = seedDepositAddress($user, ChainType::Polygon);
    $usdc = Asset::where('chain_id', $chain->id)->where('symbol', 'USDC')->first();

    $txHash = '0x'.str_repeat('a1', 32);
    $this->fake->pushTransferLog(ChainType::Polygon, $usdc->contract_address, '0x'.str_repeat('11', 20), $address, '5000000', $txHash, 0, 300);

    expect(app(ScanEvmDepositsAction::class)->execute(ChainType::Polygon))->toBe(1);
    $this->fake->confirm($txHash, 300, true);
    $this->fake->setBlock(ChainType::Polygon, 300 + 30);
    app(AdvanceEvmDepositsAction::class)->execute(ChainType::Polygon);

    expect($user->deposits()->first()->status)->toBe(DepositStatus::Credited)
        ->and($this->ledger->availableBalance($user, $usdc->id)->baseString())->toBe('5000000');
});

it('normalises an 18-decimal BSC USDT deposit to the ledger 6-decimal precision', function () {
    $user = User::factory()->create();
    [$chain, $address] = seedDepositAddress($user, ChainType::Bsc);
    $usdt = Asset::where('chain_id', $chain->id)->where('symbol', 'USDT')->first();
    expect($usdt->decimals)->toBe(6); // ledger precision

    // 7 USDT on BSC is 7 * 10^18 base units on-chain.
    $onchain = Evm::scaleDecimals('7', 0, 18); // = 7 * 10^18
    $txHash = '0x'.str_repeat('b2', 32);
    $this->fake->pushTransferLog(ChainType::Bsc, $usdt->contract_address, '0x'.str_repeat('22', 20), $address, $onchain, $txHash, 0, 400);

    app(ScanEvmDepositsAction::class)->execute(ChainType::Bsc);
    $this->fake->confirm($txHash, 400, true);
    $this->fake->setBlock(ChainType::Bsc, 400 + 15);
    app(AdvanceEvmDepositsAction::class)->execute(ChainType::Bsc);

    // 7 USDT normalised to 6-decimal ledger units = 7_000_000.
    expect($this->ledger->availableBalance($user, $usdt->id)->baseString())->toBe('7000000');
});
