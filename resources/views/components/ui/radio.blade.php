@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'error' => null,
    'value' => null,
])

{{-- DollarHub radio: gold-accented circle with an aligned label + optional hint (matches x-ui.checkbox). --}}
<div {{ $attributes->only('class') }}>
    <label class="flex cursor-pointer items-start gap-2.5 {{ $attributes->get('disabled') !== false && $attributes->has('disabled') ? 'cursor-not-allowed opacity-60' : '' }}">
        <input
            type="radio"
            @if ($name) name="{{ $name }}" @endif
            @if (! is_null($value)) value="{{ $value }}" @endif
            {{ $attributes->except('class')->merge(['class' => 'mt-0.5 h-4 w-4 shrink-0 border-gray-300 text-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-60']) }}
        />
        @if ($label || $hint)
            <span class="min-w-0">
                @if ($label)<span class="text-sm font-medium text-gray-700">{{ $label }}</span>@endif
                @if ($hint)<span class="mt-0.5 block text-xs text-gray-500">{{ $hint }}</span>@endif
            </span>
        @endif
        {{ $slot }}
    </label>
    @if ($error)
        <p class="mt-1.5 text-xs text-red-600">{{ $error }}</p>
    @endif
</div>
