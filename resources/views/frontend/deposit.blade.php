<x-layouts.app :title="'Deposit'">
    <div class="mx-auto max-w-2xl space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">Deposit</h1>
            <p class="mt-1 text-sm text-neutral-500">Fund your PoisaPay account with crypto or a supported payment method.</p>
            @if ($recentCount > 0)
                <a href="{{ route('deposits') }}" class="group mt-3 inline-flex items-center gap-1 text-sm font-medium text-neutral-500 transition hover:text-brand-600">
                    View deposit history
                    <x-heroicon-o-chevron-right class="h-4 w-4 transition group-hover:translate-x-0.5" />
                </a>
            @endif
        </div>

        @unless ($depositEnabled)
            <x-ui.alert type="warning" title="Deposits are disabled">
                Deposits are currently turned off. Please check back shortly.
            </x-ui.alert>
        @endunless

        {{-- 3-step flow (state held in the query string) --}}
        <div class="space-y-6">
            @php
                // Progress adapts: assets with deposit methods have a Method step;
                    // on-chain assets go straight from Currency to their Address.
                    $hasMethods = $selectedAsset && $selectedAsset->depositMethods->isNotEmpty();
                    if (! $selectedAsset) {
                        $stepLabels = ['Currency', 'Method', 'Confirm']; $currentStep = 1;
                    } elseif ($hasMethods) {
                        $stepLabels = ['Currency', 'Method', 'Confirm']; $currentStep = $selectedMethod ? 3 : 2;
                    } else {
                        $stepLabels = ['Currency', 'Address']; $currentStep = 2;
                    }
                    // The final step (address / confirm) is presented as its own focused
                    // details page — the recent-deposits list is hidden there.
                    $isFinal = $selectedMethod || ($selectedAsset && $selectedAsset->depositMethods->isEmpty());
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

                @if (! $selectedAsset)
                    @php $symbolParam = request('symbol'); @endphp
                    @if ($symbolParam && ($networkAssets = $assets->where('symbol', $symbolParam)->values())->isNotEmpty())
                        {{-- STEP 1b: a coin exists on several chains — choose the network --}}
                        <x-ui.card>
                            <div class="mb-4 flex items-center gap-3">
                                <x-ui.asset-icon :symbol="$symbolParam" size="lg" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-neutral-900">{{ $symbolParam }}</p>
                                    <p class="text-xs text-neutral-500">Choose a network to deposit on</p>
                                </div>
                                <a href="{{ route('deposit') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700">
                                    <x-heroicon-o-arrow-left class="h-4 w-4" /> Back
                                </a>
                            </div>
                            <div class="space-y-2.5">
                                @foreach ($networkAssets as $a)
                                    <a href="{{ route('deposit', ['asset' => $a->id]) }}"
                                        class="group flex w-full items-center gap-3 rounded-xl border border-neutral-200 p-4 text-left transition hover:border-brand-400 hover:bg-brand-50/40">
                                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                                            <x-heroicon-o-cube class="h-5 w-5" />
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-semibold text-neutral-900">{{ $a->chain?->name ?? $a->name }}</span>
                                            <span class="block text-xs text-neutral-500">Deposit {{ $a->symbol }} on {{ $a->chain?->name ?? $a->name }}</span>
                                        </span>
                                        <x-heroicon-o-chevron-right class="h-5 w-5 shrink-0 text-neutral-300 transition group-hover:translate-x-0.5 group-hover:text-brand-500" />
                                    </a>
                                @endforeach
                            </div>
                        </x-ui.card>
                    @else
                        {{-- STEP 1: choose a coin (the same symbol on multiple chains is merged into one row) --}}
                        <x-ui.card title="1. Choose a currency" subtitle="Select the coin or currency you want to deposit.">
                            @if ($assets->isEmpty())
                                <x-ui.empty-state icon="banknotes" title="No currencies available"
                                    description="Deposit currencies are not configured yet. Please check back soon." />
                            @else
                                <div class="space-y-2.5">
                                    @foreach ($assets->groupBy('symbol') as $symbol => $group)
                                        @php $multiNetwork = $group->count() > 1; $firstAsset = $group->first(); @endphp
                                        <a href="{{ $multiNetwork ? route('deposit', ['symbol' => $symbol]) : route('deposit', ['asset' => $firstAsset->id]) }}"
                                            class="group flex w-full items-center gap-3 rounded-xl border border-neutral-200 p-4 text-left transition hover:border-brand-400 hover:bg-brand-50/40">
                                            <x-ui.asset-icon :symbol="$symbol" size="md" />
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-sm font-semibold text-neutral-900">{{ $symbol }}</span>
                                                @if ($multiNetwork)
                                                    <span class="block text-xs text-neutral-500">{{ $group->count() }} networks</span>
                                                @elseif ($firstAsset->chain?->name)
                                                    <span class="mt-0.5 inline-flex items-center gap-1 text-xs font-medium text-neutral-500">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>{{ $firstAsset->chain->name }}
                                                    </span>
                                                @else
                                                    <span class="block text-xs text-neutral-500">{{ $firstAsset->name }}</span>
                                                @endif
                                            </span>
                                            <x-heroicon-o-chevron-right class="h-5 w-5 shrink-0 text-neutral-300 transition group-hover:translate-x-0.5 group-hover:text-brand-500" />
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </x-ui.card>
                    @endif
                @else
                    {{-- Selected asset header --}}
                    <x-ui.card>
                        <div class="flex items-center gap-3">
                            <x-ui.asset-icon :symbol="$selectedAsset->symbol" size="lg" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-neutral-900">{{ $selectedAsset->symbol }}</p>
                                <p class="text-xs text-neutral-500">{{ $selectedAsset->name }}</p>
                            </div>
                            <a href="{{ route('deposit') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700">
                                <x-heroicon-o-arrow-left class="h-4 w-4" /> Change currency
                            </a>
                        </div>
                    </x-ui.card>

                    @if ($selectedAsset->depositMethods->isNotEmpty() && ! $selectedMethod)
                        {{-- STEP 2: choose a deposit method --}}
                        <x-ui.card title="2. Choose a deposit method">
                            <div class="space-y-2.5">
                                @foreach ($selectedAsset->depositMethods as $m)
                                    <a href="{{ route('deposit', ['asset' => $selectedAsset->id, 'method' => $m->id]) }}"
                                        class="group flex w-full items-center gap-3 rounded-xl border border-neutral-200 p-4 text-left transition hover:border-brand-400 hover:bg-brand-50/40">
                                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                                            <x-dynamic-component :component="'heroicon-o-'.$m->type->icon()" class="h-5 w-5" />
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-semibold text-neutral-900">{{ $m->name }}</span>
                                            <span class="block text-xs text-neutral-500">{{ $m->type->label() }}</span>
                                        </span>
                                        <x-heroicon-o-chevron-right class="h-5 w-5 shrink-0 text-neutral-300 transition group-hover:translate-x-0.5 group-hover:text-brand-500" />
                                    </a>
                                @endforeach
                            </div>
                        </x-ui.card>
                    @elseif ($selectedMethod)
                        {{-- STEP 3: method details + submit --}}
                        @php $isCrypto = $selectedMethod->type === \App\Enums\DepositMethodType::Crypto; @endphp
                        <x-ui.card>
                            <div class="mb-4 flex items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                                    <x-dynamic-component :component="'heroicon-o-'.$selectedMethod->type->icon()" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-neutral-900">{{ $selectedMethod->name }}</p>
                                    <p class="text-xs text-neutral-500">{{ $selectedMethod->type->label() }}</p>
                                </div>
                                <a href="{{ route('deposit', ['asset' => $selectedAsset->id]) }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700">
                                    <x-heroicon-o-arrow-left class="h-4 w-4" /> Change method
                                </a>
                            </div>

                            @php $details = \App\Http\Controllers\Frontend\DepositController::methodDetails($selectedMethod); @endphp
                            @if (count($details))
                                <div class="mb-4 divide-y divide-neutral-100 rounded-xl border border-neutral-200 bg-neutral-50/40">
                                    @foreach ($details as $row)
                                        <div class="flex items-start justify-between gap-3 px-4 py-2.5">
                                            <span class="text-xs font-medium text-neutral-500">{{ $row['label'] }}</span>
                                            <span class="text-right text-sm font-medium text-neutral-900">{{ $row['value'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($selectedMethod->instructions)
                                <x-ui.alert type="info" class="mb-4">{{ $selectedMethod->instructions }}</x-ui.alert>
                            @endif

                            <div class="mb-5 flex flex-wrap gap-x-6 gap-y-1 text-xs text-neutral-500">
                                <span>Minimum: <span class="font-semibold text-neutral-800">{{ $selectedMethod->minMoney()->format() }}</span></span>
                                @if ($selectedMethod->maxMoney())
                                    <span>Maximum: <span class="font-semibold text-neutral-800">{{ $selectedMethod->maxMoney()->format() }}</span></span>
                                @endif
                            </div>

                            @if ($isCrypto)
                                {{-- Crypto: show address + QR --}}
                                @if ($custodySimulated)
                                    <x-ui.alert type="danger" title="Demo / testnet — do not send real funds" class="mb-5">
                                        This is a simulated address for testing PoisaPay. The platform does not hold its private key, so any real funds sent here will be lost permanently.
                                    </x-ui.alert>
                                @endif
                                @include('frontend.partials.deposit-address')
                            @else
                                {{-- Manual: amount + reference form --}}
                                <form method="POST" action="{{ route('deposit.submit') }}" class="space-y-4">
                                    @csrf
                                    <input type="hidden" name="assetId" value="{{ $selectedAsset->id }}" />
                                    <input type="hidden" name="methodId" value="{{ $selectedMethod->id }}" />
                                    <x-ui.input label="Amount" name="amount" type="number" step="any" :value="old('amount')"
                                        hint="In {{ $selectedAsset->symbol }}" placeholder="0.00" :error="$errors->first('amount')" />
                                    <x-ui.input label="Payment reference" name="reference" :value="old('reference')"
                                        hint="Your bank or mobile transaction ID." placeholder="e.g. TX-483920" :error="$errors->first('reference')" />
                                    <div class="pt-1">
                                        <x-ui.button type="submit" variant="primary" class="w-full sm:w-auto">Submit deposit for review</x-ui.button>
                                    </div>
                                </form>
                            @endif
                        </x-ui.card>
                    @elseif ($selectedAsset->depositMethods->isEmpty())
                        {{-- No methods — on-chain network fallback (address allocated server-side) --}}
                        <x-ui.card>
                            <h3 class="mb-1 text-base font-semibold text-neutral-900">2. Your {{ $selectedAsset->name }} address</h3>
                            <p class="mb-5 text-sm text-neutral-500">Send only {{ $selectedAsset->symbol }} on its native network to this address.</p>

                            @if ($custodySimulated)
                                <x-ui.alert type="danger" title="Demo / testnet — do not send real funds" class="mb-5">
                                    This is a simulated address for testing PoisaPay. The platform does not hold its private key, so any real funds sent here will be lost permanently.
                                </x-ui.alert>
                            @endif
                            <x-ui.alert type="warning" class="mb-5">
                                Only send <span class="font-semibold">{{ $selectedAsset->symbol }}</span> on the
                                <span class="font-semibold">{{ $selectedAsset->chain?->name ?? $selectedAsset->name }}</span> network.
                                Funds sent on any other network may be lost permanently.
                            </x-ui.alert>

                            @include('frontend.partials.deposit-address')

                            {{-- All info at a glance --}}
                            <div class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-neutral-200 bg-neutral-200 text-sm">
                                @php
                                    $infoRows = [
                                        ['label' => 'Coin', 'value' => $selectedAsset->symbol.' · '.$selectedAsset->name],
                                        ['label' => 'Network', 'value' => $selectedAsset->chain?->name ?? $selectedAsset->name],
                                        ['label' => 'Confirmations', 'value' => $selectedAsset->requiredConfirmations().' blocks'],
                                        ['label' => 'Credited', 'value' => 'Automatically after confirmations'],
                                    ];
                                @endphp
                                @foreach ($infoRows as $row)
                                    <div class="bg-white px-4 py-3">
                                        <p class="text-[11px] font-medium uppercase tracking-wide text-neutral-400">{{ $row['label'] }}</p>
                                        <p class="mt-1 font-medium text-neutral-900">{{ $row['value'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </x-ui.card>
                    @endif
                @endif
        </div>
    </div>
</x-layouts.app>
