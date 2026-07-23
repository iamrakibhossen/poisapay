<x-layouts.admin :title="__('Custody & Xpubs')">
    {{-- Alpine is light UI only: modal open/close + prefill for edit. The form POSTs traditionally.
         SECURITY: the xpub field is never prefilled with the raw key from JS; editing re-enters it. --}}
    <div x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            editingId: '{{ old('id') }}',
            form: {
                chain_id: '{{ old('chain_id') }}',
                label: @js(old('label', '')),
                xpub: @js(old('xpub', '')),
                derivation_path: @js(old('derivation_path', '')),
                purpose: '{{ old('purpose', 'deposit') }}',
                is_active: {{ old('is_active', true) ? 'true' : 'false' }},
            },
            create() { this.editingId = ''; this.form = { chain_id: '', label: '', xpub: '', derivation_path: '', purpose: 'deposit', is_active: true }; this.open = true; },
            edit(x) { this.editingId = x.id; this.form = { chain_id: String(x.chain_id), label: x.label, xpub: x.xpub, derivation_path: x.derivation_path, purpose: x.purpose, is_active: x.is_active }; this.open = true; },
        }" class="space-y-6">
        <x-ui.page-header :title="__('Custody & Xpubs')" :subtitle="__('HD-wallet extended public keys used to derive deposit addresses (custody design §4.2 / D4).')">
            <x-slot:actions>
                <x-ui.button x-on:click="create()" icon="plus" size="sm">{{ __('Register xpub') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.alert type="info" :title="__('Public keys only')">
            {{ __('Register only extended') }} <strong>{{ __('public') }}</strong> {{ __('keys (xpub / tpub / ypub / zpub). Private keys (xprv / tprv) are never accepted or displayed — they must stay in offline custody. These xpubs let the platform derive fresh deposit addresses without ever holding spend authority.') }}
        </x-ui.alert>

        <x-ui.table :headers="[__('Chain'), __('Label'), __('Purpose'), __('Derivation path'), __('Addresses derived'), __('Xpub'), __('Active'), '']">
            @forelse ($xpubs as $x)
                @php $raw = $x->getAttributes()['xpub'] ?? ''; @endphp
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-4 py-3">
                        @if ($x->chain)
                            <x-ui.badge :color="$x->chain->key->color()">{{ $x->chain->key->label() }}</x-ui.badge>
                        @else
                            <span class="text-neutral-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold text-neutral-900">{{ $x->label }}</td>
                    <td class="px-4 py-3"><x-ui.badge :color="$x->purpose === 'deposit' ? 'info' : 'gray'">{{ ucfirst($x->purpose) }}</x-ui.badge></td>
                    <td class="px-4 py-3 font-mono text-xs text-neutral-600">{{ $x->derivation_path }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-600" title="{{ __('Monotonic address counter (system-managed)') }}">{{ number_format($x->next_index) }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-neutral-500">{{ \Illuminate\Support\Str::substr($raw, 0, 12) }}…</td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('admin.custody.toggle', $x->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex">
                                <x-ui.badge :color="$x->is_active ? 'success' : 'gray'" dot>{{ $x->is_active ? __('Active') : __('Disabled') }}</x-ui.badge>
                            </button>
                        </form>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            <x-ui.button variant="secondary" size="sm" icon="pencil-square"
                                x-on:click="edit({{ Illuminate\Support\Js::from(['id' => $x->id, 'chain_id' => $x->chain_id, 'label' => $x->label, 'xpub' => $raw, 'derivation_path' => (string) $x->derivation_path, 'purpose' => $x->purpose, 'is_active' => (bool) $x->is_active]) }})">{{ __('Edit') }}</x-ui.button>
                            <form method="POST" action="{{ route('admin.custody.delete', $x->id) }}" onsubmit="return confirm('Delete this xpub? Existing derived deposit addresses stay valid, but no new addresses can be derived from it.')">
                                @csrf @method('DELETE')
                                <x-ui.button type="submit" variant="danger" size="sm" icon="trash">{{ __('Delete') }}</x-ui.button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-ui.empty-state icon="key" :title="__('No xpubs registered')" :description="__('Register an extended public key to start deriving deposit addresses.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{-- Register / edit modal --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500/60" x-on:click="open = false"></div>
            <div class="relative w-full max-w-lg pp-card p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-neutral-900" x-text="editingId ? 'Edit xpub' : 'Register xpub'"></h3>
                    <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </div>
                <form method="POST" action="{{ route('admin.custody.save') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="id" :value="editingId" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select :label="__('Chain')" name="chain_id" x-model="form.chain_id" :error="$errors->first('chain_id')">
                            <option value="">{{ __('Select a chain…') }}</option>
                            @foreach ($chains as $chain)
                                <option value="{{ $chain->id }}">{{ $chain->key->label() }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input :label="__('Label')" name="label" x-model="form.label" :placeholder="__('Hot deposit vault')" :error="$errors->first('label')" />
                    </div>
                    <x-ui.textarea :label="__('Extended public key (xpub)')" name="xpub" x-model="form.xpub" :rows="3" class="font-mono text-xs" placeholder="xpub6…" :error="$errors->first('xpub')" :hint="__('Public keys only. Must start with xpub, tpub, ypub or zpub.')" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input :label="__('Derivation path')" name="derivation_path" x-model="form.derivation_path" placeholder="m/44'/60'/0'/0" :error="$errors->first('derivation_path')" />
                        <x-ui.select :label="__('Purpose')" name="purpose" x-model="form.purpose" :error="$errors->first('purpose')">
                            <option value="deposit">{{ __('Deposit (derive addresses)') }}</option>
                            <option value="cold-watch">{{ __('Cold watch-only') }}</option>
                        </x-ui.select>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <x-ui.checkbox name="is_active" value="1" x-model="form.is_active" :label="__('Active')" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button type="button" variant="secondary" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" x-text="editingId ? 'Save changes' : 'Register xpub'"></x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
