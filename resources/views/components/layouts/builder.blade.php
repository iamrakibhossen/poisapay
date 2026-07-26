@props(['title' => 'Page builder'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · PoisaPay</title>
    @vite(['resources/css/app.css', 'resources/js/frontend.js'])
    @include('partials.brand-colors')
    <style>[x-cloak]{display:none!important}</style>
</head>
{{-- Full-bleed, chrome-free canvas for the visual builder. --}}
<body class="theme-minimal h-full overflow-hidden bg-neutral-100 text-neutral-900 antialiased">
    {{ $slot }}
</body>
</html>
