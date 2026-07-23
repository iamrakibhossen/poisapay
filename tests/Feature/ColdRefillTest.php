<?php

declare(strict_types=1);

use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Treasury\RequestColdRefillAction;
use App\Domain\Treasury\SettleColdRefillAction;
use App\Enums\ChainType;
use App\Enums\LedgerAccountType;
use App\Models\Chain;
use App\Models\CustodyXpub;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    Chain::where('id', $this->asset->chain_id)->update(['is_active' => true]);

    config([
        'poisapay.custody_simulated' => false,
        'poisapay.custody.seed' => '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f',
        'poisapay.custody.tron.usdt_contract' => $this->asset->contract_address,
    ]);
    updateSetting('hot_cold_refill_enabled', true, 'features');
    updateSetting('custody.watermark.low.USDT', '1000000', 'custody');  // floor 1 USDT
    updateSetting('custody.watermark.high.USDT', '5000000', 'custody'); // refill target 5 USDT

    CustodyXpub::create([
        'chain_id' => $this->asset->chain_id, 'label' => 'cold',
        'xpub' => app(SignerKeyProvider::class)->accountXpub(ChainType::Tron),
        'derivation_path' => "m/44'/195'/0'/0/{i}", 'next_index' => 0, 'purpose' => 'cold-watch', 'is_active' => true,
    ]);
});

function coldTreasury(LedgerAccountType $type, int $assetId): string
{
    return ltrim(app(AccountResolver::class)->system($type, $assetId)->fresh('balance')->money()->baseString(), '-');
}

it('raises a refill request when hot is below the low-watermark', function () {
    seedHotBalance($this->asset, '500000'); // 0.5 USDT < 1 floor

    $request = app(RequestColdRefillAction::class)->execute($this->asset);

    expect($request->status)->toBe('requested')
        ->and($request->amount)->toBe('4500000') // high 5 − hot 0.5
        ->and($request->cold_address)->not->toBeNull();
});

it('does not raise a request when hot is above the low-watermark', function () {
    seedHotBalance($this->asset, '3000000'); // 3 USDT > floor

    expect(app(RequestColdRefillAction::class)->execute($this->asset))->toBeNull();
});

it('is idempotent — one open request per asset', function () {
    seedHotBalance($this->asset, '500000');

    app(RequestColdRefillAction::class)->execute($this->asset);

    expect(app(RequestColdRefillAction::class)->execute($this->asset))->toBeNull();
});

it('settles cold -> hot after the offline-signed tx confirms', function () {
    seedHotBalance($this->asset, '500000');
    $request = app(RequestColdRefillAction::class)->execute($this->asset);

    // Operator approves, signs offline, broadcasts, records the tx hash.
    $request->update(['status' => 'broadcast', 'tx_hash' => str_repeat('e', 64)]);
    Http::fake(['*/wallet/gettransactioninfobyid' => Http::response(['blockNumber' => 100, 'receipt' => ['result' => 'SUCCESS']])]);

    $settled = app(SettleColdRefillAction::class)->execute();

    expect($settled)->toBe(1)
        ->and($request->fresh()->status)->toBe('settled')
        ->and(coldTreasury(LedgerAccountType::TreasuryHot, $this->asset->id))->toBe('5000000'); // 0.5 + 4.5 refill
});
