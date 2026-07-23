<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\User;
use App\Models\UserPushToken;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;

function flagOperator(): Admin
{
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    $admin = Admin::create(['name' => 'Flag', 'email' => 'flag@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $admin->syncRoles(['super-admin']);

    return $admin;
}

it('renders the feature-flag console and toggles a flag', function () {
    $admin = flagOperator();

    actingAs($admin, 'admin')->get(route('admin.feature-flags'))->assertOk()->assertSee('cards_enabled');

    expect(feature('security_withdrawal_whitelist', false))->toBeFalse();
    actingAs($admin, 'admin')->post(route('admin.feature-flags.toggle'), ['flag' => 'security_withdrawal_whitelist'])->assertRedirect();
    expect(feature('security_withdrawal_whitelist', false))->toBeTrue();
});

it('rejects toggling an unknown flag', function () {
    $admin = flagOperator();
    actingAs($admin, 'admin')->post(route('admin.feature-flags.toggle'), ['flag' => 'not_a_real_flag'])->assertNotFound();
});

it('registers a device push token via the API', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/push-tokens', ['token' => 'fcm-abc', 'platform' => 'ios'])->assertCreated();

    expect(UserPushToken::where('user_id', $user->id)->where('token', 'fcm-abc')->exists())->toBeTrue();

    // Idempotent re-registration.
    $this->postJson('/api/v1/push-tokens', ['token' => 'fcm-abc', 'platform' => 'ios'])->assertCreated();
    expect(UserPushToken::where('user_id', $user->id)->count())->toBe(1);
});
