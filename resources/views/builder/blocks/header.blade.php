@php
    // Homepage-style header: brand + menu + CTA, ~64px like the marketing site nav.
    $brand = $props['brand'] ?: ($ctx->seller['name'] ?? __('Your store'));
    $height = max(44, (int) ($props['height'] ?? 64));
    $sticky = (bool) ($props['sticky'] ?? true);
    $links = $props['links'] ?? [];
    $logo = $ctx->seller['logo'] ?? null;
    $initials = $ctx->seller['initials'] ?? mb_substr($brand, 0, 1);
@endphp
<header id="{{ $node->id }}" class="{{ $sticky ? 'sticky top-0' : '' }} z-30 border-b border-neutral-100 bg-white/85 backdrop-blur-md">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5" style="min-height: {{ $height }}px">
        {{-- Brand --}}
        <a href="{{ $ctx->editing ? '#' : '#' }}" class="flex items-center gap-2.5">
            @if ($props['showLogo'] ?? true)
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $brand }}" class="h-8 w-8 rounded-lg object-cover" />
                @else
                    <span class="grid h-8 w-8 place-items-center rounded-lg text-sm font-bold text-white" style="background: var(--pp-accent)">{{ mb_strtoupper($initials) }}</span>
                @endif
            @endif
            <span class="text-base font-bold tracking-tight text-neutral-900">{{ $brand }}</span>
        </a>

        {{-- Menu --}}
        @if (! empty($links))
            <nav class="hidden items-center gap-7 text-sm font-medium text-neutral-600 md:flex">
                @foreach ($links as $l)
                    @php $label = is_array($l) ? ($l['label'] ?? '') : $l; $href = is_array($l) ? ($l['href'] ?? '#') : '#'; @endphp
                    @if ($label !== '')
                        <a href="{{ $ctx->editing ? '#' : $href }}" class="transition hover:text-neutral-900">{{ $label }}</a>
                    @endif
                @endforeach
            </nav>
        @endif

        {{-- CTA --}}
        <div class="shrink-0">
            @include('builder.blocks._buy', ['label' => $props['cta'] ?? __('Buy now'), 'class' => 'px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-90'])
        </div>
    </div>
</header>
