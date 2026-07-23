<x-layouts.app :title="__('Deposit')">
    @php
        // Crypto network flow = an on-chain coin (no manual deposit methods) is in play,
        // either because a specific network asset is selected or a coin symbol is chosen.
        $coinSymbol = $selectedAsset && $selectedAsset->chain && $selectedAsset->depositMethods->isEmpty()
            ? $selectedAsset->symbol
            : request('symbol');
        $networks = $coinSymbol
            ? $assets->filter(fn ($a) => $a->symbol === $coinSymbol && $a->chain && $a->depositMethods->isEmpty())
                ->sortBy(fn ($a) => $a->chain?->name)->values()
            : collect();
        $inCryptoFlow = $networks->isNotEmpty();
        $selectedIsEvm = (bool) ($selectedAsset?->chain?->key?->isEvm());

        // Flow progress: Currency → Method/Network → Deposit.
        $hasCoin = $selectedAsset || $inCryptoFlow;
        $atDeposit = ($inCryptoFlow && $selectedAsset) || $selectedMethod !== null;
        $currentStep = ! $hasCoin ? 1 : ($atDeposit ? 3 : 2);
        $steps = [1 => __('Currency'), 2 => __('Method'), 3 => __('Deposit')];
    @endphp

    <div class="mx-auto max-w-2xl space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Deposit') }}</h1>
            <p class="mt-1 text-sm text-neutral-500">{{ __('Fund your account with crypto or a supported payment method.') }}</p>
            @if ($recentCount > 0)
                <a href="{{ route('deposit.history') }}" class="group mt-3 inline-flex items-center gap-1 text-sm font-medium text-neutral-500 transition hover:text-brand-600">
                    {{ __('View deposit history') }}
                    <x-heroicon-o-chevron-right class="h-4 w-4 transition group-hover:translate-x-0.5" />
                </a>
            @endif
        </div>

        {{-- Step progress --}}
        <div class="flex items-center justify-center gap-2 sm:gap-3">
            @foreach ($steps as $n => $label)
                @php $done = $n < $currentStep; $active = $n === $currentStep; @endphp
                <div class="flex items-center gap-2">
                    <span @class([
                        'grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-semibold transition',
                        'bg-brand-500 text-white shadow-sm ring-4 ring-brand-100' => $active,
                        'bg-brand-100 text-brand-600' => $done,
                        'bg-neutral-100 text-neutral-400' => ! $active && ! $done,
                    ])>
                        @if ($done)<x-heroicon-o-check class="h-4 w-4" />@else{{ $n }}@endif
                    </span>
                    <span @class([
                        'text-xs font-medium',
                        'text-neutral-900' => $active,
                        'text-neutral-500' => $done,
                        'text-neutral-400' => ! $active && ! $done,
                    ])>{{ $label }}</span>
                </div>
                @unless ($loop->last)
                    <span @class(['h-px w-5 sm:w-10', 'bg-brand-300' => $done, 'bg-neutral-200' => ! $done])></span>
                @endunless
            @endforeach
        </div>

        @unless ($depositEnabled)
            <x-ui.alert type="warning" :title="__('Deposits are disabled')">{{ __('Deposits are currently turned off. Please check back shortly.') }}</x-ui.alert>
        @endunless

        {{-- ══ 1. Choose a coin ══ --}}
        @if (! $selectedAsset && ! $inCryptoFlow)
            <x-ui.card :title="__('Choose a coin')" :subtitle="__('Pick what you want to deposit.')">
                @if ($assets->isEmpty())
                    <x-ui.empty-state icon="banknotes" :title="__('No currencies available')" :description="__('Deposit currencies are not configured yet.')" />
                @else
                    <div class="grid gap-2.5 sm:grid-cols-2">
                        @foreach ($assets->groupBy('symbol') as $symbol => $group)
                            @php
                                $onchain = $group->filter(fn ($a) => $a->chain && $a->depositMethods->isEmpty());
                                $multiNetwork = $onchain->count() > 1;
                                $first = $group->first();
                                $href = $multiNetwork ? route('deposit.index', ['symbol' => $symbol]) : route('deposit.index', ['asset' => $first->id]);
                            @endphp
                            <a href="{{ $href }}"
                               class="group flex items-center gap-3 rounded-xl border border-neutral-200 p-3.5 transition hover:border-brand-400 hover:bg-brand-50/40">
                                <x-ui.asset-icon :symbol="$symbol" size="md" />
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-neutral-900">{{ $symbol }}</span>
                                    <span class="block truncate text-xs text-neutral-500">
                                        {{ $multiNetwork ? $onchain->count().' '.__('networks') : ($first->chain?->name ?? $first->name) }}
                                    </span>
                                </span>
                                <x-heroicon-o-chevron-right class="h-5 w-5 shrink-0 text-neutral-300 transition group-hover:translate-x-0.5 group-hover:text-brand-500" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

        {{-- ══ 2. Crypto: unified network picker + address ══ --}}
        @elseif ($inCryptoFlow)
            <x-ui.card>
                {{-- Coin header --}}
                <div class="mb-5 flex items-center gap-3">
                    <x-ui.asset-icon :symbol="$coinSymbol" size="lg" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-neutral-900">{{ __('Deposit :symbol', ['symbol' => $coinSymbol]) }}</p>
                        <p class="text-xs text-neutral-500">{{ __("Select the network you’re sending on.") }}</p>
                    </div>
                    <a href="{{ route('deposit.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700">
                        <x-heroicon-o-arrow-left class="h-4 w-4" /> {{ __('Change') }}
                    </a>
                </div>

                {{-- Network chips --}}
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('Network') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($networks as $net)
                        @php $active = $selectedAsset && $selectedAsset->id === $net->id; @endphp
                        <a href="{{ route('deposit.index', ['asset' => $net->id]) }}" @class([
                            'inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-sm font-medium transition',
                            'border-brand-500 bg-brand-50 text-brand-700 ring-1 ring-brand-500' => $active,
                            'border-neutral-200 text-neutral-700 hover:border-brand-300 hover:bg-neutral-50' => ! $active,
                        ])>
                            <span @class(['h-2 w-2 rounded-full', 'bg-brand-500' => $active, 'bg-neutral-300' => ! $active])></span>
                            {{ $net->chain?->name ?? $net->name }}
                        </a>
                    @endforeach
                </div>

                {{-- Address (once a network is chosen) --}}
                @if ($selectedAsset)
                    <div class="mt-6 border-t border-neutral-100 pt-6">
                        @if ($custodySimulated)
                            <x-ui.alert type="danger" :title="__('Demo / testnet — do not send real funds')" class="mb-5">
                                {{ __('This is a simulated address for testing PoisaPay. The platform does not hold its private key; real funds sent here will be lost.') }}
                            </x-ui.alert>
                        @endif

                        <x-ui.alert type="warning" class="mb-5">
                            {{ __('Only send') }} <span class="font-semibold">{{ $selectedAsset->symbol }}</span> {{ __('on the') }}
                            <span class="font-semibold">{{ $selectedAsset->chain?->name ?? $selectedAsset->name }}</span> {{ __('network.') }}
                            {{ __('Sending any other asset or network may be lost permanently.') }}
                        </x-ui.alert>

                        @include('frontend.partials.deposit-address')

                        <div class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-neutral-200 bg-neutral-200 text-sm">
                            @php
                                $infoRows = [
                                    ['label' => __('Coin'), 'value' => $selectedAsset->symbol.' · '.$selectedAsset->name],
                                    ['label' => __('Network'), 'value' => $selectedAsset->chain?->name ?? $selectedAsset->name],
                                    ['label' => __('Min deposit'), 'value' => $selectedAsset->money($selectedAsset->withdrawal_min ?: '0')->format()],
                                    ['label' => __('Credited after'), 'value' => $selectedAsset->requiredConfirmations().' '.__('confirmations')],
                                ];
                            @endphp
                            @foreach ($infoRows as $row)
                                <div class="bg-white px-4 py-3">
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-neutral-400">{{ $row['label'] }}</p>
                                    <p class="mt-1 font-medium text-neutral-900">{{ $row['value'] }}</p>
                                </div>
                            @endforeach
                        </div>

                        @if ($selectedIsEvm)
                            <p class="mt-3 flex items-start gap-1.5 text-xs text-neutral-500">
                                <x-heroicon-o-information-circle class="mt-px h-4 w-4 shrink-0 text-neutral-400" />
                                {{ __("This is your shared EVM address — it’s the same across Ethereum, BSC, Polygon, Arbitrum, Optimism, Base and Avalanche. Just make sure you send on the network selected above.") }}
                            </p>
                        @endif
                    </div>
                @else
                    <p class="mt-6 rounded-xl border border-dashed border-neutral-200 bg-neutral-50/60 px-4 py-6 text-center text-sm text-neutral-500">
                        {{ __('Select a network above to reveal your :symbol deposit address.', ['symbol' => $coinSymbol]) }}
                    </p>
                @endif
            </x-ui.card>

        {{-- ══ 3. Fiat / method-based deposit ══ --}}
        @else
            <x-ui.card>
                <div class="flex items-center gap-3">
                    <x-ui.asset-icon :symbol="$selectedAsset->symbol" size="lg" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-neutral-900">{{ $selectedAsset->symbol }}</p>
                        <p class="text-xs text-neutral-500">{{ $selectedAsset->name }}</p>
                    </div>
                    <a href="{{ route('deposit.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700">
                        <x-heroicon-o-arrow-left class="h-4 w-4" /> {{ __('Change') }}
                    </a>
                </div>
            </x-ui.card>

            @if ($selectedAsset->depositMethods->isNotEmpty() && ! $selectedMethod)
                <x-ui.card :title="__('Choose a deposit method')">
                    <div class="space-y-2.5">
                        @foreach ($selectedAsset->depositMethods as $m)
                            <a href="{{ route('deposit.index', ['asset' => $selectedAsset->id, 'method' => $m->id]) }}"
                               class="group flex items-center gap-3 rounded-xl border border-neutral-200 p-4 transition hover:border-brand-400 hover:bg-brand-50/40">
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
                        <a href="{{ route('deposit.index', ['asset' => $selectedAsset->id]) }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700">
                            <x-heroicon-o-arrow-left class="h-4 w-4" /> {{ __('Change method') }}
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
                        <span>{{ __('Minimum:') }} <span class="font-semibold text-neutral-800">{{ $selectedMethod->minMoney()->format() }}</span></span>
                        @if ($selectedMethod->maxMoney())
                            <span>{{ __('Maximum:') }} <span class="font-semibold text-neutral-800">{{ $selectedMethod->maxMoney()->format() }}</span></span>
                        @endif
                    </div>

                    @if ($isCrypto)
                        @if ($custodySimulated)
                            <x-ui.alert type="danger" :title="__('Demo / testnet — do not send real funds')" class="mb-5">
                                {{ __('This is a simulated address for testing PoisaPay. Real funds sent here will be lost.') }}
                            </x-ui.alert>
                        @endif
                        @include('frontend.partials.deposit-address')
                    @else
                        <form method="POST" action="{{ route('deposit.submit') }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="assetId" value="{{ $selectedAsset->id }}" />
                            <input type="hidden" name="methodId" value="{{ $selectedMethod->id }}" />
                            <x-ui.input :label="__('Amount')" name="amount" type="number" step="any" :value="old('amount')"
                                :hint="__('In :symbol', ['symbol' => $selectedAsset->symbol])" placeholder="0.00" :error="$errors->first('amount')" />
                            <x-ui.input :label="__('Payment reference')" name="reference" :value="old('reference')"
                                :hint="__('Your bank or mobile transaction ID.')" :placeholder="__('e.g. TX-483920')" :error="$errors->first('reference')" />
                            <div class="pt-1">
                                <x-ui.button type="submit" variant="primary" class="w-full sm:w-auto">{{ __('Submit deposit for review') }}</x-ui.button>
                            </div>
                        </form>
                    @endif
                </x-ui.card>
            @endif
        @endif
    </div>
</x-layouts.app>
