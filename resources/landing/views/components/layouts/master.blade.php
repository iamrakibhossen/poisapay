@props(['title' => 'PaishaPay', 'description' => null, 'mainClass' => 'flex-1 pt-16', 'seo' => null])

{{-- Isolated Landing master layout. Loads ONLY the landing bundle (its own
     Tailwind + .lp-* design system + Alpine) — never app.css / frontend.js — so
     the landing surface shares no CSS/JS with the rest of the app. Header
     (x-landing::navbar) + footer (x-landing::footer) are landing-owned. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- All SEO/meta/icons/JSON-LD come from the reusable component (one source). --}}
    <x-seo.meta :seo="$seo ?? \App\Support\Seo\SeoData::make($title !== 'PaishaPay' ? $title : null, $description)" />

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
