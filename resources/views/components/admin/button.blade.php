@props([
    'color' => null,
    'disabled' => false,
])
@php
    $classes = 'inline-flex items-center justify-center gap-2 px-4 py-2 border rounded-lg font-semibold text-sm uppercase whitespace-nowrap tracking-widest transition ease-in-out duration-150 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none';
    if ($color == 'danger' || $color == 'red') {
        $classes .= ' bg-red-600 text-white hover:bg-red-500 active:bg-red-700 border-transparent focus-visible:ring-red-400';
    } elseif ($color == 'light' || $color == 'white') {
        $classes .= ' bg-gray-50 text-gray-900 hover:bg-gray-100 active:bg-gray-300 border-gray-300 focus-visible:ring-gray-400';
    } else {
        // Default / primary: PoisaPay brand gold on dark ink text.
        $classes .= ' bg-brand-500 text-ink-900 hover:bg-brand-400 active:bg-brand-600 border-transparent focus-visible:ring-brand-400';
    }
@endphp

<button @disabled($disabled) {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}>
    {{ $slot }}
</button>
