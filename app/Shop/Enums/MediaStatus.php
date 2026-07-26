<?php

declare(strict_types=1);

namespace App\Shop\Enums;

/**
 * Lifecycle of a media asset. An upload is immediately usable (the original is
 * stored + served synchronously); `Processing` only reflects that responsive
 * variants are still being generated on the queue.
 */
enum MediaStatus: string
{
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
