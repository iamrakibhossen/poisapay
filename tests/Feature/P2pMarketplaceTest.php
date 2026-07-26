<?php

declare(strict_types=1);

use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\P2pAd;
use App\Models\P2pMerchantProfile;
use App\Models\P2pPaymentMethod;
use App\Models\User;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->viewer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->actingAs($this->viewer);
});

/** A seller running one live SELL ad, with an optional reputation profile + rails. */
function seller(string $name, array $ad = [], array $profile = [], array $methodKeys = []): User
{
    $user = User::factory()->create(['name' => $name, 'kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);

    $advert = P2pAd::factory()->create(array_merge([
        'user_id' => $user->id,
        'asset_id' => test()->usdt->id,
    ], $ad));

    if ($methodKeys) {
        $ids = P2pPaymentMethod::whereIn('key', $methodKeys)->pluck('id')->all();
        $advert->paymentMethods()->sync($ids);
    }

    if ($profile) {
        P2pMerchantProfile::create(array_merge(['user_id' => $user->id], $profile));
    }

    return $user;
}

it('renders the marketplace with filter params applied', function () {
    seller('AliceSeller');

    $this->get(route('p2p', ['side' => 'buy', 'sort' => 'completion', 'verified' => 1, 'online' => 1, 'amount' => '50', 'q' => 'Alice']))
        ->assertOk();
});

it('filters by payment method', function () {
    seller('BkashSeller', methodKeys: ['bkash']);
    seller('NagadSeller', methodKeys: ['nagad']);

    $bkashId = P2pPaymentMethod::where('key', 'bkash')->value('id');

    $this->get(route('p2p', ['side' => 'buy', 'method' => $bkashId]))
        ->assertOk()
        ->assertSee('BkashSeller')
        ->assertDontSee('NagadSeller');
});

it('filters by order amount against the ad limits', function () {
    seller('WideSeller', ad: ['min_order' => '10.00', 'max_order' => '1000.00']);
    seller('HighSeller', ad: ['min_order' => '500.00', 'max_order' => '1000.00']);

    $this->get(route('p2p', ['side' => 'buy', 'amount' => '200']))
        ->assertOk()
        ->assertSee('WideSeller')
        ->assertDontSee('HighSeller');
});

it('filters to verified merchants only', function () {
    seller('VerifiedSeller', profile: ['level' => 3, 'trade_count' => 60, 'completed_count' => 60]);
    seller('RookieSeller', profile: ['level' => 0, 'trade_count' => 1, 'completed_count' => 1]);

    $this->get(route('p2p', ['side' => 'buy', 'verified' => 1]))
        ->assertOk()
        ->assertSee('VerifiedSeller')
        ->assertDontSee('RookieSeller');
});

it('filters to online merchants only', function () {
    seller('OnlineSeller', profile: ['is_online' => true]);
    seller('OfflineSeller', profile: ['is_online' => false]);

    $this->get(route('p2p', ['side' => 'buy', 'online' => 1]))
        ->assertOk()
        ->assertSee('OnlineSeller')
        ->assertDontSee('OfflineSeller');
});

it('sorts by most trades', function () {
    seller('BusySeller', ['priority' => 0], ['trade_count' => 500, 'completed_count' => 500]);
    seller('QuietSeller', ['priority' => 0], ['trade_count' => 3, 'completed_count' => 3]);

    $this->get(route('p2p', ['side' => 'buy', 'sort' => 'trades']))
        ->assertOk()
        ->assertSeeInOrder(['BusySeller', 'QuietSeller']);
});

it('hides vacation-mode, self, inactive and empty ads', function () {
    seller('VacationSeller', profile: ['vacation_mode' => true]);
    seller('EmptySeller', ad: ['available_amount' => '0']);
    P2pAd::factory()->create(['user_id' => $this->viewer->id, 'asset_id' => $this->usdt->id]); // self
    seller('GoodSeller');

    $this->get(route('p2p', ['side' => 'buy']))
        ->assertOk()
        ->assertSee('GoodSeller')
        ->assertDontSee('VacationSeller')
        ->assertDontSee('EmptySeller');
});
