<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** RBAC for operators (admin guard), driven by config/permissions.php. */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('permission:cache-reset');

        foreach (config('permissions.permissions') as $name) {
            Permission::findOrCreate($name, 'admin');
        }

        // super-admin gets everything (also enforced via Gate::before).
        Role::findOrCreate('super-admin', 'admin')->syncPermissions(Permission::where('guard_name', 'admin')->get());

        foreach (config('permissions.roles') as $roleName => $grants) {
            Role::findOrCreate($roleName, 'admin')->syncPermissions($grants);
        }
    }
}
