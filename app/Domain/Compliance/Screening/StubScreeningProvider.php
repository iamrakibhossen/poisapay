<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Screening;

use App\Domain\Compliance\ComplianceListService;
use App\Domain\Compliance\Contracts\ScreeningProvider;
use App\Domain\Compliance\DTO\ScreeningOutcome;
use App\Enums\ScreeningStatus;
use App\Models\KycProfile;
use App\Models\User;

/**
 * Deterministic screening stub (TDD §10.2). Screens the subject name against the
 * persistent sanctions denylist/watchlist (and legacy settings lists), plus
 * country risk from the subject's KYC profile; every other user is Clear/0.
 * Thresholds match a real provider's semantics so a flagged fixture returns
 * Review/Hit — a live vendor drops in by implementing {@see ScreeningProvider}.
 */
final class StubScreeningProvider implements ScreeningProvider
{
    /** Score at or above this is escalated for manual review. */
    private const REVIEW_THRESHOLD = 80;

    /** Score at or above this is a confirmed hit. */
    private const HIT_THRESHOLD = 95;

    public function __construct(private readonly ComplianceListService $lists) {}

    public function name(): string
    {
        return 'stub';
    }

    public function evaluate(User $user): ScreeningOutcome
    {
        $needle = strtolower(trim((string) ($user->name ?? '')));

        // Legacy settings lists remain honoured alongside the persistent lists.
        $denylist = array_map('strtolower', (array) getSetting('aml_sanctions_denylist', []));
        $watchlist = array_map('strtolower', (array) getSetting('aml_watchlist', []));

        $matches = [];
        $score = 0;

        $denied = $needle !== '' && (in_array($needle, $denylist, true) || $this->lists->isDenied('name', $needle));
        $watched = $needle !== '' && (in_array($needle, $watchlist, true) || $this->lists->isWatched('name', $needle));

        if ($denied) {
            $score = self::HIT_THRESHOLD;
            $matches[] = ['list' => 'sanctions', 'name' => $needle];
        } elseif ($watched) {
            $score = self::REVIEW_THRESHOLD;
            $matches[] = ['list' => 'watchlist', 'name' => $needle];
        }

        // Country risk (does not downgrade a hit; escalates a clear to review).
        // Direct query avoids the latestOfMany() MAX(uuid) that Postgres rejects.
        $country = KycProfile::where('user_id', $user->id)->latest()->value('country');
        if ($score < self::REVIEW_THRESHOLD && $this->lists->countryRisk($country) === 'high') {
            $score = self::REVIEW_THRESHOLD;
            $matches[] = ['list' => 'country_risk', 'country' => strtoupper((string) $country)];
        }

        $status = match (true) {
            $score >= self::HIT_THRESHOLD => ScreeningStatus::Hit,
            $score >= self::REVIEW_THRESHOLD => ScreeningStatus::Review,
            default => ScreeningStatus::Clear,
        };

        return new ScreeningOutcome($status, $score, $matches);
    }
}
