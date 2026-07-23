<x-layouts.admin :title="__('RPC Endpoints')">
    {{-- Alpine is light UI only: modal open/close + prefill for edit. The form POSTs traditionally. --}}
    <div x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            editingId: '{{ old('id') }}',
            form: {
                chain_id: '{{ old('chain_id') }}',
                name: @js(old('name', '')),
                url: @js(old('url', '')),
                priority: {{ (int) old('priority', 1) }},
                weight: {{ (int) old('weight', 100) }},
                is_active: {{ old('is_active', true) ? 'true' : 'false' }},
            },
            create() { this.editingId = ''; this.form = { chain_id: '', name: '', url: '', priority: 1, weight: 100, is_active: true }; this.open = true; },
            edit(e) { this.editingId = e.id; this.form = { chain_id: String(e.chain_id), name: e.name, url: e.url, priority: e.priority, weight: e.weight, is_active: e.is_active }; this.open = true; },
        }" class="space-y-6">
        <x-ui.page-header :title="__('RPC Endpoints')" :subtitle="__('JSON-RPC nodes the indexer and broadcaster failover across, ordered by priority per chain (Phase 3).')">
            <x-slot:actions>
                <x-ui.button x-on:click="create()" icon="plus" size="sm">{{ __('Add endpoint') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.table :headers="[__('Chain'), __('Name'), __('URL'), __('Priority'), __('Weight'), __('Status'), __('Last block'), __('Latency'), __('Checked'), __('Active'), '']">
            @forelse ($endpoints as $e)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-4 py-3">
                        @if ($e->chain)
                            <x-ui.badge :color="$e->chain->key->color()">{{ $e->chain->key->label() }}</x-ui.badge>
                        @else
                            <span class="text-neutral-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold text-neutral-900">{{ $e->name }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="block max-w-[220px] truncate font-mono text-xs text-neutral-600" title="{{ $e->url }}">{{ $e->url }}</span>
                            <x-ui.copy-text :text="$e->url" :label="__('Copy URL')" />
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-neutral-600">{{ $e->priority }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-600">{{ $e->weight }}</td>
                    <td class="px-4 py-3"><x-ui.badge :color="$e->statusColor()" dot>{{ ucfirst($e->status ?? 'unknown') }}</x-ui.badge></td>
                    <td class="px-4 py-3 font-mono text-sm text-neutral-600">{{ $e->last_block !== null ? number_format($e->last_block) : '—' }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-600">{{ $e->latency_ms !== null ? $e->latency_ms.' ms' : '—' }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $e->last_checked_at?->diffForHumans() ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('admin.rpc-endpoints.toggle', $e->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex">
                                <x-ui.badge :color="$e->is_active ? 'success' : 'gray'" dot>{{ $e->is_active ? __('Active') : __('Disabled') }}</x-ui.badge>
                            </button>
                        </form>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            <x-ui.button variant="secondary" size="sm" icon="pencil-square"
                                x-on:click="edit({{ Illuminate\Support\Js::from(['id' => $e->id, 'chain_id' => $e->chain_id, 'name' => $e->name, 'url' => $e->url, 'priority' => (int) $e->priority, 'weight' => (int) $e->weight, 'is_active' => (bool) $e->is_active]) }})">{{ __('Edit') }}</x-ui.button>
                            <form method="POST" action="{{ route('admin.rpc-endpoints.delete', $e->id) }}" onsubmit="return confirm('Delete this RPC endpoint? Failover will no longer route to it.')">
                                @csrf @method('DELETE')
                                <x-ui.button type="submit" variant="danger" size="sm" icon="trash">{{ __('Delete') }}</x-ui.button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11"><x-ui.empty-state icon="server-stack" :title="__('No RPC endpoints')" :description="__('Add a JSON-RPC node so the indexer can sync a chain.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{-- Add / edit modal --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500/60" x-on:click="open = false"></div>
            <div class="relative w-full max-w-lg pp-card p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-neutral-900" x-text="editingId ? 'Edit endpoint' : 'Add RPC endpoint'"></h3>
                    <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </div>
                <form method="POST" action="{{ route('admin.rpc-endpoints.save') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="id" :value="editingId" />
                    <x-ui.select :label="__('Chain')" name="chain_id" x-model="form.chain_id" :error="$errors->first('chain_id')">
                        <option value="">{{ __('Select a chain…') }}</option>
                        @foreach ($chains as $chain)
                            <option value="{{ $chain->id }}">{{ $chain->key->label() }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.input :label="__('Name')" name="name" x-model="form.name" :placeholder="__('Ankr primary')" :error="$errors->first('name')" />
                    <x-ui.input :label="__('URL')" name="url" x-model="form.url" placeholder="https://rpc.example.com" :error="$errors->first('url')" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input type="number" :label="__('Priority (1 = highest)')" name="priority" x-model="form.priority" min="1" max="99" :error="$errors->first('priority')" />
                        <x-ui.input type="number" :label="__('Weight (load balance)')" name="weight" x-model="form.weight" min="1" max="100" :error="$errors->first('weight')" />
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <x-ui.checkbox name="is_active" value="1" x-model="form.is_active" :label="__('Active')" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button type="button" variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" x-text="editingId ? 'Save changes' : 'Add endpoint'"></x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
