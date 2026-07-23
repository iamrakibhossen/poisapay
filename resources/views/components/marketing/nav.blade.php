@php
    // Absolute homepage anchors so the nav works from any page (homepage + auth).
    $home = route('home');
    $navLinks = [
        ['href' => $home.'#features', 'label' => 'Features'],
        ['href' => $home.'#cards', 'label' => 'Cards'],
        ['href' => $home.'#exchange', 'label' => 'Exchange'],
        ['href' => route('merchants'), 'label' => 'Merchants', 'active' => request()->routeIs('merchants')],
        ['href' => $home.'#security', 'label' => 'Security'],
        ['href' => route('faqs.public'), 'label' => 'FAQ', 'active' => request()->routeIs('faqs.public')],
    ];
@endphp
<header
    x-data="{ scrolled: false, open: false }"
    @scroll.window="scrolled = window.scrollY > 24"
    :class="scrolled ? 'glass-2 border-slate-200 shadow-[0_8px_24px_-16px_rgba(15,23,42,.25)]' : 'border-transparent'"
    class="fixed inset-x-0 top-0 z-50 border-b border-transparent transition-all duration-300"
>
    <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-xl text-white" style="background:linear-gradient(120deg,var(--brand),var(--brand-600));box-shadow:0 8px 20px -8px rgba(37,99,235,.6)">
                <x-heroicon-s-bolt class="h-5 w-5" />
            </span>
            <span class="text-lg font-bold tracking-tight text-slate-900">PoisaPay</span>
        </a>

        <div class="hidden items-center gap-8 text-sm font-medium text-slate-600 lg:flex">
            @foreach ($navLinks as $l)
                <a href="{{ $l['href'] }}" @class(['transition hover:text-slate-900', 'font-semibold text-slate-900' => $l['active'] ?? false])>{{ $l['label'] }}</a>
            @endforeach
        </div>

        <div class="hidden items-center gap-2.5 lg:flex">
            @auth
                <a href="{{ route('dashboard') }}" class="pp-btn pp-btn-primary pp-btn-sm">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="pp-btn pp-btn-sm text-slate-600 hover:text-slate-900">Log in</a>
                <a href="{{ route('register') }}" class="pp-btn pp-btn-primary pp-btn-sm">Get started</a>
            @endauth
        </div>

        {{-- Mobile toggle --}}
        <button @click="open = !open" class="grid h-10 w-10 place-items-center rounded-xl text-slate-700 glass lg:hidden" :aria-expanded="open" aria-label="Toggle menu">
            <x-heroicon-o-bars-3 x-show="!open" class="h-5 w-5" />
            <x-heroicon-o-x-mark x-show="open" x-cloak class="h-5 w-5" />
        </button>
    </nav>

    {{-- Mobile menu --}}
    <div x-show="open" x-cloak x-transition.opacity class="border-t border-slate-200 glass-2 px-4 py-4 lg:hidden">
        <div class="flex flex-col gap-1">
            @foreach ($navLinks as $l)
                <a href="{{ $l['href'] }}" @click="open=false" @class(['rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-slate-100', 'bg-slate-100 text-slate-900' => $l['active'] ?? false, 'text-slate-700' => ! ($l['active'] ?? false)])>{{ $l['label'] }}</a>

            @endforeach
            <div class="mt-2 flex gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="pp-btn pp-btn-primary pp-btn-md flex-1">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="pp-btn pp-btn-ghost pp-btn-md flex-1">Log in</a>
                    <a href="{{ route('register') }}" class="pp-btn pp-btn-primary pp-btn-md flex-1">Get started</a>
                @endauth
            </div>
        </div>
    </div>
</header>
