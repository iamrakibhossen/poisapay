@php
    // A real page header: brand + menu + actions, with layout presets, a transparent
    // overlay mode, sticky behaviour and a mobile drawer. When the page has a header
    // block, the sales chrome header is suppressed so this one takes over.
    $brand = $props['brand'] ?: ($ctx->seller['name'] ?? __('Your store'));
    $height = max(44, (int) ($props['height'] ?? 64));
    $sticky = (bool) ($props['sticky'] ?? true);
    $transparent = (bool) ($props['transparent'] ?? false);
    $preset = in_array($props['preset'] ?? 'left', ['left', 'center', 'minimal'], true) ? $props['preset'] : 'left';
    $links = $preset === 'minimal' ? [] : ($props['links'] ?? []);
    $logo = $ctx->seller['logo'] ?? null;
    $initials = $ctx->seller['initials'] ?? mb_substr($brand, 0, 1);
    $cta = $props['cta'] ?? __('Buy now');
    $secondary = trim((string) ($props['secondaryLabel'] ?? ''));
    $secondaryHref = $props['secondaryHref'] ?? '#';

    $chrome = $transparent
        ? 'absolute inset-x-0 top-0 text-white'
        : ($sticky ? 'sticky top-0 ' : '').'border-b border-neutral-100 bg-white/85 backdrop-blur-md text-neutral-900';
    $linkColor = $transparent ? 'text-white/90 hover:text-white' : 'text-neutral-600 hover:text-neutral-900';
    $brandColor = $transparent ? 'text-white' : 'text-neutral-900';
@endphp
<header id="{{ $node->id }}" class="{{ $chrome }} z-30" x-data="{ open: false }">
    <div class="mx-auto flex max-w-6xl items-center {{ $preset === 'center' ? 'relative justify-center' : 'justify-between' }} gap-4 px-5" style="min-height: {{ $height }}px">
        {{-- Brand --}}
        <a href="{{ $ctx->editing ? '#' : '/' }}" class="flex items-center gap-2.5 {{ $preset === 'center' ? 'sm:absolute sm:left-1/2 sm:-translate-x-1/2' : '' }}">
            @if ($props['showLogo'] ?? true)
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $brand }}" class="h-8 w-8 rounded-lg object-cover" />
                @else
                    <span class="grid h-8 w-8 place-items-center rounded-lg text-sm font-bold text-white" style="background: var(--pp-accent)">{{ mb_strtoupper($initials) }}</span>
                @endif
            @endif
            <span class="text-base font-bold tracking-tight {{ $brandColor }}">{{ $brand }}</span>
        </a>

        {{-- Desktop menu --}}
        @if (! empty($links))
            <nav class="hidden items-center gap-7 text-sm font-medium md:flex {{ $linkColor }} {{ $preset === 'center' ? 'mx-auto' : '' }}">
                @foreach ($links as $l)
                    @php $label = is_array($l) ? ($l['label'] ?? '') : $l; $href = is_array($l) ? ($l['href'] ?? '#') : '#'; @endphp
                    @if ($label !== '')<a href="{{ $ctx->editing ? '#' : $href }}" class="transition">{{ $label }}</a>@endif
                @endforeach
            </nav>
        @endif

        {{-- Desktop actions --}}
        <div class="hidden shrink-0 items-center gap-3 md:flex">
            @if ($secondary !== '')<a href="{{ $ctx->editing ? '#' : $secondaryHref }}" class="text-sm font-semibold {{ $linkColor }}">{{ $secondary }}</a>@endif
            @include('builder.blocks._buy', ['label' => $cta, 'class' => 'px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-90'])
        </div>

        {{-- Mobile toggle --}}
        <button type="button" x-on:click="open = ! open" class="shrink-0 md:hidden {{ $transparent ? 'text-white' : 'text-neutral-700' }}" aria-label="{{ __('Menu') }}" :aria-expanded="open">
            <x-heroicon-o-bars-3 x-show="! open" class="h-6 w-6" />
            <x-heroicon-o-x-mark x-show="open" x-cloak class="h-6 w-6" />
        </button>
    </div>

    {{-- Mobile drawer --}}
    <div x-show="open" x-cloak x-transition class="border-t border-neutral-100 bg-white px-5 py-4 text-neutral-800 md:hidden">
        @if (! empty($links))
            <nav class="flex flex-col gap-1">
                @foreach ($links as $l)
                    @php $label = is_array($l) ? ($l['label'] ?? '') : $l; $href = is_array($l) ? ($l['href'] ?? '#') : '#'; @endphp
                    @if ($label !== '')<a href="{{ $ctx->editing ? '#' : $href }}" class="rounded-lg px-2 py-2 text-sm font-medium hover:bg-neutral-50">{{ $label }}</a>@endif
                @endforeach
            </nav>
        @endif
        <div class="mt-3 flex flex-col gap-2">
            @if ($secondary !== '')<a href="{{ $ctx->editing ? '#' : $secondaryHref }}" class="text-center text-sm font-semibold text-neutral-600">{{ $secondary }}</a>@endif
            @include('builder.blocks._buy', ['label' => $cta, 'class' => 'w-full px-4 py-2.5 text-sm font-semibold text-white'])
        </div>
    </div>
</header>
