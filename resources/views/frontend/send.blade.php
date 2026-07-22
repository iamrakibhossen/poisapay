<x-layouts.app :title="'Send Money'">
    <div class="mx-auto max-w-2xl space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">Send Money</h1>
            <p class="mt-1 text-sm text-neutral-500">Instant, zero-fee transfers to any PoisaPay user.</p>
            @if ($recentCount > 0)
                <a href="{{ route('transfers') }}" class="group mt-3 inline-flex items-center gap-1 text-sm font-medium text-neutral-500 transition hover:text-brand-600">
                    View transfer history
                    <x-heroicon-o-chevron-right class="h-4 w-4 transition group-hover:translate-x-0.5" />
                </a>
            @endif
        </div>

        <x-ui.card>
            @if (! count($wallets))
                <x-ui.empty-state icon="wallet" title="No funds to send"
                    description="Deposit or top up a balance before you can send money.">
                    <x-slot:action>
                        <x-ui.button href="{{ route('deposit') }}" icon="arrow-down-tray">Make a deposit</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @else
                @php $selectedAssetId = (int) old('assetId', $wallets[0]['assetId']); @endphp
                <form method="POST" action="{{ route('send.execute') }}" class="space-y-5" x-on:submit="submitting = true"
                    x-data="{
                        assetId: {{ $selectedAssetId }},
                        amount: @js(old('amount', '')),
                        recipient: @js(old('recipient', '')),
                        wallets: @js($wallets),
                        submitting: false,
                        get selected() { return this.wallets.find(w => w.assetId === this.assetId) ?? null; },
                        get amountNum() { const n = parseFloat(this.amount); return isNaN(n) || n < 0 ? 0 : n; },
                        get insufficient() {
                            if (!this.selected || this.amount.trim() === '') return false;
                            const bal = parseFloat(this.selected.available);
                            return !isNaN(bal) && this.amountNum > bal;
                        },
                        get hasAmount() { return this.amountNum > 0; },
                        get valid() { return this.hasAmount && !this.insufficient && this.recipient.trim().length > 0; },
                        setMax() { if (this.selected) this.amount = this.selected.available; },
                        fmt(n) { return n.toLocaleString(undefined, { maximumFractionDigits: 8 }); },
                    }">
                    @csrf
                    <input type="hidden" name="assetId" :value="assetId" />

                    {{-- Recipient --}}
                    <div>
                        <label class="pp-label" for="recipient">Recipient</label>
                        <div class="relative">
                            <x-heroicon-o-user class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                            <input id="recipient" name="recipient" x-model="recipient" type="text" autocomplete="off"
                                placeholder="@handle, email or phone"
                                class="pp-input w-full !pl-10 pr-10 {{ $errors->first('recipient') ? '!border-red-400' : '' }}" />
                            <button type="button" x-show="recipient.length" x-cloak x-on:click="recipient = ''"
                                class="absolute inset-y-0 right-2 flex items-center text-neutral-300 hover:text-neutral-500" title="Clear">
                                <x-heroicon-o-x-mark class="h-4 w-4" />
                            </button>
                        </div>
                        @error('recipient')<p class="mt-1.5 text-xs font-medium text-red-500">{{ $message }}</p>@enderror
                        <p class="mt-1.5 text-xs text-neutral-400">Enter the recipient's PoisaPay handle, email address or phone number.</p>
                    </div>

                    {{-- Amount — hero field with in-field Max chip + asset pill --}}
                    <div class="rounded-2xl border p-4 transition" :class="insufficient ? 'border-rose-300 bg-rose-50/40' : 'border-neutral-200 bg-neutral-50/60'">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-medium text-neutral-500">You send</span>
                            <button type="button" x-show="selected" x-on:click="setMax()" class="text-xs font-medium text-neutral-500 transition hover:text-brand-700">
                                Balance: <span class="tabular font-semibold text-neutral-700" x-text="selected?.availableFormatted"></span>
                                <span class="ms-1 rounded bg-brand-50 px-1.5 py-0.5 font-semibold text-brand-700">MAX</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-3">
                            <input id="amount" name="amount" x-model="amount" type="text" inputmode="decimal" placeholder="0.00" autocomplete="off"
                                class="min-w-0 flex-1 border-0 bg-transparent p-0 text-3xl font-semibold text-neutral-900 placeholder:text-neutral-300 focus:ring-0" />

                            <div class="relative flex shrink-0 items-center gap-2 rounded-full bg-white py-1.5 pl-1.5 pr-8 shadow-sm ring-1 ring-neutral-200 transition hover:ring-neutral-300">
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-500 text-[10px] font-bold text-white" x-text="(selected?.symbol ?? '').slice(0, 2)"></span>
                                <span class="text-sm font-semibold text-neutral-900" x-text="selected?.symbol"></span>
                                <x-heroicon-o-chevron-down class="pointer-events-none absolute right-2.5 h-4 w-4 text-neutral-400" />
                                <select x-model.number="assetId" class="absolute inset-0 cursor-pointer opacity-0" aria-label="Asset">
                                    @foreach ($wallets as $w)
                                        <option value="{{ $w['assetId'] }}" @selected($selectedAssetId === (int) $w['assetId'])>{{ $w['symbol'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-1.5 text-xs">
                            <span x-show="insufficient" x-cloak class="font-medium text-rose-600">More than your available balance.</span>
                            @error('amount')<span class="font-medium text-red-500">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    {{-- Memo --}}
                    <x-ui.input label="Memo (optional)" name="memo" icon="chat-bubble-left-ellipsis" :value="old('memo')"
                        placeholder="What's this for?" :error="$errors->first('memo')" />

                    {{-- Live summary reinforcing the zero-fee promise --}}
                    <div x-show="valid" x-cloak class="space-y-2 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm">
                        <div class="flex items-center justify-between text-neutral-600">
                            <span>Transfer fee</span>
                            <span class="font-semibold text-emerald-600">Free</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-neutral-200 pt-2 text-neutral-600">
                            <span>Recipient gets</span>
                            <span class="tabular font-semibold text-neutral-900"><span x-text="fmt(amountNum)"></span> <span x-text="selected?.symbol"></span></span>
                        </div>
                    </div>

                    <x-ui.button type="submit" class="w-full" icon="paper-airplane" x-bind:disabled="!valid || submitting">
                        <span x-show="!submitting"><span x-show="valid">Send <span class="tabular" x-text="fmt(amountNum)"></span> <span x-text="selected?.symbol"></span></span><span x-show="!valid">Send now</span></span>
                        <span x-show="submitting" x-cloak>Sending…</span>
                    </x-ui.button>
                </form>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
