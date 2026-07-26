@props(['seo' => null])
@php
    $seo = $seo instanceof \App\Support\Seo\SeoData ? $seo : new \App\Support\Seo\SeoData;

    $title = $seo->resolvedTitle();
    $desc = $seo->resolvedDescription();
    $canonical = $seo->resolvedCanonical();
    $image = $seo->resolvedImageUrl();
    $keywords = implode(', ', $seo->resolvedKeywords());
    $locale = (string) config('seo.locale', 'en_US');
    $hreflang = str_replace('_', '-', $locale);

    // JSON-LD @graph: sitewide identity + this page + breadcrumbs + any page schemas.
    $nodes = [
        \App\Support\Seo\JsonLd::organization(),
        \App\Support\Seo\JsonLd::website(),
        \App\Support\Seo\JsonLd::webPage($canonical, $seo->title ?: (string) config('seo.site_name'), $desc),
    ];
    if (! empty($seo->breadcrumbs)) {
        $nodes[] = \App\Support\Seo\JsonLd::breadcrumb($seo->breadcrumbs);
    }
    foreach ($seo->schemas as $node) {
        $nodes[] = $node;
    }
@endphp
{{-- Primary --}}
<title>{{ $title }}</title>
<meta name="description" content="{{ $desc }}">
@if ($keywords)<meta name="keywords" content="{{ $keywords }}">@endif
<meta name="author" content="{{ config('seo.site_name') }}">
<meta name="publisher" content="{{ config('seo.organization.name', config('seo.site_name')) }}">
<meta name="robots" content="{{ $seo->robots() }}">
<link rel="canonical" href="{{ $canonical }}">

{{-- Multilingual-ready (single locale today; x-default prepared) --}}
<link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $canonical }}">
<link rel="alternate" hreflang="x-default" href="{{ $canonical }}">

{{-- Open Graph --}}
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:site_name" content="{{ config('seo.site_name') }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $desc }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:image:alt" content="{{ $seo->title ?: config('seo.site_name') }}">
<meta property="og:locale" content="{{ $locale }}">
@if ($seo->type === 'article' && $seo->publishedTime)<meta property="article:published_time" content="{{ $seo->publishedTime }}">@endif
@if ($seo->type === 'article' && $seo->modifiedTime)<meta property="article:modified_time" content="{{ $seo->modifiedTime }}">@endif

{{-- Twitter / X --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="{{ config('seo.twitter_handle') }}">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $desc }}">
<meta name="twitter:image" content="{{ $image }}">

{{-- Icons, PWA, theme --}}
<link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
<link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="{{ config('seo.theme_color') }}">

{{-- Structured data --}}
<script type="application/ld+json">{!! \App\Support\Seo\JsonLd::graph($nodes) !!}</script>
