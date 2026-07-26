<?php

declare(strict_types=1);

/*
 * Shop Media Library — image storage + optimisation for the Landing Page Builder.
 * Deliberately lightweight: one table (shop_media), one storage disk (resolved from
 * config/filesystems.php, never stored in the DB), and a small set of services.
 */
return [
    // Folder prefix under the storage disk for library assets.
    'directory' => 'media',

    // Upload constraints. SVG is accepted but sanitised (script/handlers stripped)
    // before it is ever served, since it renders as active content.
    'max_upload_kb' => (int) env('MEDIA_MAX_UPLOAD_KB', 12288), // 12 MB
    'accept' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'],

    // Responsive variants — max-edge caps. Images are only downscaled (never
    // upscaled); each raster variant also gets a WebP sibling for srcset.
    'variants' => [
        'thumb' => 400,
        'medium' => 1024,
        'large' => 1600,
    ],

    // Variant used as the picker/grid thumbnail (a key in `variants`).
    'preview_variant' => 'thumb',

    // Encoder quality. Metadata (EXIF/GPS) is always stripped on re-encode.
    'quality' => [
        'jpeg' => 82,
        'webp' => 80,
    ],

    // Formats we rasterise/optimise; anything else (svg, gif) is stored as-is.
    'rasterisable' => ['image/jpeg', 'image/png', 'image/webp'],

    // Library grid page size (infinite scroll).
    'per_page' => 30,

    // How long a URL→media resolution is cached (for srcset lookups on render).
    'resolve_cache_ttl' => (int) env('MEDIA_RESOLVE_CACHE_TTL', 86400),
];
