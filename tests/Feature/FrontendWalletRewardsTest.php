<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->user = User::factory()->create();
});

it('renders the wallet page server-side (no Livewire, no JSON)', function () {
    creditUser($this->user, $this->usdt, '2500000');

    actingAs($this->user)->get(route('wallet'))
        ->assertOk()
        ->assertSee('Wallet')
        ->assertSee('USDT');
});

it('filters wallets via the query string', function () {
    creditUser($this->user, $this->usdt, '2500000');

    actingAs($this->user)->get(route('wallet', ['filter' => 'fiat']))
        ->assertOk()
        ->assertSee('No assets found');
});

it('toggles a favorite asset and redirects back', function () {
    actingAs($this->user)->post(route('wallet.favorite', $this->usdt->id))
        ->assertRedirect();

    expect($this->user->favoriteAssets()->pluck('assets.id')->all())->toContain($this->usdt->id);
});

it('requires authentication for the wallet page', function () {
    $this->get(route('wallet'))->assertRedirect(route('login'));
});

it('renders the rewards page server-side (no Livewire, no JSON)', function () {
    actingAs($this->user)->get(route('rewards'))
        ->assertOk()
        ->assertSee('Rewards & Referrals')
        ->assertSee('No rewards yet');
});

it('requires authentication for the rewards page', function () {
    $this->get(route('rewards'))->assertRedirect(route('login'));
});
