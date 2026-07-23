<?php
declare(strict_types=1);
use App\Models\Admin;
use Illuminate\Support\Facades\Artisan;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    $this->op = Admin::create(['name' => 'Op', 'email' => 'op@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true]);
    $this->op->syncRoles(['super-admin']);
});

it('renders the system health page', function () {
    actingAs($this->op, 'admin')->get(route('admin.system-health'))
        ->assertOk()->assertSee('Server Health')->assertSee('Database')->assertSee('Redis')->assertSee('Queue');
});

it('renders the logs page with an empty log file (no fread crash)', function () {
    file_put_contents(storage_path('logs/laravel.log'), '');
    actingAs($this->op, 'admin')->get(route('admin.logs'))->assertOk()->assertSee('Application Logs');
});

it('renders and parses a populated log file', function () {
    file_put_contents(storage_path('logs/laravel.log'),
        "[2026-07-23 10:00:00] local.ERROR: Something broke\n[2026-07-23 10:01:00] local.INFO: All good\n");
    actingAs($this->op, 'admin')->get(route('admin.logs'))
        ->assertOk()->assertSee('Something broke')->assertSee('error'); // level rendered lowercase, CSS-uppercased
});

it('renders the webhooks page', function () {
    actingAs($this->op, 'admin')->get(route('admin.webhooks'))->assertOk()->assertSee('Outbound Webhooks');
});

it('blocks a non-operator from system monitoring', function () {
    $plain = Admin::create(['name' => 'X', 'email' => 'x@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true]);
    actingAs($plain, 'admin')->get(route('admin.system-health'))->assertForbidden();
});
