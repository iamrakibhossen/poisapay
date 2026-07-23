<?php

declare(strict_types=1);

use App\Models\Admin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
});

/** A super-admin operator on the admin guard. */
function superAdmin(): Admin
{
    $admin = Admin::create([
        'name' => 'Super', 'email' => 'super@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $admin->syncRoles(['super-admin']);

    return $admin;
}

it('loads the roles page for a super-admin', function () {
    actingAs(superAdmin(), 'admin')->get(route('admin.roles'))->assertOk();
});

it('loads the administrators page for a super-admin', function () {
    actingAs(superAdmin(), 'admin')->get(route('admin.administrators'))->assertOk();
});

it('creates a role and syncs its permissions', function () {
    $perms = Permission::where('guard_name', 'admin')->take(2)->pluck('name')->all();

    actingAs(superAdmin(), 'admin')
        ->post(route('admin.roles.save'), [
            'name' => 'support-lead',
            'permissions' => $perms,
        ])
        ->assertRedirect(route('admin.roles'));

    $role = Role::where('guard_name', 'admin')->where('name', 'support-lead')->first();
    expect($role)->not->toBeNull();
    expect($role->permissions->pluck('name')->all())->toEqualCanonicalizing($perms);
});

it('will not delete a protected role', function () {
    $role = Role::where('guard_name', 'admin')->where('name', 'super-admin')->first();

    actingAs(superAdmin(), 'admin')
        ->delete(route('admin.roles.delete', $role->id))
        ->assertRedirect(route('admin.roles'));

    expect(Role::where('name', 'super-admin')->where('guard_name', 'admin')->exists())->toBeTrue();
});

it('deletes a non-protected role', function () {
    $role = Role::create(['name' => 'temp-role', 'guard_name' => 'admin']);

    actingAs(superAdmin(), 'admin')
        ->delete(route('admin.roles.delete', $role->id))
        ->assertRedirect(route('admin.roles'));

    expect(Role::where('name', 'temp-role')->where('guard_name', 'admin')->exists())->toBeFalse();
});

it('creates an administrator with a role and a hashed password', function () {
    actingAs(superAdmin(), 'admin')
        ->post(route('admin.administrators.save'), [
            'name' => 'Jane Operator',
            'email' => 'jane@poisapay.test',
            'username' => 'jane',
            'password' => 'secret-password',
            'is_active' => '1',
            'roles' => ['super-admin'],
        ])
        ->assertRedirect(route('admin.administrators'));

    $admin = Admin::where('email', 'jane@poisapay.test')->first();
    expect($admin)->not->toBeNull();
    expect($admin->hasRole('super-admin'))->toBeTrue();
    expect(Hash::check('secret-password', $admin->password))->toBeTrue();
});

it('toggles an administrator active flag', function () {
    $target = Admin::create([
        'name' => 'Target', 'email' => 'target@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);

    actingAs(superAdmin(), 'admin')
        ->post(route('admin.administrators.toggle', $target->id))
        ->assertRedirect(route('admin.administrators'));

    expect($target->fresh()->is_active)->toBeFalse();
});

it('deletes an administrator', function () {
    $target = Admin::create([
        'name' => 'Target', 'email' => 'target@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);

    actingAs(superAdmin(), 'admin')
        ->delete(route('admin.administrators.delete', $target->id))
        ->assertRedirect(route('admin.administrators'));

    expect(Admin::where('email', 'target@poisapay.test')->exists())->toBeFalse();
});

it('blocks a non-permitted operator from RBAC pages', function () {
    $plain = Admin::create([
        'name' => 'Plain', 'email' => 'plain@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    // A role with no manage-roles / manage-admins permission.
    Role::create(['name' => 'read-only', 'guard_name' => 'admin']);
    $plain->syncRoles(['read-only']);

    actingAs($plain, 'admin')->get(route('admin.roles'))->assertForbidden();
    actingAs($plain, 'admin')->get(route('admin.administrators'))->assertForbidden();
});

it('prevents an admin from disabling their own account', function () {
    $me = superAdmin();

    actingAs($me, 'admin')
        ->post(route('admin.administrators.toggle', $me->id))
        ->assertRedirect(route('admin.administrators'));

    expect($me->fresh()->is_active)->toBeTrue();
});

it('prevents an admin from deleting their own account', function () {
    $me = superAdmin();

    actingAs($me, 'admin')
        ->delete(route('admin.administrators.delete', $me->id))
        ->assertRedirect(route('admin.administrators'));

    expect(Admin::whereKey($me->id)->exists())->toBeTrue();
});

it('prevents deleting the last super-admin', function () {
    // $me is a manage-admins operator but NOT a super-admin, so the self-delete
    // guard does not fire. $lone is the only super-admin in the system.
    $me = Admin::create([
        'name' => 'Manager', 'email' => 'manager@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    Role::create(['name' => 'admin-manager', 'guard_name' => 'admin'])
        ->syncPermissions(['manage-admins']);
    $me->syncRoles(['admin-manager']);

    $lone = Admin::create([
        'name' => 'Lone', 'email' => 'lone@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $lone->syncRoles(['super-admin']);

    actingAs($me, 'admin')
        ->delete(route('admin.administrators.delete', $lone->id))
        ->assertRedirect(route('admin.administrators'));

    expect(Admin::whereKey($lone->id)->exists())->toBeTrue();
});
