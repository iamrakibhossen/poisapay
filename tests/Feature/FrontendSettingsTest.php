<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserDevice;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Old Name', 'phone' => null]);
});

it('renders the settings page via a controller (no Livewire)', function () {
    actingAs($this->user)->get(route('settings'))
        ->assertOk()
        ->assertSee('Settings')
        ->assertSee('Profile')
        ->assertSee('Old Name');
});

it('saves the profile and redirects with a flash message', function () {
    actingAs($this->user)->put(route('settings.profile'), [
        'name' => 'New Name', 'phone' => '+8801711000000', 'baseCurrency' => 'USD', 'timezone' => 'UTC',
    ])->assertRedirect(route('settings', ['tab' => 'profile']))->assertSessionHas('success');

    $fresh = $this->user->fresh();
    expect($fresh->name)->toBe('New Name')
        ->and($fresh->phone)->toBe('+8801711000000')
        ->and($fresh->base_currency)->toBe('USD');
});

it('validates the profile name', function () {
    actingAs($this->user)->put(route('settings.profile'), [
        'name' => '', 'baseCurrency' => 'USD', 'timezone' => 'UTC',
    ])->assertSessionHasErrors('name');
});

it('starts 2FA enrolment flashing a QR and recovery codes', function () {
    actingAs($this->user)->post(route('settings.2fa.enable'))
        ->assertRedirect(route('settings', ['tab' => 'security']))
        ->assertSessionHas('twoFactorSetup');

    // The flashed setup renders on the security tab.
    actingAs($this->user)->get(route('settings', ['tab' => 'security']))->assertOk()->assertSee('Recovery codes');
});

it('rejects confirming 2FA with an empty code', function () {
    actingAs($this->user)->post(route('settings.2fa.confirm'), ['confirmCode' => ''])
        ->assertSessionHasErrors('confirmCode');
});

it('rejects sending a phone OTP when no phone is set', function () {
    actingAs($this->user)->post(route('settings.phone.otp'))
        ->assertSessionHasErrors('phone');
});

it('revokes a device scoped to the owner and redirects', function () {
    $device = UserDevice::create([
        'user_id' => $this->user->id, 'name' => 'Test Device', 'fingerprint' => 'fp-1',
        'ip_address' => '1.2.3.4', 'last_used_at' => now(),
    ]);

    actingAs($this->user)->delete(route('settings.device.revoke', $device->id))
        ->assertRedirect(route('settings', ['tab' => 'devices']))->assertSessionHas('success');

    expect(UserDevice::whereKey($device->id)->exists())->toBeFalse();
});

it('requires authentication for the settings page', function () {
    $this->get(route('settings'))->assertRedirect(route('login'));
});
