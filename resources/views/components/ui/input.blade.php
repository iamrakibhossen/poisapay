@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'icon' => null,          // leading heroicon addon
    'prefix' => null,        // leading text addon (e.g. "$")
    'suffix' => null,        // trailing text addon (e.g. "USDT")
    'suffixModel' => null,   // Alpine expression for a live trailing addon (x-text)
    'error' => null,
    'disabled' => false,
    'readonly' => false,
    'size' => 'md',          // sm | md | lg
])

@php
    // Wire the label to the field whether an explicit id, a name, or neither is given.
    $fieldId = $attributes->get('id') ?? $name;
    $isDisabled = $disabled !== false && $disabled !== null;
    $isReadonly = $readonly !== false && $readonly !== null;
    $isPassword = $type === 'password';
    $hasLead = $icon || $prefix;
    $hasTrail = $isPassword || $suffix || $suffixModel;

    // Shared control height recipe — kept in lock-step with x-ui.select and x-ui.button.
    $py = match ($size) {
        'sm' => 'py-2 text-xs',
        'lg' => 'py-3 text-base',
        default => 'py-2.5 text-sm',
    };
    $minH = match ($size) {
        'sm' => 'min-h-[2.25rem]',
        'lg' => 'min-h-[3rem]',
        default => 'min-h-[2.625rem]',
    };
@endphp

{{-- DollarHub input: bordered wrapper (hover/focus gold), borderless input inside,
     with optional icon/prefix/suffix addons and a built-in password toggle. --}}
<div {{ $attributes->only('class') }} @if ($isPassword) x-data="{ show: false }" @endif>
    @if ($label)
        <label @if ($fieldId) for="{{ $fieldId }}" @endif class="pp-label">{{ $label }}</label>
    @endif
    <div @class([
        'flex w-full items-center rounded-lg border bg-white transition '.$minH,
        'border-red-400 focus-within:border-red-500 focus-within:ring-2 focus-within:ring-red-500/20' => $error,
        'border-gray-300 hover:border-brand-500 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20' => ! $error,
        'cursor-not-allowed bg-gray-100 opacity-70' => $isDisabled,
    ])>
        @if ($icon)
            <span class="pointer-events-none flex shrink-0 items-center ps-3.5 text-gray-400">
                <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4" />
            </span>
        @elseif ($prefix)
            <span class="pointer-events-none flex shrink-0 items-center ps-4 font-medium text-gray-500">{{ $prefix }}</span>
        @endif

        <input
            @if ($isPassword) x-bind:type="show ? 'text' : 'password'" @else type="{{ $type }}" @endif
            @if ($fieldId) id="{{ $fieldId }}" @endif
            @if ($name) name="{{ $name }}" @endif
            @if (! is_null($value)) value="{{ $value }}" @endif
            @disabled($isDisabled)
            @readonly($isReadonly)
            {{ $attributes->except(['class', 'id'])->merge(['class' => 'w-full min-w-0 rounded-lg border-0 bg-transparent font-medium text-gray-700 focus:outline-none focus:border-transparent focus:ring-0 disabled:cursor-not-allowed '.$py.' '.($hasLead ? 'ps-2.5' : 'ps-4').' '.($hasTrail ? 'pe-2.5' : 'pe-4')]) }}
        />

        @if ($isPassword)
            <button type="button" tabindex="-1" @click="show = ! show" :title="show ? 'Hide' : 'Show'"
                class="flex shrink-0 items-center pe-3.5 text-gray-400 transition hover:text-gray-600">
                <x-heroicon-o-eye x-show="! show" class="h-5 w-5" />
                <x-heroicon-o-eye-slash x-show="show" x-cloak class="h-5 w-5" />
            </button>
        @elseif ($suffix || $suffixModel)
            <span class="pointer-events-none flex shrink-0 items-center whitespace-nowrap pe-4 font-medium text-gray-500"
                @if ($suffixModel) x-text="{{ $suffixModel }}" @endif>{{ $suffix }}</span>
        @endif
    </div>
    @if ($error)
        <p class="mt-1.5 text-xs text-red-600">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
