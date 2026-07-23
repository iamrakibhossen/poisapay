<?php

declare(strict_types=1);

namespace App\Domain\Compliance\DTO;

use App\Enums\ScreeningStatus;

/**
 * Neutral result of a sanctions / PEP / AML screen, independent of the vendor
 * that produced it. Providers translate their own payloads into this shape.
 */
final class ScreeningOutcome
{
    /**
     * @param  array<int, mixed>  $matches  raw match records (list name, matched entity, …)
     */
    public function __construct(
        public readonly ScreeningStatus $status,
        public readonly int $score,
        public readonly array $matches = [],
    ) {}
}
