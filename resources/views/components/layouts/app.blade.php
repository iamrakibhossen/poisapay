@props(['title' => 'Dashboard'])

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
@if (session()->has('impersonator_id'))
    <div class="flex flex-wrap items-center justify-center gap-3 bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-ink-900">
        <span class="flex items-center gap-1.5">
            <x-heroicon-o-eye class="h-4 w-4" />
            {{ __('You are viewing as :name (operator impersonation).', ['name' => auth()->user()?->name]) }}
        </span>
        <form method="POST" action="{{ route('impersonate.stop') }}">
            @csrf
            <button type="submit" class="rounded-md bg-ink-900 px-3 py-1 text-xs font-semibold text-white hover:bg-ink-800">
                {{ __('Stop impersonating') }}
            </button>
        </form>
    </div>
@endif
@include('partials.announcement')
{{-- DollarHub frontend shell: full-width header on top, then sidebar + main. --}}
<div x-data="{ sidebar: false }" class="flex min-h-full flex-col">
    <x-partials.topbar :title="$title" />

    <div class="flex flex-1">
        <x-partials.sidebar />

        <main class="w-full px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                @include('partials.verify-email')
                {{ $slot }}
            </div>
        </main>
    </div>

    <x-ui.toast />
    <x-flash-messages />
</div>

@auth
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (! window.Echo) return;
        const notify = (type, message) => window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }));
        try {
            window.Echo.private('user.{{ auth()->id() }}')
                .listen('.deposit.credited', () => notify('success', 'Deposit credited to your wallet'))
                .listen('.withdrawal.completed', () => notify('success', 'Withdrawal completed'))
                .listen('.transfer.completed', () => notify('info', 'Transfer completed'))
                .listen('.invoice.paid', () => notify('success', 'Invoice paid'));
        } catch (e) { /* Reverb offline — UI still works */ }
    });
</script>
@endauth

@stack('scripts')
</body>
</html>
