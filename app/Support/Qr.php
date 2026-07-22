<?php

declare(strict_types=1);

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** Render a QR code to an inline SVG string (no external calls). */
final class Qr
{
    public static function svg(string $data, int $size = 220): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        // Strip the leading XML declaration prolog so the SVG can be embedded
        // inline in HTML without rendering the declaration as stray text.
        $svg = $writer->writeString($data);

        return preg_replace('/^\s*<\?xml.*?\?>\s*/is', '', $svg) ?? $svg;
    }
}
