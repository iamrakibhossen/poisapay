<x-layouts.app :title="__('My P2P orders')">
    @php
        $tabs = [
            'all' => __('All'),
            'active' => __('Active'),
            'completed' => __('Completed'),
            'cancelled' => __('Cancelled'),
            'disputed' => __('Disputed'),
        ];
        $hasFilters = request()->hasAny(['role', 'search', 'from', 'to']) || $tab !== 'all';
    @endphp

    <div class="space-y-5">
        {{-- Header --}}
        <x-ui.page-header :title="__('My orders')" :subtitle="__('Track and manage your P2P trades.')">
            <x-slot:actions>
                <a href="{{ route('p2p') }}"><x-ui.button variant="secondary" icon="arrow-left">{{ __('Marketplace') }}</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Status tabs --}}
        <div class="flex gap-1 overflow-x-auto border-b border-neutral-200">
            @foreach ($tabs as $key => $label)
                @php $active = $tab === $key; @endphp
                <a href="{{ route('p2p.orders', array_merge(request()->except(['tab', 'page']), $key === 'all' ? [] : ['tab' => $key])) }}"
                   class="-mb-px flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition-colors {{ $active ? 'border-brand-500 text-neutral-900' : 'border-transparent text-neutral-500 hover:text-neutral-900' }}">
                    {{ $label }}
                    <span class="rounded-full px-1.5 py-0.5 text-xs tabular {{ $active ? 'bg-brand-50 text-brand-700' : 'bg-neutral-100 text-neutral-500' }}">{{ number_format($counts[$key] ?? 0) }}</span>
                </a>
            @endforeach
        </div>

        {{-- Search + advanced filters --}}
        <form method="GET" action="{{ route('p2p.orders') }}"
              x-data="{ adv: {{ request()->hasAny(['role', 'from', 'to']) ? 'true' : 'false' }} }" class="space-y-3">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative min-w-[12rem] flex-1">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search order number…') }}"
                           class="pp-input h-10 w-full min-h-0 py-0 pl-9 text-sm">
                </div>
                <button type="button" @click="adv = !adv"
                        class="pp-chip" :class="{ 'is-on': adv }">
                    <x-heroicon-o-adjustments-horizontal class="h-4 w-4" /> {{ __('Filters') }}
                    @if (request()->hasAny(['role', 'from', 'to']))<span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>@endif
                </button>
                <x-ui.button type="submit" icon="magnifying-glass">{{ __('Search') }}</x-ui.button>
                @if ($hasFilters)
                    <a href="{{ route('p2p.orders') }}" class="text-sm font-medium text-neutral-500 hover:text-neutral-900">{{ __('Reset') }}</a>
                @endif
            </div>

            {{-- Advanced --}}
            <div x-show="adv" x-cloak x-transition
                 class="grid grid-cols-1 gap-3 rounded-xl border border-neutral-200 bg-white p-4 shadow-[var(--shadow-card)] sm:grid-cols-3">
                <x-ui.select :label="__('Side')" name="role">
                    <option value="">{{ __('All') }}</option>
                    <option value="buying" @selected(request('role') === 'buying')>{{ __('Buying') }}</option>
                    <option value="selling" @selected(request('role') === 'selling')>{{ __('Selling') }}</option>
                </x-ui.select>
                <x-ui.input :label="__('From')" name="from" type="date" :value="request('from')" />
                <x-ui.input :label="__('To')" name="to" type="date" :value="request('to')" />
            </div>
        </form>

        {{-- Orders --}}
        @if ($orders->isEmpty())
            <x-ui.card>
                <x-ui.empty-state icon="shopping-bag"
                    :title="$hasFilters ? __('No matching orders') : __('No orders yet')"
                    :description="$hasFilters ? __('Try a different tab or clear your filters.') : __('Browse the marketplace to place your first order.')">
                    <x-slot:action>
                        @if ($hasFilters)
                            <a href="{{ route('p2p.orders') }}"><x-ui.button variant="secondary">{{ __('Clear filters') }}</x-ui.button></a>
                        @else
                            <a href="{{ route('p2p') }}"><x-ui.button icon="arrow-right">{{ __('Explore marketplace') }}</x-ui.button></a>
                        @endif
                    </x-slot:action>
                </x-ui.empty-state>
            </x-ui.card>
        @else
            {{-- Column header (desktop) --}}
            <div class="hidden px-4 lg:grid lg:grid-cols-[1.1fr_1.3fr_1fr_1.2fr_1fr_auto] lg:gap-4 lg:text-xs lg:font-semibold lg:uppercase lg:tracking-wider lg:text-neutral-400">
                <span>{{ __('Side / Date') }}</span>
                <span>{{ __('Order number') }}</span>
                <span>{{ __('Price') }}</span>
                <span>{{ __('Amount') }}</span>
                <span>{{ __('Counterparty') }}</span>
                <span class="text-right">{{ __('Status') }}</span>
            </div>

            <div class="space-y-3 lg:space-y-2">
                @foreach ($orders as $order)
                    @php
                        $isBuyer = $order->buyer_id === $me;
                        $status = $order->status->value;
                        $actionNeeded = ($isBuyer && $status === 'waiting_payment') || (! $isBuyer && $status === 'buyer_paid');
                        $counterparty = ($isBuyer ? $order->seller?->name : $order->buyer?->name) ?? '—';
                    @endphp
                    <div class="relative grid grid-cols-1 gap-3 rounded-xl border bg-white p-4 shadow-[var(--shadow-card)] transition-colors hover:border-neutral-300 lg:grid-cols-[1.1fr_1.3fr_1fr_1.2fr_1fr_auto] lg:items-center lg:gap-4 {{ $actionNeeded ? 'border-amber-300 ring-1 ring-amber-100' : 'border-neutral-200' }}">

                        {{-- Side / Date --}}
                        <div class="flex items-center justify-between lg:block">
                            <div>
                                <p class="flex items-center gap-1.5 text-sm font-semibold">
                                    <span class="inline-flex h-5 items-center rounded px-1.5 text-xs font-bold {{ $isBuyer ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $isBuyer ? __('BUY') : __('SELL') }}</span>
                                    <span class="text-neutral-900">{{ $order->asset->symbol ?? 'USDT' }}</span>
                                </p>
                                <p class="mt-1 text-xs text-neutral-400">{{ $order->created_at?->format('d M, Y · h:i A') }}</p>
                            </div>
                            @if ($actionNeeded)
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 lg:hidden">
                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>{{ __('Action needed') }}
                                </span>
                            @endif
                        </div>

                        {{-- Order number --}}
                        <div class="flex items-center justify-between lg:block">
                            <span class="text-xs text-neutral-400 lg:hidden">{{ __('Order') }}</span>
                            <div class="flex items-center gap-1">
                                <a href="{{ route('p2p.order', $order) }}" class="font-mono text-xs font-medium text-neutral-700 hover:text-brand-600 hover:underline">{{ $order->ref }}</a>
                                <x-ui.copy-text :text="$order->ref" />
                            </div>
                        </div>

                        {{-- Price --}}
                        <div class="flex items-center justify-between lg:block">
                            <span class="text-xs text-neutral-400 lg:hidden">{{ __('Price') }}</span>
                            <span class="text-sm font-semibold tabular text-neutral-900">{{ number_format((float) $order->price, 2) }} <span class="text-xs font-medium text-neutral-400">{{ $order->fiat_currency }}</span></span>
                        </div>

                        {{-- Amount --}}
                        <div class="flex items-center justify-between lg:block">
                            <span class="text-xs text-neutral-400 lg:hidden">{{ __('Amount') }}</span>
                            <div class="text-right lg:text-left">
                                <p class="text-sm tabular text-neutral-700">{{ $order->cryptoMoney()->format() }}</p>
                                <p class="text-xs tabular text-neutral-400">{{ number_format((float) $order->fiat_amount, 2) }} {{ $order->fiat_currency }}</p>
                            </div>
                        </div>

                        {{-- Counterparty --}}
                        <div class="flex items-center justify-between lg:block">
                            <span class="text-xs text-neutral-400 lg:hidden">{{ __('Counterparty') }}</span>
                            <div class="flex items-center gap-2">
                                <x-ui.avatar :name="$counterparty === '—' ? '?' : $counterparty" size="sm" class="hidden lg:inline-grid" />
                                <span class="truncate text-sm text-neutral-700">{{ $counterparty }}</span>
                            </div>
                        </div>

                        {{-- Status / Action --}}
                        <div class="flex items-center justify-between gap-3 border-t border-neutral-100 pt-3 lg:justify-end lg:border-0 lg:pt-0">
                            <x-ui.badge :color="$order->status->color()" dot>{{ $order->status->label() }}</x-ui.badge>
                            <a href="{{ route('p2p.order', $order) }}">
                                <x-ui.button size="sm" :variant="$actionNeeded ? 'primary' : 'secondary'" icon="arrow-right">
                                    {{ $actionNeeded ? ($isBuyer ? __('Pay now') : __('Release')) : __('View') }}
                                </x-ui.button>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div>{{ $orders->links() }}</div>
        @endif
    </div>
</x-layouts.app>
