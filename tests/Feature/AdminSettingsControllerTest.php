<?php

declare(strict_types=1);

use App\Models\Admin;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);
    $this->admin = Admin::create(['name' => 'Op', 'email' => 'set@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $this->admin->syncRoles(['super-admin']);
});

it('renders the settings page (controller + blade, not livewire)', function () {
    actingAs($this->admin, 'admin')->get(route('admin.settings'))->assertOk()->assertSee('General');
    actingAs($this->admin, 'admin')->get(route('admin.settings', 'withdrawal'))->assertOk()->assertSee('Withdrawal');
});

it('renders every settings section', function () {
    foreach (['general', 'branding', 'auth', 'deposit', 'withdrawal', 'transfer', 'exchange', 'cards', 'merchant', 'rewards', 'compliance', 'localization', 'announcement'] as $section) {
        actingAs($this->admin, 'admin')->get(route('admin.settings', $section))->assertOk();
    }
});

it('persists a card setting and a comma-list compliance setting', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update', 'cards'), [
        'card_fee_bps' => 150,
        'card_default_daily_limit' => 600000,
        'card_default_per_tx_limit' => 250000,
        'card_dispute_window_days' => 45,
        'card_allow_physical' => '1',
    ])->assertRedirect(route('admin.settings', 'cards'));

    expect((int) getSetting('card_fee_bps'))->toBe(150)
        ->and((bool) getSetting('card_reveal_enabled'))->toBeFalse(); // unchecked -> false

    actingAs($this->admin, 'admin')->put(route('admin.settings.update', 'compliance'), [
        'aml_large_amount_minor' => 200000,
        'aml_velocity_window_hours' => 12,
        'aml_sanctions_denylist' => 'john doe, jane roe',
    ])->assertRedirect();

    expect(getSetting('aml_sanctions_denylist'))->toBe(['john doe', 'jane roe']);
});

it('404s an unknown section', function () {
    actingAs($this->admin, 'admin')->get('/admin/settings/bogus')->assertNotFound();
});

it('persists a section via a PUT form submit', function () {
    actingAs($this->admin, 'admin')
        ->put(route('admin.settings.update', 'withdrawal'), [
            'withdrawal_enabled' => '1',
            'withdrawal_fee_percent' => '1',
            'withdrawal_auto_approve_limit' => 99000,
            'min_withdrawal_usd' => 2,
            'daily_withdrawal_count' => 20,
        ])
        ->assertRedirect(route('admin.settings', 'withdrawal'))
        ->assertSessionHas('success');

    expect((int) getSetting('withdrawal_auto_approve_limit'))->toBe(99000)
        ->and((int) getSetting('daily_withdrawal_count'))->toBe(20);

    // Exchange settings now live in their own section.
    actingAs($this->admin, 'admin')
        ->put(route('admin.settings.update', 'exchange'), ['exchange_spread_bps' => 120, 'exchange_restrict_pairs' => '1'])
        ->assertRedirect(route('admin.settings', 'exchange'));

    expect((int) getSetting('exchange_spread_bps'))->toBe(120)
        ->and((bool) getSetting('exchange_restrict_pairs'))->toBeTrue();
});

it('defaults an unchecked toggle to false', function () {
    updateSetting('rewards_enabled', true, 'rewards');

    actingAs($this->admin, 'admin')->put(route('admin.settings.update', 'rewards'), [
        // rewards_enabled omitted -> should become false
        'referral_enabled' => '1',
    ])->assertRedirect();

    expect((bool) getSetting('rewards_enabled'))->toBeFalse()
        ->and((bool) getSetting('referral_enabled'))->toBeTrue();
});

it('rejects an invalid payload', function () {
    actingAs($this->admin, 'admin')
        ->put(route('admin.settings.update', 'general'), [
            'site_name' => '',
            'base_currency' => 'BDT',
            'support_email' => 'not-an-email',
        ])
        ->assertSessionHasErrors(['site_name', 'support_email']);
});

it('forbids an operator without manage-settings', function () {
    $plain = Admin::create(['name' => 'Plain', 'email' => 'plain-set@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);

    actingAs($plain, 'admin')->get(route('admin.settings'))->assertForbidden();
});
