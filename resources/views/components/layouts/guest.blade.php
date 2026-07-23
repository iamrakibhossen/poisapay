@props(['title' => __('Welcome')])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · PoisaPay</title>
    @vite(['resources/css/app.css', 'resources/js/frontend.js'])
    @include('partials.marketing-styles')
    @include('partials.brand-colors')
    <style>
        /* Auth form card + gradient primary button (matches homepage pp-btn-primary). */
        .pp-auth-card{background:#fff;border:1px solid #e2e8f0;border-radius:1.5rem;box-shadow:0 24px 60px -30px rgb(15 23 42 / .3),0 2px 8px -4px rgb(15 23 42 / .06)}
        .pp-auth-card button[type="submit"]{background-image:linear-gradient(120deg,#2563eb,#1d4ed8)!important;color:#fff!important;border-color:transparent!important;box-shadow:0 10px 24px -10px rgba(37,99,235,.6),inset 0 1px 0 rgba(255,255,255,.2);transition:box-shadow .3s,transform .2s,background-image .2s}
        .pp-auth-card button[type="submit"]:hover{background-image:linear-gradient(120deg,#1d4ed8,#1e40af)!important;box-shadow:0 16px 34px -10px rgba(37,99,235,.7);transform:translateY(-1px)}
    </style>
</head>
<body class="poisa-landing h-full antialiased">

<div class="pp-mesh" aria-hidden="true"></div>
<div class="pp-grid-overlay" aria-hidden="true"></div>

<div class="relative z-10 flex min-h-full flex-col">
    <x-marketing.nav />

    <main class="flex flex-1 items-center justify-center px-4 pb-16 pt-28 sm:px-6 sm:pt-32">
        <div class="w-full max-w-md">
            <div class="theme-minimal pp-auth-card p-8 sm:p-10">
                {{ $slot }}
            </div>
        </div>
    </main>

    <x-marketing.footer />
</div>
</body>
</html>
