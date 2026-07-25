@props(['title' => 'PoisaHub'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/frontend.js'])
    @include('partials.brand-colors')
    <style>[x-cloak]{display:none!important}</style>
</head>
{{-- Standalone conversion page: theme-minimal (premium blue/slate), no app nav. --}}
<body class="theme-minimal min-h-full bg-white font-sans text-neutral-900 antialiased">
    {{ $slot }}
</body>
</html>
