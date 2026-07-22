<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Admin role + permission editor (DollarHub structure — controller + Blade, not
 * Livewire). Permissions are submitted as a `permissions[]` checkbox array and
 * synced onto a spatie role on the `admin` guard. System roles (super-admin,
 * admin) are protected from deletion, exactly as the old Livewire component did.
 */
class RolesController extends Controller
{
    /** Roles that may never be deleted. */
    private const PROTECTED_ROLES = ['super-admin', 'admin'];

    public function index(): View
    {
        abort_unless(auth('admin')->user()->can('manage-roles'), 403);

        $permissionGroups = Permission::where('guard_name', 'admin')
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($p) => permissionGroup($p->name));

        $roles = Role::where('guard_name', 'admin')
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get();

        return view('admin.roles', [
            'roles' => $roles,
            'permissionGroups' => $permissionGroups,
            'protectedRoles' => self::PROTECTED_ROLES,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('manage-roles'), 403);

        $id = $request->input('id');

        $data = $request->validate([
            'name' => 'required|string|max:60|regex:/^[a-z0-9-]+$/|unique:roles,name,'.($id ?? 'NULL').',id,guard_name,admin',
            'permissions' => 'array',
            'permissions.*' => 'string',
        ]);

        // Never pass id=null to create() (mass-assignment guard rejects it).
        if ($id) {
            $role = Role::where('guard_name', 'admin')->findOrFail($id);
            $role->update(['name' => $data['name']]);
        } else {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'admin']);
        }

        $selected = $data['permissions'] ?? [];
        $role->syncPermissions($selected);

        ActivityLogger::log(
            'role.saved',
            $role,
            ['permissions' => count($selected)],
            "Saved role {$role->name}",
        );

        return redirect()->route('admin.roles')
            ->with('success', $id ? 'Role updated.' : 'Role created.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('manage-roles'), 403);

        $role = Role::where('guard_name', 'admin')->findOrFail($id);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return redirect()->route('admin.roles')
                ->with('error', 'This role is protected and cannot be deleted.');
        }

        $name = $role->name;
        $role->delete();

        ActivityLogger::log('role.deleted', null, ['name' => $name], "Deleted role {$name}");

        return redirect()->route('admin.roles')->with('success', 'Role deleted.');
    }
}
