<x-layouts.admin :title="'Deposit Methods'">
    @php
        // Repopulate the details repeater from old() on validation error, else start with 4 blank rows.
        $oldDetailKeys = old('details.key');
        $oldDetailValues = old('details.value');
        $detailRows = [];
        if (is_array($oldDetailKeys)) {
            foreach ($oldDetailKeys as $i => $k) {
                $detailRows[] = ['key' => (string) $k, 'value' => (string) ($oldDetailValues[$i] ?? '')];
            }
        }
        while (count($detailRows) < 4) {
            $detailRows[] = ['key' => '', 'value' => ''];
        }
    @endphp
    {{-- Alpine is light UI only: modal open/close, prefill for edit, and the details repeater. The form POSTs traditionally. --}}
    <div x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            editingId: '{{ old('id') }}',
            form: {
                asset_id: @js(old('asset_id', '')),
                name: @js(old('name', '')),
                type: @js(old('type', 'bank')),
                instructions: @js(old('instructions', '')),
                min_amount: @js(old('min_amount', '')),
                max_amount: @js(old('max_amount', '')),
                fixed_fee: @js(old('fixed_fee', '')),
                percent_fee_bps: @js(old('percent_fee_bps', '0')),
                sort: @js(old('sort', '0')),
                is_active: {{ old('is_active', $errors->any() ? null : '1') ? 'true' : 'false' }},
            },
            details: @js($detailRows),
            addDetailRow() { this.details.push({ key: '', value: '' }); },
            create() {
                this.editingId = '';
                this.form = { asset_id: '', name: '', type: 'bank', instructions: '', min_amount: '', max_amount: '', fixed_fee: '', percent_fee_bps: '0', sort: '0', is_active: true };
                this.details = [{ key: '', value: '' }, { key: '', value: '' }, { key: '', value: '' }, { key: '', value: '' }];
                this.open = true;
            },
            edit(m) {
                this.editingId = m.id;
                this.form = { asset_id: m.asset_id, name: m.name, type: m.type, instructions: m.instructions ?? '', min_amount: m.min_amount ?? '', max_amount: m.max_amount ?? '', fixed_fee: m.fixed_fee ?? '', percent_fee_bps: String(m.percent_fee_bps ?? '0'), sort: String(m.sort ?? '0'), is_active: m.is_active };
                let rows = Object.entries(m.details ?? {}).map(([key, value]) => ({ key: String(key), value: String(value) }));
                while (rows.length < 4) rows.push({ key: '', value: '' });
                this.details = rows;
                this.open = true;
            },
        }" class="space-y-6">
        <x-ui.page-header title="Deposit Methods" subtitle="Configure how each currency can be funded.">
            <x-slot:actions>
                <x-ui.button x-on:click="create()" icon="plus" size="sm">New method</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Stat tiles --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat-card label="Total methods" :value="$stats['total']" icon="banknotes" accent="brand" />
            <x-ui.stat-card label="Active methods" :value="$stats['active']" icon="check-badge" accent="emerald" />
            <x-ui.stat-card label="Deposit-enabled assets" :value="$stats['depositable']" icon="currency-dollar" accent="amber" />
        </div>

        {{-- Tabs (query-string filter) --}}
        <div class="flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
            @foreach (['methods' => 'Methods', 'assets' => 'Depositable assets'] as $key => $label)
                <a href="{{ route('admin.deposit-methods', ['tab' => $key]) }}"
                    @class([
                        'flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition',
                        'bg-white text-neutral-900 shadow-sm' => $tab === $key,
                        'text-neutral-500 hover:text-neutral-800' => $tab !== $key,
                    ])>
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @if ($tab === 'methods')
            <x-ui.table :headers="['Asset', 'Method', 'Type', 'Min / Max', 'Fee', 'Active', '']">
                @forelse ($methods as $m)
                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <x-ui.asset-icon :symbol="$m->asset->symbol" size="sm" />
                                <span class="text-sm font-medium text-neutral-900">{{ $m->asset->symbol }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm font-semibold text-neutral-900">{{ $m->name }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge color="gray">
                                <x-dynamic-component :component="'heroicon-o-'.$m->type->icon()" class="mr-1 inline h-3.5 w-3.5" />
                                {{ $m->type->label() }}
                            </x-ui.badge>
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-600">
                            <span class="tabular">{{ $m->minMoney()->format() }}</span>
                            <span class="text-neutral-400"> / </span>
                            <span class="tabular">{{ $m->maxMoney()?->format() ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-600">
                            <span class="tabular">{{ $m->fixed_fee !== null ? $m->asset->money($m->fixed_fee)->format() : '0' }}</span>
                            @if (($m->percent_fee_bps ?? 0) > 0)
                                <span class="text-neutral-400"> + </span><span class="tabular">{{ rtrim(rtrim(number_format($m->percent_fee_bps / 100, 2), '0'), '.') }}%</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('admin.deposit-methods.toggle', $m->id) }}">
                                @csrf
                                <button type="submit" class="inline-flex">
                                    <x-ui.badge :color="$m->is_active ? 'success' : 'gray'" dot>{{ $m->is_active ? 'Active' : 'Disabled' }}</x-ui.badge>
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <x-ui.button variant="secondary" size="sm" icon="pencil-square"
                                x-on:click="edit({{ Illuminate\Support\Js::from(['id' => $m->id, 'asset_id' => $m->asset_id, 'name' => $m->name, 'type' => $m->type->value, 'instructions' => $m->instructions, 'min_amount' => ($m->min_amount !== null && $m->asset) ? $m->asset->money($m->min_amount)->toDecimal() : '', 'max_amount' => ($m->max_amount !== null && $m->asset) ? $m->asset->money($m->max_amount)->toDecimal() : '', 'fixed_fee' => ($m->fixed_fee !== null && $m->asset) ? $m->asset->money($m->fixed_fee)->toDecimal() : '', 'percent_fee_bps' => (string) ($m->percent_fee_bps ?? 0), 'sort' => (string) ($m->sort ?? 0), 'is_active' => (bool) $m->is_active, 'details' => collect($m->details ?? [])->map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v))->all()]) }})">Edit</x-ui.button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-ui.empty-state icon="banknotes" title="No deposit methods" description="Add a method so users can fund an asset." /></td></tr>
                @endforelse
            </x-ui.table>
        @else
            {{-- Depositable assets: per-asset deposit toggle --}}
            <x-ui.table :headers="['Asset', 'Name', 'Deposit enabled', '']">
                @forelse ($assets as $asset)
                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <x-ui.asset-icon :symbol="$asset->symbol" size="sm" />
                                <span class="text-sm font-medium text-neutral-900">{{ $asset->symbol }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-600">{{ $asset->name }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge :color="$asset->deposit_enabled ? 'success' : 'gray'" dot>{{ $asset->deposit_enabled ? 'Enabled' : 'Disabled' }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('admin.deposit-methods.deposit-enabled', $asset->id) }}">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" size="sm">
                                    {{ $asset->deposit_enabled ? 'Disable' : 'Enable' }}
                                </x-ui.button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-ui.empty-state icon="currency-dollar" title="No active assets" description="Activate an asset first." /></td></tr>
                @endforelse
            </x-ui.table>
        @endif

        {{-- Create / edit modal --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-80" x-on:click="open = false"></div>
            <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto pp-card p-6" role="dialog" aria-modal="true">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-neutral-900" x-text="editingId ? 'Edit method' : 'New deposit method'"></h3>
                    <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </div>
                <form method="POST" action="{{ route('admin.deposit-methods.save') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="id" :value="editingId" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select label="Asset" name="asset_id" x-model="form.asset_id" :error="$errors->first('asset_id')">
                            <option value="">Select asset…</option>
                            @foreach ($assets as $asset)
                                <option value="{{ $asset->id }}">{{ $asset->symbol }} — {{ $asset->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.select label="Type" name="type" x-model="form.type" :error="$errors->first('type')">
                            @foreach ($types as $t)
                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <x-ui.input label="Name" name="name" x-model="form.name" placeholder="e.g. ACME Bank Wire" :error="$errors->first('name')" />

                    {{-- Free-form details key/value rows --}}
                    <div>
                        <label class="pp-label">Details (field / value)</label>
                        <div class="mt-1 space-y-2">
                            <template x-for="(row, i) in details" :key="i">
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <x-ui.input x-bind:name="'details[key][' + i + ']'" x-model="row.key" placeholder="Field name (e.g. Account no.)" />
                                    <x-ui.input x-bind:name="'details[value][' + i + ']'" x-model="row.value" placeholder="Value" />
                                </div>
                            </template>
                        </div>
                        <button type="button" x-on:click="addDetailRow()" class="mt-2 text-sm font-medium text-amber-600 hover:text-amber-700">+ Add field</button>
                    </div>

                    <x-ui.textarea label="Instructions" name="instructions" x-model="form.instructions" rows="3" placeholder="What the user should do to complete this deposit…" :error="$errors->first('instructions')" />

                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.input label="Min amount" name="min_amount" x-model="form.min_amount" placeholder="0.00" :error="$errors->first('min_amount')" />
                        <x-ui.input label="Max amount (optional)" name="max_amount" x-model="form.max_amount" placeholder="—" :error="$errors->first('max_amount')" />
                        <x-ui.input label="Fixed fee" name="fixed_fee" x-model="form.fixed_fee" placeholder="0.00" :error="$errors->first('fixed_fee')" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input label="Percent fee (bps)" name="percent_fee_bps" x-model="form.percent_fee_bps" type="number" placeholder="0" hint="100 bps = 1%" :error="$errors->first('percent_fee_bps')" />
                        <x-ui.input label="Sort order" name="sort" x-model="form.sort" type="number" placeholder="0" :error="$errors->first('sort')" />
                    </div>

                    <x-ui.checkbox name="is_active" value="1" x-model="form.is_active" label="Active" />

                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button type="button" variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
                        <x-ui.button type="submit" x-text="editingId ? 'Save changes' : 'Create method'"></x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
