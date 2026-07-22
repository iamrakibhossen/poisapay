@props(['title' => 'Welcome'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · PoisaPay</title>
    @vite(['resources/css/app.css', 'resources/js/frontend.js'])
    @include('partials.brand-colors')
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="theme-minimal h-full">
<div class="grid min-h-full lg:grid-cols-2">
    {{-- Brand panel --}}
    <div class="relative hidden overflow-hidden bg-gradient-to-br from-brand-50 via-brand-100 to-brand-200 lg:block">
        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 20% 20%, black 1px, transparent 1px); background-size: 32px 32px;"></div>
        <div class="relative flex h-full flex-col justify-between p-12 text-neutral-900">
            <a href="{{ route('home') }}" wire:navigate class="flex w-fit items-center gap-2.5">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-500 text-white"><x-heroicon-s-bolt class="h-6 w-6" /></span>
                <span class="text-xl font-bold">PoisaPay</span>
            </a>
            <div>
                <h1 class="text-3xl font-bold leading-tight text-neutral-900">The multi-chain wallet<br>built for Bangladesh.</h1>
                <p class="mt-4 max-w-md text-neutral-600">Hold, send and exchange crypto and Taka. Instant P2P, virtual cards, and bank-grade custody — all in one app.</p>
                <div class="mt-8 flex gap-6 text-sm text-neutral-600">
                    <div><p class="text-2xl font-bold text-brand-600">3</p><p>Chains supported</p></div>
                    <div><p class="text-2xl font-bold text-brand-600">0 ৳</p><p>P2P transfer fee</p></div>
                    <div><p class="text-2xl font-bold text-brand-600">24/7</p><p>Instant settlement</p></div>
                </div>
            </div>
            <p class="text-xs text-neutral-500">Custodial · KYC/AML gated · Original design</p>
        </div>
    </div>

    {{-- Form panel --}}
    <div class="flex items-center justify-center p-6 sm:p-12">
        <div class="w-full max-w-md">
            <a href="{{ route('home') }}" wire:navigate class="mb-8 flex w-fit items-center gap-2.5 lg:hidden">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-500 text-white"><x-heroicon-s-bolt class="h-5 w-5" /></span>
                <span class="text-lg font-bold text-neutral-900">PoisaPay</span>
            </a>
            {{ $slot }}
        </div>
    </div>
</div>
</body>
</html>
