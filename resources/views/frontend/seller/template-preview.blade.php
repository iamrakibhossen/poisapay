<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=1280">
    <title>{{ __('Template preview') }}</title>
    @vite(['resources/css/app.css'])
    <style>{!! $css !!}</style>
    <style>html,body{overflow:hidden}</style>
</head>
<body class="theme-minimal bg-white text-neutral-900" style="font-family: var(--pp-font, Inter, ui-sans-serif, system-ui)">
    {{-- Inert checkout form so template buy buttons (form="buy") never error. --}}
    <form id="buy" onsubmit="return false" class="hidden"></form>
    {!! $html !!}
</body>
</html>
