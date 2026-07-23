@props(['title' => __('Admin')])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — PoisaPay Admin</title>
    <meta name="robots" content="noindex, nofollow">
    @vite(['resources/css/app.css', 'resources/js/admin.js'])
    @include('partials.brand-colors')
    <style>[x-cloak]{display:none!important}</style>
    @stack('head')
</head>

<body class="admin antialiased h-full">

@include('partials.announcement')

<div class="flex h-screen bg-gray-100" x-data="{ sidebarOpen: window.innerWidth >= 1024 }">

    <x-partials.admin-sidebar />

    <div class="flex-1 max-h-full overflow-y-auto">

        <x-partials.admin-topbar :title="$title" />

        <main class="p-4 sm:p-5 lg:p-6">
            {{ $slot }}
        </main>

    </div>

    <x-ui.toast />
    <x-flash-messages />
</div>

@stack('footer')
@stack('scripts')
</body>
</html>
