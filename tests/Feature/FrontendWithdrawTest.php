<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Enums\KycTier;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $this->user->forceFill(['created_at' => now()->subMonth()])->save();
});

it('renders the withdraw page', function () {
    creditUser($this->user, $this->asset, '5000000');

    actingAs($this->user)->get(route('withdraw.index'))->assertOk()->assertSee('Withdraw');
});

it('renders the network detail form with fee and available balance', function () {
    creditUser($this->user, $this->asset, '5000000');

    actingAs($this->user)->get(route('withdraw.index', ['coin' => 'USDT', 'asset' => $this->asset->id]))
        ->assertOk()
        ->assertSee('Destination address')
        ->assertSee('Network fee');
});

it('submits a crypto withdrawal and reserves funds', function () {
    creditUser($this->user, $this->asset, '5000000');

    actingAs($this->user)->post(route('withdraw.submit'), [
        'assetId' => $this->asset->id, 'toAddress' => 'Tdest123', 'amount' => '1',
    ])->assertRedirect(route('withdraw.index'))->assertSessionHas('success');

    // Amount + fee is no longer available (reserved for the withdrawal).
    expect($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->not->toBe('5000000');
});

it('requires a two-factor code when 2FA is enabled', function () {
    creditUser($this->user, $this->asset, '5000000');
    $this->user->forceFill(['two_factor_secret' => encrypt('SECRET'), 'two_factor_confirmed_at' => now()])->save();

    actingAs($this->user)->post(route('withdraw.submit'), [
        'assetId' => $this->asset->id, 'toAddress' => 'Tdest123', 'amount' => '1',
    ])->assertSessionHasErrors('twoFactorCode');

    // Funds untouched.
    expect($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('5000000');
});

it('requires authentication for the withdraw page', function () {
    $this->get(route('withdraw.index'))->assertRedirect(route('login'));
});
