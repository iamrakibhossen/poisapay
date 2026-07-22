@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-gray-700 whitespace-nowrap']) }}>
    {{ $value ?? $slot }}
</label>
