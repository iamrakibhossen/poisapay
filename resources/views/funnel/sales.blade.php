<x-layouts.sales :title="$product['name']">
    @php
        $accent = $theme['accent'];
        $radius = $theme['btn'] === 'pill' ? '9999px' : ($theme['btn'] === 'square' ? '2px' : '12px');
        $byType = collect($sections)->keyBy('type');
        $hero = $byType->get('hero')['content'] ?? [];
        $btnLabel = $hero['btn'] ?? __('Buy now');
        // Reusable button style — accent background + chosen corner radius.
        $btnStyle = "background: var(--accent); border-radius: {$radius}";
    @endphp

    <div style="--accent: {{ $accent }}; font-family: {{ $theme['font'] }}, ui-sans-serif, system-ui;"
        class="min-h-screen bg-white pb-20 text-neutral-900 lg:pb-0">
        {{-- The single real checkout form; buy buttons anywhere reference it. --}}
        <form id="buy" method="POST" action="{{ route('funnel.checkout', ['slug' => $slug]) }}" class="hidden">@csrf</form>

        {{-- Top bar --}}
        <header class="sticky top-0 z-30 border-b border-neutral-100 bg-white/85 backdrop-blur-md">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-5 py-3">
                <span class="flex items-center gap-2 text-sm font-bold">
                    @if (! empty($seller['logo']))
                        <img src="{{ $seller['logo'] }}" alt="{{ $seller['name'] }}" class="h-7 w-7 rounded-lg object-cover" />
                    @else
                        <span class="grid h-7 w-7 place-items-center rounded-lg text-[11px] font-bold text-white" style="background: var(--accent)">{{ mb_strtoupper(mb_substr($seller['name'], 0, 1)) }}</span>
                    @endif
                    {{ $seller['name'] }}
                </span>
                <button type="submit" form="buy" class="px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-90" style="{{ $btnStyle }}">
                    {{ $btnLabel }} · {{ $product['price'] }}
                </button>
            </div>
        </header>

        <main>
            {{-- ============ Hero ============ --}}
            <section class="relative overflow-hidden">
                <div class="pointer-events-none absolute inset-x-0 top-0 h-72" style="background: radial-gradient(60% 100% at 50% 0%, {{ $accent }}18, transparent 70%)"></div>
                <div class="relative mx-auto max-w-3xl px-5 py-14 text-center sm:py-20">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 bg-white/70 px-3 py-1 text-[11px] font-medium text-neutral-600 shadow-sm">
                        <span class="text-amber-500">★★★★★</span> 4.9 · {{ __('loved by 200+ buyers') }}
                    </span>
                    <h1 class="mx-auto mt-5 max-w-2xl text-3xl font-extrabold leading-[1.1] tracking-tight sm:text-5xl">{{ $hero['headline'] ?? $product['name'] }}</h1>
                    @if (! empty($hero['tagline']) || $product['summary'])
                        <p class="mx-auto mt-4 max-w-xl text-lg font-medium" style="color: var(--accent)">{{ $hero['tagline'] ?? $product['summary'] }}</p>
                    @endif
                    @if (! empty($hero['desc']) || $product['description'])
                        <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-neutral-600">{{ $hero['desc'] ?? $product['description'] }}</p>
                    @endif

                    <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <button type="submit" form="buy" class="w-full px-8 py-4 text-sm font-semibold text-white shadow-lg shadow-black/5 transition hover:-translate-y-0.5 hover:opacity-95 sm:w-auto" style="{{ $btnStyle }}">
                            {{ $btnLabel }}
                        </button>
                        <span class="text-xl font-bold">
                            {{ $product['price'] }}
                            @if ($product['comparePrice'])
                                <span class="ms-1 text-base font-medium text-neutral-400 line-through">{{ $product['comparePrice'] }}</span>
                            @endif
                        </span>
                    </div>

                    {{-- Trust chips --}}
                    <div class="mt-7 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-neutral-500">
                        <span class="inline-flex items-center gap-1.5"><x-heroicon-o-bolt class="h-4 w-4" style="color: var(--accent)" /> {{ __('Instant access') }}</span>
                        <span class="inline-flex items-center gap-1.5"><x-heroicon-o-lock-closed class="h-4 w-4" style="color: var(--accent)" /> {{ __('Secure PoisaPay checkout') }}</span>
                        <span class="inline-flex items-center gap-1.5"><x-heroicon-o-shield-check class="h-4 w-4" style="color: var(--accent)" /> {{ __('14-day money-back') }}</span>
                    </div>
                </div>
            </section>

            {{-- Variation picker (variant products) — selects post with the #buy form. --}}
            @if (! empty($variantOptions))
                <section class="border-t border-neutral-100 py-6">
                    <div class="mx-auto max-w-md px-5">
                        @error('buy')
                            <p class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-center text-xs font-medium text-rose-700">{{ $message }}</p>
                        @enderror
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($variantOptions as $name => $values)
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">{{ $name }}</label>
                                    <select form="buy" name="options[{{ $name }}]" required
                                        class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-neutral-400 focus:ring-1 focus:ring-neutral-300">
                                        @foreach ($values as $v)
                                            <option value="{{ $v }}" @selected(old('options.'.$name) === $v)>{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            {{-- ============ Builder sections (in the seller's chosen order) ============ --}}
            @foreach ($sections as $section)
                @php $c = $section['content'] ?? []; @endphp
                @switch($section['type'])
                    @case('product')
                        <section class="border-t border-neutral-100 py-12">
                            <div class="mx-auto max-w-md px-5">
                                <div class="overflow-hidden rounded-2xl border border-neutral-200 shadow-sm ring-1 ring-black/[0.02]">
                                    <div class="flex items-center gap-3 border-b border-neutral-100 px-5 py-4">
                                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl text-white" style="background: var(--accent)"><x-heroicon-o-cube class="h-6 w-6" /></span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold">{{ $c['name'] ?? $product['name'] }}</p>
                                            <p class="text-[11px] uppercase tracking-wide text-neutral-400">{{ $c['type'] ?? '' }}</p>
                                        </div>
                                    </div>
                                    <div class="px-5 py-4">
                                        <p class="text-sm text-neutral-600">{{ $c['summary'] ?? $product['summary'] }}</p>
                                        <div class="mt-4 flex items-baseline gap-2">
                                            <span class="text-2xl font-bold">{{ $c['price'] ?? $product['price'] }}</span>
                                            @if (! empty($c['compare']) || $product['comparePrice'])
                                                <span class="text-sm text-neutral-400 line-through">{{ $c['compare'] ?? $product['comparePrice'] }}</span>
                                            @endif
                                        </div>
                                        <button type="submit" form="buy" class="mt-4 w-full py-3 text-sm font-semibold text-white transition hover:opacity-90" style="{{ $btnStyle }}">
                                            {{ $c['btn'] ?? $btnLabel }}
                                        </button>
                                        @if (! empty($c['note']))
                                            <p class="mt-2 text-center text-[11px] text-neutral-400">{{ $c['note'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </section>
                        @break

                    @case('stats')
                        @if (! empty($c))
                            <section class="border-t border-neutral-100 py-10">
                                <div class="mx-auto grid max-w-4xl grid-cols-2 gap-6 px-5 sm:grid-cols-4">
                                    @foreach ($c as $s)
                                        <div class="text-center">
                                            <p class="text-2xl font-extrabold sm:text-3xl" style="color: var(--accent)">{{ is_array($s) ? ($s['value'] ?? '') : $s }}</p>
                                            <p class="mt-0.5 text-[11px] uppercase tracking-wide text-neutral-400">{{ is_array($s) ? ($s['label'] ?? '') : '' }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                        @break

                    @case('logos')
                        @if (! empty($c))
                            <section class="border-t border-neutral-100 bg-neutral-50/60 py-8">
                                <p class="text-center text-[11px] font-semibold uppercase tracking-wider text-neutral-400">{{ __('Trusted by teams at') }}</p>
                                <div class="mx-auto mt-4 flex max-w-4xl flex-wrap items-center justify-center gap-x-10 gap-y-3 px-5">
                                    @foreach ($c as $l)
                                        <span class="text-base font-bold text-neutral-400">{{ is_array($l) ? implode(' ', $l) : $l }}</span>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                        @break

                    @case('features')
                        @if (! empty($c))
                            <section class="border-t border-neutral-100 py-14">
                                <div class="mx-auto max-w-4xl px-5">
                                    <p class="text-center text-xs font-semibold uppercase tracking-wider" style="color: var(--accent)">{{ __('Features') }}</p>
                                    <h2 class="mt-2 text-center text-2xl font-bold tracking-tight">{{ __('Everything you get') }}</h2>
                                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                                        @foreach ($c as $f)
                                            <div class="flex items-start gap-3 rounded-2xl border border-neutral-200 bg-white p-5 transition hover:shadow-md">
                                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl text-white" style="background: var(--accent)"><x-heroicon-s-sparkles class="h-4 w-4" /></span>
                                                <p class="pt-1 text-sm font-semibold text-neutral-800">{{ is_array($f) ? ($f['title'] ?? '') : $f }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </section>
                        @endif
                        @break

                    {{-- ============ Benefits (redesigned) ============ --}}
                    @case('benefits')
                        @if (! empty($c))
                            <section class="border-t border-neutral-100 py-14" style="background: {{ $accent }}08">
                                <div class="mx-auto max-w-4xl px-5">
                                    <p class="text-center text-xs font-semibold uppercase tracking-wider" style="color: var(--accent)">{{ __('Benefits') }}</p>
                                    <h2 class="mt-2 text-center text-2xl font-bold tracking-tight sm:text-3xl">{{ __('Why you’ll love it') }}</h2>
                                    <p class="mx-auto mt-2 max-w-lg text-center text-sm text-neutral-500">{{ __('The outcomes you get from day one.') }}</p>
                                    <div class="mt-9 grid gap-4 sm:grid-cols-2">
                                        @foreach ($c as $b)
                                            @php
                                                $bTitle = is_array($b) ? ($b['title'] ?? '') : $b;
                                                $bDesc = is_array($b) ? ($b['desc'] ?? '') : '';
                                            @endphp
                                            <div class="group flex items-start gap-4 rounded-2xl border border-white bg-white/80 p-5 shadow-sm ring-1 ring-black/[0.03] backdrop-blur transition hover:-translate-y-0.5 hover:shadow-md">
                                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-white shadow-sm transition group-hover:scale-105" style="background: var(--accent)">
                                                    <x-heroicon-s-check class="h-5 w-5" />
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="text-sm font-semibold leading-snug text-neutral-900">{{ $bTitle }}</p>
                                                    @if ($bDesc)<p class="mt-1 text-sm leading-relaxed text-neutral-500">{{ $bDesc }}</p>@endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="mt-9 text-center">
                                        <button type="submit" form="buy" class="px-8 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:opacity-95" style="{{ $btnStyle }}">
                                            {{ $btnLabel }} · {{ $product['price'] }}
                                        </button>
                                    </div>
                                </div>
                            </section>
                        @endif
                        @break

                    @case('steps')
                        @if (! empty($c))
                            <section class="border-t border-neutral-100 py-14">
                                <div class="mx-auto max-w-4xl px-5">
                                    <p class="text-center text-xs font-semibold uppercase tracking-wider" style="color: var(--accent)">{{ __('How it works') }}</p>
                                    <h2 class="mt-2 text-center text-2xl font-bold tracking-tight">{{ __('Up and running in minutes') }}</h2>
                                    <div class="mt-10 grid gap-8 sm:grid-cols-3">
                                        @foreach ($c as $i => $s)
                                            <div class="relative text-center">
                                                <span class="mx-auto grid h-12 w-12 place-items-center rounded-full text-base font-bold text-white shadow-sm" style="background: var(--accent)">{{ $i + 1 }}</span>
                                                <p class="mt-4 text-base font-semibold text-neutral-900">{{ is_array($s) ? ($s['title'] ?? '') : $s }}</p>
                                                <p class="mt-1 text-sm text-neutral-500">{{ is_array($s) ? ($s['desc'] ?? '') : '' }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </section>
                        @endif
                        @break

                    @case('bonuses')
                        @if (! empty($c))
                            <section class="border-t border-neutral-100 bg-neutral-50/60 py-14">
                                <div class="mx-auto max-w-md px-5">
                                    <p class="text-center text-xs font-semibold uppercase tracking-wider" style="color: var(--accent)">{{ __('Included') }}</p>
                                    <h2 class="mt-2 text-center text-2xl font-bold tracking-tight">{{ __('Everything you get today') }}</h2>
                                    <div class="mt-6 divide-y divide-neutral-100 rounded-2xl border border-neutral-200 bg-white shadow-sm">
                                        @foreach ($c as $b)
                                            <div class="flex items-center justify-between px-5 py-4 text-sm">
                                                <span class="flex items-center gap-2.5 font-medium text-neutral-800"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full text-[10px] text-white" style="background: var(--accent)">✓</span>{{ is_array($b) ? ($b['title'] ?? '') : $b }}</span>
                                                @if (is_array($b) && ! empty($b['value']))<span class="font-semibold text-neutral-400 line-through">{{ $b['value'] }}</span>@endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </section>
                        @endif
                        @break

                    @case('video')
                        @if (! empty($c))
                            <section class="border-t border-neutral-100 py-14 text-center">
                                <div class="mx-auto max-w-2xl px-5">
                                    <h2 class="text-2xl font-bold tracking-tight">{{ $c['title'] ?? __('Watch the demo') }}</h2>
                                    @if (! empty($c['subtitle']))<p class="mt-1 text-sm text-neutral-500">{{ $c['subtitle'] }}</p>@endif
                                    <button type="button" class="group relative mt-6 grid aspect-video w-full place-items-center overflow-hidden rounded-2xl bg-neutral-900 shadow-lg">
                                        <div class="absolute inset-0 opacity-30" style="background: radial-gradient(60% 80% at 50% 50%, {{ $accent }}, transparent)"></div>
                                        <span class="relative grid h-16 w-16 place-items-center rounded-full bg-white/95 text-neutral-900 shadow-lg transition group-hover:scale-110"><x-heroicon-s-play class="h-7 w-7" /></span>
                                    </button>
                                </div>
                            </section>
                        @endif
                        @break

                    @case('testimonials')
                        @if (! empty($c))
                            <section class="border-t border-neutral-100 py-14">
                                <div class="mx-auto max-w-4xl px-5">
                                    <p class="text-center text-xs font-semibold uppercase tracking-wider" style="color: var(--accent)">{{ __('Reviews') }}</p>
                                    <h2 class="mt-2 text-center text-2xl font-bold tracking-tight">{{ __('Loved by buyers') }}</h2>
                                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                                        @foreach ($c as $t)
                                            <figure class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                                                <div class="text-sm text-amber-500">★★★★★</div>
                                                <blockquote class="mt-2 text-sm leading-relaxed text-neutral-700">“{{ is_array($t) ? ($t['quote'] ?? '') : $t }}”</blockquote>
                                                @if (is_array($t) && ! empty($t['name']))
                                                    <figcaption class="mt-3 flex items-center gap-2.5">
                                                        <span class="grid h-8 w-8 place-items-center rounded-full text-xs font-bold text-white" style="background: var(--accent)">{{ mb_strtoupper(mb_substr($t['name'], 0, 1)) }}</span>
                                                        <span class="text-xs text-neutral-500"><span class="font-semibold text-neutral-800">{{ $t['name'] }}</span>@if (! empty($t['role'])) · {{ $t['role'] }}@endif</span>
                                                    </figcaption>
                                                @endif
                                            </figure>
                                        @endforeach
                                    </div>
                                </div>
                            </section>
                        @endif
                        @break

                    @case('faq')
                        @if (! empty($c))
                            <section class="border-t border-neutral-100 py-14">
                                <div class="mx-auto max-w-2xl px-5" x-data="{ open: 0 }">
                                    <p class="text-center text-xs font-semibold uppercase tracking-wider" style="color: var(--accent)">{{ __('FAQ') }}</p>
                                    <h2 class="mt-2 text-center text-2xl font-bold tracking-tight">{{ __('Questions & answers') }}</h2>
                                    <div class="mt-8 space-y-3">
                                        @foreach ($c as $i => $q)
                                            <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white">
                                                <button type="button" x-on:click="open = (open === {{ $i }} ? null : {{ $i }})" class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left">
                                                    <span class="text-sm font-semibold text-neutral-800">{{ is_array($q) ? ($q['q'] ?? '') : $q }}</span>
                                                    <x-heroicon-o-chevron-down class="h-4 w-4 shrink-0 text-neutral-400 transition" x-bind:class="open === {{ $i }} && 'rotate-180'" />
                                                </button>
                                                @if (is_array($q) && ! empty($q['a']))
                                                    <div x-show="open === {{ $i }}" x-cloak
                                                        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                                        <p class="px-5 pb-4 text-sm leading-relaxed text-neutral-500">{{ $q['a'] }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </section>
                        @endif
                        @break

                    @case('guarantee')
                        @if (! empty($c))
                            <section class="py-10">
                                <div class="mx-auto max-w-2xl px-5">
                                    <div class="flex items-center gap-4 rounded-2xl border p-5" style="border-color: {{ $accent }}40; background: {{ $accent }}0d">
                                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full text-white shadow-sm" style="background: var(--accent)"><x-heroicon-s-shield-check class="h-6 w-6" /></span>
                                        <p class="text-sm font-medium text-neutral-700">{{ is_array($c) ? implode(' ', $c) : $c }}</p>
                                    </div>
                                </div>
                            </section>
                        @endif
                        @break

                    @case('countdown')
                        @if (! empty($c))
                            <section class="py-8 text-center">
                                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ is_array($c) ? implode(' ', $c) : $c }}</p>
                                <div class="mt-3 flex justify-center gap-2">
                                    @foreach (['02', '11', '45'] as $u)
                                        <span class="min-w-[3rem] rounded-xl px-3 py-2.5 text-xl font-bold text-white shadow-sm" style="background: var(--accent)">{{ $u }}</span>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                        @break

                    @case('cta')
                        <section class="border-t border-neutral-100 py-16 text-center">
                            <div class="mx-auto max-w-xl px-5">
                                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $c['heading'] ?? __('Get it today') }}</h2>
                                <button type="submit" form="buy" class="mt-7 px-9 py-4 text-sm font-semibold text-white shadow-lg shadow-black/5 transition hover:-translate-y-0.5 hover:opacity-95" style="{{ $btnStyle }}">
                                    {{ $c['btn'] ?? ($btnLabel.' · '.$product['price']) }}
                                </button>
                                <p class="mt-4 inline-flex items-center gap-1.5 text-[11px] text-neutral-400"><x-heroicon-o-lock-closed class="h-3.5 w-3.5" /> {{ __('Secure checkout with your PoisaPay wallet') }}</p>
                            </div>
                        </section>
                        @break
                @endswitch
            @endforeach
        </main>

        <footer class="border-t border-neutral-100 py-8 text-center">
            <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-[11px] text-neutral-400">
                <span class="inline-flex items-center gap-1"><x-heroicon-o-lock-closed class="h-3.5 w-3.5" /> {{ __('Secure checkout') }}</span>
                <span class="inline-flex items-center gap-1"><x-heroicon-o-shield-check class="h-3.5 w-3.5" /> {{ __('Buyer protection') }}</span>
                <span class="inline-flex items-center gap-1"><x-heroicon-o-arrow-uturn-left class="h-3.5 w-3.5" /> {{ __('14-day refund') }}</span>
            </div>
            <p class="mt-3 text-[11px] text-neutral-400">{{ __('Powered by PoisaHub') }}</p>
        </footer>

        {{-- Sticky mobile buy bar --}}
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-neutral-200 bg-white/95 px-4 py-3 backdrop-blur-md lg:hidden">
            <div class="flex items-center justify-between gap-3">
                <div class="leading-tight">
                    <p class="text-sm font-bold">{{ $product['price'] }}
                        @if ($product['comparePrice'])<span class="ms-1 text-xs font-medium text-neutral-400 line-through">{{ $product['comparePrice'] }}</span>@endif
                    </p>
                    <p class="text-[11px] text-neutral-400">{{ __('Secure checkout') }}</p>
                </div>
                <button type="submit" form="buy" class="flex-1 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-90" style="{{ $btnStyle }}">
                    {{ $btnLabel }}
                </button>
            </div>
        </div>
    </div>
</x-layouts.sales>
