<?php

declare(strict_types=1);

use App\Domain\Reconciliation\CustodyReconciler;
use App\Domain\Reconciliation\ReconciliationService;
use App\Models\Chain;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    Chain::where('id', $this->asset->chain_id)->update(['is_active' => true]);
    config([
        'poisapay.custody_simulated' => false,
        'poisapay.custody.seed' => '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f',
        'poisapay.custody.tron.usdt_contract' => $this->asset->contract_address,
    ]);
});

function fakeTronBalance(string $baseUnits): void
{
    $hex = str_pad(gmp_strval(gmp_init($baseUnits, 10), 16), 64, '0', STR_PAD_LEFT);
    Http::fake(['*/wallet/triggerconstantcontract' => Http::response(['constant_result' => [$hex]])]);
}

it('reports no drift when on-chain balance matches the ledger', function () {
    fakeTronBalance('0'); // ledger treasury:hot starts at 0 → matches

    $report = app(CustodyReconciler::class)->reconcile();

    expect($report)->toHaveCount(1)
        ->and($report[0]['chain'])->toBe('tron')
        ->and($report[0]['breached'])->toBeFalse()
        ->and($report[0]['drift'])->toBe('0');
});

it('flags drift when the chain holds more than the ledger records', function () {
    fakeTronBalance('5000000'); // 5 USDT on-chain, ledger records 0 → 5 USDT drift

    $report = app(CustodyReconciler::class)->reconcile();

    expect($report[0]['breached'])->toBeTrue()
        ->and($report[0]['onchain'])->toBe('5000000')
        ->and($report[0]['ledger'])->toBe('0')
        ->and($report[0]['drift'])->toBe('5000000');
});

it('is a no-op under simulated custody', function () {
    config(['poisapay.custody_simulated' => true]);

    expect(app(CustodyReconciler::class)->reconcile())->toBe([]);
});

it('populates a reconciliation run onchain_controlled from the chain probe', function () {
    fakeTronBalance('3000000'); // 3 USDT held on-chain by the hot wallet

    $run = app(ReconciliationService::class)->runForAsset($this->asset->fresh());

    expect($run->onchain_controlled)->toBe('3000000');
});
