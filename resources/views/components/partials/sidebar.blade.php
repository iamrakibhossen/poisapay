@php
    // Grouped user navigation — clean sections like the DollarHub admin shell.
    $groups = [
        [
            'heading' => null,
            'items' => [
                ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'home'],
                ['route' => 'wallet', 'label' => 'Wallet', 'icon' => 'wallet'],
            ],
        ],
        [
            'heading' => 'Money',
            'items' => [
                ['route' => 'deposit', 'label' => 'Deposit', 'icon' => 'arrow-down-tray'],
                ['route' => 'withdraw', 'label' => 'Withdraw', 'icon' => 'arrow-up-tray'],
                ['route' => 'send', 'label' => 'Send Money', 'icon' => 'paper-airplane'],
                ['route' => 'exchange', 'label' => 'Exchange', 'icon' => 'arrows-right-left'],
                ['route' => 'transactions', 'label' => 'Transactions', 'icon' => 'receipt-percent'],
            ],
        ],
        [
            'heading' => 'Products',
            'items' => [
                ['route' => 'cards', 'label' => 'Cards', 'icon' => 'credit-card'],
                ['route' => 'merchant', 'label' => 'Merchant', 'icon' => 'building-storefront'],
                ['route' => 'credit', 'label' => 'Credit', 'icon' => 'banknotes'],
                ['route' => 'rewards', 'label' => 'Rewards', 'icon' => 'gift'],
            ],
        ],
        [
            'heading' => 'Account',
            'items' => [
                ['route' => 'settings', 'label' => 'Settings', 'icon' => 'cog-6-tooth'],
            ],
        ],
    ];
@endphp

{{-- Mobile backdrop --}}
<div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-black/40 lg:hidden" @click="sidebar = false"></div>

<aside
    class="fixed lg:static inset-y-0 left-0 z-40 w-[260px] shrink-0 -translate-x-full overflow-y-auto border-r border-neutral-100 bg-white pt-6 transition-transform duration-300 lg:translate-x-0"
    :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
>
    <nav class="px-2 pb-8">
        @foreach ($groups as $group)
            @if ($group['heading'])
                <p class="mb-1 mt-5 px-4 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">{{ $group['heading'] }}</p>
            @endif
            <div class="space-y-1">
                @foreach ($group['items'] as $item)
                    @php $active = request()->routeIs($item['route']); @endphp
                    <a href="{{ route($item['route']) }}" wire:navigate
                       @class([
                           'group relative flex h-11 items-center gap-3 rounded-lg px-4 text-sm font-semibold transition-colors',
                           'bg-brand-50 text-neutral-900' => $active,
                           'text-neutral-500 hover:bg-neutral-50 hover:text-neutral-900' => ! $active,
                       ])>
                        @if ($active)
                            <span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand-500"></span>
                        @endif
                        <x-dynamic-component :component="'heroicon-o-'.$item['icon']"
                            class="h-5 w-5 shrink-0 transition-colors {{ $active ? 'text-brand-500' : 'text-neutral-400 group-hover:text-neutral-600' }}" />
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>
</aside>
