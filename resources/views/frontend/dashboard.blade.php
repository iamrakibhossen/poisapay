<x-layouts.app :title="__('Dashboard')">
    @php
        $hour = (int) now()->format('G');
        $greeting = __('Good').' '.($hour < 12 ? __('morning') : ($hour < 18 ? __('afternoon') : __('evening')));

        $activityWrap = fn (?string $color) => [
            'success' => 'bg-emerald-50 text-emerald-600',
            'warning' => 'bg-amber-50 text-amber-600',
            'info' => 'bg-sky-50 text-sky-600',
        ][$color] ?? 'bg-neutral-100 text-neutral-500';

        $activityPath = fn (?string $icon) => [
            'arrow-down-left' => 'M7 7l10 10M17 7v10H7',
            'arrow-up-right' => 'M17 17L7 7M7 17V7h10',
            'paper-airplane' => 'M6 12L3.27 3.27a.5.5 0 0 1 .68-.62l16.5 8.25a.5.5 0 0 1 0 .9L4.05 20.35a.5.5 0 0 1-.68-.62L6 12Zm0 0h7',
        ][$icon] ?? '';

        $pending = $pendingDeposits + $pendingWithdrawals;
    @endphp

    <div class="space-y-6" data-live-root data-live-url="{{ route('dashboard.live') }}">
        {{-- Greeting --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-base font-semibold tracking-tight text-neutral-900 sm:text-lg">
                {{ $greeting }}, {{ $firstName }}
            </h1>
            <p class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500">
                <x-heroicon-o-calendar-days class="h-4 w-4 text-neutral-400" />
                {{ now()->format('l, j M Y') }}
            </p>
        </div>

        {{-- KYC nudge --}}
        @if ($needsKyc)
            <div class="flex flex-col items-start justify-between gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4 sm:flex-row sm:items-center">
                <div class="flex items-start gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-500 text-white">
                        <x-heroicon-o-identification class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-neutral-900">{{ __('Finish verifying your account') }}</p>
                        <p class="text-xs text-neutral-600">{{ __('Unlock higher withdrawal limits, cards and more.') }}</p>
                    </div>
                </div>
                <x-ui.button href="{{ route('settings.index', ['tab' => 'verification']) }}" size="sm" icon="arrow-right">{{ __('Verify now') }}</x-ui.button>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Balance hero (wallet-style) --}}
            <div x-data="{ hidden: $persist(false).as('pp_hide_balance') }"
                class="pp-card relative overflow-hidden border-brand-100 bg-gradient-to-br from-white to-brand-50 p-5 lg:col-span-2">
                <div class="absolute -right-8 -top-10 h-40 w-40 rounded-full bg-brand-300/20 blur-3xl"></div>
                <div class="absolute -bottom-12 -left-6 h-32 w-32 rounded-full bg-brand-200/25 blur-2xl"></div>

                <div class="relative">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-xs font-medium uppercase tracking-wide text-brand-700">{{ __('Total balance') }} · {{ $baseCurrency }}</p>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-600" title="{{ __('Values update live') }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 motion-safe:animate-pulse"></span> {{ __('Live') }}
                                </span>
                            </div>
                            <div class="mt-1">
                                <p class="tabular text-3xl font-bold tracking-tight text-neutral-900" x-show="!hidden"><span class="js-pv">{{ $portfolioValue }}</span></p>
                                <p class="text-3xl font-bold tracking-tight text-neutral-900" x-show="hidden" x-cloak>••••••</p>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-neutral-500">
                                <span>{{ $assetCount }} {{ $assetCount === 1 ? __('asset') : __('assets') }}</span>
                                @if ($hasLocked)
                                    <span class="inline-flex items-center gap-1">
                                        <x-heroicon-o-lock-closed class="h-3.5 w-3.5" />
                                        <span x-show="!hidden" class="js-lv">{{ $lockedValue }}</span><span x-show="hidden" x-cloak>••••</span> {{ __('locked') }}
                                    </span>
                                @endif
                                @if ($pending > 0)
                                    <span class="inline-flex items-center gap-1">
                                        <x-heroicon-o-clock class="h-3.5 w-3.5" />
                                        {{ $pending }} {{ __('pending') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <button type="button" x-on:click="hidden = !hidden" class="rounded-lg p-1.5 text-neutral-400 transition hover:bg-white/70 hover:text-neutral-700" title="{{ __('Hide balance') }}" aria-label="{{ __('Toggle balance visibility') }}">
                            <x-heroicon-o-eye x-show="!hidden" class="h-5 w-5" />
                            <x-heroicon-o-eye-slash x-show="hidden" x-cloak class="h-5 w-5" />
                        </button>
                    </div>

                    {{-- Quick actions --}}
                    @php
                        $p2pOn = feature('p2p_enabled', false);
                        $actions = [
                            ['route' => route('deposit.index'), 'label' => __('Deposit'), 'icon' => 'arrow-down-tray'],
                            ['route' => route('withdraw.index'), 'label' => __('Withdraw'), 'icon' => 'arrow-up-tray'],
                            ['route' => route('send.index'), 'label' => __('Send'), 'icon' => 'paper-airplane'],
                            ['route' => route('exchange.index'), 'label' => __('Swap'), 'icon' => 'arrows-right-left'],
                            ['route' => route('cards'), 'label' => __('Cards'), 'icon' => 'credit-card'],
                        ];
                        if ($p2pOn) {
                            $actions[] = ['route' => route('p2p'), 'label' => __('P2P'), 'icon' => 'user-group'];
                        }
                    @endphp
                    <div @class([
                        'mt-5 grid grid-cols-3 gap-2 sm:max-w-xl',
                        'sm:grid-cols-6' => $p2pOn,
                        'sm:grid-cols-5' => ! $p2pOn,
                    ])>
                        @foreach ($actions as $a)
                            <a href="{{ $a['route'] }}" class="group flex flex-col items-center gap-1.5">
                                <span class="grid h-11 w-11 place-items-center rounded-full bg-white text-brand-600 shadow-sm ring-1 ring-brand-100 transition group-hover:bg-brand-500 group-hover:text-white group-hover:ring-brand-500">
                                    <x-dynamic-component :component="'heroicon-o-'.$a['icon']" class="h-5 w-5" />
                                </span>
                                <span class="text-[11px] font-medium text-neutral-600 group-hover:text-neutral-900">{{ $a['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Your card (compact, no title) --}}
            @if ($card)
                @php
                    $statusDot = ['success' => 'bg-emerald-500', 'warning' => 'bg-amber-500', 'danger' => 'bg-rose-500', 'gray' => 'bg-gray-400'][$card->status->color()] ?? 'bg-gray-400';
                    $expiry = $card->exp_month ? sprintf('%02d/%02d', $card->exp_month, $card->exp_year % 100) : '••/••';
                @endphp
                <a href="{{ route('cards') }}" class="block self-start">
                    <div class="relative aspect-[1.586/1] overflow-hidden rounded-2xl bg-gradient-to-br from-brand-500 via-brand-600 to-brand-800 p-5 text-white shadow-[var(--shadow-card)] transition hover:-translate-y-0.5">
                        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 80% 10%, black 1px, transparent 1px); background-size: 28px 28px;"></div>
                        <div class="relative flex h-full flex-col justify-between">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M14.615 1.595a.75.75 0 0 1 .359.852L12.982 9.75h7.268a.75.75 0 0 1 .548 1.262l-10.5 11.25a.75.75 0 0 1-1.272-.71l1.992-7.302H3.75a.75.75 0 0 1-.548-1.262l10.5-11.25a.75.75 0 0 1 .913-.143Z"/></svg>
                                    <span class="text-sm font-semibold">PoisaPay</span>
                                </div>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/85 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-ink-900 shadow-sm">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}"></span>{{ $card->status->label() }}
                                </span>
                            </div>
                            <div class="h-7 w-9 rounded-md bg-gradient-to-br from-yellow-100 to-amber-300 shadow-inner ring-1 ring-ink-900/10"></div>
                            <div>
                                <p class="tabular font-mono text-lg tracking-widest">•••• •••• •••• {{ $card->last4 }}</p>
                                <div class="mt-3 flex items-end justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-[9px] uppercase tracking-wider text-white/50">{{ __('Card holder') }}</p>
                                        <p class="truncate text-xs font-medium uppercase tracking-wide text-white/90">{{ $card->nickname ?: $holderName }}</p>
                                    </div>
                                    <div class="shrink-0 text-center">
                                        <p class="text-[9px] uppercase tracking-wider text-white/50">{{ __('Valid thru') }}</p>
                                        <p class="tabular text-xs font-medium text-white/90">{{ $expiry }}</p>
                                    </div>
                                    <span class="shrink-0 text-lg font-bold italic">{{ $card->network->label() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            @else
                {{-- No card yet — highlighted promo to draw attention --}}
                <a href="{{ route('cards') }}" class="group relative flex aspect-[1.586/1] flex-col items-center justify-center gap-2 self-start overflow-hidden rounded-2xl bg-gradient-to-br from-brand-500 via-brand-600 to-brand-800 p-5 text-center text-white shadow-[var(--shadow-card)] transition hover:-translate-y-0.5">
                    <div class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-white/10"></div>
                    <div class="pointer-events-none absolute -bottom-10 -left-6 h-24 w-24 rounded-full bg-white/10"></div>
                    <span class="relative grid h-12 w-12 place-items-center rounded-full bg-white/15">
                        <x-heroicon-o-credit-card class="h-6 w-6" />
                    </span>
                    <p class="relative text-sm font-semibold">{{ __('Get a virtual card') }}</p>
                    <p class="relative text-xs text-white/80">{{ __('Spend your balance anywhere.') }}</p>
                    <span class="relative mt-1 inline-flex items-center gap-1 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold">
                        {{ __('Create now') }} <x-heroicon-o-arrow-right class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
                    </span>
                </a>
            @endif
        </div>

        {{-- 30-day money flow --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat-card :label="__('Inflow · 30d')" :value="$analytics['inflow']" icon="arrow-down-left" accent="emerald" />
            <x-ui.stat-card :label="__('Outflow · 30d')" :value="$analytics['outflow']" icon="arrow-up-right" accent="rose" />
            <x-ui.stat-card :label="__('Net · 30d')" :value="$analytics['net']" icon="chart-bar" :accent="$analytics['netPositive'] ? 'emerald' : 'amber'" />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Assets --}}
            <div class="lg:col-span-2">
                <x-ui.card :title="__('Your assets')">
                    <x-slot:actions>
                        <x-ui.button href="{{ route('wallet') }}" variant="ghost" size="sm" iconRight="arrow-right">{{ __('All') }}</x-ui.button>
                    </x-slot:actions>

                    @if (count($funded))
                        <div>
                            @foreach ($funded as $i => $row)
                                <a href="{{ route('wallet.show', $row['symbol']) }}"
                                    @class(['-mx-2 flex items-center gap-4 rounded-xl px-2 py-2.5 transition hover:bg-neutral-50', 'border-b border-neutral-100' => $i < count($funded) - 1])>
                                    <span class="inline-grid h-11 w-11 shrink-0 place-items-center rounded-full text-sm font-bold text-white"
                                        style="background: {{ $row['color'] }}">{{ \Illuminate\Support\Str::substr($row['symbol'], 0, 4) }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="truncate text-sm font-semibold text-neutral-900">{{ $row['symbol'] }}</p>
                                            <p class="tabular text-sm font-semibold text-neutral-900">{{ $row['available'] }}</p>
                                        </div>
                                        <div class="mt-1 flex items-center justify-between gap-2">
                                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-neutral-100">
                                                <div class="js-asset-bar h-full rounded-full transition-[width] duration-500 ease-out" data-sym="{{ $row['symbol'] }}" style="width: {{ min(100, $row['share']) }}%; background: {{ $row['color'] }}"></div>
                                            </div>
                                            <p class="js-asset-fiat tabular text-xs text-neutral-500" data-sym="{{ $row['symbol'] }}">{{ $row['fiat'] }}</p>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <x-ui.empty-state icon="wallet" :title="__('No funds yet')"
                            :description="__('Deposit crypto or top up your Taka balance to get started.')">
                            <x-slot:action>
                                <x-ui.button href="{{ route('deposit.index') }}" icon="arrow-down-tray">{{ __('Make a deposit') }}</x-ui.button>
                            </x-slot:action>
                        </x-ui.empty-state>
                    @endif
                </x-ui.card>
            </div>

            {{-- Recent activity --}}
            <div>
                <x-ui.card :title="__('Recent activity')">
                    @if (count($recent))
                        <div>
                            @foreach ($recent as $i => $item)
                                <div @class(['flex items-center gap-3 py-2.5', 'border-b border-neutral-100' => $i < count($recent) - 1])>
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full {{ $activityWrap($item['color']) }}">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $activityPath($item['icon']) }}" />
                                        </svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-neutral-900">{{ $item['title'] }}</p>
                                        <p class="text-xs text-neutral-500">{{ $item['at_human'] }} · {{ $item['status'] }}</p>
                                    </div>
                                    <p class="tabular text-sm font-semibold {{ str_starts_with($item['amount'], '+') ? 'text-emerald-600' : 'text-neutral-700' }}">{{ $item['amount'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <x-ui.empty-state icon="clock" :title="__('No activity yet')" :description="__('Your transactions will appear here.')" />
                    @endif
                </x-ui.card>
            </div>
        </div>
    </div>

    {{-- Live currency values: poll the dashboard.live JSON feed and refresh the
         portfolio total, per-asset fiat values and allocation shares in place.
         Mirrors the homepage converter's live-rate refresh. --}}
    <style>
        @keyframes ppFlash { 0% { background-color: rgba(37,99,235,.14); } 100% { background-color: transparent; } }
        .pp-flash { animation: ppFlash .8s ease-out; border-radius: 4px; }
        @media (prefers-reduced-motion: reduce) { .pp-flash { animation: none; } }
    </style>

    @push('scripts')
    <script>
    (function () {
        var root = document.querySelector('[data-live-root]');
        if (!root || !window.fetch) return;
        var url = root.getAttribute('data-live-url');

        function flash(el) { el.classList.remove('pp-flash'); void el.offsetWidth; el.classList.add('pp-flash'); }

        function setText(el, val) {
            if (el && val != null && el.textContent.trim() !== String(val)) { el.textContent = val; flash(el); }
        }

        function apply(data) {
            if (!data) return;
            setText(root.querySelector('.js-pv'), data.portfolioValue);
            var lv = root.querySelector('.js-lv');
            if (lv && data.lockedValue != null) lv.textContent = data.lockedValue;

            (data.assets || []).forEach(function (a) {
                var sel = '[data-sym="' + a.symbol + '"]';
                setText(root.querySelector('.js-asset-fiat' + sel), a.fiat);
                var bar = root.querySelector('.js-asset-bar' + sel);
                if (bar) bar.style.width = Math.min(100, a.share) + '%';
                var sh = root.querySelector('.js-alloc-share' + sel);
                if (sh) sh.textContent = a.share + '%';
            });
        }

        function refresh() {
            if (document.hidden) return;
            fetch(url, { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(apply)
                .catch(function () {});
        }

        setInterval(refresh, 60000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
    })();
    </script>
    @endpush
</x-layouts.app>
