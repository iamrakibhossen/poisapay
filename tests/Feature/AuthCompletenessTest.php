<?php

declare(strict_types=1);

use App\Domain\Auth\DeviceService;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

it('lets an unverified user fulfill email verification via a signed link', function () {
    updateSetting('email_verification_required', true, 'auth');

    $user = User::factory()->unverified()->create();

    expect($user->hasVerifiedEmail())->toBeFalse();

    $this->actingAs($user);

    // The notice page reports the unverified state.
    $this->get(route('verification.notice'))->assertOk();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
    );

    $this->get($url)->assertRedirect(route('dashboard'));

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('records a user device from a request', function () {
    $user = User::factory()->create();

    $request = Request::create('/login', 'POST', server: [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X) Chrome/120',
        'REMOTE_ADDR' => '203.0.113.9',
    ]);

    app(DeviceService::class)->record($user, $request);

    $device = UserDevice::where('user_id', $user->id)->first();

    expect($device)->not->toBeNull()
        ->and($device->name)->toBe('Chrome on macOS')
        ->and($device->ip_address)->toBe('203.0.113.9');
});

it('stores the chosen locale in the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('locale.switch'), ['locale' => 'bn'])
        ->assertRedirect();

    expect(session('locale'))->toBe('bn')
        ->and($user->fresh()->locale)->toBe('bn');
});
