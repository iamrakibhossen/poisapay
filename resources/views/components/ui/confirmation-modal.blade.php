@props([
    'name',
    'maxWidth' => 'md',
])

{{-- Mercury-style confirmation: soft warning glyph, airy content, clean footer.
     Open via: $dispatch('open-modal', '<name>') --}}
<x-ui.modal :name="$name" :maxWidth="$maxWidth" {{ $attributes }}>
    <div class="sm:flex sm:items-start sm:gap-4">
        <div class="mx-auto flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-50 ring-1 ring-red-100 sm:mx-0">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-red-500" />
        </div>

        <div class="mt-3 text-center sm:mt-0.5 sm:text-left">
            @isset($title)
                <h3 class="text-[17px] font-semibold tracking-[-0.01em] text-slate-900">{{ $title }}</h3>
            @endisset

            @isset($content)
                <div class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ $content }}</div>
            @endisset
        </div>
    </div>

    @isset($footer)
        <div class="-mx-7 -mb-7 mt-7 flex flex-row justify-end gap-3 border-t border-slate-100 px-7 pt-5 pb-7">
            {{ $footer }}
        </div>
    @endisset
</x-ui.modal>
