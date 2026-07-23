@props(['title' => 'PoisaPay'])

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
<body class="h-full bg-neutral-50 font-sans text-neutral-900">
<div class="min-h-full">
    {{-- Top bar --}}
    <header class="sticky top-0 z-40 border-b border-neutral-200/70 bg-neutral-50/80 backdrop-blur">
        <nav class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3.5 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-500 text-ink-900"><x-heroicon-s-bolt class="h-5 w-5" /></span>
                <span class="text-lg font-bold text-neutral-900">PoisaPay</span>
            </a>
            <div class="flex items-center gap-4 text-sm">
                <a href="{{ route('faqs.public') }}" class="font-medium text-neutral-600 hover:text-neutral-900">{{ __('FAQs') }}</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-lg bg-brand-500 px-4 py-2 font-semibold text-ink-900 hover:bg-brand-400">{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="font-medium text-neutral-600 hover:text-neutral-900">{{ __('Log in') }}</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-brand-500 px-4 py-2 font-semibold text-ink-900 hover:bg-brand-400">{{ __('Get started') }}</a>
                @endauth
            </div>
        </nav>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="border-t border-neutral-200 py-8">
        <div class="mx-auto max-w-5xl px-4 text-center text-xs text-neutral-500 sm:px-6">
            &copy; {{ date('Y') }} PoisaPay. {{ __('Custodial · KYC/AML gated.') }}
        </div>
    </footer>
</div>
</body>
</html>
