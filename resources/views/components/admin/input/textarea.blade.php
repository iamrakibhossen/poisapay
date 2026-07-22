@props([
    'name' => null,
    'label' => null,
    'value' => null,
    'hint' => null,
    'required' => false,
    'rows' => 3,
])

<div>
    @if ($label)
        <x-admin.label for="{{ $name }}" class="mb-1">
            {{ __($label) }}
            @if ($required)<span class="text-red-500">*</span>@endif
        </x-admin.label>
    @endif

    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
        {!! $attributes->merge([
            'class' =>
                'w-full text-md text-gray-600 border border-gray-300 rounded-lg shadow-xs focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 py-3 px-4',
        ]) !!}>{{ $value }}</textarea>

    @if ($hint)
        <p class="mt-1.5 text-sm text-gray-500">{{ $hint }}</p>
    @endif
    @if ($name)
        <x-admin.input-error :for="$name" class="mt-1.5" />
    @endif
</div>
