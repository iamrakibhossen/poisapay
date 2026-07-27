@props([
    'title' => 'PaishaHub',
    'description' => null,
    'canonical' => null,
    'robots' => 'index,follow',
    'ogImage' => null,
    'ogType' => 'website',
    'tracking' => [],        // per-sales-page pixel config (shop_sales_pages.tracking)
    'trackingEvents' => [],  // list<App\Shop\Tracking\TrackingEvent> fired on load
])

@php($__tracker = app(\App\Shop\Tracking\TrackingManager::class))

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }}</title>
    @if ($description)<meta name="description" content="{{ $description }}">@endif
    <meta name="robots" content="{{ $robots }}">
    @if ($canonical)<link rel="canonical" href="{{ $canonical }}">@endif

    {{-- Open Graph --}}
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $title }}">
    @if ($description)<meta property="og:description" content="{{ $description }}">@endif
    @if ($canonical)<meta property="og:url" content="{{ $canonical }}">@endif
    @if ($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endif

    {{-- Twitter --}}
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title }}">
    @if ($description)<meta name="twitter:description" content="{{ $description }}">@endif
    @if ($ogImage)<meta name="twitter:image" content="{{ $ogImage }}">@endif

    {{-- Page-specific head (JSON-LD, etc.) --}}
    {{ $head ?? '' }}

    {{-- Per-sales-page tracking pixels + consent-gated event runtime. '' when none. --}}
    {!! $__tracker->head($tracking, $trackingEvents) !!}

    @vite(['resources/css/app.css', 'resources/js/frontend.js'])
    @include('partials.brand-colors')
    <style>[x-cloak]{display:none!important}</style>
    {{-- Arm entrance animations before first paint (JS-gated) so revealed blocks
         don't flash visible→hidden. frontend.js then reveals them on scroll. --}}
    <script>document.documentElement.classList.add('pp-anim')</script>
</head>
{{-- Standalone conversion page: theme-minimal (premium blue/slate), no app nav. --}}
<body class="theme-minimal min-h-full bg-white font-sans text-neutral-900 antialiased">
    {!! $__tracker->body($tracking) !!}
    {{ $slot }}
</body>
</html>
