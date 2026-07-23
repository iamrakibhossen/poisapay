<x-layouts.admin :title="'P2P Payment Methods'">
    <div x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            base: '{{ route('admin.p2p-payment-methods') }}',
            editingId: '{{ old('id', '') }}',
            form: {
                name: @js(old('name', '')),
                key: @js(old('key', '')),
                type: @js(old('type', 'mobile')),
                country: @js(old('country', '')),
                sort: @js(old('sort', 0)),
                is_active: {{ old('is_active', $errors->any() ? null : 1) ? 'true' : 'false' }},
            },
            fields: @js(old('fields', [['key' => 'account_name', 'label' => 'Account name', 'required' => true], ['key' => 'account_number', 'label' => 'Account number', 'required' => true]])),
            get action() { return this.editingId ? this.base + '/' + this.editingId : this.base; },
            create() {
                this.editingId = '';
                this.form = { name: '', key: '', type: 'mobile', country: '', sort: 0, is_active: true };
                this.fields = [{ key: 'account_name', label: 'Account name', required: true }, { key: 'account_number', label: 'Account number', required: true }];
                this.open = true;
            },
            edit(m) {
                this.editingId = m.id;
                this.form = { name: m.name, key: m.key, type: m.type, country: m.country ?? '', sort: m.sort ?? 0, is_active: m.is_active };
                this.fields = (m.fields && m.fields.length) ? JSON.parse(JSON.stringify(m.fields)) : [];
                this.open = true;
            },
            addField() { this.fields.push({ key: '', label: '', required: false }); },
        }" class="space-y-6">

        <x-ui.page-header title="P2P Payment Methods" subtitle="The rails users can save payout accounts on. Each method's fields define what a user must enter — “bank” can require more than a mobile wallet.">
            <x-slot:actions>
                <x-ui.button x-on:click="create()" icon="plus" size="sm">Add method</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif
        @if (session('error'))<x-ui.alert type="error">{{ session('error') }}</x-ui.alert>@endif

        <x-ui.card class="p-0">
            <x-ui.table :headers="['Method', 'Key', 'Type', 'Fields', 'Accounts', 'Status', '']">
                @forelse ($methods as $m)
                    <tr class="border-b border-gray-100">
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('admin.p2p-payment-methods.show', $m) }}" class="text-gray-900 hover:text-brand-600 hover:underline">{{ $m->name }}</a>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $m->key }}</td>
                        <td class="px-4 py-3"><x-ui.badge color="gray">{{ ucfirst($m->type) }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-gray-600">{{ count($m->fields ?? []) }}</td>
                        <td class="px-4 py-3 text-gray-600 tabular">{{ $m->user_accounts_count }}</td>
                        <td class="px-4 py-3"><x-ui.badge :color="$m->is_active ? 'success' : 'gray'" dot>{{ $m->is_active ? 'Active' : 'Off' }}</x-ui.badge></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <x-ui.button size="sm" variant="secondary" icon="pencil-square"
                                    x-on:click="edit({{ Illuminate\Support\Js::from(['id' => (string) $m->id, 'name' => $m->name, 'key' => $m->key, 'type' => $m->type, 'country' => $m->country, 'sort' => (int) $m->sort, 'is_active' => (bool) $m->is_active, 'fields' => $m->fields ?? []]) }})">Edit</x-ui.button>
                                <form method="POST" action="{{ route('admin.p2p-payment-methods.delete', $m) }}">
                                    @csrf @method('DELETE')
                                    <x-ui.button type="submit" size="sm" variant="ghost" icon="trash" aria-label="Delete" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-ui.empty-state icon="credit-card" title="No payment methods" description="Add the first rail users can save accounts on." /></td></tr>
                @endforelse
            </x-ui.table>
        </x-ui.card>

        @include('admin.partials.p2p-payment-method-form')
    </div>
</x-layouts.admin>
