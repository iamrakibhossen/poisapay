<?php

declare(strict_types=1);

use App\Models\Asset;
use App\Models\Currency;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Old Name', 'phone' => null]);

    // Base-currency options come from real fiat assets — seed USD so it is selectable.
    $usd = Currency::firstOrCreate(['symbol' => 'USD'], ['name' => 'US Dollar', 'kind' => 'fiat']);
    Asset::firstOrCreate(
        ['symbol' => 'USD', 'chain_id' => null, 'contract_address' => 'FIAT_USD'],
        ['currency_id' => $usd->id, 'name' => 'US Dollar', 'kind' => 'fiat', 'currency_code' => 'USD', 'decimals' => 2, 'is_active' => true],
    );
});

it('renders the settings page via a controller (no Livewire)', function () {
    actingAs($this->user)->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Settings')
        ->assertSee('Profile')
        ->assertSee('Old Name');
});

it('saves the profile and redirects with a flash message', function () {
    actingAs($this->user)->put(route('settings.profile'), [
        'name' => 'New Name', 'phone' => '+8801711000000', 'baseCurrency' => 'USD', 'timezone' => 'UTC',
    ])->assertRedirect(route('settings.index', ['tab' => 'profile']))->assertSessionHas('success');

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

it('uploads a profile picture to the public disk', function () {
    Storage::fake('public');

    actingAs($this->user)->put(route('settings.profile'), [
        'name' => 'Old Name', 'baseCurrency' => 'USD', 'timezone' => 'UTC',
        'avatar' => UploadedFile::fake()->image('me.jpg', 256, 256),
    ])->assertRedirect()->assertSessionHas('success');

    $path = $this->user->fresh()->image;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('removes the current profile picture when asked', function () {
    Storage::fake('public');
    $old = UploadedFile::fake()->image('old.jpg')->store('avatars/'.$this->user->id, 'public');
    $this->user->update(['image' => $old]);

    actingAs($this->user)->put(route('settings.profile'), [
        'name' => 'Old Name', 'baseCurrency' => 'USD', 'timezone' => 'UTC', 'remove_avatar' => '1',
    ])->assertRedirect()->assertSessionHas('success');

    expect($this->user->fresh()->image)->toBeNull();
    Storage::disk('public')->assertMissing($old);
});

it('rejects a non-image avatar upload', function () {
    actingAs($this->user)->put(route('settings.profile'), [
        'name' => 'Old Name', 'baseCurrency' => 'USD', 'timezone' => 'UTC',
        'avatar' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors('avatar');
});

it('starts 2FA enrolment flashing a QR and recovery codes', function () {
    actingAs($this->user)->post(route('settings.2fa.enable'))
        ->assertRedirect(route('settings.index', ['tab' => 'security']))
        ->assertSessionHas('twoFactorSetup');

    // The flashed setup renders on the security tab.
    actingAs($this->user)->get(route('settings.index', ['tab' => 'security']))->assertOk()->assertSee('Recovery codes');
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
        ->assertRedirect(route('settings.index', ['tab' => 'devices']))->assertSessionHas('success');

    expect(UserDevice::whereKey($device->id)->exists())->toBeFalse();
});

it('requires authentication for the settings page', function () {
    $this->get(route('settings.index'))->assertRedirect(route('login'));
});
