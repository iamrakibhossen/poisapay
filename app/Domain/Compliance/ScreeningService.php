<?php

declare(strict_types=1);

namespace App\Domain\Compliance;

use App\Enums\ScreeningStatus;
use App\Models\ScreeningResult;
use App\Models\User;

/**
 * Deterministic sanctions / PEP screening stub (TDD §10.2). Real providers
 * (e.g. ComplyAdvantage) will replace `evaluate()`; until then every normal user
 * screens Clear with a score of 0. The pseudo score is derived from a stable
 * hash of the subject so the same input always yields the same outcome, and the
 * thresholds are wired so a genuinely flagged fixture could return Review/Hit.
 */
class ScreeningService
{
    private const PROVIDER = 'stub';

    /** Score at or above this is escalated for manual review. */
    private const REVIEW_THRESHOLD = 80;

    /** Score at or above this is a confirmed hit. */
    private const HIT_THRESHOLD = 95;

    public function screen(User $user, string $context, ?string $subjectId = null): ScreeningResult
    {
        [$status, $score, $matches] = $this->evaluate($user);

        return ScreeningResult::create([
            'user_id' => $user->id,
            'context' => $context,
            'subject_id' => $subjectId,
            'provider' => self::PROVIDER,
            'result' => $status,
            'score' => $score,
            'matches' => $matches ?: null,
        ]);
    }

    /**
     * Compute a deterministic outcome. Normal users default to Clear/0; the
     * threshold branches exist so the same code path serves flagged fixtures.
     *
     * @return array{0: ScreeningStatus, 1: int, 2: array<int, mixed>}
     */
    private function evaluate(User $user): array
    {
        $needle = strtolower(trim((string) ($user->name ?? '')));

        // A curated denylist would be injected here; empty by default keeps
        // every real user Clear until a provider is wired in.
        // Admin-configurable watchlists (lowercased names). A real provider
        // (ComplyAdvantage, etc.) replaces these lookups; the thresholds stay.
        $denylist = array_map('strtolower', (array) getSetting('aml_sanctions_denylist', []));
        $watchlist = array_map('strtolower', (array) getSetting('aml_watchlist', []));

        $matches = [];
        $score = 0;
        if ($needle !== '' && in_array($needle, $denylist, true)) {
            $score = self::HIT_THRESHOLD;
            $matches[] = ['list' => 'sanctions', 'name' => $needle];
        } elseif ($needle !== '' && in_array($needle, $watchlist, true)) {
            $score = self::REVIEW_THRESHOLD;
            $matches[] = ['list' => 'watchlist', 'name' => $needle];
        }

        $status = match (true) {
            $score >= self::HIT_THRESHOLD => ScreeningStatus::Hit,
            $score >= self::REVIEW_THRESHOLD => ScreeningStatus::Review,
            default => ScreeningStatus::Clear,
        };

        return [$status, $score, $matches];
    }
}
