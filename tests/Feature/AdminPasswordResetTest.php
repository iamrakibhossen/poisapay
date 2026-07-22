<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Op', 'email' => 'op@poisapay.test', 'password' => Hash::make('old-pass-1'), 'is_active' => true,
    ]);
});

it('renders the operator forgot-password page', function () {
    $this->get(route('admin.password.request'))->assertOk()->assertSee('Forgot your password');
});

it('links to forgot-password from the operator login page', function () {
    $this->get(route('admin.login'))->assertOk()->assertSee(route('admin.password.request'));
});

it('sends an operator reset link (admin-scoped notification)', function () {
    Notification::fake();

    $this->post(route('admin.password.email'), ['email' => 'op@poisapay.test'])
        ->assertRedirect()->assertSessionHas('status');

    Notification::assertSentTo($this->admin, AdminResetPasswordNotification::class);
});

it('does not enumerate unknown operator emails', function () {
    Notification::fake();

    $this->post(route('admin.password.email'), ['email' => 'nobody@poisapay.test'])
        ->assertRedirect()->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('resets the operator password with a valid token', function () {
    $token = Password::broker('admins')->createToken($this->admin);

    $this->post(route('admin.password.update'), [
        'token' => $token,
        'email' => 'op@poisapay.test',
        'password' => 'new-pass-123',
        'password_confirmation' => 'new-pass-123',
    ])->assertRedirect(route('admin.login'))->assertSessionHas('status');

    expect(Hash::check('new-pass-123', $this->admin->fresh()->password))->toBeTrue();
});

it('rejects an invalid operator reset token', function () {
    $this->post(route('admin.password.update'), [
        'token' => 'bad-token',
        'email' => 'op@poisapay.test',
        'password' => 'new-pass-123',
        'password_confirmation' => 'new-pass-123',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('old-pass-1', $this->admin->fresh()->password))->toBeTrue();
});
