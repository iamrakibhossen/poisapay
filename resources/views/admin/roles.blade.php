<x-layouts.admin :title="__('Roles & Permissions')">
    {{-- Alpine is light UI only: modal open/close + prefill for edit. The form POSTs traditionally. --}}
    <div x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            editingId: '{{ old('id') }}',
            name: @js(old('name', '')),
            selected: @js(old('permissions', [])),
            create() {
                this.editingId = '';
                this.name = '';
                this.selected = [];
                this.open = true;
            },
            edit(role) {
                this.editingId = role.id;
                this.name = role.name;
                this.selected = role.permissions;
                this.open = true;
            },
        }" class="space-y-6">
        <x-ui.page-header :title="__('Roles & Permissions')" :subtitle="__('Operator roles and the permissions they grant on the admin guard.')">
            <x-slot:actions>
                <x-ui.button x-on:click="create()" icon="plus" size="sm">{{ __('Add role') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.table :headers="[__('Role'), __('Permissions'), __('Admins'), __('Created'), '']">
            @forelse ($roles as $role)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-4 py-3">
                        <p class="text-sm font-semibold text-neutral-900">{{ $role->name }}</p>
                        @if (in_array($role->name, $protectedRoles, true))
                            <p class="text-xs text-neutral-400">{{ __('System role') }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($role->name === 'super-admin')
                            <x-ui.badge color="warning">{{ __('All permissions') }}</x-ui.badge>
                        @else
                            <x-ui.badge color="info">{{ $role->permissions_count }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-neutral-600">{{ $role->users_count }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-600">{{ $role->created_at?->format('M j, Y') }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            <x-ui.button variant="secondary" size="sm" icon="pencil-square"
                                x-on:click="edit({{ Illuminate\Support\Js::from(['id' => (string) $role->id, 'name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all()]) }})">{{ __('Edit') }}</x-ui.button>
                            @unless (in_array($role->name, $protectedRoles, true))
                                <form method="POST" action="{{ route('admin.roles.delete', $role->id) }}" onsubmit="return confirm('Delete the {{ $role->name }} role?')">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="danger" size="sm" icon="trash">{{ __('Delete') }}</x-ui.button>
                                </form>
                            @endunless
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-ui.empty-state icon="shield-check" :title="__('No roles')" :description="__('Create a role to grant operators scoped permissions.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{-- Add / edit modal --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500/60" x-on:click="open = false"></div>
            <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col pp-card p-6" role="dialog" aria-modal="true">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-neutral-900" x-text="editingId ? 'Edit role' : 'Add role'"></h3>
                    <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </div>
                <form method="POST" action="{{ route('admin.roles.save') }}" class="flex min-h-0 flex-1 flex-col space-y-4">
                    @csrf
                    <input type="hidden" name="id" :value="editingId" />
                    <x-ui.input :label="__('Role name')" name="name" x-model="name" :placeholder="__('e.g. support-lead')" :hint="__('Lowercase letters, numbers and dashes.')" :error="$errors->first('name')" />

                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
                        <label class="pp-label">{{ __('Permissions') }}</label>
                        @foreach ($permissionGroups as $group => $permissions)
                            <div x-data="{
                                    names: @js($permissions->pluck('name')->all()),
                                    get allChecked() {
                                        return this.names.every(n => selected.includes(n));
                                    },
                                    toggleAll(e) {
                                        if (e.target.checked) {
                                            this.names.forEach(n => { if (!selected.includes(n)) selected.push(n); });
                                        } else {
                                            selected = selected.filter(n => !this.names.includes(n));
                                        }
                                    }
                                }" class="rounded-lg border border-gray-200 p-4">
                                <div class="mb-3 flex items-center justify-between border-b border-gray-100 pb-2">
                                    <h4 class="text-sm font-semibold text-neutral-700">{{ $group }}</h4>
                                    <label class="inline-flex cursor-pointer items-center gap-1.5 text-xs text-neutral-500">
                                        <input type="checkbox" :checked="allChecked" @change="toggleAll($event)" class="h-3.5 w-3.5 rounded border-neutral-300 text-brand-500 focus:ring-brand-500">
                                        {{ __('Select all') }}
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    @foreach ($permissions as $permission)
                                        <label class="inline-flex items-center gap-2 text-sm text-neutral-700">
                                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" x-model="selected" class="h-4 w-4 rounded border-neutral-300 text-brand-500 focus:ring-brand-500">
                                            <span>{{ \Illuminate\Support\Str::of($permission->name)->replace('-', ' ')->title() }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <x-ui.button type="button" variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" x-text="editingId ? 'Save changes' : 'Add role'"></x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
