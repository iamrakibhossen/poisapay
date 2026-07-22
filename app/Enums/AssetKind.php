<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** Crypto vs fiat asset — the F1 generalisation of the asset model. */
enum AssetKind: string
{
    use HasMeta;

    case Crypto = 'crypto';
    case Fiat = 'fiat';

    public function color(): string
    {
        return $this === self::Crypto ? 'info' : 'success';
    }
}
