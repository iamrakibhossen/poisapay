{{-- Settings › Preferences tab — spending priority order.
     Expects (from the settings view scope): $priorities. --}}
<x-settings.section title="Spending priority" description="The order assets are used when spending (e.g. with cards).">
    <div class="space-y-2.5">
        @forelse ($priorities as $i => $p)
            <div class="flex items-center gap-3 rounded-xl border border-neutral-200 p-3">
                <span class="tabular grid h-6 w-6 shrink-0 place-items-center rounded-full bg-neutral-900 text-[11px] font-bold text-white">{{ $i + 1 }}</span>
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-neutral-100 text-[10px] font-semibold text-neutral-600">{{ \Illuminate\Support\Str::substr($p['symbol'] ?? '?', 0, 2) }}</span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-neutral-900">{{ $p['symbol'] ?? 'Unknown' }}</p>
                    <p class="truncate text-xs text-neutral-500">{{ $p['name'] }}</p>
                </div>
                @if ($i === 0)
                    <span class="shrink-0 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">Used first</span>
                @endif
            </div>
        @empty
            <x-ui.empty-state icon="adjustments-horizontal" title="No spending order set"
                description="Your default spending order will be used until you customise it." />
        @endforelse
    </div>
</x-settings.section>
