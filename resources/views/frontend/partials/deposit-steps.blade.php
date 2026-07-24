{{-- Deposit flow progress (Currency → Method → Deposit), rendered inside the step card. --}}
<div class="mb-5 flex items-center justify-center gap-2 border-b border-neutral-100 pb-4 sm:gap-3">
    @foreach ($steps as $n => $label)
        @php $done = $n < $currentStep; $active = $n === $currentStep; @endphp
        <div class="flex items-center gap-2">
            <span @class([
                'grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-semibold transition',
                'bg-brand-500 text-white shadow-sm ring-4 ring-brand-100' => $active,
                'bg-brand-100 text-brand-600' => $done,
                'bg-neutral-100 text-neutral-400' => ! $active && ! $done,
            ])>
                @if ($done)<x-heroicon-o-check class="h-4 w-4" />@else{{ $n }}@endif
            </span>
            <span @class([
                'text-xs font-medium',
                'text-neutral-900' => $active,
                'text-neutral-500' => $done,
                'text-neutral-400' => ! $active && ! $done,
            ])>{{ $label }}</span>
        </div>
        @unless ($loop->last)
            <span @class(['h-px w-5 sm:w-10', 'bg-brand-300' => $done, 'bg-neutral-200' => ! $done])></span>
        @endunless
    @endforeach
</div>
