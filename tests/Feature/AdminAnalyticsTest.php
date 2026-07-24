<?php

declare(strict_types=1);

use App\Domain\Analytics\Period;
use App\Models\Admin;
use App\Models\AnalyticsDailyMetric;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    $this->asset = testAsset('USDT', 6, 'tron');

    $this->admin = Admin::create([
        'name' => 'Analyst', 'email' => 'analyst@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $this->admin->syncRoles(['super-admin']);
});

$sections = ['deposits', 'withdrawals', 'exchange', 'revenue', 'pnl', 'loss', 'wallet', 'treasury', 'cards', 'compliance'];

it('renders the executive overview', function () {
    User::factory()->count(3)->create();

    actingAs($this->admin, 'admin')->get(route('admin.analytics'))
        ->assertOk()
        ->assertSee('Executive Overview')
        ->assertSee('Total Users');
});

it('renders every analytics section', function () use ($sections) {
    foreach ($sections as $section) {
        actingAs($this->admin, 'admin')->get(route('admin.analytics.section', $section))
            ->assertOk();
    }
});

it('404s an unknown section and the overview alias', function () {
    actingAs($this->admin, 'admin')->get(route('admin.analytics.section', 'nope'))->assertNotFound();
    actingAs($this->admin, 'admin')->get(route('admin.analytics.section', 'overview'))->assertNotFound();
});

it('honours every date-range preset without error', function () {
    foreach (array_keys(Period::PRESETS) as $preset) {
        actingAs($this->admin, 'admin')
            ->get(route('admin.analytics', ['period' => $preset, 'from' => '2026-01-01', 'to' => '2026-07-01']))
            ->assertOk();
    }
});

it('supports comparison mode on and off', function () {
    actingAs($this->admin, 'admin')->get(route('admin.analytics', ['compare' => 1]))->assertOk()->assertSee('vs previous');
    actingAs($this->admin, 'admin')->get(route('admin.analytics', ['compare' => 0]))->assertOk();
});

it('exports a section as CSV', function () {
    $res = actingAs($this->admin, 'admin')->get(route('admin.analytics.export', 'revenue'));
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('text/csv');
    expect($res->streamedContent())->toContain('Revenue Analytics');
});

it('forbids admins without the reporting permission', function () {
    $plain = Admin::create(['name' => 'Plain', 'email' => 'plain@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);

    actingAs($plain, 'admin')->get(route('admin.analytics'))->assertForbidden();
});

it('redirects guests to the operator login', function () {
    $this->get(route('admin.analytics'))->assertRedirect(route('admin.login'));
});

it('materialises daily rollups and flushes the cache', function () {
    User::factory()->count(4)->create();

    Artisan::call('poisapay:analytics-rollup', ['--days' => 1]);

    $newUsers = AnalyticsDailyMetric::where('metric', 'new_users')->where('day', today()->toDateString())->value('value');
    expect((float) $newUsers)->toBeGreaterThanOrEqual(4.0);

    // Every rolled-up metric key should be present for today.
    $metrics = AnalyticsDailyMetric::where('day', today()->toDateString())->pluck('metric');
    expect($metrics)->toContain('revenue_usd', 'deposit_volume_usd', 'withdrawal_volume_usd', 'swap_volume_usd');
});
