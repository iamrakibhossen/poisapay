<?php

declare(strict_types=1);

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Evm\EvmHotColdMoveAction;
use App\Domain\Chain\Evm\FakeBlockchainProvider;
use App\Domain\Chain\Evm\SettleEvmHotColdMovesAction;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Ledger\AccountResolver;
use App\Enums\ChainType;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\Currency;
use App\Models\CustodyXpub;
use App\Models\OnchainTx;

beforeEach(function () {
    config([
        'poisapay.custody_simulated' => false,
        'poisapay.custody.seed' => str_repeat('a1', 32),
        'providers.blockchain.driver' => 'fake',
    ]);
    app()->forgetInstance(BlockchainProvider::class);
    $this->fake = app(BlockchainProvider::class);
    expect($this->fake)->toBeInstanceOf(FakeBlockchainProvider::class);
    updateSetting('hot_cold_move_enabled', true, 'features');
    updateSetting('custody.watermark.high.USDT', '2000000', 'custody');

    $this->chain = Chain::create(['key' => 'ethereum', 'name' => 'Ethereum', 'native_symbol' => 'ETH', 'min_confirmations' => 12, 'is_evm' => true, 'is_active' => true]);
    $currency = Currency::firstOrCreate(['symbol' => 'USDT'], ['name' => 'Tether', 'kind' => 'crypto', 'is_stablecoin' => true, 'is_active' => true]);
    $this->asset = Asset::create([
        'currency_id' => $currency->id, 'symbol' => 'USDT', 'name' => 'USDT', 'kind' => 'crypto',
        'chain_id' => $this->chain->id, 'contract_address' => strtolower((string) config('poisapay.custody.ethereum.usdt_contract')), 'decimals' => 6,
        'is_stablecoin' => true, 'is_active' => true, 'withdrawal_min' => '0', 'withdrawal_fee' => '0',
    ]);
    app(AccountResolver::class)->ensureSystemAccounts($this->asset->id);

    CustodyXpub::create([
        'chain_id' => $this->chain->id, 'label' => 'cold',
        'xpub' => app(SignerKeyProvider::class)->accountXpub(ChainType::Ethereum),
        'derivation_path' => "m/44'/60'/0'/0", 'next_index' => 0, 'purpose' => 'cold-watch', 'is_active' => true,
    ]);

    seedHotBalance($this->asset, '5000000'); // 5 USDT in hot
});

function evmTreasury(LedgerAccountType $type, int $assetId): string
{
    return ltrim(app(AccountResolver::class)->system($type, $assetId)->fresh('balance')->money()->baseString(), '-');
}

it('broadcasts an EVM hot->cold move for the excess above the high-watermark', function () {
    $this->fake->setBlock(ChainType::Ethereum, 100);

    $move = app(EvmHotColdMoveAction::class)->execute($this->asset);

    expect($move->status)->toBe('broadcast')
        ->and($move->amount)->toBe('3000000') // 5 − 2
        ->and(evmTreasury(LedgerAccountType::TreasuryHot, $this->asset->id))->toBe('5000000'); // ledger untouched
});

it('settles the EVM move (hot -> cold) only after confirmation depth', function () {
    $this->fake->setBlock(ChainType::Ethereum, 100);
    $move = app(EvmHotColdMoveAction::class)->execute($this->asset);

    $tx = OnchainTx::find($move->onchain_tx_id);
    $this->fake->confirm($tx->tx_hash, 100, true);
    $this->fake->setBlock(ChainType::Ethereum, 100 + 12);

    $settled = app(SettleEvmHotColdMovesAction::class)->execute();

    expect($settled)->toBe(1)
        ->and(evmTreasury(LedgerAccountType::TreasuryHot, $this->asset->id))->toBe('2000000')
        ->and(evmTreasury(LedgerAccountType::TreasuryCold, $this->asset->id))->toBe('3000000');
});

it('does nothing when the flag is off', function () {
    updateSetting('hot_cold_move_enabled', false, 'features');

    expect(app(EvmHotColdMoveAction::class)->execute($this->asset))->toBeNull();
});
