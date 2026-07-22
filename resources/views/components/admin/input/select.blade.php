@props([
    'disabled' => false,
    'required' => false,
    'name' => null,
    'label' => null,
    'errorName' => $name,
    'multiple' => false,
])

<div>
    @if ($label)
        <x-admin.label for="{{ $name }}" class="mb-1">
            {{ __($label) }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </x-admin.label>
    @endif

    <select id="{{ $name }}" name="{{ $name }}" {!! $attributes->merge([
        'class' =>
            'bg-white text-md w-full text-gray-600 border border-gray-300
            focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50
            rounded-lg shadow-sm pl-4 pr-8 py-3
            disabled:bg-gray-100 disabled:text-gray-600',
    ]) !!} {{ $multiple ? 'multiple' : '' }} @disabled($disabled)>
        {{ $slot }}
    </select>

    @if ($errorName)
        <x-admin.input-error :for="$errorName" class="mt-1.5" />
    @endif
</div>
