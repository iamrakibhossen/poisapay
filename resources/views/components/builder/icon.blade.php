{{-- Safe outline icon for builder sections. Maps a name to a Heroicon, falling
     back to a sparkle for anything unknown — so a hand-typed / bad icon key can
     never fatal a rendered page. Sizing via `class`.
     Usage: <x-builder.icon name="bolt" class="h-5 w-5" /> --}}
@props(['name' => 'sparkles'])
@php
    $n = strtolower(trim((string) $name)) ?: 'sparkles';
    $allowed = [
        'sparkles', 'bolt', 'check-circle', 'check-badge', 'shield-check', 'lock-closed',
        'rocket-launch', 'star', 'heart', 'fire', 'gift', 'clock', 'chart-bar', 'cube',
        'globe-alt', 'device-phone-mobile', 'credit-card', 'truck', 'user-group',
        'chat-bubble-left-right', 'arrow-path', 'adjustments-horizontal', 'light-bulb',
        'wrench-screwdriver', 'banknotes', 'academic-cap', 'trophy', 'hand-thumb-up',
        'puzzle-piece', 'beaker', 'tag', 'envelope', 'map-pin', 'phone', 'key',
        'finger-print', 'cursor-arrow-rays', 'cog-6-tooth', 'presentation-chart-line',
        'squares-2x2', 'sparkles',
    ];
    $icon = in_array($n, $allowed, true) ? $n : 'sparkles';
    $cls = $attributes->get('class') ?: 'h-5 w-5';
@endphp
<x-dynamic-component :component="'heroicon-o-'.$icon" class="{{ $cls }}" />
