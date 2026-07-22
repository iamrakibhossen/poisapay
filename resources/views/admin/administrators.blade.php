<x-layouts.admin :title="'Administrators'">
    {{-- Alpine is light UI only: modal open/close + prefill for edit. The form POSTs traditionally. --}}
    <div x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            editingId: '{{ old('id') }}',
            form: {
                name: @js(old('name', '')),
                email: @js(old('email', '')),
                username: @js(old('username', '')),
                password: '',
                is_active: {{ old('is_active', $errors->any() ? null : '1') ? 'true' : 'false' }},
            },
            roles: @js(old('roles', [])),
            create() {
                this.editingId = '';
                this.form = { name: '', email: '', username: '', password: '', is_active: true };
                this.roles = [];
                this.open = true;
            },
            edit(admin) {
                this.editingId = admin.id;
                this.form = { name: admin.name, email: admin.email, username: admin.username ?? '', password: '', is_active: admin.is_active };
                this.roles = admin.roles;
                this.open = true;
            },
        }" class="space-y-6">
        <x-ui.page-header title="Administrators" subtitle="Operator accounts and the roles assigned to them.">
            <x-slot:actions>
                <x-ui.button x-on:click="create()" icon="plus" size="sm">Add administrator</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.table :headers="['Administrator', 'Roles', 'Status', 'Last login', 'Created', '']">
            @forelse ($admins as $admin)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$admin->name" size="sm" />
                            <div>
                                <p class="text-sm font-semibold text-neutral-900">{{ $admin->name }}</p>
                                <p class="text-xs text-neutral-400">{{ $admin->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-1">
                            @forelse ($admin->roles as $role)
                                <x-ui.badge :color="$role->name === 'super-admin' ? 'warning' : 'info'">{{ $role->name }}</x-ui.badge>
                            @empty
                                <span class="text-xs text-neutral-400">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        @if ($admin->id === $currentId)
                            <span class="inline-flex cursor-not-allowed opacity-60">
                                <x-ui.badge :color="$admin->is_active ? 'success' : 'gray'" dot>{{ $admin->is_active ? 'Active' : 'Disabled' }}</x-ui.badge>
                            </span>
                        @else
                            <form method="POST" action="{{ route('admin.administrators.toggle', $admin->id) }}">
                                @csrf
                                <button type="submit" class="inline-flex">
                                    <x-ui.badge :color="$admin->is_active ? 'success' : 'gray'" dot>{{ $admin->is_active ? 'Active' : 'Disabled' }}</x-ui.badge>
                                </button>
                            </form>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-neutral-600">{{ $admin->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-600">{{ $admin->created_at?->format('M j, Y') }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            <x-ui.button variant="secondary" size="sm" icon="pencil-square"
                                x-on:click="edit({{ Illuminate\Support\Js::from(['id' => (string) $admin->id, 'name' => $admin->name, 'email' => $admin->email, 'username' => $admin->username, 'is_active' => (bool) $admin->is_active, 'roles' => $admin->roles->pluck('name')->all()]) }})">Edit</x-ui.button>
                            @if ($admin->id !== $currentId)
                                <form method="POST" action="{{ route('admin.administrators.delete', $admin->id) }}" onsubmit="return confirm('Delete {{ $admin->email }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="danger" size="sm" icon="trash">Delete</x-ui.button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ui.empty-state icon="users" title="No administrators" description="Add an operator account to grant admin access." /></td></tr>
            @endforelse
        </x-ui.table>

        {{-- Add / edit modal --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500/60" x-on:click="open = false"></div>
            <div class="relative flex max-h-[90vh] w-full max-w-2xl flex-col pp-card p-6" role="dialog" aria-modal="true">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-neutral-900" x-text="editingId ? 'Edit administrator' : 'Add administrator'"></h3>
                    <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </div>
                <form method="POST" action="{{ route('admin.administrators.save') }}" class="flex min-h-0 flex-1 flex-col space-y-4 overflow-y-auto pr-1">
                    @csrf
                    <input type="hidden" name="id" :value="editingId" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input label="Name" name="name" x-model="form.name" placeholder="Jane Operator" :error="$errors->first('name')" />
                        <x-ui.input label="Email" type="email" name="email" x-model="form.email" placeholder="jane@poisapay.com" :error="$errors->first('email')" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input label="Username (optional)" name="username" x-model="form.username" placeholder="jane" :error="$errors->first('username')" />
                        <x-ui.input label="Password" type="password" name="password" x-model="form.password" autocomplete="new-password" hint="Minimum 8 characters. Leave blank when editing to keep current." :error="$errors->first('password')" />
                    </div>

                    <div>
                        <label class="pp-label">Roles</label>
                        <div class="grid gap-2 rounded-lg border border-gray-200 p-4 sm:grid-cols-2">
                            @forelse ($allRoles as $role)
                                <label class="flex items-center gap-2 text-sm text-neutral-700">
                                    <input type="checkbox" name="roles[]" value="{{ $role->name }}" x-model="roles" class="rounded border-neutral-300 text-brand-500 focus:ring-brand-500">
                                    {{ $role->name }}
                                </label>
                            @empty
                                <p class="text-sm text-neutral-400">No roles defined yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-neutral-700">
                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="rounded border-neutral-300 text-brand-500 focus:ring-brand-500"> Active
                    </label>

                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <x-ui.button type="button" variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
                        <x-ui.button type="submit" x-text="editingId ? 'Save changes' : 'Add administrator'"></x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
