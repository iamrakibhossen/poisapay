<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Admin operator accounts (DollarHub structure — controller + Blade, not
 * Livewire). Roles are submitted as a `roles[]` checkbox array. Passwords are
 * hashed on save (required on create, optional on edit). Self-protection guards
 * prevent an operator from disabling or deleting their own account, and the last
 * super-admin cannot be deleted — mirroring the old Livewire component.
 */
class AdministratorsController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()->can('manage-admins'), 403);

        return view('admin.administrators', [
            'admins' => Admin::with('roles')->orderBy('name')->get(),
            'allRoles' => Role::where('guard_name', 'admin')->orderBy('name')->get(),
            'currentId' => auth('admin')->id(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('manage-admins'), 403);

        $id = $request->input('id');

        $request->merge(['is_active' => $request->boolean('is_active')]);

        $data = $request->validate([
            'name' => 'required|string|max:80',
            'email' => [
                'required', 'email', 'max:160',
                Rule::unique('admins', 'email')->ignore($id),
            ],
            'username' => [
                'nullable', 'string', 'max:60',
                Rule::unique('admins', 'username')->ignore($id),
            ],
            'password' => $id ? 'nullable|string|min:8' : 'required|string|min:8',
            'is_active' => 'boolean',
            'roles' => 'array',
            'roles.*' => 'string',
        ]);

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => ($data['username'] ?? '') ?: null,
            'is_active' => (bool) $data['is_active'],
        ];

        if (($data['password'] ?? '') !== '') {
            $attributes['password'] = Hash::make($data['password']);
        }

        // Never pass id=null to create() on a HasUuids model (mass-assignment guard).
        if ($id) {
            $admin = Admin::whereKey($id)->firstOrFail();
            $admin->update($attributes);
        } else {
            $admin = Admin::create($attributes);
        }

        $admin->syncRoles($data['roles'] ?? []);

        ActivityLogger::log('admin.saved', $admin, [], "Saved operator {$admin->email}");

        return redirect()->route('admin.administrators')
            ->with('success', $id ? 'Administrator updated.' : 'Administrator added.');
    }

    public function toggleActive(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('manage-admins'), 403);

        $admin = Admin::findOrFail($id);

        if ($admin->id === auth('admin')->id()) {
            return redirect()->route('admin.administrators')
                ->with('error', 'You cannot disable your own account.');
        }

        $admin->update(['is_active' => ! $admin->is_active]);
        ActivityLogger::log('admin.toggled', $admin, ['is_active' => $admin->is_active], "Toggled operator {$admin->email}");

        return redirect()->route('admin.administrators')
            ->with('success', $admin->is_active ? 'Administrator enabled.' : 'Administrator disabled.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('manage-admins'), 403);

        $admin = Admin::findOrFail($id);

        if ($admin->id === auth('admin')->id()) {
            return redirect()->route('admin.administrators')
                ->with('error', 'You cannot delete your own account.');
        }

        if ($admin->hasRole('super-admin')) {
            $superAdmins = Admin::role('super-admin', 'admin')->count();
            if ($superAdmins <= 1) {
                return redirect()->route('admin.administrators')
                    ->with('error', 'Cannot delete the last super-admin.');
            }
        }

        $email = $admin->email;
        $admin->delete();

        ActivityLogger::log('admin.deleted', null, ['email' => $email], "Deleted operator {$email}");

        return redirect()->route('admin.administrators')->with('success', 'Administrator deleted.');
    }
}
