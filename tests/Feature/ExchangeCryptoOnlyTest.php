<?php

declare(strict_types=1);

use App\Domain\Exchange\ExchangeService;
use App\Domain\Ledger\AccountResolver;
use App\Enums\ConversionContext;
use App\Models\Asset;
use App\Models\User;
use App\Support\Money;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->trx = Asset::firstOrCreate(
        ['symbol' => 'TRX', 'chain_id' => $this->usdt->chain_id, 'contract_address' => null],
        ['name' => 'Tron', 'kind' => 'crypto', 'decimals' => 18],
    );
    app(AccountResolver::class)->ensureSystemAccounts($this->trx->id);
    seedInventory($this->trx, '100000000000000000000000');

    $this->usd = fiatAsset('USD');
    $this->eur = fiatAsset('EUR');

    $this->exchange = app(ExchangeService::class);
    $this->user = User::factory()->create();
});

$msg = 'Only cryptocurrency-to-cryptocurrency exchanges are supported.';

it('rejects a fiat -> crypto swap quote', function () use ($msg) {
    expect(fn () => $this->exchange->quote($this->user, $this->usd, $this->usdt, Money::ofDecimal('10', 2, 'USD'), ConversionContext::Swap))
        ->toThrow(RuntimeException::class, $msg);
});

it('rejects a crypto -> fiat swap quote', function () use ($msg) {
    expect(fn () => $this->exchange->quote($this->user, $this->usdt, $this->usd, Money::ofDecimal('10', 6, 'USDT'), ConversionContext::Swap))
        ->toThrow(RuntimeException::class, $msg);
});

it('rejects a fiat -> fiat swap quote', function () use ($msg) {
    expect(fn () => $this->exchange->quote($this->user, $this->usd, $this->eur, Money::ofDecimal('10', 2, 'USD'), ConversionContext::Swap))
        ->toThrow(RuntimeException::class, $msg);
});

it('allows a crypto -> crypto swap quote', function () {
    $quote = $this->exchange->quote($this->user, $this->usdt, $this->trx, Money::ofDecimal('10', 6, 'USDT'), ConversionContext::Swap);

    expect($quote->to_amount)->not->toBe('0');
});

it('still allows crypto -> fiat under the CardSettle context (auto card conversion)', function () {
    // Card settlement legitimately converts crypto to the card's fiat currency —
    // the crypto-only rule is scoped to the user-initiated Swap context.
    $quote = $this->exchange->quote($this->user, $this->usdt, $this->usd, Money::ofDecimal('10', 6, 'USDT'), ConversionContext::CardSettle);

    expect($quote->to_amount)->not->toBe('0');
});

it('rejects a fiat pair through the web exchange.quote endpoint', function () use ($msg) {
    creditUser($this->user, $this->usdt, '10000000');

    actingAs($this->user)->post(route('exchange.quote'), [
        'fromAssetId' => $this->usd->id, 'toAssetId' => $this->usdt->id, 'fromAmount' => '10',
    ])->assertSessionHasErrors(['fromAmount' => $msg]);
});

it('rejects a fiat pair through the swap API', function () use ($msg) {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/swaps/quote', ['from' => 'USD', 'to' => 'USDT', 'amount' => '10'])
        ->assertStatus(422)
        ->assertJsonFragment(['message' => $msg]);
});

it('never lists fiat assets in the exchange selectors', function () {
    creditUser($this->user, $this->usdt, '10000000');

    $response = actingAs($this->user)->get(route('exchange.index'))->assertOk();

    $coinSymbols = collect($response->viewData('coins'))->pluck('symbol');
    $fromIds = $response->viewData('fromAssetIds');

    expect($coinSymbols)->toContain('USDT')
        ->and($coinSymbols)->not->toContain('USD')
        ->and($coinSymbols)->not->toContain('EUR')
        ->and($fromIds)->not->toContain($this->usd->id)
        ->and($fromIds)->not->toContain($this->eur->id);
});
