<?php

declare(strict_types=1);

use App\Domain\Chain\Tron\SettleTronSweepsAction;
use App\Domain\Chain\Tron\TronSweepAction;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Bip32;
use App\Domain\Custody\TronAddressDeriver;
use App\Domain\Ledger\AccountResolver;
use App\Enums\ChainType;
use App\Enums\LedgerAccountType;
use App\Enums\SweepStatus;
use App\Models\Chain;
use App\Models\CustodyXpub;
use App\Models\DepositAddress;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $tron = Chain::where('id', $this->asset->chain_id)->first();
    $tron->update(['is_active' => true]);

    config([
        'poisapay.custody_simulated' => false,
        'poisapay.custody.seed' => '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f',
        'poisapay.custody.tron.usdt_contract' => $this->asset->contract_address,
    ]);
    updateSetting('onchain_sweep_enabled', true, 'features');

    $xpub = app(SignerKeyProvider::class)->accountXpub(ChainType::Tron);
    $xpubRow = CustodyXpub::create([
        'chain_id' => $tron->id, 'label' => 'Tron deposits', 'xpub' => $xpub,
        'derivation_path' => "m/44'/195'/0'/0/{i}", 'next_index' => 1, 'purpose' => 'deposit', 'is_active' => true,
    ]);

    $this->user = User::factory()->create();
    $this->address = DepositAddress::create([
        'user_id' => $this->user->id, 'chain_id' => $tron->id, 'xpub_id' => $xpubRow->id,
        'derivation_index' => 0, 'is_watched' => true,
        'address' => (new TronAddressDeriver(new Bip32))->derive(ChainType::Tron, $xpub, 0),
    ]);
});

function fakeTronSweep(string $baseUnits, ?int $block): void
{
    $hex = str_pad(gmp_strval(gmp_init($baseUnits, 10), 16), 64, '0', STR_PAD_LEFT);
    Http::fake([
        '*/wallet/triggerconstantcontract' => Http::response(['constant_result' => [$hex]]),
        '*/wallet/triggersmartcontract' => Http::response(['transaction' => ['txID' => str_repeat('b', 64), 'raw_data' => ['contract' => []], 'raw_data_hex' => '0a']]),
        '*/wallet/broadcasttransaction' => Http::response(['result' => true, 'txid' => str_repeat('b', 64)]),
        '*/wallet/gettransactioninfobyid' => Http::response($block === null ? [] : ['blockNumber' => $block, 'receipt' => ['result' => 'SUCCESS']]),
    ]);
}

function treasuryHotBase(int $assetId): string
{
    return ltrim(app(AccountResolver::class)->system(LedgerAccountType::TreasuryHot, $assetId)->fresh('balance')->money()->baseString(), '-');
}

it('broadcasts a real sweep without touching the ledger', function () {
    fakeTronSweep('3000000', null); // 3 USDT on-chain, tx not yet confirmed

    $sweep = app(TronSweepAction::class)->execute($this->address, $this->asset);

    expect($sweep->status)->toBe(SweepStatus::Broadcast)
        ->and($sweep->amount)->toBe('3000000')
        ->and(treasuryHotBase($this->asset->id))->toBe('0'); // ledger untouched until confirmed
});

it('settles the sweep to the ledger only after the tx confirms', function () {
    fakeTronSweep('3000000', 100); // confirmed in block 100

    $sweep = app(TronSweepAction::class)->execute($this->address, $this->asset);
    $settled = app(SettleTronSweepsAction::class)->execute();

    expect($settled)->toBe(1)
        ->and($sweep->fresh()->status)->toBe(SweepStatus::Swept)
        ->and(treasuryHotBase($this->asset->id))->toBe('3000000'); // moves into hot only after confirmation
});

it('does nothing when the sweep flag is off', function () {
    updateSetting('onchain_sweep_enabled', false, 'features');
    fakeTronSweep('3000000', null);

    expect(app(TronSweepAction::class)->execute($this->address, $this->asset))->toBeNull();
});
