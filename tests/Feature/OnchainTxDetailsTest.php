<?php

declare(strict_types=1);

use App\Domain\Chain\Evm\AdvanceEvmDepositsAction;
use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Evm\ScanEvmDepositsAction;
use App\Domain\Custody\AllocateDepositAddressAction;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Enums\ChainType;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\CustodyXpub;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

it('builds block-explorer URLs per chain', function () {
    Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);

    expect(Chain::where('key', 'ethereum')->first()->explorerTxUrl('0xabc'))->toBe('https://etherscan.io/tx/0xabc')
        ->and(Chain::where('key', 'polygon')->first()->explorerTxUrl('0xdef'))->toBe('https://polygonscan.com/tx/0xdef')
        ->and(Chain::where('key', 'tron')->first()->explorerTxUrl('T123'))->toBe('https://tronscan.org/#/transaction/T123')
        ->and(Chain::where('key', 'ethereum')->first()->explorerTxUrl(null))->toBeNull();
});

it('shows the txid + explorer link on deposit history', function () {
    config(['poisapay.custody_simulated' => false, 'poisapay.custody.seed' => str_repeat('d4', 32), 'providers.blockchain.driver' => 'fake']);
    app()->forgetInstance(BlockchainProvider::class);
    $fake = app(BlockchainProvider::class);
    Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);

    $chain = Chain::where('key', 'ethereum')->first();
    $asset = Asset::where('chain_id', $chain->id)->where('symbol', 'USDT')->first();
    $xpub = app(SignerKeyProvider::class)->accountXpub(ChainType::Ethereum);
    CustodyXpub::where('chain_id', $chain->id)->where('purpose', 'deposit')->update(['xpub' => $xpub, 'next_index' => 0]);

    $user = User::factory()->create();
    $address = app(AllocateDepositAddressAction::class)->execute($user, $chain);

    $txHash = '0x'.str_repeat('ab', 32);
    $fake->pushTransferLog(ChainType::Ethereum, $asset->contract_address, '0x'.str_repeat('11', 20), $address->address, '1000000', $txHash, 0, 100);
    app(ScanEvmDepositsAction::class)->execute(ChainType::Ethereum);
    $fake->confirm($txHash, 100, true);
    $fake->setBlock(ChainType::Ethereum, 100 + 12);
    app(AdvanceEvmDepositsAction::class)->execute(ChainType::Ethereum);

    actingAs($user)->get(route('deposit.history'))
        ->assertOk()
        ->assertSee('https://etherscan.io/tx/'.$txHash, false) // explorer link
        ->assertSee(substr($txHash, 0, 10), false);            // shortened txid
});
