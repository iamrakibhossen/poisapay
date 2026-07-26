<x-layouts.app :title="__('My P2P ads')">
    @php
        $rep = app(\App\Domain\P2p\P2pReputationService::class);
        $tabs = [
            'all' => __('All'),
            'active' => __('Active'),
            'paused' => __('Paused'),
            'closed' => __('Closed'),
        ];
    @endphp

    <div class="space-y-5" x-data="{ del: { url: '', ref: '' }, sel: [], submitBulk(action) { this.$refs.bulkAction.value = action; this.$refs.bulkForm.submit(); } }">
        {{-- Header --}}
        <x-ui.page-header :title="__('My ads')" :subtitle="__('Manage your P2P advertisements and availability.')">
            <x-slot:actions>
                <a href="{{ route('p2p.dashboard') }}"><x-ui.button variant="secondary" icon="chart-bar">{{ __('Dashboard') }}</x-ui.button></a>
                <a href="{{ route('p2p.payment-methods') }}"><x-ui.button variant="secondary" icon="credit-card">{{ __('Payment accounts') }}</x-ui.button></a>
                <a href="{{ route('p2p.ads.create') }}"><x-ui.button icon="plus">{{ __('Post ad') }}</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif

        {{-- Reputation + availability --}}
        <x-ui.card>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                {{-- Reputation --}}
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <x-ui.avatar :name="$profile->user->name ?? '?'" size="md" />
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge color="primary">{{ $rep->levelLabel((int) $profile->level) }}</x-ui.badge>
                            @foreach ($profile->badges ?? [] as $b)<x-ui.badge color="info">{{ $rep->badgeLabel($b) }}</x-ui.badge>@endforeach
                        </div>
                        <p class="mt-1 text-sm text-neutral-500">
                            {{ number_format($profile->completion_rate_bps / 100, 1) }}% {{ __('completion') }}
                            <span class="text-neutral-300">·</span>
                            {{ number_format($profile->trade_count) }} {{ __('trades') }}
                            <span class="text-neutral-300">·</span>
                            <a href="{{ route('p2p.merchant', $profile->user_id) }}" class="font-medium text-brand-600 hover:underline">{{ __('Public profile') }}</a>
                        </p>
                    </div>
                </div>

                {{-- Availability --}}
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('p2p.merchant.online') }}">@csrf
                        <x-ui.button type="submit" size="sm" :variant="$profile->is_online ? 'success' : 'secondary'" :icon="$profile->is_online ? 'bolt' : 'pause'">
                            {{ $profile->is_online ? __('Online') : __('Offline') }}
                        </x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('p2p.merchant.vacation') }}">@csrf
                        <x-ui.button type="submit" size="sm" :variant="$profile->vacation_mode ? 'primary' : 'secondary'" icon="sun">
                            {{ $profile->vacation_mode ? __('Vacation on') : __('Vacation off') }}
                        </x-ui.button>
                    </form>
                </div>
            </div>

            @if ($profile->vacation_mode)
                <div class="mt-3 flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <x-heroicon-s-sun class="h-4 w-4 shrink-0" />
                    {{ __('Vacation mode is on — your ads are hidden from the marketplace until you turn it off.') }}
                </div>
            @endif
        </x-ui.card>

        {{-- Status tabs --}}
        <div class="flex gap-1 overflow-x-auto border-b border-neutral-200">
            @foreach ($tabs as $key => $label)
                @php $active = $tab === $key; @endphp
                <a href="{{ route('p2p.ads', $key === 'all' ? [] : ['tab' => $key]) }}"
                   class="-mb-px flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition-colors {{ $active ? 'border-brand-500 text-neutral-900' : 'border-transparent text-neutral-500 hover:text-neutral-900' }}">
                    {{ $label }}
                    <span class="rounded-full px-1.5 py-0.5 text-xs tabular {{ $active ? 'bg-brand-50 text-brand-700' : 'bg-neutral-100 text-neutral-500' }}">{{ number_format($counts[$key] ?? 0) }}</span>
                </a>
            @endforeach
        </div>

        {{-- Ads --}}
        @if ($ads->isEmpty())
            <x-ui.card>
                <x-ui.empty-state icon="megaphone"
                    :title="$tab === 'all' ? __('No ads yet') : __('No :tab ads', ['tab' => strtolower($tabs[$tab])])"
                    :description="__('Post an ad to advertise your rate and start trading.')">
                    <x-slot:action>
                        <a href="{{ route('p2p.ads.create') }}"><x-ui.button icon="plus">{{ __('Post an ad') }}</x-ui.button></a>
                    </x-slot:action>
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ($ads as $ad)
                    @php
                        $isBuy = $ad->side->value === 'buy';
                        $totalBase = (float) $ad->total_amount;
                        $availBase = (float) $ad->available_amount;
                        $remainPct = $totalBase > 0 ? min(100, max(0, $availBase / $totalBase * 100)) : 0;
                        $totalMoney = \App\Support\Money::ofBase($ad->total_amount ?: '0', $ad->asset->decimals, $ad->asset->symbol);
                        $editable = in_array($ad->status->value, ['active', 'paused', 'draft']);
                        $toggleable = in_array($ad->status->value, ['active', 'paused']);
                    @endphp
                    <div class="flex flex-col rounded-2xl border border-neutral-200 bg-white p-5 shadow-[var(--shadow-card)] transition-colors hover:border-neutral-300">
                        {{-- Header: select + side + status --}}
                        <div class="flex items-center justify-between gap-2">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" value="{{ $ad->id }}" x-model="sel" class="h-4 w-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $isBuy ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $isBuy ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                    {{ $isBuy ? __('Buy USDT') : __('Sell USDT') }}
                                </span>
                            </label>
                            <x-ui.badge :color="$ad->status->color()" dot>{{ $ad->status->label() }}</x-ui.badge>
                        </div>

                        {{-- Price --}}
                        <div class="mt-4">
                            <p class="text-xs font-medium text-neutral-500">{{ __('Price') }}</p>
                            @if ($ad->price_type->value === 'fixed')
                                <p class="text-2xl font-bold tabular text-neutral-900">{{ number_format((float) $ad->fixed_price, 2) }} <span class="text-sm font-medium text-neutral-400">{{ $ad->fiat_currency }}</span></p>
                            @else
                                <p class="text-2xl font-bold text-neutral-900">{{ __('Floating') }}</p>
                                <p class="text-xs text-neutral-400">{{ __('market') }} {{ $ad->margin_bps >= 0 ? '+' : '' }}{{ number_format($ad->margin_bps / 100, 2) }}%</p>
                            @endif
                        </div>

                        {{-- Liquidity --}}
                        <div class="mt-4">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-neutral-500">{{ __('Available') }}</span>
                                <span class="tabular font-medium text-neutral-700">{{ $ad->availableMoney()->format() }} <span class="text-neutral-400">/ {{ $totalMoney->format() }}</span></span>
                            </div>
                            <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-neutral-100">
                                <div class="h-full rounded-full {{ $remainPct > 0 ? 'bg-brand-500' : 'bg-neutral-300' }}" style="width: {{ $remainPct }}%"></div>
                            </div>
                        </div>

                        {{-- Limits + payment --}}
                        <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                            <div>
                                <span class="text-xs text-neutral-400">{{ __('Limit') }}</span>
                                <span class="ml-1 tabular text-neutral-700">{{ number_format((float) $ad->min_order, 0) }}–{{ number_format((float) $ad->max_order, 0) }} {{ $ad->fiat_currency }}</span>
                            </div>
                        </div>
                        @if ($ad->paymentMethods->isNotEmpty())
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($ad->paymentMethods as $m)
                                    <span class="inline-flex items-center rounded-md border border-neutral-200 border-l-[3px] border-l-brand-400 bg-neutral-50 px-2 py-0.5 text-xs font-medium text-neutral-600">{{ $m->name }}</span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Actions --}}
                        <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-neutral-100 pt-4">
                            @if ($editable)
                                <a href="{{ route('p2p.ads.edit', $ad) }}" class="flex-1"><x-ui.button size="sm" variant="secondary" icon="pencil-square" class="w-full">{{ __('Edit') }}</x-ui.button></a>
                            @endif
                            @if ($toggleable)
                                <form method="POST" action="{{ route('p2p.ads.toggle', $ad) }}" class="flex-1">
                                    @csrf
                                    <x-ui.button type="submit" size="sm" :variant="$ad->status->value === 'active' ? 'secondary' : 'success'" :icon="$ad->status->value === 'active' ? 'pause' : 'play'" class="w-full">
                                        {{ $ad->status->value === 'active' ? __('Pause') : __('Resume') }}
                                    </x-ui.button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('p2p.ads.duplicate', $ad) }}">
                                @csrf
                                <x-ui.button type="submit" size="sm" variant="secondary" icon="document-duplicate" title="{{ __('Duplicate') }}">{{ __('Duplicate') }}</x-ui.button>
                            </form>
                            <x-ui.button type="button" size="sm" variant="danger" icon="trash" title="{{ __('Delete') }}"
                                x-on:click="del = { url: '{{ route('p2p.ads.delete', $ad) }}', ref: '{{ $ad->side->value }} · {{ $totalMoney->format() }}' }; $dispatch('open-modal', 'p2p-ad-delete')">{{ __('Delete') }}</x-ui.button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div>{{ $ads->links() }}</div>
        @endif

        {{-- Bulk action bar (shown when ads are selected) --}}
        <div x-show="sel.length" x-cloak
             class="sticky bottom-4 z-30 mx-auto flex max-w-2xl flex-wrap items-center justify-between gap-3 rounded-2xl border border-neutral-200 bg-white/95 p-3 shadow-[var(--shadow-pop)] backdrop-blur">
            <span class="px-2 text-sm font-medium text-neutral-700"><span x-text="sel.length"></span> {{ __('selected') }}</span>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button type="button" size="sm" variant="secondary" icon="pause" x-on:click="submitBulk('pause')">{{ __('Pause') }}</x-ui.button>
                <x-ui.button type="button" size="sm" variant="secondary" icon="play" x-on:click="submitBulk('resume')">{{ __('Resume') }}</x-ui.button>
                <x-ui.button type="button" size="sm" variant="secondary" icon="archive-box" x-on:click="submitBulk('archive')">{{ __('Archive') }}</x-ui.button>
                <x-ui.button type="button" size="sm" variant="danger" icon="trash" x-on:click="$dispatch('open-modal', 'p2p-bulk-delete')">{{ __('Delete') }}</x-ui.button>
                <button type="button" class="px-2 text-sm text-neutral-500 hover:text-neutral-900" x-on:click="sel = []">{{ __('Clear') }}</button>
            </div>
        </div>

        {{-- Hidden bulk form — ids injected from the current selection --}}
        <form method="POST" action="{{ route('p2p.ads.bulk') }}" x-ref="bulkForm" class="hidden">
            @csrf
            <input type="hidden" name="action" x-ref="bulkAction">
            <template x-for="id in sel" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
        </form>

        <x-ui.modal name="p2p-bulk-delete" :title="__('Delete selected ads')" maxWidth="sm">
            <p class="text-sm text-neutral-600"><span x-text="sel.length"></span> {{ __('ad(s) will be deleted. Ads with open orders are skipped.') }}</p>
            <div class="mt-5 flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'p2p-bulk-delete')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="button" variant="danger" icon="trash" x-on:click="$dispatch('close-modal', 'p2p-bulk-delete'); submitBulk('delete')">{{ __('Delete') }}</x-ui.button>
            </div>
        </x-ui.modal>

        {{-- Delete confirmation (shared) --}}
        <x-ui.modal name="p2p-ad-delete" :title="__('Delete ad')" maxWidth="sm">
            <p class="text-sm text-neutral-600">{{ __('Delete this ad?') }} <span class="font-medium text-neutral-900" x-text="del.ref"></span></p>
            <p class="mt-1 text-xs text-neutral-400">{{ __('Ads with open orders can’t be deleted. This can’t be undone.') }}</p>
            <div class="mt-5 flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'p2p-ad-delete')">{{ __('Cancel') }}</x-ui.button>
                <form method="POST" :action="del.url">
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" variant="danger" icon="trash">{{ __('Delete ad') }}</x-ui.button>
                </form>
            </div>
        </x-ui.modal>
    </div>
</x-layouts.app>
