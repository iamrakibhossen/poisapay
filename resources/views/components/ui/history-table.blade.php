@props(['columns' => []])

{{-- Card-wrapped table shell for the history pages. `columns` is a list of
     ['label' => '', 'align' => 'left'|'right', 'sr' => bool] (align/sr optional).
     The slot provides the <tr> rows. --}}
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[var(--shadow-card)]']) }}>
    <table class="min-w-full text-sm">
        <thead>
            <tr class="border-b border-neutral-200 bg-neutral-50/60 text-[11px] uppercase tracking-wider text-neutral-400">
                @foreach ($columns as $col)
                    <th @class([
                        'px-5 py-3 font-semibold',
                        'text-right' => ($col['align'] ?? 'left') === 'right',
                        'text-left' => ($col['align'] ?? 'left') !== 'right',
                        'sr-only' => $col['sr'] ?? false,
                    ])>{{ $col['label'] ?? '' }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
            {{ $slot }}
        </tbody>
    </table>
</div>
