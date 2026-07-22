@props([
    'label' => null,
    'value' => null,
    'striped' => false,
    'html' => false,
    'tooltip' => null,
    'tooltipWidth' => 'w-40',
    'copy' => false,
    'xText' => null,
])
<li {{ $attributes->class(['flex items-center justify-between gap-3 py-1 text-sm', 'bg-gray-50' => $striped]) }}>
    <span class="flex items-center gap-1 font-medium text-gray-700">
        {{ $label }} @if ($tooltip)
            <x-ui.tooltip :message="$tooltip" :width="$tooltipWidth">
                <x-heroicon-o-information-circle class="h-5 w-5 cursor-pointer text-gray-400 hover:text-amber-700" />
            </x-ui.tooltip>
        @endif
    </span>
    <span class="flex items-center gap-1 font-semibold text-gray-900" @if ($xText) x-text="{{ $xText }}" @endif>
        @if ($html)
            {!! $value !!}
        @else
            {{ $value }} @if ($copy)
                <x-ui.copy-text :text="$value" />
            @endif
        @endif
    </span>
</li>
