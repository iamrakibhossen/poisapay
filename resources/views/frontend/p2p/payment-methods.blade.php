<x-layouts.app :title="__('Payment accounts')">
    <div class="mx-auto max-w-3xl space-y-6"
         x-data="{ del: { id: null, name: '' } }"
         @if ($errors->any()) x-init="$nextTick(() => $dispatch('open-modal', 'pm-add'))" @endif>

        <x-ui.page-header :title="__('Payment accounts')" :subtitle="__('Save the accounts you receive fiat into. Buyers see these only after an order against your ad is open.')">
            <x-slot:actions>
                <a href="{{ route('p2p.ads') }}"><x-ui.button variant="secondary" icon="arrow-left">{{ __('My ads') }}</x-ui.button></a>
                <x-ui.button icon="plus" x-on:click="$dispatch('open-modal', 'pm-add')">{{ __('Add account') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif
        @if (session('error'))<x-ui.alert type="error">{{ session('error') }}</x-ui.alert>@endif

        {{-- Privacy note --}}
        <div class="flex items-start gap-2.5 rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-600">
            <x-heroicon-o-lock-closed class="mt-0.5 h-4 w-4 shrink-0 text-neutral-400" />
            <p>{{ __('Your details stay private. A buyer only sees the account tied to their open order — never your full list.') }}</p>
        </div>

        {{-- Saved accounts --}}
        @if ($accounts->isEmpty())
            <x-ui.card>
                <x-ui.empty-state icon="credit-card" :title="__('No payment accounts yet')"
                    :description="__('Add the accounts buyers should pay into. Without one, buyers have to ask for your details in chat.')">
                    <x-slot:action>
                        <x-ui.button icon="plus" x-on:click="$dispatch('open-modal', 'pm-add')">{{ __('Add your first account') }}</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($accounts as $acc)
                    @php $fields = $acc->method?->fields ?: []; @endphp
                    <div class="flex flex-col rounded-2xl border border-neutral-200 bg-white p-5 shadow-[var(--shadow-card)]">
                        <div class="flex items-start gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-sm font-bold uppercase text-brand-600">
                                {{ \Illuminate\Support\Str::substr($acc->method->name ?? '?', 0, 2) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-neutral-900">{{ $acc->method->name ?? __('Account') }}</p>
                                @if ($acc->label)<p class="truncate text-xs text-neutral-400">{{ $acc->label }}</p>@endif
                            </div>
                            <button type="button" aria-label="{{ __('Remove account') }}"
                                    class="shrink-0 rounded-lg p-1.5 text-neutral-400 transition-colors hover:bg-red-50 hover:text-red-600"
                                    x-on:click="del = { id: '{{ $acc->id }}', name: @js($acc->method->name ?? __('this account')) }; $dispatch('open-modal', 'pm-delete')">
                                <x-heroicon-o-trash class="h-4 w-4" />
                            </button>
                        </div>

                        <dl class="mt-4 space-y-2 border-t border-neutral-100 pt-4 text-sm">
                            @foreach ($fields as $f)
                                @if (! empty($acc->account[$f['key']]))
                                    <div class="flex items-center justify-between gap-3">
                                        <dt class="shrink-0 text-neutral-500">{{ $f['label'] }}</dt>
                                        <dd class="flex min-w-0 items-center gap-1.5">
                                            <span class="truncate font-medium text-neutral-900">{{ $acc->account[$f['key']] }}</span>
                                            <x-ui.copy-text :text="$acc->account[$f['key']]" />
                                        </dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Add account modal — fields adapt to the chosen method --}}
        <x-ui.modal name="pm-add" :title="__('Add a payment account')" :subtitle="__('Buyers pay into this when they trade against your ad.')" maxWidth="lg">
            <form method="POST" action="{{ route('p2p.payment-methods.store') }}" class="space-y-5"
                  x-data="{
                      method: @js(old('payment_method_id', '')),
                      schema: @js($methodFields),
                      values: @js((object) old('account', [])),
                      get fields() { return this.schema[this.method] || []; },
                  }">
                @csrf
                <div>
                    <label class="pp-label">{{ __('Payment method') }}</label>
                    <select name="payment_method_id" x-model="method" class="pp-input" required>
                        <option value="" disabled>{{ __('Choose a method…') }}</option>
                        @foreach ($methods as $m)
                            <option value="{{ $m->id }}">{{ $m->name }}</option>
                        @endforeach
                    </select>
                    @error('payment_method_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                {{-- Dynamic fields for the selected method --}}
                <div x-show="method" x-cloak class="grid gap-4 sm:grid-cols-2">
                    <template x-for="f in fields" :key="f.key">
                        <div>
                            <label class="pp-label">
                                <span x-text="f.label"></span><span x-show="f.required" class="text-red-500"> *</span>
                            </label>
                            <input type="text" :name="`account[${f.key}]`" x-model="values[f.key]"
                                   :required="f.required" class="pp-input">
                        </div>
                    </template>
                </div>
                @foreach ($errors->get('account.*') as $msgs)
                    <p class="text-xs text-red-600">{{ $msgs[0] }}</p>
                @endforeach

                <x-ui.input name="label" :label="__('Label (optional)')" :value="old('label')" placeholder="{{ __('e.g. Personal bKash') }}" :error="$errors->first('label')" />

                <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'pm-add')">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" icon="plus" x-bind:disabled="!method">{{ __('Add account') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        {{-- Delete confirmation --}}
        <x-ui.confirmation-modal name="pm-delete">
            <x-slot:title>{{ __('Remove payment account?') }}</x-slot:title>
            <x-slot:content>
                {{ __('Buyers with an open order on this account will no longer see where to pay. You can add it again anytime.') }}
                <span class="mt-1 block font-medium text-slate-700" x-text="del.name"></span>
            </x-slot:content>
            <x-slot:footer>
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'pm-delete')">{{ __('Cancel') }}</x-ui.button>
                <form method="POST" :action="`{{ url('p2p/payment-methods') }}/${del.id}`">
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" variant="danger" icon="trash">{{ __('Remove') }}</x-ui.button>
                </form>
            </x-slot:footer>
        </x-ui.confirmation-modal>
    </div>
</x-layouts.app>
