<?php

declare(strict_types=1);

use App\Domain\Exchange\ExchangeService;
use App\Domain\Ledger\AccountResolver;
use App\Enums\ConversionContext;
use App\Models\Asset;
use App\Models\TradingPair;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->trx = Asset::firstOrCreate(
        ['symbol' => 'TRX', 'chain_id' => $this->usdt->chain_id, 'contract_address' => null],
        ['name' => 'Tron', 'kind' => 'crypto', 'decimals' => 18],
    );
    app(AccountResolver::class)->ensureSystemAccounts($this->trx->id);
    $this->exchange = app(ExchangeService::class);
    $this->user = User::factory()->create();
    creditUser($this->user, $this->usdt, '10000000');
});

it('uses a per-pair spread override when configured', function () {
    TradingPair::create(['from_asset_id' => $this->usdt->id, 'to_asset_id' => $this->trx->id, 'spread_bps' => 500, 'is_active' => true]);

    $quote = $this->exchange->quote($this->user, $this->usdt, $this->trx, Money::ofBase('10000000', 6, 'USDT'), ConversionContext::Swap);

    expect($quote->spread_bps)->toBe(500);
});

it('rejects a disabled pair', function () {
    TradingPair::create(['from_asset_id' => $this->usdt->id, 'to_asset_id' => $this->trx->id, 'is_active' => false]);

    $this->exchange->quote($this->user, $this->usdt, $this->trx, Money::ofBase('10000000', 6, 'USDT'));
})->throws(RuntimeException::class);

it('restricts to configured pairs when the flag is on', function () {
    updateSetting('exchange_restrict_pairs', true, 'features');
    // no pair configured for USDT->TRX

    $this->exchange->quote($this->user, $this->usdt, $this->trx, Money::ofBase('10000000', 6, 'USDT'));
})->throws(RuntimeException::class);

it('allows any pair when restriction is off (default)', function () {
    updateSetting('exchange_restrict_pairs', false, 'features');

    $quote = $this->exchange->quote($this->user, $this->usdt, $this->trx, Money::ofBase('10000000', 6, 'USDT'));

    expect($quote->to_amount)->not->toBe('0');
});
