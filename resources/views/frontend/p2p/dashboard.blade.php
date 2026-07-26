<x-layouts.app :title="__('P2P Dashboard')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('P2P Dashboard')" :subtitle="__('Your trading performance, reputation and activity at a glance.')">
            <x-slot:actions>
                <a href="{{ route('p2p') }}"><x-ui.button variant="secondary" icon="arrow-left">{{ __('Marketplace') }}</x-ui.button></a>
                <a href="{{ route('p2p.orders') }}"><x-ui.button variant="secondary" icon="clock">{{ __('Orders') }}</x-ui.button></a>
                <a href="{{ route('p2p.ads') }}"><x-ui.button variant="secondary" icon="megaphone">{{ __('My ads') }}</x-ui.button></a>
                <a href="{{ route('p2p.ads.create') }}"><x-ui.button icon="plus">{{ __('Post ad') }}</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Reputation summary --}}
        <x-ui.card>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <x-ui.avatar :name="auth()->user()->name" size="md" />
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-neutral-900">{{ auth()->user()->name }}</p>
                            <x-ui.badge color="primary">{{ $rep->levelLabel((int) $profile->level) }}</x-ui.badge>
                            @if ($profile->is_online)<x-ui.badge color="success" dot>{{ __('Online') }}</x-ui.badge>@endif
                            @if ($profile->vacation_mode)<x-ui.badge color="warning">{{ __('On vacation') }}</x-ui.badge>@endif
                        </div>
                        <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-neutral-500">
                            @forelse ($profile->badges ?? [] as $b)
                                <span class="rounded-full bg-neutral-100 px-2 py-0.5 font-medium text-neutral-600">{{ $rep->badgeLabel($b) }}</span>
                            @empty
                                <span>{{ __('Complete trades to earn badges.') }}</span>
                            @endforelse
                        </p>
                    </div>
                </div>
                <a href="{{ route('p2p.merchant', $profile->user_id) }}" class="text-sm font-medium text-brand-600 hover:underline">{{ __('View public profile') }}</a>
            </div>
        </x-ui.card>

        {{-- Conversion CTA --}}
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-brand-100 bg-gradient-to-r from-brand-50 to-white p-5 shadow-[var(--shadow-card)]">
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white text-brand-600 ring-1 ring-brand-100"><x-heroicon-o-bolt class="h-5 w-5" /></span>
                <div>
                    <p class="text-sm font-semibold text-neutral-900">{{ __('Grow your P2P trading') }}</p>
                    <p class="text-xs text-neutral-500">{{ __('Post an ad to get discovered, or grab the best price on the marketplace.') }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('p2p') }}"><x-ui.button variant="secondary" icon="squares-2x2">{{ __('Browse market') }}</x-ui.button></a>
                <a href="{{ route('p2p.ads.create') }}"><x-ui.button icon="plus">{{ __('Post an ad') }}</x-ui.button></a>
            </div>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($kpis as $kpi)
                <x-analytics.kpi :kpi="$kpi" :compare="false" />
            @endforeach
        </div>

        {{-- Charts — two equal columns on one row --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <x-analytics.chart :chart="$volumeChart" />
            <x-analytics.chart :chart="$outcomeChart" />
        </div>
    </div>
</x-layouts.app>
