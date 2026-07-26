@props(['title' => 'PoishaPay', 'description' => null, 'mainClass' => 'flex-1 pt-16'])

{{-- Isolated Landing master layout. Loads ONLY the landing bundle (its own
     Tailwind + .lp-* design system + Alpine) — never app.css / frontend.js — so
     the landing surface shares no CSS/JS with the rest of the app. Header
     (x-landing::navbar) + footer (x-landing::footer) are landing-owned. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · PoishaPay</title>
    @if ($description)<meta name="description" content="{{ $description }}">@endif
    @vite(['resources/landing/css/landing.css', 'resources/landing/js/landing.js'])
    @stack('head')
</head>
<body class="lp-wrapper h-full antialiased">

<div class="lp-mesh" aria-hidden="true"></div>
<div class="lp-grid" aria-hidden="true"></div>

<div class="relative z-10 flex min-h-full flex-col">
    <x-landing::navbar />
    {{-- pt offsets the fixed header so page content isn't tucked under it
         (the home hero manages its own offset via `main-class="flex-1"`). --}}
    <main class="{{ $mainClass }}">
        {{ $slot }}
    </main>
    <x-landing::footer />
</div>

@stack('scripts')
</body>
</html>
