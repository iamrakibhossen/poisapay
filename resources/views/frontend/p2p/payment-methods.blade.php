<x-layouts.app :title="'Payment accounts'">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-ui.page-header title="Payment accounts" subtitle="Save the accounts you receive fiat into. Buyers see these only after an order against your ad is open.">
            <x-slot:actions>
                <a href="{{ route('p2p.ads') }}"><x-ui.button variant="secondary" icon="arrow-left">My ads</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif
        @if (session('error'))<x-ui.alert type="error">{{ session('error') }}</x-ui.alert>@endif

        {{-- Saved accounts --}}
        <div class="space-y-3">
            @forelse ($accounts as $acc)
                @php $fields = $acc->method?->fields ?: []; @endphp
                <div class="pp-row flex items-start gap-4 p-4">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-sm font-bold text-brand-600">
                        {{ \Illuminate\Support\Str::substr($acc->method->name ?? '?', 0, 2) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-neutral-900">{{ $acc->method->name ?? 'Account' }}</p>
                            @if ($acc->label)<span class="text-xs text-neutral-400">· {{ $acc->label }}</span>@endif
                        </div>
                        <dl class="mt-1 grid gap-x-6 gap-y-0.5 text-sm sm:grid-cols-2">
                            @foreach ($fields as $f)
                                @if (! empty($acc->account[$f['key']]))
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-neutral-500">{{ $f['label'] }}</dt>
                                        <dd class="truncate font-medium text-neutral-900">{{ $acc->account[$f['key']] }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    </div>
                    <form method="POST" action="{{ route('p2p.payment-methods.destroy', $acc) }}">
                        @csrf @method('DELETE')
                        <x-ui.button type="submit" size="sm" variant="ghost" icon="trash" aria-label="Remove account" />
                    </form>
                </div>
            @empty
                <x-ui.card>
                    <x-ui.empty-state icon="credit-card" title="No payment accounts yet"
                        description="Add the accounts buyers should pay into. Without one, buyers have to ask for your details in chat." />
                </x-ui.card>
            @endforelse
        </div>

        {{-- Add account — fields adapt to the chosen method --}}
        <x-ui.card title="Add a payment account">
            <form method="POST" action="{{ route('p2p.payment-methods.store') }}" class="space-y-5"
                  x-data="{
                      method: @js(old('payment_method_id', '')),
                      schema: @js($methodFields),
                      values: @js((object) old('account', [])),
                      get fields() { return this.schema[this.method] || []; },
                  }">
                @csrf
                <div>
                    <label class="pp-label">Payment method</label>
                    <select name="payment_method_id" x-model="method" class="pp-input" required>
                        <option value="" disabled>Choose a method…</option>
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

                <x-ui.input name="label" label="Label (optional)" :value="old('label')" placeholder="e.g. Personal bKash" :error="$errors->first('label')" />

                <div class="flex justify-end">
                    <x-ui.button type="submit" icon="plus" x-bind:disabled="!method">Add account</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
