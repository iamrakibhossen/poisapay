@props(['cards' => []])

{{-- Compact stat grid shared by the history pages (deposits / withdrawals /
     transfers / transactions). Each card: ['label','value','icon','fg']. --}}
<div {{ $attributes->merge(['class' => 'grid grid-cols-2 gap-3 lg:grid-cols-4']) }}>
    @foreach ($cards as $c)
        <div class="pp-card flex items-center gap-3 p-4">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-neutral-100 text-neutral-500">
                <x-dynamic-component :component="'heroicon-o-'.($c['icon'] ?? 'clock')" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="truncate text-[11px] font-medium uppercase tracking-wide text-neutral-500">{{ $c['label'] }}</p>
                <p class="tabular truncate text-lg font-bold {{ $c['fg'] ?? 'text-neutral-900' }}">{{ $c['value'] }}</p>
            </div>
        </div>
    @endforeach
</div>
