<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/**
 * Lifecycle of a P2P ad. Only `Active` ads are matchable in the marketplace;
 * `Paused` is a temporary owner hold, `Disabled` an automatic/admin stop,
 * `Archived` a soft retirement.
 */
enum P2pAdStatus: string
{
    use HasMeta;

    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Disabled = 'disabled';
    case Archived = 'archived';

    public function isMatchable(): bool
    {
        return $this === self::Active;
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Draft, self::Paused => 'warning',
            self::Disabled => 'danger',
            self::Archived => 'muted',
        };
    }
}
