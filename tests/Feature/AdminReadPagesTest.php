<?php

declare(strict_types=1);

use App\Enums\KycTier;
use App\Models\Admin;
use App\Models\Asset;
use App\Models\User;
use App\Notifications\OperatorNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);
});

/** A super-admin operator on the `admin` guard (implicitly gets every permission). */
function operator(): Admin
{
    $admin = Admin::create([
        'name' => 'Op', 'email' => 'op@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $admin->syncRoles(['super-admin']);

    return $admin;
}

it('renders each converted read page for an operator', function () {
    $admin = operator();

    foreach (['dashboard', 'activity-logs', 'treasury', 'reports', 'blockchain-health', 'simulation', 'notifications'] as $route) {
        actingAs($admin, 'admin')->get(route("admin.{$route}"))->assertOk();
    }
});

it('exports the trial balance CSV as a download', function () {
    $admin = operator();

    $response = actingAs($admin, 'admin')->get(route('admin.reports.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

it('runs treasury reconciliation and redirects back', function () {
    $admin = operator();

    actingAs($admin, 'admin')
        ->from(route('admin.treasury'))
        ->post(route('admin.treasury.reconcile'))
        ->assertRedirect(route('admin.treasury'));
});

it('runs each blockchain-health action and redirects back', function () {
    $admin = operator();

    foreach (['check', 'tick', 'reconcile'] as $action) {
        actingAs($admin, 'admin')
            ->from(route('admin.blockchain-health'))
            ->post(route("admin.blockchain-health.{$action}"))
            ->assertRedirect(route('admin.blockchain-health'));
    }
});

it('runs a simulation chain tick and redirects back', function () {
    $admin = operator();

    actingAs($admin, 'admin')
        ->from(route('admin.simulation'))
        ->post(route('admin.simulation.tick'))
        ->assertRedirect(route('admin.simulation'));
});

it('simulates a deposit and redirects back', function () {
    $admin = operator();
    $user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $asset = Asset::with('chain')->where('is_active', true)->where('kind', 'crypto')
        ->whereHas('chain')->orderBy('sort')->firstOrFail();

    actingAs($admin, 'admin')
        ->from(route('admin.simulation'))
        ->post(route('admin.simulation.deposit'), [
            'userEmail' => $user->email,
            'assetId' => $asset->id,
            'amount' => '1.5',
        ])
        ->assertRedirect(route('admin.simulation'));
})->group('simulation');

it('marks admin notifications read and all-read, redirecting back', function () {
    $admin = operator();

    // Seed a raw database notification row (avoids the queued/broadcast path).
    $notification = $admin->notifications()->create([
        'id' => Str::uuid()->toString(),
        'type' => OperatorNotification::class,
        'data' => ['title' => 'Test alert', 'body' => 'Body', 'category' => 'general', 'url' => null],
        'read_at' => null,
    ]);

    actingAs($admin, 'admin')
        ->from(route('admin.notifications'))
        ->post(route('admin.notifications.read', $notification->id))
        ->assertRedirect(route('admin.notifications'));

    actingAs($admin, 'admin')
        ->from(route('admin.notifications'))
        ->post(route('admin.notifications.read-all'))
        ->assertRedirect(route('admin.notifications'));
});

it('blocks a non-operator role from gated pages', function () {
    // The `support` role lacks view-treasury / view-reports / view-activity-logs.
    $support = Admin::create([
        'name' => 'Support', 'email' => 'support@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $support->syncRoles(['support']);

    foreach (['treasury', 'reports', 'activity-logs', 'blockchain-health'] as $route) {
        actingAs($support, 'admin')->get(route("admin.{$route}"))->assertForbidden();
    }
});

it('bounces guests from the converted pages to admin login', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.treasury'))->assertRedirect(route('admin.login'));
});
