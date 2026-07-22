<?php

declare(strict_types=1);

use App\Domain\Audit\ActivityLogger;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Support\Settings;
use Illuminate\Support\Facades\Artisan;

it('reads, writes and caches settings', function () {
    Settings::set('site_name', 'PoisaPay Test', 'general');

    expect(getSetting('site_name'))->toBe('PoisaPay Test')
        ->and(getSetting('missing_key', 'fallback'))->toBe('fallback');

    // Update flows through the cache.
    updateSetting('site_name', 'Changed');
    expect(getSetting('site_name'))->toBe('Changed');
});

it('gates features via settings-based flags', function () {
    updateSetting('cards_enabled', false, 'features');
    updateSetting('deposit_enabled', true, 'features');

    expect(feature('cards_enabled'))->toBeFalse()
        ->and(feature('deposit_enabled'))->toBeTrue()
        ->and(feature('unset_flag', true))->toBeTrue();
});

it('records an activity/audit entry with the resolved actor', function () {
    $admin = Admin::create(['name' => 'Op', 'email' => 'op@t.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $this->actingAs($admin, 'admin');

    ActivityLogger::log('thing.done', null, ['a' => 1], 'Did a thing');

    $log = AuditLog::latest()->first();
    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('thing.done')
        ->and($log->actor_type)->toBe('operator')
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->changes)->toBe(['a' => 1]);
});

it('groups permissions for the admin UI', function () {
    expect(permissionGroup('view-deposits'))->toBe('Money Movement')
        ->and(permissionGroup('manage-settings'))->toBe('Configuration')
        ->and(permissionGroup('review-kyc'))->toBe('Compliance');
});

it('lets a super-admin bypass permission checks and gates others', function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);

    $super = Admin::create(['name' => 'S', 'email' => 's@t.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $super->syncRoles(['super-admin']);
    $support = Admin::create(['name' => 'Sup', 'email' => 'sup@t.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $support->syncRoles(['support']);

    expect($super->can('manage-settings'))->toBeTrue()      // via Gate::before
        ->and($support->can('view-dashboard'))->toBeTrue()
        ->and($support->can('manage-settings'))->toBeFalse();
});
