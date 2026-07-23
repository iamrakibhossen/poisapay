<x-layouts.app :title="__('Trader · :name', ['name' => $trader->name])">
    @php
        $rep = app(\App\Domain\P2p\P2pReputationService::class);
        $volume = \App\Support\Money::ofBase($profile->total_volume ?: '0', 6, 'USDT');
        $verified = in_array('verified', (array) $profile->badges, true) || (int) $profile->level >= 2;
        $rate = $profile->completion_rate_bps / 100;
        $buys = $ads->filter(fn ($a) => $a->side->value === 'buy');
        $sells = $ads->filter(fn ($a) => $a->side->value === 'sell');

        $verifications = [
            ['label' => __('Email'), 'ok' => (bool) $trader->email_verified_at],
            ['label' => __('SMS'), 'ok' => (bool) $trader->phone_verified_at],
            ['label' => __('ID Verification'), 'ok' => $trader->kyc_tier === \App\Enums\KycTier::Full && $trader->kyc_status === \App\Enums\KycStatus::Approved],
        ];

        $stats = [
            ['label' => __('All trades'), 'value' => number_format($profile->trade_count)],
            ['label' => __('Completed'), 'value' => number_format($profile->completed_count)],
            ['label' => __('Completion rate'), 'value' => number_format($rate, 1).'%'],
            ['label' => __('Avg. release'), 'value' => $profile->avg_release_seconds ? max(1, (int) round($profile->avg_release_seconds / 60)).' '.__('min') : '—'],
            ['label' => __('Lifetime volume'), 'value' => $volume->format()],
        ];

        $adGroups = [
            ['title' => __('Sell Ads'), 'ads' => $sells, 'viewerSide' => 'buy', 'label' => __('Buy'), 'button' => 'bg-green-600 hover:bg-green-700 focus-visible:ring-green-400'],
            ['title' => __('Buy Ads'), 'ads' => $buys, 'viewerSide' => 'sell', 'label' => __('Sell'), 'button' => 'bg-red-600 hover:bg-red-500 focus-visible:ring-red-400'],
        ];
    @endphp

    <div class="mx-auto max-w-4xl"
         x-data="{
            ad: null,
            choose(a) { this.ad = a; $dispatch('open-modal', 'p2p-order'); },
         }">

        {{-- Back link --}}
        <a href="{{ route('p2p') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 hover:text-neutral-900">
            <x-heroicon-o-arrow-left class="h-4 w-4" /> {{ __('Marketplace') }}
        </a>

        @if (session('success'))<div class="mb-6"><x-ui.alert type="success">{{ session('success') }}</x-ui.alert></div>@endif

        {{-- Profile header --}}
        <div class="mt-4 flex flex-col items-center text-center">
            <div class="relative mb-3 inline-block">
                <span class="m-auto grid h-24 w-24 place-items-center rounded-full border border-neutral-200 bg-brand-600 text-3xl font-bold text-white">
                    {{ strtoupper(mb_substr($trader->name, 0, 1)) }}
                </span>
                <span class="absolute bottom-1 right-2 h-4 w-4 rounded-full border-2 border-white shadow {{ $profile->is_online ? 'bg-green-500' : 'bg-neutral-300' }}"
                      title="{{ $profile->is_online ? __('Online') : __('Offline') }}"></span>
            </div>

            <h1 class="mb-2 flex items-center gap-2 text-2xl font-semibold text-neutral-900">
                {{ $trader->name }}
                @if ($verified)<x-heroicon-s-check-badge class="h-6 w-6 text-brand-500" title="{{ __('Verified merchant') }}" />@endif
            </h1>

            <div class="mb-2 flex flex-wrap items-center justify-center gap-2">
                <x-ui.badge color="primary">{{ $rep->levelLabel((int) $profile->level) }}</x-ui.badge>
                @if ($profile->vacation_mode)<x-ui.badge color="warning">{{ __('On vacation') }}</x-ui.badge>@endif
            </div>

            <p class="text-sm text-neutral-500">{{ __('Member since') }} {{ $trader->created_at?->format('M d, Y') }}</p>

            {{-- Self controls --}}
            @if ($isSelf)
                <div class="mt-4 flex flex-wrap justify-center gap-3">
                    <form method="POST" action="{{ route('p2p.merchant.online') }}">
                        @csrf
                        <x-ui.button type="submit" :variant="$profile->is_online ? 'secondary' : 'success'" size="sm" :icon="$profile->is_online ? 'pause' : 'bolt'">
                            {{ $profile->is_online ? __('Go offline') : __('Go online') }}
                        </x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('p2p.merchant.vacation') }}">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm" icon="sun">
                            {{ $profile->vacation_mode ? __('End vacation') : __('Vacation mode') }}
                        </x-ui.button>
                    </form>
                </div>
            @endif

            {{-- Verifications --}}
            <div class="mt-4 flex flex-wrap justify-center gap-4">
                @foreach ($verifications as $v)
                    <div class="flex items-center gap-1.5 text-sm {{ $v['ok'] ? 'text-neutral-600' : 'text-neutral-400' }}">
                        <x-heroicon-o-check class="h-3.5 w-3.5 {{ $v['ok'] ? 'text-green-600' : 'text-neutral-300' }}" />{{ $v['label'] }}
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Stats --}}
        <div class="mt-10 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-neutral-100 bg-neutral-100 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($stats as $stat)
                <div class="bg-white px-4 py-5 text-center">
                    <div class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $stat['label'] }}</div>
                    <div class="mt-1 text-lg font-semibold tabular text-neutral-900">{{ $stat['value'] }}</div>
                </div>
            @endforeach
        </div>

        {{-- Ad groups --}}
        <div class="mt-12 space-y-10">
            @foreach ($adGroups as $group)
                <div>
                    <h2 class="mb-4 flex items-center gap-2 text-xl font-semibold text-neutral-900">
                        {{ $group['title'] }}
                        <span class="text-sm font-medium text-neutral-500">({{ $group['ads']->count() }})</span>
                    </h2>

                    @if ($group['ads']->isEmpty())
                        <p class="py-4 text-sm text-neutral-500">{{ __('No active ads.') }}</p>
                    @else
                        {{-- Column header (desktop only) --}}
                        <div class="hidden items-center gap-4 border-b border-neutral-200 px-4 pb-2 text-xs font-medium text-neutral-500 lg:flex">
                            <div class="flex-1">{{ __('Payment') }}</div>
                            <div class="w-56">{{ __('Available / Order Limit') }}</div>
                            <div class="w-32">{{ __('Price') }}</div>
                            <div class="w-24 text-center">{{ __('Action') }}</div>
                        </div>
                        <div class="space-y-3 lg:space-y-0">
                            @foreach ($group['ads'] as $ad)
                                <div class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-4 transition-colors lg:flex-row lg:items-center lg:gap-4 lg:rounded-none lg:border-x-0 lg:border-t-0 lg:p-4 lg:hover:bg-neutral-50">
                                    {{-- Payment --}}
                                    <div class="flex items-center justify-between lg:block lg:flex-1">
                                        <span class="text-sm text-neutral-400 lg:hidden">{{ __('Payment') }}</span>
                                        <div class="flex flex-wrap justify-end gap-1.5 lg:justify-start">
                                            @forelse ($ad->paymentMethods as $m)
                                                <span class="inline-flex items-center rounded-md border border-neutral-200 bg-neutral-50 px-2 py-0.5 text-xs font-medium text-neutral-600">{{ $m->name }}</span>
                                            @empty
                                                <span class="text-sm text-neutral-400">—</span>
                                            @endforelse
                                        </div>
                                    </div>

                                    {{-- Available / Order Limit --}}
                                    <div class="text-sm text-neutral-700 lg:w-56">
                                        <span class="text-neutral-400 lg:hidden">{{ __('Available / Order Limit') }}</span>
                                        <p class="tabular lg:mb-1">{{ $ad->availableMoney()->format() }}</p>
                                        <p class="tabular text-neutral-500">{{ number_format((float) $ad->min_order, 0) }} – {{ number_format((float) $ad->max_order, 0) }} {{ $ad->fiat_currency }}</p>
                                    </div>

                                    {{-- Price --}}
                                    <div class="flex items-baseline justify-between lg:block lg:w-32">
                                        <span class="text-sm text-neutral-400 lg:hidden">{{ __('Price') }}</span>
                                        @if ($ad->price_type->value === 'fixed')
                                            <span class="text-lg font-medium tabular text-neutral-800">{{ number_format((float) $ad->fixed_price, 2) }} <span class="text-xs text-neutral-400">{{ $ad->fiat_currency }}</span></span>
                                        @else
                                            <span class="text-sm font-medium text-neutral-800">{{ __('Floating') }} <span class="text-xs text-neutral-400">{{ $ad->margin_bps >= 0 ? '+' : '' }}{{ number_format($ad->margin_bps / 100, 2) }}%</span></span>
                                        @endif
                                    </div>

                                    {{-- Action --}}
                                    <div class="lg:w-24 lg:text-center">
                                        @if ($isSelf)
                                            <a href="{{ route('p2p.ads') }}" class="block rounded-lg border border-neutral-200 px-5 py-2 text-center text-sm font-medium text-neutral-700 hover:bg-neutral-50 lg:inline-block">{{ __('Manage') }}</a>
                                        @else
                                            <button type="button"
                                                class="block w-full rounded-lg px-5 py-2 text-center text-sm font-medium text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 lg:inline-block {{ $group['button'] }}"
                                                x-on:click="choose({ id: '{{ $ad->id }}', price: '{{ $ad->fixed_price ?? 0 }}', min: '{{ $ad->min_order }}', max: '{{ $ad->max_order }}', sym: '{{ $ad->asset->symbol }}', fiat: '{{ $ad->fiat_currency }}', who: '{{ addslashes($trader->name) }}', side: '{{ $group['viewerSide'] }}' })">
                                                {{ $group['label'] }}
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Order modal (shared, populated by Alpine) --}}
        @unless ($isSelf)
            <x-ui.modal name="p2p-order" :title="__('Place order')" maxWidth="sm">
                <form method="POST" action="{{ route('p2p.orders.store') }}" class="space-y-5" x-data="{ amount: '' }">
                    @csrf
                    <input type="hidden" name="ad_id" :value="ad?.id">

                    <div class="flex items-center gap-3 rounded-xl border border-neutral-200 bg-neutral-50 p-3">
                        <span class="grid h-9 w-9 place-items-center rounded-full text-sm font-semibold text-white"
                              :class="ad?.side === 'buy' ? 'bg-green-600' : 'bg-red-600'"
                              x-text="(ad?.who || '?').slice(0,1).toUpperCase()"></span>
                        <div class="min-w-0 text-sm">
                            <p class="truncate font-semibold text-neutral-900" x-text="ad?.who"></p>
                            <p class="text-neutral-500">
                                <span class="tabular font-medium text-neutral-900" x-text="Number(ad?.price).toLocaleString()"></span>
                                <span x-text="ad?.fiat"></span> / <span x-text="ad?.sym"></span>
                            </p>
                        </div>
                        <span class="ml-auto rounded-full px-2.5 py-0.5 text-xs font-semibold"
                              :class="ad?.side === 'buy' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                              x-text="ad?.side === 'buy' ? 'BUY' : 'SELL'"></span>
                    </div>

                    <div>
                        <label class="pp-label">{{ __('Amount') }} (<span x-text="ad?.sym"></span>)</label>
                        <div class="relative">
                            <input type="text" name="amount" inputmode="decimal" x-model="amount" placeholder="0.00" class="pp-input pr-16" required>
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-medium text-neutral-400" x-text="ad?.sym"></span>
                        </div>
                        <p class="mt-2 flex items-center justify-between text-xs text-neutral-500">
                            <span x-show="amount && ad">≈ <span class="font-semibold tabular text-neutral-700" x-text="(Number(amount) * Number(ad?.price)).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span> <span x-text="ad?.fiat"></span></span>
                            <span class="text-neutral-400">{{ __('Limit') }} <span class="tabular" x-text="Number(ad?.min).toLocaleString()"></span>–<span class="tabular" x-text="Number(ad?.max).toLocaleString()"></span></span>
                        </p>
                    </div>

                    <button type="submit"
                        class="inline-flex w-full min-h-[2.625rem] items-center justify-center gap-2 rounded-lg border border-transparent px-4 py-2.5 text-sm font-normal text-white transition duration-150 ease-in-out active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                        :class="ad?.side === 'buy' ? 'bg-green-600 hover:bg-green-700 focus-visible:ring-green-400' : 'bg-red-600 hover:bg-red-500 focus-visible:ring-red-400'">
                        <span x-text="ad?.side === 'buy' ? '{{ __('Buy') }}' : '{{ __('Sell') }}'"></span> &amp; {{ __('lock escrow') }}
                    </button>
                    <p class="flex items-center justify-center gap-1.5 text-center text-xs text-neutral-400">
                        <x-heroicon-s-lock-closed class="h-3.5 w-3.5" /> {{ __('USDT is escrowed instantly. Pay off-platform, then confirm.') }}
                    </p>
                </form>
            </x-ui.modal>
        @endunless
    </div>
</x-layouts.app>
