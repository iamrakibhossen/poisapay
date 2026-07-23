<?php

declare(strict_types=1);

use App\Domain\Chain\Tron\SettleTronSweepsAction;
use App\Domain\Chain\Tron\TronSweepAction;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Bip32;
use App\Domain\Custody\TronAddressDeriver;
use App\Domain\Reconciliation\CustodyReconciler;
use App\Enums\ChainType;
use App\Enums\SweepStatus;
use App\Models\Chain;
use App\Models\CustodyXpub;
use App\Models\DepositAddress;
use App\Models\OnchainTx;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * End-to-end custody validation: a real (faked-chain) sweep settles to the ledger only
 * after confirmation, and the reconciler then confirms on-chain hot == ledger treasury:hot
 * (zero drift). Exercises the invariant that ties the whole system together — the books
 * follow the chain, and reconciliation proves it.
 */
beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    Chain::where('id', $this->asset->chain_id)->update(['is_active' => true]);

    config([
        'poisapay.custody_simulated' => false,
        'poisapay.custody.seed' => '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f',
        'poisapay.custody.tron.usdt_contract' => $this->asset->contract_address,
    ]);
    updateSetting('onchain_sweep_enabled', true, 'features'); // gas sponsoring stays OFF → direct broadcast

    $xpub = app(SignerKeyProvider::class)->accountXpub(ChainType::Tron);
    $xpubRow = CustodyXpub::create([
        'chain_id' => $this->asset->chain_id, 'label' => 'Tron deposits', 'xpub' => $xpub,
        'derivation_path' => "m/44'/195'/0'/0/{i}", 'next_index' => 1, 'purpose' => 'deposit', 'is_active' => true,
    ]);
    $this->address = DepositAddress::create([
        'user_id' => User::factory()->create()->id, 'chain_id' => $this->asset->chain_id, 'xpub_id' => $xpubRow->id,
        'derivation_index' => 0, 'is_watched' => true,
        'address' => (new TronAddressDeriver(new Bip32))->derive(ChainType::Tron, $xpub, 0),
    ]);
});

it('sweeps, settles after confirmation, and reconciliation confirms zero drift', function () {
    // On-chain: 3 USDT sitting at the deposit address; the sweep tx confirms in block 100.
    $hex = str_pad(dechex(3000000), 64, '0', STR_PAD_LEFT);
    Http::fake([
        '*/wallet/triggerconstantcontract' => Http::response(['constant_result' => [$hex]]),
        '*/wallet/triggersmartcontract' => Http::response(['transaction' => ['txID' => str_repeat('f', 64), 'raw_data' => ['contract' => []], 'raw_data_hex' => '0a']]),
        '*/wallet/broadcasttransaction' => Http::response(['result' => true]),
        '*/wallet/gettransactioninfobyid' => Http::response(['blockNumber' => 100, 'receipt' => ['result' => 'SUCCESS']]),
    ]);

    // 1. Sweep broadcasts; ledger untouched until confirmed.
    $sweep = app(TronSweepAction::class)->execute($this->address, $this->asset);
    expect($sweep->status)->toBe(SweepStatus::Broadcast);
    expect(OnchainTx::find($sweep->onchain_tx_id))->not->toBeNull();

    // 2. Settle after confirmation → treasury:hot credited.
    expect(app(SettleTronSweepsAction::class)->execute())->toBe(1);
    expect($sweep->fresh()->status)->toBe(SweepStatus::Swept);

    // 3. Reconcile: on-chain hot (3 USDT) == ledger treasury:hot (3 USDT) → no drift.
    $row = collect(app(CustodyReconciler::class)->reconcile())->firstWhere('asset', 'USDT');
    expect($row['onchain'])->toBe('3000000')
        ->and($row['ledger'])->toBe('3000000')
        ->and($row['drift'])->toBe('0')
        ->and($row['breached'])->toBeFalse();
});
