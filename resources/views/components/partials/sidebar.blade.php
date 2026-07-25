@php
    // Show "My Purchases" only once the user has actually bought something —
    // an indexed exists() on their orders (nothing to show → no menu item).
    $hasPurchases = auth()->check() && rescue(fn () => \App\Shop\Models\Order::query()
        ->where('buyer_user_id', auth()->id())
        ->whereNotIn('status', ['pending', 'cancelled'])
        ->exists(), false, false);

    // Only approved sellers see "Sell" — everyone else reaches onboarding from the
    // marketing site (/products/sell), not the app nav.
    $isSeller = auth()->check() && rescue(
        fn () => app(\App\Shop\Services\SellerService::class)->forUser(auth()->user())?->canSell() ?? false,
        false, false,
    );

    // Grouped user navigation — clean sections like the DollarHub admin shell.
    $groups = [
        [
            'heading' => __('Overview'),
            'items' => array_values(array_filter([
                ['route' => 'dashboard', 'label' => __('Dashboard'), 'icon' => 'home'],
                ['route' => 'wallet', 'label' => __('Wallet'), 'icon' => 'wallet'],
                (feature('shop_enabled', false) && $isSeller) ? ['route' => 'shop', 'label' => __('Sell'), 'icon' => 'rocket-launch'] : null,
            ])),
        ],
        [
            'heading' => __('Money'),
            'items' => [
                ['route' => 'send.index', 'label' => __('Send Money'), 'icon' => 'paper-airplane'],
                ['route' => 'deposit.index', 'label' => __('Deposit'), 'icon' => 'arrow-down-tray'],
                ['route' => 'withdraw.index', 'label' => __('Withdraw'), 'icon' => 'arrow-up-tray'],
                ['route' => 'exchange.index', 'label' => __('Exchange'), 'icon' => 'arrows-right-left'],
                ['route' => 'transactions', 'label' => __('Transactions'), 'icon' => 'receipt-percent'],
            ],
        ],
        [
            'heading' => __('Products'),
            'items' => array_values(array_filter([
                ['route' => 'cards', 'label' => __('Cards'), 'icon' => 'credit-card'],
                ['route' => 'merchant', 'label' => __('Merchant'), 'icon' => 'building-storefront'],
                feature('p2p_enabled', false) ? ['route' => 'p2p', 'label' => 'P2P', 'icon' => 'user-group'] : null,
                $hasPurchases ? ['route' => 'purchases', 'label' => __('My Purchases'), 'icon' => 'shopping-bag'] : null,
                ['route' => 'rewards', 'label' => __('Rewards'), 'icon' => 'gift'],
            ])),
        ],
        [
            'heading' => __('Account'),
            'items' => [
                // Security lives under Settings › Security; Support is in the header user menu.
                ['route' => 'settings.index', 'label' => __('Settings'), 'icon' => 'cog-6-tooth'],
            ],
        ],
    ];
@endphp

{{-- Mobile backdrop --}}
<div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-black/40 lg:hidden" @click="sidebar = false"></div>

<aside
    class="fixed lg:static inset-y-0 left-0 z-40 w-[260px] shrink-0 -translate-x-full overflow-y-auto border-r border-neutral-100 bg-white pt-3 transition-transform duration-300 lg:translate-x-0"
    :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
>
    <nav class="px-2 pb-8">
        @foreach ($groups as $group)
            @if ($group['heading'])
                <p class="mb-1 mt-5 px-4 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">{{ $group['heading'] }}</p>
            @endif
            <div class="space-y-1">
                @foreach ($group['items'] as $item)
                    @php
                        // Active for the item's own route AND any child pages
                        // (e.g. wallet → wallet.show, send.index → send.*, settings.index → settings.*).
                        $base = str_ends_with($item['route'], '.index')
                            ? substr($item['route'], 0, -6)
                            : \Illuminate\Support\Str::before($item['route'], '.');
                        $active = request()->routeIs($item['route']) || request()->routeIs($base) || request()->routeIs($base.'.*');
                    @endphp
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
