<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    $this->admin = Admin::create(['name' => 'Op', 'email' => 'imp@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $this->admin->syncRoles(['super-admin']);
    $this->target = User::factory()->create(['name' => 'Target User']);
});

it('lets an authorized operator impersonate a user', function () {
    actingAs($this->admin, 'admin')
        ->post(route('admin.impersonate', $this->target->id))
        ->assertRedirect(route('dashboard'));

    expect(auth('web')->id())->toBe($this->target->id)
        ->and(session('impersonator_id'))->toBe($this->admin->id);
});

it('stops impersonation and clears the session', function () {
    actingAs($this->admin, 'admin')->post(route('admin.impersonate', $this->target->id));

    post(route('impersonate.stop'))->assertRedirect(route('admin.users'));

    expect(auth('web')->check())->toBeFalse()
        ->and(session()->has('impersonator_id'))->toBeFalse();
});

it('forbids an operator without the impersonate permission', function () {
    $plain = Admin::create(['name' => 'Plain', 'email' => 'plain@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    // No roles/permissions granted.

    actingAs($plain, 'admin')
        ->post(route('admin.impersonate', $this->target->id))
        ->assertForbidden();
});

it('refuses to nest a second impersonation', function () {
    $other = User::factory()->create();

    actingAs($this->admin, 'admin')->post(route('admin.impersonate', $this->target->id));

    // Already impersonating -> conflict.
    post(route('admin.impersonate', $other->id))->assertStatus(409);
});
