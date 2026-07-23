@props(['title' => 'Dashboard'])

{{-- DollarHub frontend header — full width across the top, logo left, actions right. --}}
<header class="relative flex h-16 items-center justify-between border-b border-neutral-200 bg-white px-4 sm:px-6">
    <div class="flex items-center gap-4">
        <button class="lg:hidden text-neutral-500 hover:text-neutral-900" @click="sidebar = !sidebar" aria-label="Menu">
            <x-heroicon-o-bars-3 class="h-6 w-6" />
        </button>
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-500 text-white shadow-sm">
                <x-heroicon-s-bolt class="h-5 w-5" />
            </span>
            <span class="text-lg font-bold tracking-tight text-neutral-900">PoisaPay</span>
        </a>
    </div>

    <div class="flex items-center gap-2 sm:gap-3">
        {{-- Highlighted P2P entry (feature-gated) --}}
        @if (feature('p2p_enabled', false))
            @php $p2pActive = request()->routeIs('p2p') || request()->routeIs('p2p.*'); @endphp
            <a href="{{ route('p2p') }}" @class([
                'hidden items-center gap-1.5 rounded-lg border px-3.5 py-2 text-sm font-semibold transition sm:inline-flex',
                'border-brand-500 bg-brand-500 text-white shadow-sm' => $p2pActive,
                'border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100' => ! $p2pActive,
            ])>
                <x-heroicon-o-user-group class="h-4 w-4" /> P2P
            </a>
        @endif

        {{-- Locale switcher --}}
        <div x-data="{ open: false }" class="relative" @keydown.escape="open = false">
            <button type="button" @click="open = !open"
                    class="flex h-9 items-center gap-1.5 rounded-lg border border-neutral-200 px-2.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    aria-label="{{ __('app.language') }}">
                <x-heroicon-o-language class="h-4 w-4" />
                <span>{{ app()->getLocale() === 'bn' ? 'বাংলা' : 'EN' }}</span>
            </button>
            <div x-show="open" x-cloak x-transition @click.outside="open = false"
                 class="absolute right-0 mt-2 w-36 origin-top-right overflow-hidden rounded-xl border border-neutral-200 bg-white py-1 shadow-[var(--shadow-pop)]">
                @foreach (['en' => 'English', 'bn' => 'বাংলা'] as $code => $label)
                    <form method="POST" action="{{ route('locale.switch') }}">
                        @csrf
                        <input type="hidden" name="locale" value="{{ $code }}">
                        <button type="submit"
                                class="flex w-full items-center justify-between px-4 py-2.5 text-sm text-neutral-700 hover:bg-neutral-100">
                            {{ $label }}
                            @if (app()->getLocale() === $code)
                                <x-heroicon-o-check class="h-4 w-4 text-brand-600" />
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>
        </div>

        {{-- Notifications --}}
        @php $unreadNotifications = auth()->user()?->unreadNotifications()->count() ?? 0; @endphp
        <a href="{{ route('notifications') }}" class="relative rounded-full p-2 text-neutral-500 hover:bg-neutral-100" aria-label="Notifications">
            <x-heroicon-o-bell class="h-6 w-6" />
            @if ($unreadNotifications > 0)
                <span class="absolute right-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold leading-none text-white ring-2 ring-white">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
            @endif
        </a>

        {{-- User dropdown --}}
        @auth
        <div x-data="{ open: false }" class="relative" @keydown.escape="open = false">
            <button type="button" @click="open = !open" class="flex h-10 items-center gap-2 rounded-full border border-neutral-200 py-0.5 pl-0.5 pr-2 hover:bg-neutral-50">
                <x-ui.user :user="auth()->user()" compact size="sm" />
                <x-heroicon-m-chevron-down class="h-4 w-4 text-neutral-400" />
            </button>
            <div x-show="open" x-cloak x-transition @click.outside="open = false"
                 class="absolute right-0 mt-2 w-56 origin-top-right overflow-hidden rounded-xl border border-neutral-200 bg-white py-1 shadow-[var(--shadow-pop)]">
                <div class="px-4 py-3">
                    <x-ui.user :user="auth()->user()" size="sm">
                        <div class="mt-2"><x-ui.badge :color="auth()->user()->tier()->color()" dot>{{ auth()->user()->tier()->label() }}</x-ui.badge></div>
                    </x-ui.user>
                </div>
                <div class="border-t border-neutral-100"></div>
                <a href="{{ route('settings') }}" class="flex items-center gap-2 px-4 py-3 text-sm text-neutral-700 hover:bg-neutral-100"><x-heroicon-o-user class="h-5 w-5" /> Profile &amp; Security</a>
                <a href="{{ route('support') }}" class="flex items-center gap-2 px-4 py-3 text-sm text-neutral-700 hover:bg-neutral-100"><x-heroicon-o-lifebuoy class="h-5 w-5" /> Support</a>
                <div class="border-t border-neutral-100"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2 px-4 py-3 text-sm text-rose-600 hover:bg-rose-50"><x-heroicon-o-arrow-right-start-on-rectangle class="h-5 w-5" /> Sign out</button>
                </form>
            </div>
        </div>
        @endauth
    </div>
</header>
