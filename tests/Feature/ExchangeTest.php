<?php

declare(strict_types=1);

use App\Domain\Exchange\ExchangeService;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\LedgerService;
use App\Enums\ConversionContext;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    // A second asset to swap into (native TRX on the same chain).
    $this->trx = Asset::firstOrCreate(
        ['symbol' => 'TRX', 'chain_id' => $this->usdt->chain_id, 'contract_address' => null],
        ['name' => 'Tron', 'kind' => 'crypto', 'decimals' => 18],
    );
    app(AccountResolver::class)->ensureSystemAccounts($this->trx->id);

    // House injects its own TRX into the hot wallet as tradable dealer inventory,
    // which internal swaps deliver the to-asset from (DR treasury:hot / CR dealer:inventory).
    seedInventory($this->trx, '100000000000000000000000');

    $this->ledger = app(LedgerService::class);
});

it('quotes and executes a swap, moving both balances atomically', function () {
    $user = User::factory()->create();
    creditUser($user, $this->usdt, '10000000'); // 10 USDT

    $exchange = app(ExchangeService::class);
    $quote = $exchange->quote($user, $this->usdt, $this->trx, Money::ofBase('10000000', 6, 'USDT'), ConversionContext::Swap);

    expect($quote->to_amount)->not->toBe('0');

    $exchange->execute($user, $quote, 'swap:1');

    expect($this->ledger->availableBalance($user, $this->usdt->id)->baseString())->toBe('0')
        ->and($this->ledger->availableBalance($user, $this->trx->id)->isPositive())->toBeTrue();
});

it('books the spread as fx:spread_income (admin profit) on a swap', function () {
    $user = User::factory()->create();
    creditUser($user, $this->usdt, '10000000'); // 10 USDT

    $exchange = app(ExchangeService::class);
    $quote = $exchange->quote($user, $this->usdt, $this->trx, Money::ofBase('10000000', 6, 'USDT'), ConversionContext::Swap);
    $exchange->execute($user, $quote, 'swap:profit');

    // Spread = 10 USDT × spread_bps / 10000, booked in the from-asset.
    $expected = (string) (intdiv(10000000 * $quote->spread_bps, 10000));
    $spreadAccount = app(AccountResolver::class)->system(LedgerAccountType::FxSpreadIncome, $this->usdt->id);

    expect($quote->spread_bps)->toBeGreaterThan(0)
        ->and($spreadAccount->fresh('balance')->money()->baseString())->toBe($expected);
});

it('rejects an expired quote', function () {
    $user = User::factory()->create();
    creditUser($user, $this->usdt, '10000000');

    $exchange = app(ExchangeService::class);
    $quote = $exchange->quote($user, $this->usdt, $this->trx, Money::ofBase('10000000', 6, 'USDT'));
    $quote->update(['expires_at' => now()->subMinute()]);

    $exchange->execute($user, $quote, 'swap:expired');
})->throws(RuntimeException::class);
