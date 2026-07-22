@props([
    'color' => 'gray',   // gray|success|warning|danger|info|primary|indigo
    'icon' => null,
    'dot' => false,
])

@php
    // DollarHub label: uppercase, tracking-wide, soft-100 bg + 700 text + 200 border.
    $map = [
        'gray'    => 'bg-gray-100 text-gray-600 border-gray-200',
        'success' => 'bg-green-100 text-green-700 border-green-200',
        'warning' => 'bg-amber-100 text-amber-700 border-amber-200',
        'danger'  => 'bg-red-100 text-red-700 border-red-200',
        'info'    => 'bg-blue-100 text-blue-700 border-blue-200',
        'primary' => 'bg-amber-100 text-amber-700 border-amber-200',
        'indigo'  => 'bg-indigo-100 text-indigo-700 border-indigo-200',
    ];
    $dotColor = [
        'gray' => 'bg-gray-400', 'success' => 'bg-green-500', 'warning' => 'bg-amber-500',
        'danger' => 'bg-red-500', 'info' => 'bg-blue-500', 'primary' => 'bg-brand-500', 'indigo' => 'bg-indigo-500',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide '.($map[$color] ?? $map['gray'])]) }}>
    @if ($dot)<span class="h-1.5 w-1.5 rounded-full {{ $dotColor[$color] ?? $dotColor['gray'] }}"></span>@endif
    @if ($icon)<x-dynamic-component :component="'heroicon-m-'.$icon" class="h-3.5 w-3.5" />@endif
    {{ $slot }}
</span>
