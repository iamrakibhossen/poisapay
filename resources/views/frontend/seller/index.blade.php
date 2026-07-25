<x-layouts.app :title="__('Sell')">
    @php
        $pending = $status === \App\Sell\Enums\SellerStatus::PendingReview;
        $rejected = $status === \App\Sell\Enums\SellerStatus::Rejected;
        $suspended = $status === \App\Sell\Enums\SellerStatus::Suspended;
        $notApplied = $status === null || $status === \App\Sell\Enums\SellerStatus::Draft;
    @endphp

    <div class="mt-6 space-y-6">
        {{-- ─────────────────────────  HEADER  ───────────────────────── --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            @if ($isSeller)
                <div class="flex min-w-0 items-center gap-3">
                    @if ($seller->logoUrl())
                        <img src="{{ $seller->logoUrl() }}" alt="{{ $seller->displayName() }}" class="h-12 w-12 shrink-0 rounded-xl object-cover ring-1 ring-neutral-200" />
                    @else
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-500 text-lg font-bold text-white">{{ \Illuminate\Support\Str::substr($seller->displayName(), 0, 1) }}</span>
                    @endif
                    <div class="min-w-0">
                        <h1 class="truncate text-2xl font-semibold tracking-tight text-neutral-900">{{ $seller->displayName() }}</h1>
                        <p class="mt-0.5 flex items-center gap-2 text-sm text-neutral-500">
                            <span class="inline-flex items-center gap-1 text-emerald-600">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ __('Seller') }}
                            </span>
                        </p>
                    </div>
                </div>
            @else
                <div class="flex items-center gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600">
                        <x-heroicon-o-rocket-launch class="h-5 w-5" />
                    </span>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Seller') }}</h1>
                        <p class="mt-1 text-sm text-neutral-500">{{ __('Turn your products into shareable sales pages.') }}</p>
                    </div>
                </div>
            @endif

            @if ($pending)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700">
                    <x-heroicon-s-clock class="h-4 w-4" /> {{ __('Under review') }}
                </span>
            @elseif ($isSeller && $hasProducts)
                <div class="flex items-center gap-2">
                    <x-ui.button href="{{ route('sell.products') }}" variant="secondary" icon="cube">{{ __('Products') }}</x-ui.button>
                    <x-ui.button href="{{ route('sell.products.create') }}" icon="plus">{{ __('New product') }}</x-ui.button>
                </div>
            @endif
        </div>

        {{-- Status / flash banners --}}
        @if (session('success'))
            <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if ($pending)
            <x-ui.alert type="warning" :title="__('Your application is under review')">
                {{ __('We usually review within 1–2 business days. You can prepare your first product in the meantime.') }}
            </x-ui.alert>
        @elseif ($suspended)
            <x-ui.alert type="danger" :title="__('Your seller account is suspended')">
                {{ __('Contact support to resolve this and restore selling.') }}
            </x-ui.alert>
        @endif

        {{-- ─────────────────  ONBOARDING (not yet a seller)  ───────────────── --}}
        @if ($notApplied || $rejected)
            {{-- Hero --}}
            <div class="relative overflow-hidden rounded-2xl border border-brand-100 bg-gradient-to-br from-white to-brand-50 p-6 sm:p-8">
                <div class="absolute -right-10 -top-12 h-52 w-52 rounded-full bg-brand-300/20 blur-3xl"></div>
                <div class="relative max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ $rejected ? __('Try again') : __('Get started') }}</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Sell your first product in minutes') }}</h2>
                    <p class="mt-2 text-sm text-neutral-600">{{ __('Turn a digital download, license, membership or service into a hosted sales page with built-in checkout — no website required.') }}</p>
                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <x-ui.button href="{{ route('sell.apply') }}" size="lg" icon="paper-airplane">{{ $rejected ? __('Re-apply to sell') : __('Become a seller') }}</x-ui.button>
                        <span class="inline-flex items-center gap-1.5 text-xs text-neutral-500">
                            <x-heroicon-o-clock class="h-4 w-4" /> {{ __('Reviewed within 1–2 business days') }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Steps --}}
            <div>
                <h3 class="mb-3 px-1 text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('Start selling in 3 steps') }}</h3>
                <ol class="grid gap-3 sm:grid-cols-3">
                    @foreach ([
                        ['identification', __('Apply & verify'), __('Tell us what you sell and verify your identity.')],
                        ['cube', __('Add your product'), __('Digital download, license key, membership or service.')],
                        ['share', __('Share & get paid'), __('Drop the sales-page link anywhere — payouts to your wallet.')],
                    ] as $i => [$icon, $t, $d])
                        <li class="pp-card p-5">
                            <div class="flex items-center gap-2">
                                <span class="tabular grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-500 text-[11px] font-bold text-white">{{ $i + 1 }}</span>
                                <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5 text-brand-600" />
                            </div>
                            <p class="mt-3 text-sm font-semibold text-neutral-900">{{ $t }}</p>
                            <p class="mt-0.5 text-xs text-neutral-500">{{ $d }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>

            {{-- Why sell here --}}
            <div>
                <h3 class="mb-3 px-1 text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('Why sell here') }}</h3>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ([
                        ['document-text', __('Hosted sales page'), __('Every product gets a shareable page + checkout.')],
                        ['banknotes', __('Get paid your way'), __('Wallet, card, crypto, bank & mobile money at checkout.')],
                        ['bolt', __('Instant delivery'), __('Digital goods delivered on payment; earnings settle to your wallet.')],
                    ] as [$icon, $title, $desc])
                        <div class="pp-card p-4">
                            <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-50 text-brand-600">
                                <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" />
                            </span>
                            <p class="mt-3 text-sm font-semibold text-neutral-900">{{ $title }}</p>
                            <p class="mt-0.5 text-xs text-neutral-500">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ─────────────  FIRST RUN (approved, no products yet)  ───────────── --}}
        @if ($isSeller && ! $hasProducts)
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
            {{-- ─────────────────────  ACTIVE SELLER DASHBOARD  ───────────────────── --}}

            {{-- Needs attention — only rendered when there's something to act on. --}}
            @php $needsAttention = ($attention['fulfil'] ?? 0) + ($attention['unread'] ?? 0) + ($attention['refunds'] ?? 0); @endphp
            @if ($needsAttention > 0)
                <div class="flex flex-wrap items-center gap-2 rounded-xl border border-amber-200 bg-amber-50/60 p-3">
                    <span class="ml-1 mr-1 inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-amber-700">
                        <x-heroicon-s-bell-alert class="h-4 w-4" /> {{ __('Needs attention') }}
                    </span>
                    @if ($attention['fulfil'] > 0)
                        <a href="{{ route('sell.orders') }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm ring-1 ring-neutral-200 hover:ring-brand-300">
                            <x-heroicon-o-inbox-stack class="h-4 w-4 text-brand-600" /> {{ trans_choice(':count order to fulfil|:count orders to fulfil', $attention['fulfil'], ['count' => $attention['fulfil']]) }}
                        </a>
                    @endif
                    @if ($attention['unread'] > 0)
                        <a href="{{ route('sell.inbox') }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm ring-1 ring-neutral-200 hover:ring-brand-300">
                            <x-heroicon-o-chat-bubble-left-right class="h-4 w-4 text-brand-600" /> {{ trans_choice(':count unread message|:count unread messages', $attention['unread'], ['count' => $attention['unread']]) }}
                        </a>
                    @endif
                    @if ($attention['refunds'] > 0)
                        <a href="{{ route('sell.orders') }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm ring-1 ring-neutral-200 hover:ring-brand-300">
                            <x-heroicon-o-arrow-uturn-left class="h-4 w-4 text-rose-500" /> {{ trans_choice(':count refund request|:count refund requests', $attention['refunds'], ['count' => $attention['refunds']]) }}
                        </a>
                    @endif
                </div>
            @endif

            {{-- KPIs — money first. --}}
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.stat-card :label="__('Total revenue')" :value="$stats['revenue']" icon="banknotes" accent="brand" />
                <x-ui.stat-card :label="__('Available')" :value="$stats['available']" icon="wallet" accent="emerald" />
                <x-ui.stat-card :label="__('Pending')" :value="$stats['pending']" icon="clock" accent="amber" />
                <x-ui.stat-card :label="__('Sales')" :value="number_format($stats['sales'])" icon="shopping-bag" accent="brand" />
            </div>

            {{-- Draft-not-published nudge. --}}
            @if ($counts['published'] === 0)
                <x-ui.alert type="info" :title="__('Publish a product to start selling')">
                    {{ __('You have :n draft product(s). Publish one to generate its sales page and share the link.', ['n' => $counts['products']]) }}
                    <a href="{{ route('sell.products') }}" class="font-semibold text-brand-600 hover:underline">{{ __('Go to products →') }}</a>
                </x-ui.alert>
            @endif

            {{-- Recent orders + store card. --}}
            <div class="grid gap-5 lg:grid-cols-3">
                {{-- Recent orders --}}
                <div class="lg:col-span-2">
                    <x-ui.card class="!p-0">
                        <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
                            <h2 class="text-sm font-semibold text-neutral-900">{{ __('Recent orders') }}</h2>
                            <a href="{{ route('sell.orders') }}" class="text-xs font-semibold text-brand-600 hover:underline">{{ __('View all') }}</a>
                        </div>
                        @if (count($recentOrders))
                            <ul class="divide-y divide-neutral-100">
                                @foreach ($recentOrders as $o)
                                    <li>
                                        <a href="{{ route('sell.order', $o['id']) }}" class="flex items-center gap-3 px-5 py-3 transition hover:bg-neutral-50">
                                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-neutral-100 text-neutral-500">
                                                <x-heroicon-o-shopping-bag class="h-4 w-4" />
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="flex items-center gap-1.5 truncate text-sm font-medium text-neutral-900">
                                                    {{ $o['product'] }}
                                                    @if ($o['unread'])<span class="h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500" title="{{ __('Unread') }}"></span>@endif
                                                </p>
                                                <p class="truncate text-xs text-neutral-500">{{ $o['buyer'] }} · {{ $o['date'] }}</p>
                                            </div>
                                            <div class="text-right">
                                                <p class="tabular text-sm font-semibold text-neutral-900">{{ $o['amount'] }}</p>
                                                <x-ui.badge :color="$o['color']">{{ $o['status'] }}</x-ui.badge>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="px-5 py-10">
                                <x-ui.empty-state icon="inbox-stack" :title="__('No orders yet')" :description="__('Share a live sales page to land your first sale.')" />
                            </div>
                        @endif
                    </x-ui.card>
                </div>

                {{-- Store card: branding + quick links --}}
                <x-ui.card class="!p-0">
                    <div class="border-b border-neutral-100 px-5 py-4">
                        <h2 class="text-sm font-semibold text-neutral-900">{{ __('Your store') }}</h2>
                    </div>
                    <div class="space-y-4 px-5 py-4">
                        <div class="flex items-center gap-3">
                            @if ($seller->logoUrl())
                                <img src="{{ $seller->logoUrl() }}" alt="{{ $seller->displayName() }}" class="h-12 w-12 rounded-xl object-cover ring-1 ring-neutral-200" />
                            @else
                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-500 text-lg font-bold text-white">{{ \Illuminate\Support\Str::substr($seller->displayName(), 0, 1) }}</span>
                            @endif
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-neutral-900">{{ $seller->displayName() }}</p>
                                <p class="text-xs text-neutral-500">{{ __('Shown on every sales page.') }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('sell.logo') }}" enctype="multipart/form-data" x-data class="flex-1">
                                @csrf
                                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden"
                                    x-ref="logo" x-on:change="$el.files.length && $el.form.submit()" />
                                <x-ui.button type="button" variant="secondary" size="sm" icon="arrow-up-tray" class="w-full" x-on:click="$refs.logo.click()">
                                    {{ $seller->logoUrl() ? __('Replace logo') : __('Upload logo') }}
                                </x-ui.button>
                            </form>
                            @if ($seller->logoUrl())
                                <form method="POST" action="{{ route('sell.logo.delete') }}" onsubmit="return confirm('{{ __('Remove the store logo?') }}')">
                                    @csrf @method('DELETE')
                                    <x-ui.button type="submit" variant="ghost" size="sm">{{ __('Remove') }}</x-ui.button>
                                </form>
                            @endif
                        </div>
                        @error('logo')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror

                        <div class="grid grid-cols-2 gap-2 border-t border-neutral-100 pt-4">
                            <a href="{{ route('sell.products.create') }}" class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                <x-heroicon-o-plus class="h-4 w-4 text-brand-600" /> {{ __('New product') }}
                            </a>
                            <a href="{{ route('sell.sales-pages') }}" class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                <x-heroicon-o-document-text class="h-4 w-4 text-brand-600" /> {{ __('Sales pages') }}
                            </a>
                            <a href="{{ route('sell.earnings') }}" class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                <x-heroicon-o-banknotes class="h-4 w-4 text-brand-600" /> {{ __('Earnings') }}
                            </a>
                            <a href="{{ route('sell.coupons') }}" class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                <x-heroicon-o-ticket class="h-4 w-4 text-brand-600" /> {{ __('Coupons') }}
                            </a>
                        </div>
                        <p class="text-[11px] text-neutral-400">{{ __('PNG, JPG, WebP or SVG · square works best · up to 1 MB.') }}</p>
                    </div>
                </x-ui.card>
            </div>

            {{-- ─────────────────  30-DAY PERFORMANCE  ───────────────── --}}
            @if ($insights)
                <div class="flex items-center justify-between px-1">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('Last 30 days') }}</h2>
                    <a href="{{ route('sell.analytics') }}" class="text-xs font-semibold text-brand-600 hover:underline">{{ __('Full analytics →') }}</a>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['eye', __('Visitors'), number_format($insights['visitors']), 'text-neutral-900'],
                        ['cursor-arrow-rays', __('Conversion'), $insights['conversion'].'%', 'text-emerald-600'],
                        ['banknotes', __('Revenue'), $insights['revenue30'], 'text-neutral-900'],
                        ['shopping-cart', __('Avg. order'), $insights['aov'], 'text-neutral-900'],
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

                <div class="grid gap-5 lg:grid-cols-2">
                    {{-- Conversion funnel --}}
                    <x-ui.card class="!p-0">
                        <div class="border-b border-neutral-100 px-5 py-4">
                            <h3 class="text-sm font-semibold text-neutral-900">{{ __('Conversion funnel') }}</h3>
                        </div>
                        <div class="space-y-4 px-5 py-5">
                            @foreach ($insights['funnel'] as [$label, $count, $width])
                                <div>
                                    <div class="mb-1 flex items-center justify-between text-xs">
                                        <span class="font-medium text-neutral-700">{{ $label }}</span>
                                        <span class="tabular text-neutral-500">{{ number_format($count) }} <span class="text-neutral-300">·</span> {{ $width }}%</span>
                                    </div>
                                    <div class="h-2.5 overflow-hidden rounded-full bg-neutral-100">
                                        <div class="h-full rounded-full bg-brand-500 transition-all" style="width: {{ max(2, $width) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.card>

                    {{-- Top products --}}
                    <x-ui.card class="!p-0">
                        <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
                            <h3 class="text-sm font-semibold text-neutral-900">{{ __('Top products') }}</h3>
                            <a href="{{ route('sell.products') }}" class="text-xs font-semibold text-brand-600 hover:underline">{{ __('All products') }}</a>
                        </div>
                        @if (count($insights['topProducts']))
                            <ul class="divide-y divide-neutral-100">
                                @foreach ($insights['topProducts'] as $i => $p)
                                    <li class="flex items-center gap-3 px-5 py-3">
                                        <span class="tabular grid h-6 w-6 shrink-0 place-items-center rounded-md bg-brand-50 text-[11px] font-bold text-brand-700">{{ $i + 1 }}</span>
                                        <p class="min-w-0 flex-1 truncate text-sm font-medium text-neutral-900">{{ $p['name'] }}</p>
                                        <span class="tabular shrink-0 text-xs text-neutral-500">{{ trans_choice(':count sale|:count sales', $p['units'], ['count' => number_format($p['units'])]) }}</span>
                                        <span class="tabular w-24 shrink-0 text-right text-sm font-semibold text-neutral-900">{{ $p['net'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="px-5 py-10">
                                <x-ui.empty-state icon="cube" :title="__('No sales yet')" :description="__('Your best sellers appear here once orders come in.')" />
                            </div>
                        @endif
                    </x-ui.card>
                </div>
            @endif

            {{-- Workspace — grouped by job-to-be-done for clear hierarchy. --}}
            @php
                $workspace = [
                    __('Catalog') => [
                        ['cube', __('Products'), __('Create and manage your products.'), route('sell.products'), $counts['products']],
                        ['document-text', __('Sales pages'), __('Customize each product landing page.'), route('sell.sales-pages'), $counts['pages'] ?: null],
                        ['squares-2x2', __('Funnels'), __('Order bumps, upsells and downsells.'), route('sell.funnels'), null],
                    ],
                    __('Customers') => [
                        ['inbox-stack', __('Orders'), __('Track sales and fulfilment.'), route('sell.orders'), $counts['sales'] ?: null],
                        ['chat-bubble-left-right', __('Inbox'), __('Reply to buyer messages.'), route('sell.inbox'), ($attention['unread'] ?? 0) ?: null],
                        ['star', __('Reviews'), __('Ratings and buyer feedback.'), route('sell.reviews'), null],
                        ['users', __('Customers'), __('Buyers and their purchases.'), route('sell.customers'), null],
                    ],
                    __('Grow') => [
                        ['ticket', __('Coupons'), __('Discounts and campaigns.'), route('sell.coupons'), null],
                        ['chart-bar', __('Analytics'), __('Traffic, conversion and upsell rates.'), route('sell.analytics'), null],
                        ['globe-alt', __('Custom domain'), __('Serve pages from your own domain.'), route('sell.domains'), null],
                        ['banknotes', __('Earnings & payouts'), __('Balance, vesting and withdrawals.'), route('sell.earnings'), null],
                    ],
                ];
            @endphp

            <div class="space-y-5">
                @foreach ($workspace as $group => $items)
                    <div>
                        <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ $group }}</h2>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($items as [$icon, $title, $desc, $url, $badge])
                                <a href="{{ $url }}" class="pp-card group flex items-start gap-3 p-4 transition hover:border-brand-200 hover:shadow-md">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600 transition group-hover:bg-brand-100">
                                        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="flex items-center gap-2 text-sm font-semibold text-neutral-900">
                                            {{ $title }}
                                            @if (! is_null($badge))
                                                <span class="tabular rounded-full bg-brand-50 px-1.5 py-0.5 text-[10px] font-semibold text-brand-700">{{ number_format($badge) }}</span>
                                            @endif
                                        </p>
                                        <p class="mt-0.5 text-xs text-neutral-500">{{ $desc }}</p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
