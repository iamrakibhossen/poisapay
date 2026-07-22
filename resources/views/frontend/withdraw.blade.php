<x-layouts.app :title="'Withdraw'">
    <div class="mx-auto max-w-2xl space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">Withdraw</h1>
            <p class="mt-1 text-sm text-neutral-500">Cash out to a bank account or mobile wallet, or send crypto to an external wallet.</p>
            @if ($recentCount > 0)
                <a href="{{ route('withdrawals') }}" class="group mt-3 inline-flex items-center gap-1 text-sm font-medium text-neutral-500 transition hover:text-brand-600">
                    View withdrawal history
                    <x-heroicon-o-chevron-right class="h-4 w-4 transition group-hover:translate-x-0.5" />
                </a>
            @endif
        </div>

        @unless ($enabled)
            <x-ui.alert type="warning" title="Withdrawals temporarily disabled">
                Withdrawals are paused for maintenance right now. Please check back shortly.
            </x-ui.alert>
        @endunless

        @if ($enabled)
            @php
                // Progress nav mirrors the deposit flow. Fiat cash-out collapses to a
                // single details step; crypto has an explicit network step.
                if ($fiatDetail) {
                    $stepLabels = ['Asset', 'Cash-out']; $currentStep = 2;
                } elseif (! $coin) {
                    $stepLabels = ['Asset', 'Destination', 'Confirm']; $currentStep = 1;
                } elseif (! $assetId || ! $networkDetail) {
                    $stepLabels = ['Asset', 'Network', 'Confirm']; $currentStep = 2;
                } else {
                    $stepLabels = ['Asset', 'Network', 'Confirm']; $currentStep = 3;
                }
            @endphp
            <nav aria-label="Progress" class="flex items-center gap-2 px-1">
                @foreach ($stepLabels as $i => $label)
                    @php $n = $i + 1; $done = $n < $currentStep; $active = $n === $currentStep; @endphp
                    <div class="flex items-center gap-2">
                        <span @class([
                            'grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-bold transition',
                            'bg-brand-500 text-white' => $active,
                            'bg-brand-100 text-brand-700' => $done,
                            'bg-neutral-100 text-neutral-400' => ! $active && ! $done,
                        ])>
                            @if ($done)<x-heroicon-o-check class="h-4 w-4" />@else{{ $n }}@endif
                        </span>
                        <span @class(['text-sm font-medium', 'text-neutral-900' => $active, 'text-neutral-500' => ! $active])>{{ $label }}</span>
                    </div>
                    @unless ($loop->last)<span class="h-px flex-1 bg-neutral-200"></span>@endunless
                @endforeach
            </nav>

            <div>
                <x-ui.card>
                        @if ($fiatDetail)
                            {{-- Fiat cash-out: pick a saved account or add a new one (methods are per-currency) --}}
                            @php $hasMethods = count($fiatDetail['methods']) > 0; @endphp
                            @php $dp = min((int) $fiatDetail['decimals'], 2); @endphp
                            <div x-data="{
                                    target: '{{ old('accountId', count($fiatDetail['accounts']) ? $fiatDetail['accounts'][0]['id'] : 'new') }}',
                                    methodId: '{{ old('methodId', $hasMethods ? $fiatDetail['methods'][0]['id'] : '') }}',
                                    methods: @js($fiatDetail['methods']),
                                    accounts: @js($fiatDetail['accounts']),
                                    save: {{ old('saveAccount') ? 'true' : 'false' }},
                                    amount: @js(old('amount', '')),
                                    maxStr: @js($fiatDetail['max']),
                                    max: {{ (float) $fiatDetail['max'] }},
                                    available: {{ (float) $fiatDetail['availableDecimal'] }},
                                    dp: {{ $dp }},
                                    submitting: false,
                                    get isNew() { return this.target === 'new'; },
                                    get account() { return this.accounts.find(a => String(a.id) === String(this.target)) ?? null; },
                                    get method() {
                                        const id = this.isNew ? this.methodId : this.account?.methodId;
                                        return this.methods.find(m => String(m.id) === String(id)) ?? null;
                                    },
                                    get amountNum() { const n = parseFloat(this.amount); return isNaN(n) || n < 0 ? 0 : n; },
                                    get feeAmount() { if (!this.method) return 0; return this.method.feeFixed + this.amountNum * this.method.feeBps / 10000; },
                                    get totalDebited() { return this.amountNum + this.feeAmount; },
                                    get exceeds() { return this.totalDebited > this.available + 1e-9; },
                                    get belowMin() { return this.method && this.amountNum > 0 && this.amountNum < this.method.minNum; },
                                    get aboveMax() { return this.method && this.method.maxNum !== null && this.amountNum > this.method.maxNum; },
                                    get hasAmount() { return this.amountNum > 0; },
                                    get valid() { return this.hasAmount && !this.exceeds && !this.belowMin && !this.aboveMax; },
                                    fmt(n) { return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: this.dp }); },
                                    setMax() { this.amount = this.maxStr; },
                                }">
                                <div class="mb-5 flex items-center gap-3 rounded-xl bg-emerald-50/60 p-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                                        <x-heroicon-o-banknotes class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-neutral-900">Local Withdraw · {{ $fiatDetail['symbol'] }}</p>
                                        <p class="text-xs text-neutral-500">Available <span class="tabular font-medium text-neutral-700">{{ $fiatDetail['available'] }}</span></p>
                                    </div>
                                    <a href="{{ route('withdraw') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Change</a>
                                </div>

                                @unless ($hasMethods)
                                    <x-ui.alert type="warning">No cash-out methods are configured for {{ $fiatDetail['symbol'] }} yet. Please check back soon.</x-ui.alert>
                                @else
                                    <form method="POST" action="{{ route('withdraw.fiat') }}" class="space-y-5" x-on:submit="submitting = true">
                                        @csrf
                                        <input type="hidden" name="assetId" value="{{ $fiatDetail['assetId'] }}" />
                                        <input type="hidden" name="accountId" :value="isNew ? '' : target" />

                                        {{-- Amount — hero field with in-field Max chip + currency suffix --}}
                                        <div>
                                            <div class="flex items-center justify-between">
                                                <label class="pp-label" for="fiatAmount">Amount</label>
                                                <span class="text-xs text-neutral-500">Max <span class="tabular font-medium text-neutral-700" x-text="fmt(max)"></span></span>
                                            </div>
                                            <div class="relative">
                                                <input id="fiatAmount" name="amount" type="text" inputmode="decimal" x-model="amount"
                                                    placeholder="0.00" autocomplete="off"
                                                    class="pp-input tabular w-full !py-3.5 pr-32 text-2xl font-semibold"
                                                    x-bind:class="(exceeds || belowMin || aboveMax) ? '!border-red-400 focus:!border-red-400 focus:!ring-red-500/20' : ''" />
                                                <div class="absolute inset-y-0 right-3 flex items-center gap-2">
                                                    <button type="button" x-on:click="setMax()"
                                                        class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-600 transition hover:bg-emerald-100">MAX</button>
                                                    <span class="text-sm font-semibold text-neutral-400">{{ $fiatDetail['symbol'] }}</span>
                                                </div>
                                            </div>
                                            <div class="mt-1.5 text-xs">
                                                <span x-show="exceeds" x-cloak class="font-medium text-red-500">Exceeds your available balance after fees.</span>
                                                <span x-show="!exceeds && belowMin" x-cloak class="font-medium text-red-500">Below the minimum (<span x-text="method?.min"></span>).</span>
                                                <span x-show="!exceeds && !belowMin && aboveMax" x-cloak class="font-medium text-red-500">Above the maximum (<span x-text="method?.max"></span>).</span>
                                                <span x-show="!exceeds && !belowMin && !aboveMax && method" class="text-neutral-400">
                                                    Fee <span x-text="method?.feeLabel"></span> · Min <span x-text="method?.min"></span><template x-if="method?.max"> · Max <span x-text="method.max"></span></template>
                                                </span>
                                                @error('amount')<span class="font-medium text-red-500">{{ $message }}</span>@enderror
                                            </div>
                                        </div>

                                        {{-- Saved accounts --}}
                                        @if (count($fiatDetail['accounts']))
                                            <div>
                                                <label class="pp-label">Pay out to</label>
                                                <div class="space-y-2">
                                                    @foreach ($fiatDetail['accounts'] as $acct)
                                                        <label class="group flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition"
                                                            :class="target === '{{ $acct['id'] }}' ? 'border-brand-500 bg-brand-50' : 'border-neutral-200 hover:border-neutral-300'">
                                                            <input type="radio" x-model="target" value="{{ $acct['id'] }}" class="text-brand-500 focus:ring-brand-400" />
                                                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-neutral-100 text-neutral-500">
                                                                <x-heroicon-o-building-library class="h-4 w-4" />
                                                            </span>
                                                            <span class="min-w-0 flex-1">
                                                                <span class="block truncate text-sm font-medium text-neutral-900">{{ $acct['label'] }}</span>
                                                                <span class="block truncate text-xs text-neutral-500">{{ $acct['accountName'] }} · {{ $acct['accountNumber'] }}</span>
                                                            </span>
                                                            <button type="button" form="delacct-{{ $acct['id'] }}" class="text-neutral-300 hover:text-red-500" title="Remove">
                                                                <x-heroicon-o-trash class="h-4 w-4" />
                                                            </button>
                                                        </label>
                                                    @endforeach
                                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition"
                                                        :class="target === 'new' ? 'border-brand-500 bg-brand-50' : 'border-neutral-200 hover:border-neutral-300'">
                                                        <input type="radio" x-model="target" value="new" class="text-brand-500 focus:ring-brand-400" />
                                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-neutral-100 text-neutral-500">
                                                            <x-heroicon-o-plus class="h-4 w-4" />
                                                        </span>
                                                        <span class="text-sm font-medium text-neutral-900">Use a new account</span>
                                                    </label>
                                                </div>
                                            </div>
                                        @endif

                                        {{-- New account fields --}}
                                        <div x-show="isNew" x-cloak class="space-y-4">
                                            <div>
                                                <x-ui.select label="Payout method" name="methodId" x-model="methodId" :error="$errors->first('methodId')">
                                                    @foreach ($fiatDetail['methods'] as $m)
                                                        <option value="{{ $m['id'] }}">{{ $m['name'] }} ({{ ucfirst($m['type']) }})</option>
                                                    @endforeach
                                                </x-ui.select>
                                            </div>

                                            {{-- Bank name only for bank-type methods --}}
                                            <div x-show="method && method.isBank" x-cloak>
                                                <x-ui.input label="Bank name" name="bankName" :value="old('bankName')" placeholder="e.g. BRAC Bank" :error="$errors->first('bankName')" />
                                            </div>

                                            <x-ui.input label="Account holder name" name="accountName" :value="old('accountName')" icon="user" placeholder="As registered" :error="$errors->first('accountName')" />
                                            <div>
                                                <label class="pp-label" x-text="method ? method.numberLabel : 'Account number'">Account number</label>
                                                <x-ui.input name="accountNumber" :value="old('accountNumber')" icon="hashtag" placeholder="Account or mobile number" :error="$errors->first('accountNumber')" />
                                            </div>

                                            <label class="flex items-center gap-2 text-sm text-neutral-700">
                                                <input type="checkbox" name="saveAccount" value="1" x-model="save" class="rounded border-neutral-300 text-brand-500 focus:ring-brand-400" />
                                                Save this account for next time
                                            </label>
                                            <div x-show="save" x-cloak>
                                                <x-ui.input name="label" :value="old('label')" placeholder="Label (e.g. My bKash)" :error="$errors->first('label')" />
                                            </div>
                                        </div>

                                        {{-- Summary --}}
                                        <div class="space-y-2 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm" x-show="hasAmount" x-cloak>
                                            <div class="flex justify-between text-neutral-600">
                                                <span>You receive</span><span class="tabular font-medium text-neutral-900"><span x-text="fmt(amountNum)"></span> {{ $fiatDetail['symbol'] }}</span>
                                            </div>
                                            <div class="flex justify-between text-neutral-600" x-show="method">
                                                <span>Payout fee</span><span class="tabular font-medium text-neutral-900"><span x-text="fmt(feeAmount)"></span> {{ $fiatDetail['symbol'] }}</span>
                                            </div>
                                            <div class="flex justify-between border-t border-neutral-200 pt-2 font-semibold text-neutral-900">
                                                <span>Total debited</span><span class="tabular" x-bind:class="exceeds ? 'text-red-500' : ''"><span x-text="fmt(totalDebited)"></span> {{ $fiatDetail['symbol'] }}</span>
                                            </div>
                                        </div>

                                        @if ($requires2fa)
                                            <x-ui.input label="Two-factor code" name="twoFactorCode" icon="shield-check"
                                                inputmode="numeric" autocomplete="one-time-code" placeholder="123456" :error="$errors->first('twoFactorCode')" />
                                        @endif

                                        <x-ui.button type="submit" class="w-full" icon="banknotes" x-bind:disabled="!valid || submitting">
                                            <span x-show="!submitting"><span x-show="valid">Cash out <span class="tabular" x-text="fmt(amountNum)"></span> {{ $fiatDetail['symbol'] }}</span><span x-show="!valid">Request cash-out</span></span>
                                            <span x-show="submitting" x-cloak>Processing…</span>
                                        </x-ui.button>
                                    </form>

                                    {{-- Out-of-band delete forms for saved accounts --}}
                                    @foreach ($fiatDetail['accounts'] as $acct)
                                        <form id="delacct-{{ $acct['id'] }}" method="POST" action="{{ route('withdraw.account.delete', $acct['id']) }}" class="hidden"
                                            onsubmit="return confirm('Remove this saved account?')">
                                            @csrf @method('DELETE')
                                        </form>
                                    @endforeach
                                @endunless
                            </div>
                        @elseif ($coins->isEmpty() && $cashOptions->isEmpty())
                            <x-ui.empty-state icon="wallet" title="Nothing to withdraw"
                                description="You need a funded balance before you can withdraw.">
                                <x-slot:action>
                                    <x-ui.button href="{{ route('deposit') }}" icon="arrow-down-tray">Make a deposit</x-ui.button>
                                </x-slot:action>
                            </x-ui.empty-state>
                        @elseif (! $coin)
                            {{-- Step 1: choose what to withdraw --}}
                            @if ($cashOptions->isNotEmpty())
                                <p class="mb-3 text-sm font-semibold text-neutral-900">Local Withdraw <span class="font-normal text-neutral-400">· bank or mobile wallet</span></p>
                                <div class="mb-6 space-y-2.5">
                                    @foreach ($cashOptions as $cash)
                                        <a href="{{ route('withdraw', ['cash' => $cash['assetId']]) }}"
                                            class="group flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/40 p-4 text-left transition hover:border-emerald-400 hover:bg-emerald-50">
                                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                                                <x-heroicon-o-banknotes class="h-5 w-5" />
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-semibold text-neutral-900">{{ $cash['symbol'] }} cash-out</p>
                                                <p class="truncate text-xs text-neutral-500">Bank · mobile wallet</p>
                                            </div>
                                            <p class="tabular text-sm font-semibold text-neutral-900">{{ $cash['available'] }}</p>
                                            <x-heroicon-o-chevron-right class="h-5 w-5 shrink-0 text-neutral-300 transition group-hover:translate-x-0.5 group-hover:text-emerald-500" />
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            @if ($coins->isNotEmpty())
                                <p class="mb-3 text-sm font-semibold text-neutral-900">Crypto Withdraw <span class="font-normal text-neutral-400">· to an external wallet</span></p>
                                <div class="space-y-2.5">
                                    @foreach ($coins as $c)
                                        @php
                                            // Single-network coins skip straight to the network step.
                                            $params = $c['networkCount'] === 1
                                                ? ['coin' => $c['symbol'], 'asset' => $c['networks'][0]['assetId']]
                                                : ['coin' => $c['symbol']];
                                        @endphp
                                        <a href="{{ route('withdraw', $params) }}"
                                            class="group flex items-center gap-3 rounded-xl border border-neutral-200 p-4 text-left transition hover:border-brand-400 hover:bg-brand-50/40">
                                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-50 text-sm font-bold text-brand-600">{{ Str::substr($c['symbol'], 0, 2) }}</span>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-semibold text-neutral-900">{{ $c['symbol'] }}</p>
                                                <p class="truncate text-xs text-neutral-500">{{ $c['name'] }}</p>
                                            </div>
                                            <div class="text-right">
                                                <p class="tabular text-sm font-semibold text-neutral-900">{{ $c['total'] }}</p>
                                                <p class="text-[11px] text-neutral-400">{{ $c['networkCount'] }} {{ Str::plural('network', $c['networkCount']) }}</p>
                                            </div>
                                            <x-heroicon-o-chevron-right class="h-5 w-5 shrink-0 text-neutral-300 transition group-hover:translate-x-0.5 group-hover:text-brand-500" />
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        @elseif (! $assetId || ! $networkDetail)
                            {{-- Step 2: choose a network --}}
                            <div class="mb-4 flex items-center justify-between">
                                <p class="text-sm font-semibold text-neutral-900">Choose the {{ $coin }} network</p>
                                <a href="{{ route('withdraw') }}" class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700">
                                    <x-heroicon-o-arrow-left class="h-3.5 w-3.5" /> Change coin
                                </a>
                            </div>
                            <x-ui.alert type="warning" class="mb-4">
                                Send to the matching network only. Withdrawing on the wrong network can permanently lose funds.
                            </x-ui.alert>
                            <div class="space-y-2.5">
                                @foreach ($selectedCoin['networks'] as $n)
                                    <a href="{{ route('withdraw', ['coin' => $coin, 'asset' => $n['assetId']]) }}"
                                        class="group flex w-full items-center gap-3 rounded-xl border border-neutral-200 p-4 text-left transition hover:border-brand-400 hover:bg-brand-50/40">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600">
                                            <x-heroicon-o-globe-alt class="h-5 w-5" />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-neutral-900">{{ $n['chainName'] }}</p>
                                            <p class="text-xs text-neutral-500">{{ $coin }} network</p>
                                        </div>
                                        <p class="tabular text-sm font-medium text-neutral-700">{{ $n['available'] }}</p>
                                        <x-heroicon-o-chevron-right class="h-5 w-5 shrink-0 text-neutral-300 transition group-hover:translate-x-0.5 group-hover:text-brand-500" />
                                    </a>
                                @endforeach
                            </div>
                        @else
                            {{-- Step 3: amount + address form --}}
                            @php $dp = min((int) $networkDetail['decimals'], 8); @endphp
                            <div x-data="{
                                    fee: {{ (float) $networkDetail['feeDecimal'] }},
                                    feePercent: {{ (float) $networkDetail['feePercent'] }},
                                    available: {{ (float) $networkDetail['availableDecimal'] }},
                                    max: {{ (float) $networkDetail['max'] }},
                                    maxStr: @js($networkDetail['max']),
                                    dp: {{ $dp }},
                                    amount: @js(old('amount', '')),
                                    address: @js(old('toAddress', '')),
                                    submitting: false,
                                    get amountNum() { const n = parseFloat(this.amount); return isNaN(n) || n < 0 ? 0 : n; },
                                    get platformFee() { return this.amountNum * this.feePercent / 100; },
                                    get totalDebited() { return this.amountNum + this.fee + this.platformFee; },
                                    get exceeds() { return this.totalDebited > this.available + 1e-12; },
                                    get hasAmount() { return this.amountNum > 0; },
                                    get valid() { return this.hasAmount && !this.exceeds && this.address.trim().length > 0; },
                                    fmt(n) { return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: this.dp }); },
                                    setMax() { this.amount = this.maxStr; },
                                }">
                                {{-- Breadcrumb --}}
                                <div class="mb-5 flex items-center gap-3 rounded-xl bg-neutral-50 p-3">
                                    <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-600">{{ Str::substr($coin, 0, 2) }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-neutral-900">{{ $coin }}</p>
                                        <p class="text-xs text-neutral-500">{{ $networkDetail['network'] }} network</p>
                                    </div>
                                    <a href="{{ route('withdraw') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Change</a>
                                </div>

                                <form method="POST" action="{{ route('withdraw.submit') }}" class="space-y-5" x-on:submit="submitting = true">
                                    @csrf
                                    <input type="hidden" name="assetId" value="{{ $networkDetail['assetId'] }}" />

                                    {{-- Amount — the hero field, with an in-field Max chip and coin suffix --}}
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <label class="pp-label" for="amount">Amount</label>
                                            <span class="text-xs text-neutral-500">Available <span class="tabular font-medium text-neutral-700">{{ $networkDetail['available'] }}</span></span>
                                        </div>
                                        <div class="relative">
                                            <input id="amount" name="amount" type="text" inputmode="decimal" x-model="amount"
                                                placeholder="0.00" autocomplete="off"
                                                class="pp-input tabular w-full !py-3.5 pr-32 text-2xl font-semibold"
                                                x-bind:class="exceeds ? '!border-red-400 focus:!border-red-400 focus:!ring-red-500/20' : ''" />
                                            <div class="absolute inset-y-0 right-3 flex items-center gap-2">
                                                <button type="button" x-on:click="setMax()"
                                                    class="rounded-md bg-brand-50 px-2 py-1 text-xs font-semibold text-brand-600 transition hover:bg-brand-100">MAX</button>
                                                <span class="text-sm font-semibold text-neutral-400">{{ $coin }}</span>
                                            </div>
                                        </div>
                                        <div class="mt-1.5 flex items-center justify-between text-xs">
                                            <span x-show="exceeds" x-cloak class="font-medium text-red-500">Exceeds your available balance after fees.</span>
                                            <span x-show="!exceeds" class="text-neutral-400">Max withdrawable: <span class="tabular" x-text="fmt(max)"></span> {{ $coin }}</span>
                                            @error('amount')<span class="font-medium text-red-500">{{ $message }}</span>@enderror
                                        </div>
                                    </div>

                                    {{-- Destination address --}}
                                    <div>
                                        <label class="pp-label" for="toAddress">Destination address</label>
                                        <div class="relative">
                                            <input id="toAddress" name="toAddress" x-model="address" type="text"
                                                placeholder="Recipient wallet address" autocomplete="off" spellcheck="false"
                                                class="pp-input w-full pr-10 font-mono text-sm {{ $errors->first('toAddress') ? '!border-red-400' : '' }}" />
                                            <button type="button" x-show="address.length" x-cloak x-on:click="address = ''"
                                                class="absolute inset-y-0 right-2 flex items-center text-neutral-300 hover:text-neutral-500" title="Clear">
                                                <x-heroicon-o-x-mark class="h-4 w-4" />
                                            </button>
                                        </div>
                                        @error('toAddress')<p class="mt-1.5 text-xs font-medium text-red-500">{{ $message }}</p>@enderror
                                        <p class="mt-1.5 text-xs text-neutral-400">Double-check the address — on-chain withdrawals to the wrong address can't be reversed.</p>
                                    </div>

                                    {{-- Memo --}}
                                    <x-ui.input label="Memo (optional)" name="memo" icon="chat-bubble-left-ellipsis" :value="old('memo')"
                                        placeholder="Internal note (not sent on-chain)" :error="$errors->first('memo')" />

                                    {{-- Summary --}}
                                    <div class="space-y-2 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm">
                                        <div class="flex justify-between text-neutral-600">
                                            <span>Recipient gets</span><span class="tabular font-medium text-neutral-900"><span x-text="fmt(amountNum)"></span> {{ $coin }}</span>
                                        </div>
                                        <div class="flex justify-between text-neutral-600">
                                            <span>Network fee</span><span class="tabular font-medium text-neutral-900">{{ $networkDetail['fee'] }}</span>
                                        </div>
                                        <div class="flex justify-between text-neutral-600" x-show="feePercent > 0">
                                            <span>Platform fee (<span x-text="feePercent"></span>%)</span><span class="tabular font-medium text-neutral-900"><span x-text="fmt(platformFee)"></span> {{ $coin }}</span>
                                        </div>
                                        <div class="flex justify-between border-t border-neutral-200 pt-2 font-semibold text-neutral-900">
                                            <span>Total debited</span><span class="tabular" x-bind:class="exceeds ? 'text-red-500' : ''"><span x-text="fmt(totalDebited)"></span> {{ $coin }}</span>
                                        </div>
                                    </div>

                                    {{-- 2FA --}}
                                    @if ($requires2fa)
                                        <x-ui.input label="Two-factor code" name="twoFactorCode" icon="shield-check"
                                            inputmode="numeric" autocomplete="one-time-code" placeholder="123456"
                                            :error="$errors->first('twoFactorCode')"
                                            hint="Enter the 6-digit code from your authenticator app." />
                                    @else
                                        <x-ui.alert type="info">
                                            Tip: enable two-factor authentication to add an extra layer of protection to withdrawals.
                                        </x-ui.alert>
                                    @endif

                                    <x-ui.button type="submit" class="w-full" icon="arrow-up-tray"
                                        x-bind:disabled="!valid || submitting">
                                        <span x-show="!submitting"><span x-show="hasAmount && !exceeds">Withdraw <span class="tabular" x-text="fmt(amountNum)"></span> {{ $coin }}</span><span x-show="!hasAmount || exceeds">Withdraw</span></span>
                                        <span x-show="submitting" x-cloak>Processing…</span>
                                    </x-ui.button>
                                </form>
                            </div>
                        @endif
                    </x-ui.card>
            </div>
        @endif
    </div>
</x-layouts.app>
