<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** Withdrawal / transaction risk banding (TDD §10.3). */
enum RiskLevel: string
{
    use HasMeta;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 80 => self::Critical,
            $score >= 50 => self::High,
            $score >= 25 => self::Medium,
            default => self::Low,
        };
    }

    /** Above Low requires manual admin approval (auto-approve gate). */
    public function requiresManualReview(): bool
    {
        return $this !== self::Low;
    }

    /** Ordinal severity, for comparing/escalating. */
    public function rank(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Medium => 1,
            self::High => 2,
            self::Critical => 3,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'success',
            self::Medium => 'warning',
            self::High => 'danger',
            self::Critical => 'danger',
        };
    }
}
