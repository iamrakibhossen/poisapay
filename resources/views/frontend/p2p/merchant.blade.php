<x-layouts.app :title="__('Trader · :name', ['name' => $trader->name])">
    @php
        $rep = app(\App\Domain\P2p\P2pReputationService::class);
        $volume = \App\Support\Money::ofBase($profile->total_volume ?: '0', 6, 'USDT');
    @endphp
    <div class="space-y-6">
        <x-ui.page-header :title="$trader->name" :subtitle="__('P2P trader profile and reputation.')">
            <x-slot:actions>
                <a href="{{ route('p2p') }}"><x-ui.button variant="secondary" icon="arrow-left">{{ __('Marketplace') }}</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif

        <x-ui.card>
            <div class="flex flex-wrap items-center gap-4">
                <x-ui.avatar :name="$trader->name" size="lg" />
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-bold text-neutral-900">{{ $trader->name }}</h2>
                        <x-ui.badge color="brand">{{ $rep->levelLabel((int) $profile->level) }}</x-ui.badge>
                        @if ($profile->is_online)
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> {{ __('Online') }}</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs text-neutral-400"><span class="h-2 w-2 rounded-full bg-neutral-300"></span> {{ __('Offline') }}</span>
                        @endif
                        @if ($profile->vacation_mode)<x-ui.badge color="warning">{{ __('On vacation') }}</x-ui.badge>@endif
                    </div>
                    <p class="mt-0.5 text-xs text-neutral-500">{{ __('Member since') }} {{ $trader->created_at?->format('M Y') }}</p>
                    @if (! empty($profile->badges))
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($profile->badges as $b)<x-ui.badge color="info">{{ $rep->badgeLabel($b) }}</x-ui.badge>@endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.stat-card :label="__('Completion rate')" :value="number_format($profile->completion_rate_bps / 100, 1).'%'" icon="check-badge" accent="emerald" />
                <x-ui.stat-card :label="__('Total trades')" :value="number_format($profile->trade_count)" icon="arrows-right-left" accent="brand" />
                <x-ui.stat-card :label="__('Avg release')" :value="$profile->avg_release_seconds ? gmdate('i:s', $profile->avg_release_seconds) : '—'" icon="clock" accent="amber" />
                <x-ui.stat-card :label="__('Lifetime volume')" :value="$volume->format()" icon="banknotes" accent="brand" />
            </div>
        </x-ui.card>

        <x-ui.card>
            <h3 class="text-base font-semibold text-neutral-900">{{ __('Active ads') }}</h3>
            <div class="mt-3">
                <x-ui.table :headers="[__('Type'), __('Price'), __('Available'), __('Limits'), '']">
                    @forelse ($ads as $ad)
                        <tr class="border-b border-neutral-100">
                            <td class="px-3 py-3"><x-ui.badge :color="$ad->side->color()">{{ $ad->side->label() }}</x-ui.badge></td>
                            <td class="px-3 py-3 text-sm text-neutral-900 tabular">{{ $ad->price_type->value === 'fixed' ? number_format((float) $ad->fixed_price, 2).' '.$ad->fiat_currency : __('Floating') }}</td>
                            <td class="px-3 py-3 text-sm text-neutral-600 tabular">{{ $ad->availableMoney()->format() }}</td>
                            <td class="px-3 py-3 text-sm text-neutral-500 tabular">{{ number_format((float) $ad->min_order, 0) }}–{{ number_format((float) $ad->max_order, 0) }}</td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ route('p2p', ['side' => $ad->side->value === 'sell' ? 'buy' : 'sell']) }}"><x-ui.button size="sm">{{ __('Trade') }}</x-ui.button></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ui.empty-state icon="megaphone" :title="__('No active ads')" :description="__('This trader has no live ads right now.')" /></td></tr>
                    @endforelse
                </x-ui.table>
            </div>
        </x-ui.card>
    </div>
</x-layouts.app>
