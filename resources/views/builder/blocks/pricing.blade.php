@php $tiers = $props['items'] ?? []; @endphp
@if (! empty($tiers))
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
        <div class="mx-auto max-w-5xl px-5">
            @if (! empty($props['heading']))<h2 class="text-center text-2xl font-bold tracking-tight sm:text-3xl">{{ $props['heading'] }}</h2>@endif
            @if (! empty($props['sub']))<p class="mx-auto mt-2 max-w-lg text-center text-sm text-neutral-500">{{ $props['sub'] }}</p>@endif
            <div class="mt-9 grid gap-5 md:grid-cols-3">
                @foreach ($tiers as $t)
                    @php
                        $t = is_array($t) ? $t : ['name' => $t];
                        $featured = ! empty($t['featured']) && $t['featured'] !== 'false';
                        $feats = array_filter(array_map('trim', explode("\n", (string) ($t['features'] ?? ''))));
                    @endphp
                    <div class="relative flex flex-col rounded-3xl border bg-white p-6 {{ $featured ? 'border-transparent shadow-xl ring-2' : 'border-neutral-200 shadow-sm' }}" @if ($featured) style="--tw-ring-color: var(--pp-accent)" @endif>
                        @if ($featured)<span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full px-3 py-1 text-[11px] font-bold text-white" style="background: var(--pp-accent)">{{ $t['badge'] ?? __('Most popular') }}</span>@endif
                        <p class="text-sm font-semibold text-neutral-800">{{ $t['name'] ?? '' }}</p>
                        <p class="mt-3 text-3xl font-extrabold tracking-tight text-neutral-900">{{ $t['price'] ?? '' }}<span class="text-sm font-medium text-neutral-400">{{ $t['period'] ?? '' }}</span></p>
                        @if (! empty($t['desc']))<p class="mt-2 text-sm text-neutral-500">{{ $t['desc'] }}</p>@endif
                        @if ($feats)
                            <ul class="mt-5 space-y-2.5 text-sm text-neutral-600">
                                @foreach ($feats as $f)<li class="flex items-start gap-2"><x-heroicon-s-check class="mt-0.5 h-4 w-4 shrink-0" style="color: var(--pp-accent)" /><span>{{ $f }}</span></li>@endforeach
                            </ul>
                        @endif
                        <div class="mt-auto pt-6">
                            @include('builder.blocks._buy', ['label' => $t['cta'] ?? __('Choose plan'), 'class' => 'block w-full py-3 text-center text-sm font-semibold text-white transition hover:opacity-95', 'style' => $featured ? 'background: var(--pp-accent); border-radius: var(--pp-btn-radius)' : 'background:#0f172a; border-radius: var(--pp-btn-radius)'])
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
