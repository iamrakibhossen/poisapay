@props([
    'steps' => [],          // ordered stage labels
    'current' => 1,         // 1-based index of the reached/active stage
    'failed' => false,      // terminal failure — stops the track and turns the reached stage red
    'failedLabel' => null,  // label shown on the failed node (defaults to the reached stage's label)
])

{{-- Horizontal lifecycle tracker (Requested → … → Completed). Steps before the
     current one show a check; the current one is highlighted; later ones are
     muted. On failure the track halts at the reached stage and turns rose. --}}
<div class="flex items-center justify-center gap-2 sm:gap-3">
    @foreach ($steps as $i => $label)
        @php
            $n = $i + 1;
            $done = ! $failed && $n < $current;
            $active = $n === $current;
            $failedHere = $failed && $active;
        @endphp
        <div class="flex items-center gap-2">
            <span @class([
                'grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-semibold transition',
                'bg-rose-500 text-white ring-4 ring-rose-100' => $failedHere,
                'bg-brand-500 text-white shadow-sm ring-4 ring-brand-100' => $active && ! $failed,
                'bg-brand-100 text-brand-600' => $done,
                'bg-neutral-100 text-neutral-400' => ! $active && ! $done,
            ])>
                @if ($failedHere)
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                @elseif ($done)
                    <x-heroicon-o-check class="h-4 w-4" />
                @else
                    {{ $n }}
                @endif
            </span>
            <span @class([
                'text-xs font-medium',
                'text-rose-700' => $failedHere,
                'text-neutral-900' => $active && ! $failed,
                'text-neutral-500' => $done,
                'text-neutral-400' => ! $active && ! $done,
            ])>{{ $failedHere && $failedLabel ? $failedLabel : $label }}</span>
        </div>
        @unless ($loop->last)
            <span @class(['h-px w-5 sm:w-8', 'bg-brand-300' => $done, 'bg-neutral-200' => ! $done])></span>
        @endunless
    @endforeach
</div>
