<?php

declare(strict_types=1);

use App\Domain\P2p\CreateAdAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\P2pAd;
use App\Models\P2pMerchantProfile;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    $this->usdt = testAsset('USDT', 6, 'tron');
});

function expressAdInput(array $overrides = []): array
{
    return array_merge([
        'side' => 'sell',
        'asset_id' => test()->usdt->id,
        'decimals' => 6,
        'symbol' => 'USDT',
        'fiat_currency' => 'BDT',
        'price_type' => 'fixed',
        'fixed_price' => '120',
        'min_order' => '10',
        'max_order' => '100000',
        'total_amount' => Money::ofDecimal('1000', 6, 'USDT')->baseString(),
        'payment_window_min' => 15,
        'is_express' => true,
    ], $overrides);
}

function merchant(string $name = 'M'): User
{
    $user = User::factory()->create(['name' => $name, 'kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    // Sell ads via CreateAdAction require the seller to actually hold the USDT.
    creditUser($user, test()->usdt, '2000000000'); // 2000 USDT

    return $user;
}

it('lets a new merchant (no release history) offer express', function () {
    $ad = app(CreateAdAction::class)->execute(merchant(), expressAdInput());

    expect($ad->is_express)->toBeTrue();
});

it('lets a fast merchant offer express', function () {
    $user = merchant();
    P2pMerchantProfile::create(['user_id' => $user->id, 'avg_release_seconds' => 120]); // ≤ 300s default

    $ad = app(CreateAdAction::class)->execute($user, expressAdInput());

    expect($ad->is_express)->toBeTrue();
});

it('blocks a slow merchant from offering express', function () {
    $user = merchant();
    P2pMerchantProfile::create(['user_id' => $user->id, 'avg_release_seconds' => 900]); // > 300s

    expect(fn () => app(CreateAdAction::class)->execute($user, expressAdInput()))
        ->toThrow(RuntimeException::class, 'fast average release');
});

it('creates a normal ad when express is not requested', function () {
    $ad = app(CreateAdAction::class)->execute(merchant(), expressAdInput(['is_express' => false]));

    expect($ad->is_express)->toBeFalse();
});

it('filters the marketplace to express ads only', function () {
    P2pAd::factory()->create(['user_id' => merchant('FastCo')->id, 'asset_id' => $this->usdt->id, 'is_express' => true]);
    P2pAd::factory()->create(['user_id' => merchant('SlowCo')->id, 'asset_id' => $this->usdt->id, 'is_express' => false]);

    $this->actingAs(merchant('Viewer'))
        ->get(route('p2p', ['side' => 'buy', 'express' => 1]))
        ->assertOk()
        ->assertSee('FastCo')
        ->assertDontSee('SlowCo');
});

it('ranks express ads first under the recommended sort', function () {
    P2pAd::factory()->create(['user_id' => merchant('PlainCo')->id, 'asset_id' => $this->usdt->id, 'is_express' => false, 'priority' => 0]);
    P2pAd::factory()->create(['user_id' => merchant('ExpressCo')->id, 'asset_id' => $this->usdt->id, 'is_express' => true, 'priority' => 0]);

    $this->actingAs(merchant('Viewer'))
        ->get(route('p2p', ['side' => 'buy']))
        ->assertOk()
        ->assertSeeInOrder(['ExpressCo', 'PlainCo']);
});
