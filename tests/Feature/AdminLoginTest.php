<?php

declare(strict_types=1);

use App\Models\Admin;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Op', 'email' => 'op@poisapay.test', 'password' => Hash::make('secret-pass-1'), 'is_active' => true,
    ]);
});

it('renders the operator login page', function () {
    $this->get(route('admin.login'))->assertOk()->assertSee('Sign in');
});

it('signs in with valid credentials and redirects to the dashboard', function () {
    $this->post(route('admin.login.attempt'), ['email' => 'op@poisapay.test', 'password' => 'secret-pass-1'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($this->admin, 'admin');
});

it('rejects invalid credentials', function () {
    $this->post(route('admin.login.attempt'), ['email' => 'op@poisapay.test', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest('admin');
});

it('blocks a disabled operator account', function () {
    $this->admin->update(['is_active' => false]);

    $this->post(route('admin.login.attempt'), ['email' => 'op@poisapay.test', 'password' => 'secret-pass-1'])
        ->assertForbidden();

    $this->assertGuest('admin');
});

it('requires a second factor when 2FA is enabled', function () {
    $this->admin->forceFill(['two_factor_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();

    // Step 1: valid credentials → stays out, pending 2FA in the session.
    $this->post(route('admin.login.attempt'), ['email' => 'op@poisapay.test', 'password' => 'secret-pass-1'])
        ->assertRedirect(route('admin.login'))
        ->assertSessionHas('admin_2fa_pending', $this->admin->id);
    $this->assertGuest('admin');

    // The login page now shows the 2FA form.
    $this->get(route('admin.login'))->assertOk()->assertSee('Two-factor check');

    // Step 2: a wrong code is rejected.
    $this->post(route('admin.login.attempt'), ['twoFactorCode' => '000000'])
        ->assertSessionHasErrors('twoFactorCode');
    $this->assertGuest('admin');
});

it('redirects an authenticated operator away from the login page', function () {
    $this->actingAs($this->admin, 'admin')->get(route('admin.login'))->assertRedirect(route('admin.dashboard'));
});
