<?php

declare(strict_types=1);

use App\Domain\P2p\P2pPresenceService;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\P2pMerchantProfile;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->service = app(P2pPresenceService::class);
});

it('marks an existing merchant active and stamps last_seen', function () {
    $user = User::factory()->create();
    $profile = P2pMerchantProfile::create(['user_id' => $user->id, 'is_online' => false]);

    $this->service->markActive($user->id);

    $profile->refresh();
    expect($profile->is_online)->toBeTrue()
        ->and($profile->last_seen_at)->not->toBeNull();
});

it('never creates a profile for a non-merchant', function () {
    $user = User::factory()->create();

    $this->service->markActive($user->id);

    expect(P2pMerchantProfile::where('user_id', $user->id)->exists())->toBeFalse();
});

it('keeps a vacationing merchant offline even while active', function () {
    $user = User::factory()->create();
    P2pMerchantProfile::create(['user_id' => $user->id, 'is_online' => false, 'vacation_mode' => true]);

    $this->service->markActive($user->id);

    expect(P2pMerchantProfile::where('user_id', $user->id)->first()->is_online)->toBeFalse();
});

it('sweeps inactive merchants offline but leaves recent ones', function () {
    updateSetting('p2p_presence_timeout_minutes', 10);

    $stale = P2pMerchantProfile::create(['user_id' => User::factory()->create()->id, 'is_online' => true, 'last_seen_at' => now()->subMinutes(20)]);
    $fresh = P2pMerchantProfile::create(['user_id' => User::factory()->create()->id, 'is_online' => true, 'last_seen_at' => now()->subMinutes(2)]);

    $count = $this->service->sweepOffline();

    expect($count)->toBe(1)
        ->and($stale->refresh()->is_online)->toBeFalse()
        ->and($fresh->refresh()->is_online)->toBeTrue();
});

it('runs the sweep command', function () {
    P2pMerchantProfile::create(['user_id' => User::factory()->create()->id, 'is_online' => true, 'last_seen_at' => now()->subHour()]);

    Artisan::call('p2p:sweep-presence');

    expect(P2pMerchantProfile::where('is_online', true)->count())->toBe(0);
});

it('marks a merchant online via the presence middleware on a P2P request', function () {
    $user = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    P2pMerchantProfile::create(['user_id' => $user->id, 'is_online' => false]);

    $this->actingAs($user)->get(route('p2p'))->assertOk();

    expect(P2pMerchantProfile::where('user_id', $user->id)->first()->is_online)->toBeTrue();
});
