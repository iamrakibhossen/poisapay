@props([
    'label' => null,
    'name' => null,
    'id' => null,     // override the field id (defaults to name); use when two selects share a name
    'hint' => null,
    'icon' => null,
    'error' => null,
])

@php $fieldId = $id ?? $name; @endphp

{{-- DollarHub select: bordered wrapper (hover/focus gold), borderless native select inside. --}}
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label @if($fieldId) for="{{ $fieldId }}" @endif class="pp-label">{{ $label }}</label>
    @endif
    <div class="relative flex min-h-[2.625rem] w-full items-center rounded-lg border bg-white transition {{ $error ? 'border-red-400 focus-within:border-red-500 focus-within:ring-2 focus-within:ring-red-500/20' : 'border-gray-300 hover:border-brand-500 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20' }}">
        @if ($icon)
            <span class="pointer-events-none grid w-10 shrink-0 place-items-center text-gray-400">
                <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4" />
            </span>
        @endif
        <select
            @if ($fieldId) id="{{ $fieldId }}" @endif
            @if ($name) name="{{ $name }}" @endif
            {{ $attributes->except(['class', 'id'])->merge(['class' => 'w-full appearance-none rounded-lg border-0 bg-transparent bg-none py-2.5 pr-10 text-sm text-gray-700 font-medium focus:outline-none focus:border-transparent focus:ring-0 '.($icon ? 'pl-2.5' : 'pl-4')]) }}
        >
            {{ $slot }}
        </select>
        <span class="pointer-events-none absolute right-3 text-gray-400">
            <x-heroicon-o-chevron-down class="h-4 w-4" />
        </span>
    </div>
    @if ($error)
        <p class="mt-1.5 text-xs text-red-600">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
