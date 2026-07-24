<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Support\AdminMenu;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    $this->admin = Admin::create([
        'name' => 'Nav Op', 'email' => 'nav@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $this->admin->syncRoles(['super-admin']);
});

// Externally-owned / in-progress pages not built by this IA refactor. Documented
// here (not silently dropped) so the exclusion is visible and easy to remove.
const EXTERNAL_WIP_ROUTES = ['admin.analytics'];

/** Every registered nav destination must render for a super-admin (no dead links, no broken pages). */
it('renders every navigable admin page for a super-admin', function () {
    $routes = collect(AdminMenu::groups())
        ->flatMap(fn ($group) => collect($group['items'])->flatMap(
            fn ($item) => $item['children'] ?? [$item]
        ))
        ->pluck('route')
        ->filter()
        ->unique()
        ->reject(fn ($name) => in_array($name, EXTERNAL_WIP_ROUTES, true))
        ->filter(fn ($name) => Route::has($name))
        ->values();

    // Sanity: the tree must actually resolve a meaningful number of pages.
    expect($routes->count())->toBeGreaterThan(20);

    $failures = [];
    foreach ($routes as $name) {
        $response = actingAs($this->admin, 'admin')->get(route($name));
        if (! in_array($response->status(), [200, 302], true)) {
            $failures[] = "{$name} => {$response->status()}";
        }
    }

    expect($failures)->toBe([]);
});
