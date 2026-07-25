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
            @php
                $pending = $status === \App\Sell\Enums\SellerStatus::PendingReview;
                $rejected = $status === \App\Sell\Enums\SellerStatus::Rejected;
                $suspended = $status === \App\Sell\Enums\SellerStatus::Suspended;
                $notApplied = $status === null || $status === \App\Sell\Enums\SellerStatus::Draft;
            @endphp
            @if ($notApplied || $rejected)
                <x-ui.button href="{{ route('sell.apply') }}" size="lg" icon="rocket-launch">{{ $rejected ? __('Re-apply') : __('Become a seller') }}</x-ui.button>
            @elseif ($pending)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700">
                    <x-heroicon-s-clock class="h-4 w-4" /> {{ __('Under review') }}
                </span>
            @elseif ($isSeller && $hasProducts)
                <div class="flex items-center gap-2">
                    <x-ui.button href="{{ route('sell.products') }}" variant="ghost" icon="cube">{{ __('Products') }}</x-ui.button>
                    <x-ui.button href="{{ route('sell.products.create') }}" icon="plus">{{ __('New product') }}</x-ui.button>
                </div>
            @endif
        </div>

        {{-- Status banner --}}
        @if ($pending)
            <x-ui.alert type="warning" :title="__('Your application is under review')">
                {{ __('We usually review within 1–2 business days. You can prepare your first product in the meantime.') }}
            </x-ui.alert>
        @elseif ($suspended)
            <x-ui.alert type="danger" :title="__('Your seller account is suspended')">
                {{ __('Contact support to resolve this and restore selling.') }}
            </x-ui.alert>
        @endif

        {{-- Onboarding steps (shown until applied/approved) --}}
        @if ($notApplied || $rejected)
            <div class="relative overflow-hidden rounded-2xl border border-brand-100 bg-gradient-to-br from-white to-brand-50 p-6 sm:p-7">
                <div class="absolute -right-8 -top-10 h-40 w-40 rounded-full bg-brand-300/20 blur-3xl"></div>
                <div class="relative max-w-xl">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ $rejected ? __('Try again') : __('Get started') }}</p>
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
                        <x-ui.button href="{{ route('sell.apply') }}" icon="paper-airplane">{{ $rejected ? __('Re-apply') : __('Apply now') }}</x-ui.button>
                    </div>
                </div>
            </div>
        @endif

        @if ($isSeller && ! $hasProducts)
            {{-- First run: approved but nothing to sell yet. Guide to the first product. --}}
            <div class="relative overflow-hidden rounded-2xl border border-brand-100 bg-gradient-to-br from-white to-brand-50 p-6 sm:p-8">
                <div class="absolute -right-10 -top-12 h-48 w-48 rounded-full bg-brand-300/20 blur-3xl"></div>
                <div class="relative">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                        <x-heroicon-s-check-badge class="h-4 w-4" /> {{ __('You’re approved') }}
                    </span>
                    <h2 class="mt-3 text-xl font-semibold text-neutral-900">{{ __('Let’s launch your first product') }}</h2>
                    <p class="mt-1 max-w-xl text-sm text-neutral-500">{{ __('Create a product, publish it to generate a shareable sales page, then drop the link anywhere to start getting paid.') }}</p>

                    <ol class="mt-6 grid gap-3 sm:grid-cols-3">
                        @foreach ([
                            ['cube', __('Create a product'), __('Digital, physical, license, membership or service.')],
                            ['document-text', __('Publish its sales page'), __('A hosted landing page is generated automatically.')],
                            ['share', __('Share & get paid'), __('Post the link in ads, email or social.')],
                        ] as $i => [$icon, $t, $d])
                            <li class="rounded-xl border border-white bg-white/70 p-4 shadow-sm">
                                <span class="flex items-center gap-2">
                                    <span class="tabular grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-500 text-[11px] font-bold text-white">{{ $i + 1 }}</span>
                                    <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4 text-brand-600" />
                                </span>
                                <p class="mt-2 text-sm font-medium text-neutral-900">{{ $t }}</p>
                                <p class="mt-0.5 text-xs text-neutral-500">{{ $d }}</p>
                            </li>
                        @endforeach
                    </ol>

                    <div class="mt-6">
                        <x-ui.button href="{{ route('sell.products.create') }}" size="lg" icon="plus">{{ __('Create your first product') }}</x-ui.button>
                    </div>
                </div>
            </div>
        @elseif ($isSeller)
        {{-- Store branding — logo shown in the public storefront header. --}}
        @if (session('success'))
            <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
        @endif
        <x-ui.card>
            <div class="flex flex-wrap items-center gap-4">
                @if ($seller->logoUrl())
                    <img src="{{ $seller->logoUrl() }}" alt="{{ $seller->displayName() }}" class="h-14 w-14 rounded-xl object-cover ring-1 ring-neutral-200" />
                @else
                    <span class="grid h-14 w-14 shrink-0 place-items-center rounded-xl bg-brand-500 text-lg font-bold text-white">{{ \Illuminate\Support\Str::substr($seller->displayName(), 0, 1) }}</span>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-neutral-900">{{ $seller->displayName() }}</p>
                    <p class="text-xs text-neutral-500">{{ __('Your store logo appears in the header of every sales page.') }}</p>
                    @error('logo')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('sell.logo') }}" enctype="multipart/form-data" x-data
                        class="flex items-center gap-2">
                        @csrf
                        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden"
                            x-ref="logo" x-on:change="$el.files.length && $el.form.submit()" />
                        <x-ui.button type="button" variant="secondary" size="sm" icon="arrow-up-tray" x-on:click="$refs.logo.click()">
                            {{ $seller->logoUrl() ? __('Replace') : __('Upload logo') }}
                        </x-ui.button>
                    </form>
                    @if ($seller->logoUrl())
                        <form method="POST" action="{{ route('sell.logo.delete') }}" onsubmit="return confirm('{{ __('Remove the store logo?') }}')">
                            @csrf @method('DELETE')
                            <x-ui.button type="submit" variant="ghost" size="sm">{{ __('Remove') }}</x-ui.button>
                        </form>
                    @endif
                </div>
            </div>
            <p class="mt-3 text-[11px] text-neutral-400">{{ __('PNG, JPG, WebP or SVG · square works best · up to 1 MB.') }}</p>
        </x-ui.card>

        {{-- KPI shell — real figures from paid orders. --}}
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

        {{-- Draft-not-published nudge: products exist but none are live. --}}
        @if ($counts['published'] === 0)
            <x-ui.alert type="info" :title="__('Publish a product to start selling')">
                {{ __('You have :n draft product(s). Publish one to generate its sales page and share the link.', ['n' => $counts['products']]) }}
                <a href="{{ route('sell.products') }}" class="font-semibold text-brand-600 hover:underline">{{ __('Go to products →') }}</a>
            </x-ui.alert>
        @endif

        {{-- Module grid --}}
        <div>
            <h2 class="mb-3 px-1 text-sm font-semibold text-neutral-900">{{ __('Your workspace') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['cube', __('Products'), __('Create and manage your products.'), route('sell.products'), $counts['products']],
                    ['squares-2x2', __('Funnels'), __('Order bumps, upsells and downsells.'), route('sell.funnels'), null],
                    ['document-text', __('Sales pages'), __('Customize each product landing page.'), route('sell.sales-pages'), $counts['pages'] ?: null],
                    ['inbox-stack', __('Orders'), __('Track sales and fulfilment.'), route('sell.orders'), $counts['sales'] ?: null],
                    ['chat-bubble-left-right', __('Inbox'), __('Reply to buyer messages.'), route('sell.inbox'), null],
                    ['star', __('Reviews'), __('Ratings and buyer feedback.'), route('sell.reviews'), null],
                    ['users', __('Customers'), __('Buyers and their purchases.'), route('sell.customers'), null],
                    ['ticket', __('Coupons'), __('Discounts and campaigns.'), route('sell.coupons'), null],
                    ['chart-bar', __('Analytics'), __('Traffic, conversion and upsell rates.'), route('sell.analytics'), null],
                    ['banknotes', __('Earnings & payouts'), __('Balance, vesting and withdrawals.'), route('sell.earnings'), null],
                    ['globe-alt', __('Custom domain'), __('Serve pages from your own domain.'), route('sell.domains'), null],
                ] as [$icon, $title, $desc, $url, $badge])
                    <{{ $url ? 'a' : 'div' }} @if ($url) href="{{ $url }}" @endif class="pp-card flex items-start gap-3 p-4 transition {{ $url ? 'hover:border-brand-200 hover:shadow-md' : 'opacity-70' }}">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-2 text-sm font-semibold text-neutral-900">
                                {{ $title }}
                                @if (! is_null($badge))
                                    <span class="tabular rounded-full bg-brand-50 px-1.5 py-0.5 text-[10px] font-semibold text-brand-700">{{ number_format($badge) }}</span>
                                @endif
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
        @endif
    </div>
</x-layouts.app>
