@php
    $before = array_filter(array_map('trim', explode("\n", (string) ($props['before'] ?? ''))));
    $after = array_filter(array_map('trim', explode("\n", (string) ($props['after'] ?? ''))));
@endphp
<section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
    <div class="mx-auto max-w-4xl px-5">
        @if (! empty($props['heading']))<h2 class="text-center text-2xl font-bold tracking-tight sm:text-3xl">{{ $props['heading'] }}</h2>@endif
        <div class="mt-8 grid gap-5 sm:grid-cols-2">
            <div class="rounded-3xl border border-neutral-200 bg-neutral-50 p-6">
                <p class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-neutral-400"><x-heroicon-s-x-circle class="h-4 w-4" /> {{ $props['beforeLabel'] ?? __('Before') }}</p>
                <ul class="mt-4 space-y-2.5 text-sm text-neutral-500">
                    @foreach ($before as $b)<li class="flex items-start gap-2"><x-heroicon-o-minus class="mt-0.5 h-4 w-4 shrink-0 text-neutral-300" /><span>{{ $b }}</span></li>@endforeach
                </ul>
            </div>
            <div class="rounded-3xl border-2 bg-white p-6 shadow-sm" style="border-color: color-mix(in srgb, var(--pp-accent) 30%, transparent)">
                <p class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide" style="color: var(--pp-accent)"><x-heroicon-s-check-circle class="h-4 w-4" /> {{ $props['afterLabel'] ?? __('After') }}</p>
                <ul class="mt-4 space-y-2.5 text-sm text-neutral-700">
                    @foreach ($after as $a)<li class="flex items-start gap-2"><x-heroicon-s-check class="mt-0.5 h-4 w-4 shrink-0" style="color: var(--pp-accent)" /><span>{{ $a }}</span></li>@endforeach
                </ul>
            </div>
        </div>
    </div>
</section>
