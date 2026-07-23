<?php

declare(strict_types=1);

use App\Domain\Chain\Tron\SettleTronHotColdMovesAction;
use App\Domain\Chain\Tron\TronHotColdMoveAction;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Ledger\AccountResolver;
use App\Enums\ChainType;
use App\Enums\LedgerAccountType;
use App\Models\Chain;
use App\Models\CustodyXpub;
use App\Models\TreasuryMove;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    Chain::where('id', $this->asset->chain_id)->update(['is_active' => true]);

    config([
        'poisapay.custody_simulated' => false,
        'poisapay.custody.seed' => '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f',
        'poisapay.custody.tron.usdt_contract' => $this->asset->contract_address,
    ]);
    updateSetting('hot_cold_move_enabled', true, 'features');
    updateSetting('custody.watermark.high.USDT', '2000000', 'custody'); // keep 2 USDT in hot

    CustodyXpub::create([
        'chain_id' => $this->asset->chain_id, 'label' => 'cold',
        'xpub' => app(SignerKeyProvider::class)->accountXpub(ChainType::Tron),
        'derivation_path' => "m/44'/195'/0'/0/{i}", 'next_index' => 0, 'purpose' => 'cold-watch', 'is_active' => true,
    ]);

    seedHotBalance($this->asset, '5000000'); // 5 USDT in hot
});

function tronTreasury(LedgerAccountType $type, int $assetId): string
{
    return ltrim(app(AccountResolver::class)->system($type, $assetId)->fresh('balance')->money()->baseString(), '-');
}

function fakeMove(?int $block): void
{
    Http::fake([
        '*/wallet/triggersmartcontract' => Http::response(['transaction' => ['txID' => str_repeat('d', 64), 'raw_data' => ['contract' => []], 'raw_data_hex' => '0a']]),
        '*/wallet/broadcasttransaction' => Http::response(['result' => true]),
        '*/wallet/gettransactioninfobyid' => Http::response($block === null ? [] : ['blockNumber' => $block, 'receipt' => ['result' => 'SUCCESS']]),
    ]);
}

it('broadcasts a hot->cold move for the excess above the high-watermark', function () {
    fakeMove(null);

    $move = app(TronHotColdMoveAction::class)->execute($this->asset);

    expect($move->status)->toBe('broadcast')
        ->and($move->amount)->toBe('3000000') // 5 − 2 = 3 USDT excess
        ->and(tronTreasury(LedgerAccountType::TreasuryHot, $this->asset->id))->toBe('5000000'); // ledger untouched pre-confirm
});

it('settles the move (hot -> cold) only after confirmation', function () {
    fakeMove(100);

    app(TronHotColdMoveAction::class)->execute($this->asset);
    $settled = app(SettleTronHotColdMovesAction::class)->execute();

    expect($settled)->toBe(1)
        ->and(tronTreasury(LedgerAccountType::TreasuryHot, $this->asset->id))->toBe('2000000')   // 5 − 3
        ->and(tronTreasury(LedgerAccountType::TreasuryCold, $this->asset->id))->toBe('3000000');  // moved to cold
});

it('does nothing when the flag is off', function () {
    updateSetting('hot_cold_move_enabled', false, 'features');
    fakeMove(null);

    expect(app(TronHotColdMoveAction::class)->execute($this->asset))->toBeNull();
});

it('the rebalance command broadcasts a move when over the watermark', function () {
    fakeMove(null);

    $this->artisan('poisapay:rebalance')->assertSuccessful();

    expect(TreasuryMove::where('asset_id', $this->asset->id)->where('status', 'broadcast')->exists())->toBeTrue();
});
