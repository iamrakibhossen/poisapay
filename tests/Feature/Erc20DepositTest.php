<?php

declare(strict_types=1);

use App\Domain\Chain\Evm\AdvanceEvmDepositsAction;
use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
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

it('seeds EVM USDT assets with their real contract addresses + deposit enabled', function () {
    Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);

    foreach (['ethereum', 'bsc', 'tron'] as $key) {
        $chain = Chain::where('key', $key)->first();
        $asset = Asset::where('chain_id', $chain->id)->where('symbol', 'USDT')->first();

        expect($asset)->not->toBeNull()
            ->and(strtolower($asset->contract_address))->toBe(strtolower((string) config("poisapay.custody.{$key}.usdt_contract")))
            ->and($asset->is_active)->toBeTrue()
            ->and($asset->deposit_enabled)->toBeTrue()
            ->and($asset->decimals)->toBe(6);
    }
});

it('detects and credits a seeded ERC-20 (USDT-on-Ethereum) deposit end to end', function () {
    config(['poisapay.custody_simulated' => false, 'poisapay.custody.seed' => str_repeat('b2', 32), 'providers.blockchain.driver' => 'fake']);
    app()->forgetInstance(BlockchainProvider::class);
    $fake = app(BlockchainProvider::class);

    Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);
    $chain = Chain::where('key', 'ethereum')->first();
    $asset = Asset::where('chain_id', $chain->id)->where('symbol', 'USDT')->first();
    $contract = strtolower($asset->contract_address);

    // Live custody: register a real deposit xpub (replaces the demo placeholder).
    $xpub = app(SignerKeyProvider::class)->accountXpub(ChainType::Ethereum);
    CustodyXpub::where('chain_id', $chain->id)->where('purpose', 'deposit')->update(['xpub' => $xpub, 'next_index' => 0]);

    $user = User::factory()->create();
    $address = app(AllocateDepositAddressAction::class)->execute($user, $chain);
    expect($address->address)->toStartWith('0x'); // real EIP-55 EVM address

    // An inbound USDT transfer to the user's address.
    $txHash = '0x'.str_repeat('ef', 32);
    $fake->pushTransferLog(ChainType::Ethereum, $contract, '0x'.str_repeat('11', 20), $address->address, '2500000', $txHash, 0, 500);

    expect(app(ScanEvmDepositsAction::class)->execute(ChainType::Ethereum))->toBe(1);

    $fake->confirm($txHash, 500, true);
    $fake->setBlock(ChainType::Ethereum, 500 + 12);
    app(AdvanceEvmDepositsAction::class)->execute(ChainType::Ethereum);

    $deposit = $user->deposits()->first();
    expect($deposit->status)->toBe(DepositStatus::Credited)
        ->and(app(LedgerService::class)->availableBalance($user, $asset->id)->baseString())->toBe('2500000');
});
