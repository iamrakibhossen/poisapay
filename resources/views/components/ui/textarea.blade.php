@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'error' => null,
    'rows' => 4,
])

{{-- DollarHub textarea: bordered wrapper (hover/focus gold), borderless textarea inside. --}}
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label @if($name) for="{{ $name }}" @endif class="pp-label">{{ $label }}</label>
    @endif
    <div class="flex w-full rounded-lg border bg-white transition {{ $error ? 'border-red-400 focus-within:border-red-500 focus-within:ring-2 focus-within:ring-red-500/20' : 'border-gray-300 hover:border-brand-500 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20' }}">
        <textarea
            rows="{{ $rows }}"
            @if ($name) id="{{ $name }}" name="{{ $name }}" @endif
            {{ $attributes->except('class')->merge(['class' => 'w-full rounded-lg border-0 bg-transparent px-4 py-2.5 text-sm text-gray-700 font-medium focus:outline-none focus:border-transparent focus:ring-0']) }}
        >{{ $slot }}</textarea>
    </div>
    @if ($error)
        <p class="mt-1.5 text-xs text-red-600">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
