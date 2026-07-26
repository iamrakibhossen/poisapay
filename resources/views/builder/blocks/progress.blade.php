@php $items = $props['items'] ?? []; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block py-12">
        <div class="mx-auto max-w-2xl px-5">
            @if (! empty($props['heading']))<h2 class="mb-7 text-center text-2xl font-bold tracking-tight">{{ $props['heading'] }}</h2>@endif
            <div class="space-y-5">
                @foreach ($items as $it)
                    @php $it = is_array($it) ? $it : ['label' => $it]; $pct = max(0, min(100, (int) ($it['value'] ?? 0))); @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm"><span class="font-medium text-neutral-700">{{ $it['label'] ?? '' }}</span><span class="font-semibold text-neutral-400">{{ $pct }}%</span></div>
                        <div class="mt-1.5 h-2.5 overflow-hidden rounded-full bg-neutral-100"><div class="h-full rounded-full" style="width: {{ $pct }}%; background: var(--pp-accent)"></div></div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
