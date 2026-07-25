<x-layouts.app :title="__('Sell')">
    <div class="mt-6 space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600">
                    <x-heroicon-o-rocket-launch class="h-5 w-5" />
                </span>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Seller') }}</h1>
                    <p class="mt-1 text-sm text-neutral-500">{{ __('Turn your digital products into shareable sales pages.') }}</p>
                </div>
            </div>
            @unless ($isSeller)
                <x-ui.button href="{{ route('seller.apply') }}" size="lg" icon="rocket-launch">{{ __('Become a seller') }}</x-ui.button>
            @endunless
        </div>

        {{-- Onboarding banner (shown until approved) --}}
        @unless ($isSeller)
            <div class="relative overflow-hidden rounded-2xl border border-brand-100 bg-gradient-to-br from-white to-brand-50 p-6 sm:p-7">
                <div class="absolute -right-8 -top-10 h-40 w-40 rounded-full bg-brand-300/20 blur-3xl"></div>
                <div class="relative max-w-xl">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ __('Get started') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-neutral-900">{{ __('Start selling in 3 steps') }}</h2>
                    <ol class="mt-4 space-y-3">
                        @foreach ([
                            [__('Apply to become a seller'), __('Tell us what you sell and verify your identity.')],
                            [__('Create your first product'), __('Download, physical product, license key, membership or service.')],
                            [__('Share your sales page'), __('Drop the link in ads, email or social and get paid.')],
                        ] as $i => [$t, $d])
                            <li class="flex gap-3">
                                <span class="tabular grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-500 text-[11px] font-bold text-white">{{ $i + 1 }}</span>
                                <div>
                                    <p class="text-sm font-medium text-neutral-900">{{ $t }}</p>
                                    <p class="text-xs text-neutral-500">{{ $d }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <div class="mt-5">
                        <x-ui.button href="{{ route('seller.apply') }}" icon="paper-airplane">{{ __('Apply now') }}</x-ui.button>
                    </div>
                </div>
            </div>
        @endunless

        {{-- KPI shell --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['banknotes', __('Total revenue'), $stats['revenue'], 'text-neutral-900'],
                ['wallet', __('Available'), $stats['available'], 'text-emerald-600'],
                ['clock', __('Pending'), $stats['pending'], 'text-amber-600'],
                ['shopping-bag', __('Sales'), number_format($stats['sales']), 'text-neutral-900'],
            ] as [$icon, $label, $value, $tone])
                <div class="pp-card p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-neutral-400">{{ $label }}</p>
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-neutral-100 text-neutral-500">
                            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4" />
                        </span>
                    </div>
                    <p class="tabular mt-2 text-xl font-bold tracking-tight {{ $tone }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        {{-- Module grid (coming online as we build each) --}}
        <div>
            <h2 class="mb-3 px-1 text-sm font-semibold text-neutral-900">{{ __('Your workspace') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['cube', __('Products'), __('Create and manage your products.'), route('seller.products')],
                    ['squares-2x2', __('Funnels'), __('Order bumps, upsells and downsells.'), route('seller.funnels')],
                    ['document-text', __('Sales pages'), __('Customize each product landing page.'), route('seller.sales-pages')],
                    ['inbox-stack', __('Orders'), __('Track sales and fulfilment.'), route('seller.orders')],
                    ['chat-bubble-left-right', __('Inbox'), __('Reply to buyer messages.'), route('seller.inbox')],
                    ['star', __('Reviews'), __('Ratings and buyer feedback.'), route('seller.reviews')],
                    ['users', __('Customers'), __('Buyers and their purchases.'), route('seller.customers')],
                    ['ticket', __('Coupons'), __('Discounts and campaigns.'), route('seller.coupons')],
                    ['chart-bar', __('Analytics'), __('Traffic, conversion and upsell rates.'), route('seller.analytics')],
                    ['banknotes', __('Earnings & payouts'), __('Balance, vesting and withdrawals.'), route('seller.earnings')],
                    ['globe-alt', __('Custom domain'), __('Serve pages from your own domain.'), route('seller.domains')],
                ] as [$icon, $title, $desc, $url])
                    <{{ $url ? 'a' : 'div' }} @if ($url) href="{{ $url }}" @endif class="pp-card flex items-start gap-3 p-4 transition {{ $url ? 'hover:border-brand-200 hover:shadow-md' : 'opacity-70' }}">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="flex items-center gap-2 text-sm font-semibold text-neutral-900">
                                {{ $title }}
                                @unless ($url)
                                    <span class="rounded-full bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-400">{{ __('Soon') }}</span>
                                @endunless
                            </p>
                            <p class="mt-0.5 text-xs text-neutral-500">{{ $desc }}</p>
                        </div>
                    </{{ $url ? 'a' : 'div' }}>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>
