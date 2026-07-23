<x-layouts.admin :title="__('Withdrawal Methods')">
    {{-- Alpine is light UI only: modal open/close + prefill for edit. The form POSTs traditionally. --}}
    <div x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            editingId: '{{ old('id') }}',
            form: {
                asset_id: @js(old('asset_id', '')),
                name: @js(old('name', '')),
                type: @js(old('type', 'bank')),
                number_label: @js(old('number_label', '')),
                instructions: @js(old('instructions', '')),
                min_amount: @js(old('min_amount', '')),
                max_amount: @js(old('max_amount', '')),
                fixed_fee: @js(old('fixed_fee', '')),
                percent_fee_bps: @js(old('percent_fee_bps', '0')),
                sort: @js(old('sort', '0')),
                is_active: {{ old('is_active', $errors->any() ? null : '1') ? 'true' : 'false' }},
            },
            create() {
                this.editingId = '';
                this.form = { asset_id: '', name: '', type: 'bank', number_label: '', instructions: '', min_amount: '', max_amount: '', fixed_fee: '', percent_fee_bps: '0', sort: '0', is_active: true };
                this.open = true;
            },
            edit(m) {
                this.editingId = m.id;
                this.form = { asset_id: m.asset_id, name: m.name, type: m.type, number_label: m.number_label ?? '', instructions: m.instructions ?? '', min_amount: m.min_amount ?? '', max_amount: m.max_amount ?? '', fixed_fee: m.fixed_fee ?? '', percent_fee_bps: String(m.percent_fee_bps ?? '0'), sort: String(m.sort ?? '0'), is_active: m.is_active };
                this.open = true;
            },
        }" class="space-y-6">
        <x-ui.page-header :title="__('Withdrawal Methods')" :subtitle="__('Configure how each currency can be cashed out.')">
            <x-slot:actions>
                <x-ui.button x-on:click="create()" icon="plus" size="sm">{{ __('New method') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Stat tiles --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat-card :label="__('Total methods')" :value="$stats['total']" icon="banknotes" accent="brand" />
            <x-ui.stat-card :label="__('Active methods')" :value="$stats['active']" icon="check-badge" accent="emerald" />
            <x-ui.stat-card :label="__('Fiat currencies')" :value="$stats['currencies']" icon="currency-dollar" accent="amber" />
        </div>

        <x-ui.table :headers="[__('Asset'), __('Method'), __('Type'), __('Min / Max'), __('Fee'), __('Active'), '']">
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
                            <x-dynamic-component :component="'heroicon-o-'.($m->type === 'bank' ? 'building-library' : 'device-phone-mobile')" class="mr-1 inline h-3.5 w-3.5" />
                            {{ $types[$m->type] ?? ucfirst($m->type) }}
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
                        <form method="POST" action="{{ route('admin.withdrawal-methods.toggle', $m->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex">
                                <x-ui.badge :color="$m->is_active ? 'success' : 'gray'" dot>{{ $m->is_active ? __('Active') : __('Disabled') }}</x-ui.badge>
                            </button>
                        </form>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <x-ui.button variant="secondary" size="sm" icon="pencil-square"
                            x-on:click="edit({{ Illuminate\Support\Js::from(['id' => $m->id, 'asset_id' => $m->asset_id, 'name' => $m->name, 'type' => $m->type, 'number_label' => $m->details['number_label'] ?? '', 'instructions' => $m->instructions, 'min_amount' => ($m->min_amount !== null && $m->asset) ? $m->asset->money($m->min_amount)->toDecimal() : '', 'max_amount' => ($m->max_amount !== null && $m->asset) ? $m->asset->money($m->max_amount)->toDecimal() : '', 'fixed_fee' => ($m->fixed_fee !== null && $m->asset) ? $m->asset->money($m->fixed_fee)->toDecimal() : '', 'percent_fee_bps' => (string) ($m->percent_fee_bps ?? 0), 'sort' => (string) ($m->sort ?? 0), 'is_active' => (bool) $m->is_active]) }})">{{ __('Edit') }}</x-ui.button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="banknotes" :title="__('No withdrawal methods')" :description="__('Add a method so users can cash out an asset.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{-- Create / edit modal --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-80" x-on:click="open = false"></div>
            <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto pp-card p-6" role="dialog" aria-modal="true">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-neutral-900" x-text="editingId ? 'Edit method' : 'New withdrawal method'"></h3>
                    <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </div>
                <form method="POST" action="{{ route('admin.withdrawal-methods.save') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="id" :value="editingId" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select :label="__('Asset')" name="asset_id" x-model="form.asset_id" :error="$errors->first('asset_id')">
                            <option value="">{{ __('Select asset…') }}</option>
                            @foreach ($assets as $asset)
                                <option value="{{ $asset->id }}">{{ $asset->symbol }} — {{ $asset->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.select :label="__('Type')" name="type" x-model="form.type" :error="$errors->first('type')">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <x-ui.input :label="__('Name')" name="name" x-model="form.name" placeholder="{{ __('e.g. bKash') }}" :error="$errors->first('name')" />

                    <x-ui.input :label="__('Number label')" name="number_label" x-model="form.number_label" placeholder="{{ __('e.g. bKash number') }}" :hint="__('Label shown to the user for the account/number they enter.')" :error="$errors->first('number_label')" />

                    <x-ui.textarea :label="__('Instructions')" name="instructions" x-model="form.instructions" rows="3" placeholder="{{ __('What the user should know about this payout method…') }}" :error="$errors->first('instructions')" />

                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.input :label="__('Min amount')" name="min_amount" x-model="form.min_amount" placeholder="{{ __('0.00') }}" :error="$errors->first('min_amount')" />
                        <x-ui.input :label="__('Max amount (optional)')" name="max_amount" x-model="form.max_amount" placeholder="{{ __('—') }}" :error="$errors->first('max_amount')" />
                        <x-ui.input :label="__('Fixed fee')" name="fixed_fee" x-model="form.fixed_fee" placeholder="{{ __('0.00') }}" :error="$errors->first('fixed_fee')" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input :label="__('Percent fee (bps)')" name="percent_fee_bps" x-model="form.percent_fee_bps" type="number" placeholder="{{ __('0') }}" :hint="__('100 bps = 1%')" :error="$errors->first('percent_fee_bps')" />
                        <x-ui.input :label="__('Sort order')" name="sort" x-model="form.sort" type="number" placeholder="{{ __('0') }}" :error="$errors->first('sort')" />
                    </div>

                    <x-ui.checkbox name="is_active" value="1" x-model="form.is_active" :label="__('Active')" />

                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button type="button" variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" x-text="editingId ? 'Save changes' : 'Create method'"></x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
