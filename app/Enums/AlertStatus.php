<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** AML alert lifecycle (TDD §10.2). */
enum AlertStatus: string
{
    use HasMeta;

    case Open = 'open';
    case Cleared = 'cleared';
    case Escalated = 'escalated';

    /** Cleared is the only terminal state; an escalated alert is still active work. */
    public function isResolved(): bool
    {
        return $this === self::Cleared;
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Cleared => 'success',
            self::Escalated => 'danger',
        };
    }
}
