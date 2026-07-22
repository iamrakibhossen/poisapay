<x-layouts.admin :title="'Exchange'">
    {{-- Alpine is light UI only: modal open/close + prefill for edit. The form POSTs traditionally. --}}
    <div x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            editingId: '{{ old('id') }}',
            form: {
                fromAssetId: '{{ old('fromAssetId') }}',
                toAssetId: '{{ old('toAssetId') }}',
                spreadBps: @js(old('spreadBps', '')),
                minAmount: @js(old('minAmount', '')),
                maxAmount: @js(old('maxAmount', '')),
                sort: {{ (int) old('sort', 0) }},
                is_active: {{ old('is_active', true) ? 'true' : 'false' }},
            },
            create() { this.editingId = ''; this.form = { fromAssetId: '', toAssetId: '', spreadBps: '', minAmount: '', maxAmount: '', sort: 0, is_active: true }; this.open = true; },
            edit(p) { this.editingId = p.id; this.form = { fromAssetId: String(p.from_asset_id), toAssetId: String(p.to_asset_id), spreadBps: p.spread_bps, minAmount: p.min_amount, maxAmount: p.max_amount, sort: p.sort, is_active: p.is_active }; this.open = true; },
        }" class="space-y-6">
        <x-ui.page-header title="Exchange" subtitle="Manage trading pairs and review swap volume (TDD §5 FX).">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui.button x-on:click="create()" icon="plus" size="sm">Add pair</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        {{-- KPI strip --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card label="Total swaps" :value="number_format($stats['total_swaps'])" icon="arrows-right-left" accent="brand" />
            <x-ui.stat-card label="Swaps today" :value="number_format($stats['today'])" icon="clock" accent="emerald" />
            <x-ui.stat-card label="Active pairs" :value="number_format($stats['pairs_traded'])" icon="squares-2x2" accent="amber" />
            <x-ui.stat-card label="Spread income accounts" :value="number_format($stats['spread_accounts'])" icon="banknotes" accent="rose" />
        </div>

        @if (! empty($spreadIncome))
            <x-ui.card>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">Spread income (fx:spread_income)</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($spreadIncome as $line)
                        <x-ui.badge color="success">{{ $line }}</x-ui.badge>
                    @endforeach
                </div>
            </x-ui.card>
        @endif

        {{-- Trading pairs --}}
        <x-ui.card>
            <h3 class="mb-4 text-sm font-semibold text-neutral-900">Trading pairs</h3>
            <x-ui.table :headers="['Pair', 'Spread', 'Min', 'Max', 'Active', 'Sort', '']">
                @forelse ($pairs as $pair)
                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <x-ui.asset-icon :symbol="$pair->fromAsset?->symbol ?? '?'" size="sm" />
                                <x-heroicon-o-arrow-right class="h-4 w-4 text-neutral-400" />
                                <x-ui.asset-icon :symbol="$pair->toAsset?->symbol ?? '?'" size="sm" />
                                <span class="ml-1 text-sm font-medium text-neutral-900">{{ $pair->label() }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-600">
                            @if ($pair->spread_bps === null)
                                <span class="text-neutral-400">default</span>
                            @else
                                <span class="tabular">{{ number_format($pair->spread_bps / 100, 2) }}%</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 tabular text-sm text-neutral-600">
                            {{ $pair->fromAsset ? $pair->fromAsset->money($pair->min_amount)->format() : $pair->min_amount }}
                        </td>
                        <td class="px-4 py-3 tabular text-sm text-neutral-600">
                            @if ($pair->max_amount === null)
                                <span class="text-neutral-400">—</span>
                            @else
                                {{ $pair->fromAsset ? $pair->fromAsset->money($pair->max_amount)->format() : $pair->max_amount }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($canManage)
                                <form method="POST" action="{{ route('admin.exchange.toggle', $pair->id) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex">
                                        <x-ui.badge :color="$pair->is_active ? 'success' : 'gray'" dot>{{ $pair->is_active ? 'Active' : 'Disabled' }}</x-ui.badge>
                                    </button>
                                </form>
                            @else
                                <x-ui.badge :color="$pair->is_active ? 'success' : 'gray'" dot>{{ $pair->is_active ? 'Active' : 'Disabled' }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 tabular text-sm text-neutral-500">{{ $pair->sort }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($canManage)
                                <div class="flex justify-end gap-2">
                                    <x-ui.button variant="secondary" size="sm" icon="pencil-square"
                                        x-on:click="edit({{ Illuminate\Support\Js::from([
                                            'id' => $pair->id,
                                            'from_asset_id' => $pair->from_asset_id,
                                            'to_asset_id' => $pair->to_asset_id,
                                            'spread_bps' => $pair->spread_bps === null ? '' : (string) $pair->spread_bps,
                                            'min_amount' => $pair->fromAsset ? \App\Support\Money::ofBase($pair->min_amount, $pair->fromAsset->decimals)->toDecimal() : (string) $pair->min_amount,
                                            'max_amount' => $pair->max_amount !== null && $pair->fromAsset ? \App\Support\Money::ofBase($pair->max_amount, $pair->fromAsset->decimals)->toDecimal() : '',
                                            'sort' => (int) $pair->sort,
                                            'is_active' => (bool) $pair->is_active,
                                        ]) }})">Edit</x-ui.button>
                                    <form method="POST" action="{{ route('admin.exchange.delete', $pair->id) }}" onsubmit="return confirm('Delete this trading pair?')">
                                        @csrf @method('DELETE')
                                        <x-ui.button type="submit" variant="danger" size="sm" icon="trash" />
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-ui.empty-state icon="squares-2x2" title="No trading pairs" description="Add a pair to enable swaps between two assets." /></td></tr>
                @endforelse
            </x-ui.table>
        </x-ui.card>

        {{-- Exchange history --}}
        <x-ui.card>
            <h3 class="mb-4 text-sm font-semibold text-neutral-900">Recent swaps</h3>
            <x-ui.table :headers="['User', 'Pair', 'From', 'To', 'Rate', 'Spread', 'Time']">
                @forelse ($conversions as $c)
                    @php $q = $c->quote; @endphp
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <x-ui.avatar :name="$c->user?->name ?? '?'" size="sm" />
                                <span class="truncate text-sm font-medium text-neutral-900">{{ $c->user?->name ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($q)
                                <div class="flex items-center gap-2">
                                    <x-ui.asset-icon :symbol="$q->fromAsset?->symbol ?? '?'" size="sm" />
                                    <x-heroicon-o-arrow-right class="h-3.5 w-3.5 text-neutral-400" />
                                    <x-ui.asset-icon :symbol="$q->toAsset?->symbol ?? '?'" size="sm" />
                                </div>
                            @else
                                <span class="text-sm text-neutral-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 tabular text-sm font-medium text-neutral-900">
                            {{ $q && $q->fromAsset ? $q->fromAsset->money($q->from_amount)->format() : '—' }}
                        </td>
                        <td class="px-4 py-3 tabular text-sm font-medium text-neutral-900">
                            {{ $q && $q->toAsset ? $q->toAsset->money($q->to_amount)->format() : '—' }}
                        </td>
                        <td class="px-4 py-3 tabular text-sm text-neutral-600">
                            {{ $q ? rtrim(rtrim(number_format((float) $q->rate, 8, '.', ''), '0'), '.') : '—' }}
                        </td>
                        <td class="px-4 py-3 tabular text-sm text-neutral-600">
                            {{ $q ? number_format($q->spread_bps / 100, 2).'%' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-500">{{ $c->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-ui.empty-state icon="arrows-right-left" title="No swaps yet" description="Completed conversions will appear here." /></td></tr>
                @endforelse
            </x-ui.table>

            <div class="mt-4">{{ $conversions->links() }}</div>
        </x-ui.card>

        {{-- Add / edit modal --}}
        @if ($canManage)
            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="open = false"></div>
                <div class="relative w-full max-w-lg pp-card p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-neutral-900" x-text="editingId ? 'Edit trading pair' : 'Add trading pair'"></h3>
                        <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>
                    <form method="POST" action="{{ route('admin.exchange.save') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="id" :value="editingId" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.select label="From asset" name="fromAssetId" x-model="form.fromAssetId" :error="$errors->first('fromAssetId')">
                                <option value="">Select…</option>
                                @foreach ($assets as $a)
                                    <option value="{{ $a->id }}">{{ $a->symbol }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.select label="To asset" name="toAssetId" x-model="form.toAssetId" :error="$errors->first('toAssetId')">
                                <option value="">Select…</option>
                                @foreach ($assets as $a)
                                    <option value="{{ $a->id }}">{{ $a->symbol }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <x-ui.input label="Spread (bps — blank = global default)" name="spreadBps" x-model="form.spreadBps" type="number" placeholder="75" :error="$errors->first('spreadBps')" />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input label="Min amount (from units)" name="minAmount" x-model="form.minAmount" placeholder="0.00" :error="$errors->first('minAmount')" />
                            <x-ui.input label="Max amount (optional)" name="maxAmount" x-model="form.maxAmount" placeholder="0.00" :error="$errors->first('maxAmount')" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input label="Sort" name="sort" x-model="form.sort" type="number" :error="$errors->first('sort')" />
                            <x-ui.checkbox name="is_active" value="1" x-model="form.is_active" label="Active" class="flex items-end pb-2" />
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <x-ui.button type="button" variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
                            <x-ui.button type="submit" x-text="editingId ? 'Save changes' : 'Add pair'"></x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts.admin>
