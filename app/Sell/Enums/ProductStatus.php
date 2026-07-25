<?php

declare(strict_types=1);

namespace App\Sell\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function isBuyable(): bool
    {
        return $this === self::Published;
    }
}
