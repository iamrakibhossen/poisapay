<?php

declare(strict_types=1);

namespace App\Domain\Risk;

use App\Enums\RiskLevel;

final readonly class RiskAssessment
{
    /** @param  array<int, string>  $reasons */
    public function __construct(
        public int $score,
        public RiskLevel $level,
        public array $reasons = [],
    ) {}

    public function requiresManualReview(): bool
    {
        return $this->level->requiresManualReview();
    }
}
