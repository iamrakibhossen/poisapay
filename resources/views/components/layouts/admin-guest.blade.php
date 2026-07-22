@props(['title' => 'Operator Sign-in'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · PoisaPay Admin</title>
    @vite(['resources/css/app.css', 'resources/js/admin.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="h-full">
<div class="relative flex min-h-full items-center justify-center bg-neutral-50 p-6">
    <div class="pointer-events-none absolute inset-0 opacity-[0.06]" style="background-image: radial-gradient(circle at 30% 20%, #FFC107 1px, transparent 1px); background-size: 40px 40px;"></div>
    <div class="relative w-full max-w-sm">
        <div class="mb-8 flex flex-col items-center text-center">
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-brand-500 text-ink-900 shadow-lg"><x-heroicon-s-bolt class="h-6 w-6" /></span>
            <h1 class="mt-4 text-lg font-bold text-neutral-900">PoisaPay</h1>
            <p class="text-xs font-medium uppercase tracking-widest text-amber-700">Operator Console</p>
        </div>
        <div class="pp-card p-6">
            {{ $slot }}
        </div>
        <p class="mt-6 text-center text-xs text-neutral-400">Authorised operators only · all actions are audit-logged</p>
    </div>
</div>
</body>
</html>
