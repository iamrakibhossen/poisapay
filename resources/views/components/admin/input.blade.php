@props([
    'disabled' => false,
    'readonly' => false,
    'required' => false,
    'type' => 'text',
    'name' => null,
    'label' => null,
    'errorName' => $name,
    'hint' => null,
])
@php
    $defaultClasses = '
        bg-white
        w-full text-md text-gray-600 border border-gray-300 rounded-lg py-3 px-4 shadow-xs
        focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50
        disabled:bg-gray-100 disabled:text-gray-600';
@endphp
<div @if ($type === 'password') x-data="{ show: false }" @endif>
    @if ($label)
        <x-admin.label for="{{ $name }}" class="mb-1">
            {{ __($label) }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </x-admin.label>
    @endif

    @if ($type === 'password')
        <div class="relative">
            <input
                id="{{ $name }}"
                name="{{ $name }}"
                x-bind:type="show ? 'text' : 'password'"
                @disabled($disabled)
                @readonly($readonly)
                {!! $attributes->merge(['class' => $defaultClasses.' pr-11']) !!}
            >
            <button type="button" tabindex="-1" @click="show = ! show" :title="show ? '{{ __('Hide') }}' : '{{ __('Show') }}'"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition cursor-pointer hover:text-gray-600">
                <x-heroicon-o-eye x-show="! show" class="w-5 h-5" />
                <x-heroicon-o-eye-slash x-show="show" x-cloak class="w-5 h-5" />
            </button>
        </div>
    @else
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @disabled($disabled)
            @readonly($readonly)
            {!! $attributes->merge(['class' => $defaultClasses]) !!}
        >
    @endif
    @if ($hint)
        <p class="mt-1 text-sm leading-none opacity-50">{{ $hint }}</p>
    @endif
    @if ($errorName)
        <x-admin.input-error :for="$errorName" class="mt-2" />
    @endif
</div>
