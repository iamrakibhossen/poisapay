<x-layouts.admin :title="__('Hot Wallet')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Hot Wallet')" :subtitle="__('Per-chain hot wallets that fund withdrawals, their gas balance and treasury reserve.')" />

        @if ($simulated)
            <x-ui.alert type="info">{{ __('Custody is in simulated mode — hot addresses and balances are illustrative.') }}</x-ui.alert>
        @endif

        <div class="grid grid-cols-3 gap-4">
            <x-ui.stat-card :label="__('Chains')" :value="$chainCount" icon="signal" accent="brand" />
            <x-ui.stat-card :label="__('Hot configured')" :value="$hotConfigured" icon="fire" accent="amber" />
            <x-ui.stat-card :label="__('Low gas')" :value="$lowGasCount" icon="exclamation-triangle" :accent="$lowGasCount ? 'rose' : 'emerald'" />
        </div>

        @forelse ($wallets as $w)
            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $w['chain']->name }}</h3>
                        <x-ui.badge color="gray">{{ __('Hot wallet') }}</x-ui.badge>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="text-neutral-500">{{ __('Gas') }}:</span>
                        <span class="tabular font-medium {{ $w['gasLow'] ? 'text-red-600' : 'text-neutral-900' }}">{{ $w['gasBalance'] ?? '—' }} {{ $w['gasSymbol'] }}</span>
                        @if ($w['gasLow'])<x-ui.badge color="danger">{{ __('Low') }}</x-ui.badge>@endif
                    </div>
                </div>

                <div class="pt-3">
                    @if ($w['hotAddress'])
                        <p class="mb-3 font-mono text-xs text-neutral-500">
                            {{ $w['hotAddress'] }}
                            @if ($w['hotExplorer'])<a href="{{ $w['hotExplorer'] }}" target="_blank" rel="noopener" class="ms-2 text-brand-600 hover:underline">{{ __('explorer ↗') }}</a>@endif
                        </p>
                    @else
                        <p class="mb-3 text-xs text-neutral-400">{{ __('No hot address configured for this chain.') }}</p>
                    @endif

                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($w['assets'] as $a)
                            <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-3 py-2">
                                <span class="text-sm font-medium text-neutral-700">{{ $a['symbol'] }}</span>
                                <span class="tabular text-sm font-semibold {{ $a['zero'] ? 'text-neutral-400' : 'text-neutral-900' }}">{{ $a['hot'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-ui.card>
        @empty
            <x-ui.empty-state icon="fire" :title="__('No active chains')" :description="__('Activate a chain to see its hot wallet.')" />
        @endforelse
    </div>
</x-layouts.admin>
