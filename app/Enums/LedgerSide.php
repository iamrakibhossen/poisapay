<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

enum LedgerSide: string
{
    use HasMeta;

    case Debit = 'debit';
    case Credit = 'credit';

    public function opposite(): self
    {
        return $this === self::Debit ? self::Credit : self::Debit;
    }

    public function sign(): int
    {
        return $this === self::Debit ? 1 : -1;
    }
}
