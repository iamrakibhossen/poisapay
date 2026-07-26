@php
    // Minimal, single-column footer. Prop keys: brandName, tagline, links[],
    // socialLinks[], copyright, darkMode. Legacy footers (brand / columns / social /
    // href / dark) are read as fallbacks so already-published pages keep rendering.
    // Legacy defaults are read with `?:` / emptiness (not `??`) because the schema
    // always injects the new keys as empty — so an empty new key must fall through.
    $brand = ($props['brandName'] ?? '') ?: ($props['brand'] ?? '') ?: ($ctx->seller['name'] ?? '');
    $tagline = (string) ($props['tagline'] ?? '');
    $dark = (bool) ($props['darkMode'] ?? false) || (bool) ($props['dark'] ?? false);
    $logo = $ctx->seller['logo'] ?? null;
    $year = date('Y');
    $href = fn ($u) => $ctx->editing ? '#' : (($u ?? '#') ?: '#');

    // Links are {label, url} rows. Legacy footers stored a "columns" repeater of
    // {title, links:"Label|url\n…"}; flatten those so old pages don't lose their links.
    $links = $props['links'] ?? [];
    if (empty($links) && ! empty($props['columns'])) {
        $links = collect($props['columns'])->flatMap(function ($c) {
            $raw = is_array($c) ? ($c['links'] ?? '') : '';

            return collect(array_filter(array_map('trim', explode("\n", (string) $raw))))
                ->map(function ($row) {
                    [$label, $url] = array_pad(array_map('trim', explode('|', $row, 2)), 2, '');

                    return ['label' => $label, 'url' => $url];
                })->all();
        })->all();
    }
    $links = collect($links ?? [])
        ->map(fn ($l) => ['label' => trim((string) ($l['label'] ?? '')), 'url' => $l['url'] ?? $l['href'] ?? '#'])
        ->filter(fn ($l) => $l['label'] !== '')
        ->take(6);

    $socialRaw = $props['socialLinks'] ?? [];
    if (empty($socialRaw) && ! empty($props['social'])) {
        $socialRaw = $props['social'];
    }
    $social = collect($socialRaw)
        ->map(fn ($s) => is_array($s)
            ? ['label' => trim((string) ($s['label'] ?? '')), 'url' => $s['url'] ?? $s['href'] ?? '#']
            : ['label' => trim((string) $s), 'url' => '#'])
        ->filter(fn ($s) => $s['label'] !== '');

    $bg = $dark ? 'bg-neutral-900 text-neutral-300' : 'border-t border-neutral-100 bg-neutral-50 text-neutral-600';
    $head = $dark ? 'text-white' : 'text-neutral-900';
    $muted = $dark ? 'text-neutral-400' : 'text-neutral-500';
    $line = $dark ? 'border-white/10' : 'border-neutral-200';
@endphp
<footer id="{{ $node->id }}" class="pp-block {{ $bg }} py-10">
    <div class="mx-auto max-w-3xl px-5 text-center">
        <div class="flex items-center justify-center gap-2.5">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $brand }}" class="h-8 w-8 rounded-lg object-cover" />
            @else
                <span class="grid h-8 w-8 place-items-center rounded-lg text-sm font-bold text-white" style="background: var(--pp-accent)">{{ mb_strtoupper(mb_substr($brand ?: '?', 0, 1)) }}</span>
            @endif
            <span class="text-base font-bold {{ $head }}">{{ $brand }}</span>
        </div>
        @if ($tagline !== '')<p class="mx-auto mt-2 max-w-md text-sm {{ $muted }}">{{ $tagline }}</p>@endif

        @if ($links->isNotEmpty())
            <nav class="mt-5 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm {{ $muted }}">
                @foreach ($links as $l)<a href="{{ $href($l['url']) }}" class="transition hover:opacity-80">{{ $l['label'] }}</a>@endforeach
            </nav>
        @endif

        @if ($social->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                @foreach ($social as $s)<a href="{{ $href($s['url']) }}" class="rounded-lg border {{ $line }} px-2.5 py-1 text-xs font-medium transition hover:opacity-80">{{ $s['label'] }}</a>@endforeach
            </div>
        @endif

        <div class="mt-6 border-t {{ $line }} pt-5">
            <p class="text-xs {{ $muted }}">{{ $props['copyright'] ?: '© '.$year.' '.$brand }}</p>
        </div>
    </div>
</footer>
