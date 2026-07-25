<?php

declare(strict_types=1);

namespace App\Shop\Enums;

enum SalesPageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function isLive(): bool
    {
        return $this === self::Published;
    }
}
