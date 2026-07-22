@props([
    'id' => '',
    'label' => '',
    'labelClass' => 'block text-sm font-medium text-gray-700 mb-1.5',
    'hints' => null,
    'required' => false,
])
<div {{ $attributes }}>
    @if ($label)
        <label for="{{ $id }}" class="{{ $labelClass }}">
            <span>{{ $label }}</span>
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <div>
        {{ $slot }}
    </div>

    @if ($hints)
        <p class="mt-1.5 text-sm text-gray-500">{{ $hints }}</p>
    @endif
</div>
