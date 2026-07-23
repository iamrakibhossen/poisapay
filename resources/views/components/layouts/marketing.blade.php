@props(['title' => 'PoisaPay', 'description' => null])

{{-- Public marketing layout: the same document shell, header (x-marketing.nav) and
     footer (x-marketing.footer) the homepage uses, so every marketing page (FAQs,
     etc.) shares one header/footer. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · PoisaPay</title>
    @if ($description)<meta name="description" content="{{ $description }}">@endif
    @vite(['resources/css/app.css', 'resources/js/frontend.js'])
    @include('partials.marketing-styles')
    <style>[x-cloak]{display:none!important}</style>
    @stack('head')
</head>
<body class="poisa-landing h-full antialiased">

<div class="pp-mesh" aria-hidden="true"></div>
<div class="pp-grid-overlay" aria-hidden="true"></div>

<div class="relative z-10 flex min-h-full flex-col">
    <x-marketing.nav />
    {{-- pt offsets the fixed header (x-marketing.nav) so page content isn't tucked under it. --}}
    <main class="flex-1 pt-16">
        {{ $slot }}
    </main>
    <x-marketing.footer />
</div>

{{-- Scroll reveal — dependency-free; reveals `.reveal` elements as they enter view
     (and immediately for reduced-motion / no-IO). Shared by every marketing page. --}}
<script>
(function () {
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var els = document.querySelectorAll('.reveal');
    if (reduce || !('IntersectionObserver' in window)) {
        els.forEach(function (el) { el.classList.add('in'); });
        return;
    }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) { entry.target.classList.add('in'); io.unobserve(entry.target); }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    els.forEach(function (el) { io.observe(el); });
})();
</script>
@stack('scripts')
</body>
</html>
