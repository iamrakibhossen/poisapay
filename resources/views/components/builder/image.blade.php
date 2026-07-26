{{-- Responsive, lazy image for builder blocks. Resolves a stored URL to its Media
     Library asset and emits WebP + native `srcset` (via <picture>) with intrinsic
     width/height to avoid layout shift. A legacy/external URL that isn't a library
     asset simply renders as a plain lazy <img> — so existing pages are unaffected.

     Usage: <x-builder.image :src="$url" alt="…" class="…" sizes="(min-width:768px) 50vw, 100vw" /> --}}
@props(['src' => '', 'alt' => null, 'sizes' => '100vw'])
@php
    $__attrs = [
        'class' => $attributes->get('class'),
        'style' => $attributes->get('style'),
        'alt' => $alt,
    ];
    if ($attributes->has('loading')) {
        $__attrs['loading'] = $attributes->get('loading');
    }
    echo app(\App\Shop\Services\Media\MediaUrlService::class)->img(
        $src !== '' ? $src : null,
        array_filter($__attrs, fn ($v) => $v !== null),
        $sizes,
    );
@endphp
