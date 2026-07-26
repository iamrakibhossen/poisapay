<?php

declare(strict_types=1);

namespace App\Shop\Jobs;

use App\Shop\Models\ShopMedia;
use App\Shop\Services\Media\MediaVariantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generate + store an uploaded image's responsive variants (thumb/medium/large +
 * WebP siblings) off the request path. Idempotent: re-running simply regenerates
 * and overwrites the deterministic variant files, so a retry is always safe.
 */
class ProcessMediaImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly string $mediaId) {}

    public function handle(MediaVariantService $variants): void
    {
        $media = ShopMedia::find($this->mediaId);
        if ($media instanceof ShopMedia) {
            $variants->generate($media);
        }
    }
}
