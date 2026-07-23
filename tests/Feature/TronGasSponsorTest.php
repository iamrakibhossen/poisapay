<?php

declare(strict_types=1);

use App\Domain\Chain\Tron\TronGasSponsor;
use App\Domain\Chain\Tron\TronSweepAction;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Bip32;
use App\Domain\Custody\TronAddressDeriver;
use App\Enums\ChainType;
use App\Enums\SweepStatus;
use App\Models\Chain;
use App\Models\CustodyXpub;
use App\Models\DepositAddress;
use App\Models\GasSponsorship;
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
        'poisapay.custody.tron.gas_max_attempts' => 3,
    ]);
    updateSetting('gas_sponsoring_enabled', true, 'features');

    $xpub = app(SignerKeyProvider::class)->accountXpub(ChainType::Tron);
    $xpubRow = CustodyXpub::create([
        'chain_id' => $tron->id, 'label' => 'Tron deposits', 'xpub' => $xpub,
        'derivation_path' => "m/44'/195'/0'/0/{i}", 'next_index' => 1, 'purpose' => 'deposit', 'is_active' => true,
    ]);
    $this->address = DepositAddress::create([
        'user_id' => User::factory()->create()->id, 'chain_id' => $tron->id, 'xpub_id' => $xpubRow->id,
        'derivation_index' => 0, 'is_watched' => true,
        'address' => (new TronAddressDeriver(new Bip32))->derive(ChainType::Tron, $xpub, 0),
    ]);
});

function fakeGas(string $trxBalanceSun, bool $broadcastOk = true): void
{
    Http::fake([
        '*/wallet/getaccount' => Http::response(['balance' => (int) $trxBalanceSun]),
        '*/wallet/createtransaction' => Http::response(['txID' => str_repeat('c', 64), 'raw_data' => [], 'raw_data_hex' => '0a']),
        '*/wallet/broadcasttransaction' => Http::response(['result' => $broadcastOk]),
    ]);
}

it('reports ready when the address already holds enough TRX', function () {
    fakeGas('40000000'); // 40 TRX ≥ 30 budget

    expect(app(TronGasSponsor::class)->ensure($this->address, $this->asset)->isReady())->toBeTrue();
});

it('sends a TRX top-up and returns pending when underfunded', function () {
    fakeGas('0');

    $result = app(TronGasSponsor::class)->ensure($this->address, $this->asset);

    expect($result->status)->toBe('pending')
        ->and(GasSponsorship::where('target_address', $this->address->address)->first()->status)->toBe('funded');
});

it('dead-letters after exhausting retries', function () {
    fakeGas('0', broadcastOk: false); // broadcast keeps failing

    $last = null;
    foreach (range(1, 4) as $i) {
        $last = app(TronGasSponsor::class)->ensure($this->address, $this->asset);
    }

    expect($last->status)->toBe('failed')
        ->and(GasSponsorship::where('target_address', $this->address->address)->first()->status)->toBe('failed');
});

it('is skipped when the flag is off', function () {
    updateSetting('gas_sponsoring_enabled', false, 'features');
    fakeGas('0');

    expect(app(TronGasSponsor::class)->ensure($this->address, $this->asset)->status)->toBe('skipped');
});

it('makes the sweep wait (Gassing) until gas is ready', function () {
    updateSetting('onchain_sweep_enabled', true, 'features');
    Http::fake([
        '*/wallet/triggerconstantcontract' => Http::response(['constant_result' => [str_pad(dechex(3000000), 64, '0', STR_PAD_LEFT)]]),
        '*/wallet/getaccount' => Http::response(['balance' => 0]), // underfunded → not ready
        '*/wallet/createtransaction' => Http::response(['txID' => str_repeat('c', 64)]),
        '*/wallet/broadcasttransaction' => Http::response(['result' => true]),
    ]);

    $sweep = app(TronSweepAction::class)->execute($this->address, $this->asset);

    expect($sweep->status)->toBe(SweepStatus::Gassing);
});
