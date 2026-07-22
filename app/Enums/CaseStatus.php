<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** Compliance investigation lifecycle (TDD §10.4). */
enum CaseStatus: string
{
    use HasMeta;

    case Open = 'open';
    case Investigating = 'investigating';
    case Closed = 'closed';

    public function isOpen(): bool
    {
        return $this !== self::Closed;
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Investigating => 'info',
            self::Closed => 'gray',
        };
    }
}
