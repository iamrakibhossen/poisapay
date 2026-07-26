<?php

declare(strict_types=1);

use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\Admin;
use App\Models\P2pAd;
use App\Models\P2pMerchantProfile;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    $this->usdt = testAsset('USDT', 6, 'tron');
});

function fmerchant(string $name, array $profile = []): User
{
    $user = User::factory()->create(['name' => $name, 'kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    if ($profile) {
        P2pMerchantProfile::create(array_merge(['user_id' => $user->id], $profile));
    }

    return $user;
}

it('reports featured status against the time window', function () {
    $p = new P2pMerchantProfile;
    expect($p->isFeatured())->toBeFalse();

    $p->featured_until = now()->addDay();
    expect($p->isFeatured())->toBeTrue();

    $p->featured_until = now()->subDay();
    expect($p->isFeatured())->toBeFalse();
});

it('ranks featured merchants first and shows the badge', function () {
    $featured = fmerchant('FeaturedCo', ['featured_until' => now()->addDays(10)]);
    $plain = fmerchant('PlainCo');

    P2pAd::factory()->create(['user_id' => $featured->id, 'asset_id' => $this->usdt->id]);
    P2pAd::factory()->create(['user_id' => $plain->id, 'asset_id' => $this->usdt->id]);

    $this->actingAs(fmerchant('Viewer'))
        ->get(route('p2p', ['side' => 'buy']))
        ->assertOk()
        ->assertSeeInOrder(['FeaturedCo', 'PlainCo'])
        ->assertSee('Featured');
});

it('ranks a featured ad above an express ad', function () {
    $featured = fmerchant('FeaturedCo', ['featured_until' => now()->addDays(10)]);
    $express = fmerchant('ExpressCo');

    P2pAd::factory()->create(['user_id' => $featured->id, 'asset_id' => $this->usdt->id, 'is_express' => false]);
    P2pAd::factory()->create(['user_id' => $express->id, 'asset_id' => $this->usdt->id, 'is_express' => true]);

    $this->actingAs(fmerchant('Viewer'))
        ->get(route('p2p', ['side' => 'buy']))
        ->assertOk()
        ->assertSeeInOrder(['FeaturedCo', 'ExpressCo']);
});

it('lets an operator feature and unfeature a merchant', function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    $admin = Admin::create(['name' => 'Op', 'email' => 'feat@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $admin->syncRoles(['super-admin']);

    $merchant = fmerchant('Shop', ['trade_count' => 5]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.p2p-merchants.feature', $merchant->id), ['days' => 14])
        ->assertRedirect();

    expect(P2pMerchantProfile::where('user_id', $merchant->id)->first()->isFeatured())->toBeTrue();

    // Toggling again unfeatures.
    $this->actingAs($admin, 'admin')->post(route('admin.p2p-merchants.feature', $merchant->id));

    expect(P2pMerchantProfile::where('user_id', $merchant->id)->first()->isFeatured())->toBeFalse();
});

it('forbids featuring without manage-p2p', function () {
    $viewer = Admin::create(['name' => 'Viewer', 'email' => 'v@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $merchant = fmerchant('Shop', ['trade_count' => 1]);

    $this->actingAs($viewer, 'admin')
        ->post(route('admin.p2p-merchants.feature', $merchant->id))
        ->assertForbidden();
});
