<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->user = User::factory()->create();
});

it('renders the asset detail page server-side (no Livewire, no JSON)', function () {
    creditUser($this->user, $this->usdt, '1000000');

    actingAs($this->user)->get(route('wallet.show', 'USDT'))
        ->assertOk()
        ->assertSee('USDT')
        ->assertSee('Available');
});

it('shows swaps in the per-asset activity (both directions)', function () {
    $btc = testAsset('BTC', 8, 'tron');
    creditUser($this->user, $this->usdt, '10000000'); // 10 USDT

    // Swap 4 USDT -> BTC.
    $exchange = app(App\Domain\Exchange\ExchangeService::class);
    $quote = $exchange->quote($this->user, $this->usdt, $btc, $this->usdt->money('4000000'));
    $exchange->execute($this->user, $quote, 'swap-test-1');

    // The swap appears on BOTH coins' pages.
    actingAs($this->user)->get(route('wallet.show', 'USDT'))->assertOk()->assertSee('Swap USDT → BTC');
    actingAs($this->user)->get(route('wallet.show', 'BTC'))->assertOk()->assertSee('Swap USDT → BTC');
});

it('404s for an unknown asset symbol', function () {
    actingAs($this->user)->get(route('wallet.show', 'NOPE'))->assertNotFound();
});

it('requires authentication for the asset page', function () {
    $this->get(route('wallet.show', 'USDT'))->assertRedirect(route('login'));
});
