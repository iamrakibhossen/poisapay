<x-layouts.admin :title="__('Card Providers')">
    {{-- Alpine is light UI only: modal open/close + prefill for edit. The form POSTs traditionally. --}}
    <div x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            editingId: '{{ old('id') }}',
            form: {
                name: @js(old('name', '')),
                slug: @js(old('slug', '')),
                network: @js(old('network', 'visa')),
                driver: @js(old('driver', 'mock')),
                bin: @js(old('bin', '')),
                settlement_currency: @js(old('settlement_currency', 'USD')),
                api_base: @js(old('api_base', '')),
                supports_virtual: {{ old('supports_virtual', $errors->any() ? null : '1') ? 'true' : 'false' }},
                supports_physical: {{ old('supports_physical') ? 'true' : 'false' }},
                is_active: {{ old('is_active', $errors->any() ? null : '1') ? 'true' : 'false' }},
            },
            create() {
                this.editingId = '';
                this.form = { name: '', slug: '', network: 'visa', driver: 'mock', bin: '', settlement_currency: 'USD', api_base: '', supports_virtual: true, supports_physical: false, is_active: true };
                this.open = true;
            },
            edit(p) {
                this.editingId = p.id;
                this.form = { name: p.name, slug: p.slug, network: p.network, driver: p.driver, bin: p.bin ?? '', settlement_currency: p.settlement_currency, api_base: p.api_base ?? '', supports_virtual: p.supports_virtual, supports_physical: p.supports_physical, is_active: p.is_active };
                this.open = true;
            },
            slugify() {
                if (!this.editingId && this.form.slug === '') {
                    this.form.slug = this.form.name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
                }
            },
        }" class="space-y-6">
        <x-ui.page-header :title="__('Card Providers')" :subtitle="__('Issuer / BIN-sponsor programs the card generator can provision through (TDD §F3).')">
            <x-slot:actions>
                <x-ui.button x-on:click="create()" icon="plus" size="sm">{{ __('Add provider') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.table :headers="[__('Provider'), __('Network'), __('BIN'), __('Cards'), __('Currency'), __('Active'), '']">
            @forelse ($providers as $p)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-4 py-3">
                        <p class="text-sm font-semibold text-neutral-900">{{ $p->name }}</p>
                        <p class="font-mono text-xs text-neutral-400">{{ $p->slug }}</p>
                        <p class="text-xs text-neutral-500">{{ __('via') }} {{ $p->driver?->label() }}</p>
                    </td>
                    <td class="px-4 py-3"><x-ui.badge :color="$p->network === 'visa' ? 'info' : 'warning'">{{ ucfirst($p->network) }}</x-ui.badge></td>
                    <td class="px-4 py-3 font-mono text-sm text-neutral-600">{{ $p->bin ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-600">
                        @if ($p->supports_virtual)<x-ui.badge color="gray">{{ __('Virtual') }}</x-ui.badge>@endif
                        @if ($p->supports_physical)<x-ui.badge color="gray">{{ __('Physical') }}</x-ui.badge>@endif
                    </td>
                    <td class="px-4 py-3 text-sm text-neutral-600">{{ $p->settlement_currency }}</td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('admin.card-providers.toggle', $p->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex">
                                <x-ui.badge :color="$p->is_active ? 'success' : 'gray'" dot>{{ $p->is_active ? __('Active') : __('Disabled') }}</x-ui.badge>
                            </button>
                        </form>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <x-ui.button variant="secondary" size="sm" icon="pencil-square"
                            x-on:click="edit({{ Illuminate\Support\Js::from(['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'network' => $p->network, 'driver' => $p->driver?->value, 'bin' => $p->bin, 'settlement_currency' => $p->settlement_currency, 'api_base' => $p->api_base, 'supports_virtual' => (bool) $p->supports_virtual, 'supports_physical' => (bool) $p->supports_physical, 'is_active' => (bool) $p->is_active]) }})">{{ __('Edit') }}</x-ui.button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="credit-card" :title="__('No providers')" :description="__('Add a card issuer program to enable the generator.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{-- Add / edit modal --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-80" x-on:click="open = false"></div>
            <div class="relative w-full max-w-lg pp-card p-6" role="dialog" aria-modal="true">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-neutral-900" x-text="editingId ? 'Edit provider' : 'Add card provider'"></h3>
                    <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </div>
                <form method="POST" action="{{ route('admin.card-providers.save') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="id" :value="editingId" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input :label="__('Name')" name="name" x-model="form.name" x-on:blur="slugify()" :placeholder="__('Acme Issuer')" :error="$errors->first('name')" />
                        <x-ui.input :label="__('Slug (program code)')" name="slug" x-model="form.slug" :placeholder="__('acme-issuer')" :error="$errors->first('slug')" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select :label="__('Provider')" name="driver" x-model="form.driver" :error="$errors->first('driver')">
                            @foreach ($drivers as $d)
                                <option value="{{ $d->value }}">{{ $d->label() }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.select :label="__('Network')" name="network" x-model="form.network" :error="$errors->first('network')"><option value="visa">{{ __('Visa') }}</option><option value="mastercard">{{ __('Mastercard') }}</option></x-ui.select>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input :label="__('BIN prefix')" name="bin" x-model="form.bin" placeholder="453201" :error="$errors->first('bin')" />
                        <x-ui.input :label="__('Currency')" name="settlement_currency" x-model="form.settlement_currency" placeholder="USD" :error="$errors->first('settlement_currency')" />
                    </div>
                    <x-ui.input :label="__('API base (sandbox)')" name="api_base" x-model="form.api_base" :placeholder="__('https://sandbox…')" :error="$errors->first('api_base')" />
                    <div class="flex flex-wrap gap-4">
                        <x-ui.checkbox name="supports_virtual" value="1" x-model="form.supports_virtual" :label="__('Virtual cards')" />
                        <x-ui.checkbox name="supports_physical" value="1" x-model="form.supports_physical" :label="__('Physical cards')" />
                        <x-ui.checkbox name="is_active" value="1" x-model="form.is_active" :label="__('Active')" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button type="button" variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" x-text="editingId ? 'Save changes' : 'Add provider'"></x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
