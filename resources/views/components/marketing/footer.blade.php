@php
    $home = route('home');
    $page = fn (string $slug) => route('page.show', $slug);

    // Link columns (each destination appears once).
    $columns = [
        __('Products') => [
            [__('Virtual Card'), route('products.show', 'virtual-card')],
            [__('Wallet'), route('products.show', 'wallet')],
            [__('Exchange'), route('products.show', 'exchange')],
            [__('Merchant Pay'), route('merchants')],
        ],
        __('Company') => [
            [__('About Us'), $page('about-us')],
            [__('Careers'), $page('careers')],
            [__('Blog'), $page('blog')],
            [__('Contact'), $page('contact')],
        ],
        __('Resources') => [
            [__('Help Center'), route('faqs.public')],
            [__('Live Rates'), route('marketing.rates')],
            [__('System Status'), route('status')],
            [__('API Documentation'), $page('api')],
        ],
        // All legal policies live on one combined page, linked by section anchor.
        __('Legal') => [
            [__('Terms of Service'), $page('legal').'#terms'],
            [__('Privacy Policy'), $page('legal').'#privacy'],
            [__('AML & KYC Policy'), $page('legal').'#aml-kyc'],
            [__('Compliance'), $page('legal').'#compliance'],
        ],
    ];

    // Brand social icons (inline SVG paths, 24×24 viewBox).
    $socials = [
        ['label' => 'Facebook', 'href' => '#', 'path' => 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z'],
        ['label' => 'X', 'href' => '#', 'path' => 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z'],
        ['label' => 'LinkedIn', 'href' => '#', 'path' => 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z'],
        ['label' => 'Telegram', 'href' => '#', 'path' => 'M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z'],
    ];
@endphp

<footer class="relative border-t border-slate-200 bg-white text-slate-600">
    {{-- Brand gradient hairline --}}
    <div class="absolute inset-x-0 top-0 h-0.5" style="background:linear-gradient(90deg,transparent,var(--brand),transparent);opacity:.5"></div>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        {{-- ═══════════ Main footer: 5 columns ═══════════ --}}
        <div class="grid gap-10 py-16 sm:grid-cols-2 lg:grid-cols-[1.6fr_1fr_1fr_1fr_1.2fr]">
            {{-- Brand --}}
            <div class="sm:col-span-2 lg:col-span-1">
                <a href="{{ $home }}" class="inline-flex items-center gap-2.5">
                    <span class="grid h-9 w-9 place-items-center rounded-xl text-white" style="background:linear-gradient(120deg,var(--brand),var(--brand-600))">
                        <x-heroicon-s-bolt class="h-5 w-5" />
                    </span>
                    <span class="text-lg font-bold text-slate-900">PoisaPay</span>
                </a>
                <p class="mt-4 max-w-xs text-sm leading-relaxed text-slate-500">
                    {{ __('Borderless global payments powered by digital assets. Manage your wallet, create virtual cards, exchange currencies, and pay merchants securely.') }}
                </p>
                <div class="mt-6 flex gap-2.5">
                    @foreach ($socials as $s)
                        <a href="{{ $s['href'] }}" aria-label="{{ $s['label'] }}"
                            class="grid h-9 w-9 place-items-center rounded-full bg-slate-50 text-slate-500 ring-1 ring-slate-200 transition duration-200 hover:-translate-y-0.5 hover:bg-blue-600 hover:text-white hover:ring-transparent hover:shadow-md">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $s['path'] }}" /></svg>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Link columns --}}
            @foreach ($columns as $title => $links)
                <nav aria-label="{{ $title }}">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $title }}</h3>
                    <ul class="mt-4 space-y-3 text-sm">
                        @foreach ($links as $link)
                            <li>
                                <a href="{{ $link[1] }}" class="text-slate-600 transition duration-200 hover:text-blue-600">{{ $link[0] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endforeach
        </div>

        {{-- ═══════════ Legal disclaimer ═══════════ --}}
        <div class="border-t border-slate-200 py-6">
            <p class="mx-auto max-w-3xl text-center text-xs leading-relaxed text-slate-400">
                {{ __('Card services are provided by licensed issuing partners where applicable. Cryptocurrency services may not be available in all jurisdictions.') }}
            </p>
        </div>

        {{-- ═══════════ Bottom bar ═══════════ --}}
        <div class="flex flex-col items-center gap-4 border-t border-slate-200 py-8 text-sm text-slate-500 sm:flex-row sm:justify-between">
            <p>© {{ date('Y') }} PoisaPay. {{ __('All rights reserved.') }}</p>

            <div class="flex items-center gap-2">
                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-xs font-medium text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-100 hover:text-slate-900">
                    <x-heroicon-o-language class="h-4 w-4" /> {{ __('English') }}
                </button>
                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-xs font-medium text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-100 hover:text-slate-900">
                    <x-heroicon-o-currency-dollar class="h-4 w-4" /> {{ __('USD') }}
                </button>
            </div>
        </div>
    </div>
</footer>
