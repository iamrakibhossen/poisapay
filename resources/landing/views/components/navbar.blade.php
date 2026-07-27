@php
    // Absolute homepage anchors so the nav works from any page (homepage + auth).
    $home = route('home');
    $onProduct = fn (string $slug) => request()->routeIs('products.show') && request()->route('product') === $slug;
    $navLinks = [
        ['href' => route('products.show', 'shop'), 'label' => __('Start selling'), 'active' => $onProduct('shop')],
        ['href' => route('products.show', 'virtual-card'), 'label' => __('Virtual Cards'), 'active' => $onProduct('virtual-card')],
        ['href' => route('products.show', 'wallet'), 'label' => __('Wallet'), 'active' => $onProduct('wallet')],
        ['href' => route('products.show', 'exchange'), 'label' => __('Exchange'), 'active' => $onProduct('exchange')],
        ['href' => route('products.show', 'p2p'), 'label' => __('P2P'), 'active' => $onProduct('p2p')],
        ['href' => route('products.show', 'merchant-pay'), 'label' => __('Merchant Pay'), 'active' => $onProduct('merchant-pay')],
        ['href' => route('help-center'), 'label' => __('Help Center'), 'active' => request()->routeIs('help-center')],
    ];
@endphp
<header
    x-data="{ scrolled: false, open: false }"
    @scroll.window="scrolled = window.scrollY > 24"
    :class="scrolled ? 'lp-glass-2 border-slate-200 shadow-[0_8px_24px_-16px_rgba(15,23,42,.25)]' : 'border-transparent'"
    class="fixed inset-x-0 top-0 z-50 border-b border-transparent transition-all duration-300"
>
    <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
            <img src="{{ asset('images/logo.svg') }}" alt="PaishaPay" class="h-9 w-9 shrink-0" />
            <span class="text-lg font-bold tracking-tight text-slate-900">PaishaPay</span>
        </a>

        <div class="hidden items-center gap-8 text-sm font-medium text-slate-600 lg:flex">
            @foreach ($navLinks as $l)
                <a href="{{ $l['href'] }}" @class(['transition hover:text-slate-900', 'font-semibold text-slate-900' => $l['active'] ?? false])>{{ $l['label'] }}</a>
            @endforeach
        </div>

        <div class="hidden items-center gap-2.5 lg:flex">
            @auth
                <a href="{{ route('dashboard') }}" class="lp-btn lp-btn-primary lp-btn-md" style="border-radius:5px">{{ __('Dashboard') }}</a>
            @else
                <a href="{{ route('login') }}" class="lp-btn lp-btn-ghost lp-btn-md" style="border-radius:5px">{{ __('Log in') }}</a>
                <a href="{{ route('register') }}" class="lp-btn lp-btn-primary lp-btn-md" style="border-radius:5px">{{ __('Get started') }}</a>
            @endauth
        </div>

        {{-- Mobile toggle --}}
        <button @click="open = !open" class="grid h-10 w-10 place-items-center rounded-xl text-slate-700 lp-glass lg:hidden" :aria-expanded="open" aria-label="{{ __('Toggle menu') }}">
            <x-heroicon-o-bars-3 x-show="!open" class="h-5 w-5" />
            <x-heroicon-o-x-mark x-show="open" x-cloak class="h-5 w-5" />
        </button>
    </nav>

    {{-- Mobile menu --}}
    <div x-show="open" x-cloak x-transition.opacity class="border-t border-slate-200 lp-glass-2 px-4 py-4 lg:hidden">
        <div class="flex flex-col gap-1">
            @foreach ($navLinks as $l)
                <a href="{{ $l['href'] }}" @click="open=false" @class(['rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-slate-100', 'bg-slate-100 text-slate-900' => $l['active'] ?? false, 'text-slate-700' => ! ($l['active'] ?? false)])>{{ $l['label'] }}</a>

            @endforeach
            <div class="mt-2 flex gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="lp-btn lp-btn-primary lp-btn-md flex-1">{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="lp-btn lp-btn-ghost lp-btn-md flex-1">{{ __('Log in') }}</a>
                    <a href="{{ route('register') }}" class="lp-btn lp-btn-primary lp-btn-md flex-1">{{ __('Get started') }}</a>
                @endauth
            </div>
        </div>
    </div>
</header>
