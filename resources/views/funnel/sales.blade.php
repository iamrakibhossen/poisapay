<x-layouts.sales :title="$product['name']">
    @php
        $accent = $theme['accent'];
        $radius = $theme['btn'] === 'pill' ? '9999px' : ($theme['btn'] === 'square' ? '2px' : '12px');
        $byType = collect($sections)->keyBy('type');
        $hero = $byType->get('hero')['content'] ?? [];
        $btnLabel = $hero['btn'] ?? __('Buy now');
    @endphp

    <div style="--accent: {{ $accent }}; font-family: {{ $theme['font'] }}, ui-sans-serif, system-ui;" class="min-h-screen bg-white text-neutral-900">
        {{-- The single real checkout form; buy buttons anywhere reference it. --}}
        <form id="buy" method="POST" action="{{ route('funnel.checkout', ['slug' => $slug]) }}" class="hidden">@csrf</form>

        {{-- Top bar --}}
        <header class="sticky top-0 z-20 border-b border-neutral-100 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-5 py-3">
                <span class="text-sm font-bold">{{ $seller['name'] }}</span>
                <button type="submit" form="buy" class="px-4 py-2 text-xs font-semibold text-white" style="background: var(--accent); border-radius: {{ $radius }}">
                    {{ $btnLabel }} · {{ $product['price'] }}
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-5">
            {{-- Hero --}}
            <section class="py-12 text-center sm:py-16">
                <h1 class="mx-auto max-w-2xl text-3xl font-bold leading-tight sm:text-4xl">{{ $hero['headline'] ?? $product['name'] }}</h1>
                @if (! empty($hero['tagline']) || $product['summary'])
                    <p class="mt-3 text-base font-medium" style="color: var(--accent)">{{ $hero['tagline'] ?? $product['summary'] }}</p>
                @endif
                @if (! empty($hero['desc']) || $product['description'])
                    <p class="mx-auto mt-4 max-w-xl text-sm text-neutral-600">{{ $hero['desc'] ?? $product['description'] }}</p>
                @endif
                <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                    <button type="submit" form="buy" class="px-7 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90" style="background: var(--accent); border-radius: {{ $radius }}">
                        {{ $btnLabel }}
                    </button>
                    <span class="text-lg font-bold">
                        {{ $product['price'] }}
                        @if ($product['comparePrice'])
                            <span class="ms-1 text-sm font-medium text-neutral-400 line-through">{{ $product['comparePrice'] }}</span>
                        @endif
                    </span>
                </div>
            </section>

            {{-- Builder sections (in the seller's chosen order) --}}
            @foreach ($sections as $section)
                @php $c = $section['content'] ?? []; @endphp
                @switch($section['type'])
                    @case('features')
                        @if (! empty($c))
                            <section class="border-t border-neutral-100 py-10">
                                <h2 class="text-center text-xl font-bold">{{ __('Everything you get') }}</h2>
                                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                                    @foreach ($c as $f)
                                        <div class="flex items-start gap-3 rounded-xl border border-neutral-200 p-4">
                                            <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-lg text-white" style="background: var(--accent)">★</span>
                                            <p class="text-sm font-medium">{{ is_array($f) ? ($f['title'] ?? '') : $f }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                        @break

                    @case('benefits')
                        @if (! empty($c))
                            <section class="border-t border-neutral-100 py-10">
                                <h2 class="text-xl font-bold">{{ __('Why buy this') }}</h2>
                                <ul class="mt-5 space-y-3">
                                    @foreach ($c as $b)
                                        <li class="flex items-center gap-3 text-sm text-neutral-700">
                                            <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full text-[10px] text-white" style="background: var(--accent)">✓</span>
                                            {{ is_array($b) ? implode(' ', $b) : $b }}
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif
                        @break

                    @case('testimonials')
                        @if (! empty($c))
                            <section class="border-t border-neutral-100 py-10">
                                <h2 class="text-center text-xl font-bold">{{ __('Loved by buyers') }}</h2>
                                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                    @foreach ($c as $t)
                                        <figure class="rounded-xl border border-neutral-200 p-4">
                                            <div class="text-xs text-amber-500">★★★★★</div>
                                            <blockquote class="mt-2 text-sm text-neutral-700">“{{ is_array($t) ? ($t['quote'] ?? '') : $t }}”</blockquote>
                                            @if (is_array($t) && ! empty($t['name']))
                                                <figcaption class="mt-2 text-xs text-neutral-400">{{ $t['name'] }}</figcaption>
                                            @endif
                                        </figure>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                        @break

                    @case('faq')
                        @if (! empty($c))
                            <section class="border-t border-neutral-100 py-10">
                                <h2 class="text-xl font-bold">{{ __('Questions & answers') }}</h2>
                                <div class="mt-4 divide-y divide-neutral-100 rounded-xl border border-neutral-200">
                                    @foreach ($c as $q)
                                        <div class="px-4 py-3 text-sm font-medium text-neutral-800">{{ is_array($q) ? ($q['q'] ?? '') : $q }}</div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                        @break

                    @case('guarantee')
                        @if (! empty($c))
                            <section class="py-8">
                                <div class="flex items-center gap-3 rounded-xl border p-4" style="border-color: var(--accent); background: {{ $accent }}10">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-white" style="background: var(--accent)">✓</span>
                                    <p class="text-sm font-medium">{{ is_array($c) ? implode(' ', $c) : $c }}</p>
                                </div>
                            </section>
                        @endif
                        @break

                    @case('countdown')
                        @if (! empty($c))
                            <section class="py-8 text-center">
                                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ is_array($c) ? implode(' ', $c) : $c }}</p>
                                <div class="mt-2 flex justify-center gap-2">
                                    @foreach (['02', '11', '45'] as $u)
                                        <span class="rounded-lg px-3 py-2 text-lg font-bold text-white" style="background: var(--accent)">{{ $u }}</span>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                        @break

                    @case('cta')
                        <section class="border-t border-neutral-100 py-12 text-center">
                            <h2 class="text-2xl font-bold">{{ $c['heading'] ?? __('Get it today') }}</h2>
                            <button type="submit" form="buy" class="mt-6 px-8 py-3.5 text-sm font-semibold text-white transition hover:opacity-90" style="background: var(--accent); border-radius: {{ $radius }}">
                                {{ $c['btn'] ?? ($btnLabel.' · '.$product['price']) }}
                            </button>
                            <p class="mt-3 text-[11px] text-neutral-400">{{ __('Secure checkout with your PoisaPay wallet') }}</p>
                        </section>
                        @break
                @endswitch
            @endforeach
        </main>

        <footer class="border-t border-neutral-100 py-6 text-center text-[11px] text-neutral-400">
            {{ __('Powered by PoisaHub') }}
        </footer>
    </div>
</x-layouts.sales>
