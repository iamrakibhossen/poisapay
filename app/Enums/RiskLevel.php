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

    /**
     * @param  array{critical:int,high:int,medium:int}|null  $bands  score thresholds;
     *                                                               passed in by callers that source them from admin settings so the enum stays
     *                                                               free of container/config calls. Defaults reproduce the original hardcoded bands.
     */
    public static function fromScore(int $score, ?array $bands = null): self
    {
        $bands ??= ['critical' => 80, 'high' => 50, 'medium' => 25];

        return match (true) {
            $score >= $bands['critical'] => self::Critical,
            $score >= $bands['high'] => self::High,
            $score >= $bands['medium'] => self::Medium,
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
