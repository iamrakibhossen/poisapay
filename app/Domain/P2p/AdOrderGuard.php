<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Models\P2pAd;
use App\Models\P2pMerchantProfile;
use App\Models\P2pOrder;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Enforces the advertiser-configured trading constraints that live on a P2P ad
 * but were previously advisory-only: trading hours, per-ad daily volume cap,
 * merchant completion requirement, and country restriction. Without these, the
 * limits shown to takers in the marketplace are bypassable.
 *
 * Split into two entry points by concurrency needs:
 *  - {@see assertOrderable()} — static gates; run before the escrow transaction.
 *  - {@see assertWithinAdDailyLimit()} — reads today's volume for the ad and must
 *    run under the ad row lock inside the order transaction so two concurrent
 *    orders on the same ad cannot both slip past the cap.
 *
 * trade_hours JSON shape (empty/null ⇒ always open):
 *   {"tz": "Asia/Dhaka", "windows": [{"days": [1,2,3,4,5], "start": "09:00", "end": "22:00"}]}
 * A bare list of windows (no tz wrapper) is also accepted; `days` uses Carbon's
 * dayOfWeek (0=Sun … 6=Sat), absent/empty ⇒ every day; a window whose end ≤ start
 * wraps past midnight.
 */
class AdOrderGuard
{
    /** Concurrency-independent gates — cheap, fail fast before any lock. */
    public function assertOrderable(P2pAd $ad, User $taker): void
    {
        $this->assertWithinTradeHours($ad);
        $this->assertMeetsMerchantRequirement($ad, $taker);
        $this->assertCountryAllowed($ad, $taker);
    }

    public function assertWithinTradeHours(P2pAd $ad): void
    {
        if (empty($ad->trade_hours)) {
            return;
        }

        [$tz, $windows] = $this->normaliseTradeHours($ad->trade_hours);
        if (empty($windows)) {
            return;
        }

        $now = Carbon::now($tz);
        $dow = (int) $now->dayOfWeek;
        $minutes = $now->hour * 60 + $now->minute;

        foreach ($windows as $window) {
            if (! empty($window['days']) && ! in_array($dow, $window['days'], true)) {
                continue;
            }

            $start = $this->toMinutes($window['start']);
            $end = $this->toMinutes($window['end']);
            $open = $end > $start
                ? ($minutes >= $start && $minutes < $end)
                : ($minutes >= $start || $minutes < $end); // wraps midnight

            if ($open) {
                return;
            }
        }

        throw new RuntimeException('This ad is outside its trading hours right now.');
    }

    /**
     * The taker's completion rate must meet the ad's requirement. Brand-new
     * traders (no finished trades) aren't judged on a rate they can't have — the
     * gate filters poor history, not newcomers.
     */
    public function assertMeetsMerchantRequirement(P2pAd $ad, User $taker): void
    {
        $min = (int) ($ad->min_completion_bps ?? 0);
        if ($min <= 0) {
            return;
        }

        $profile = P2pMerchantProfile::query()->where('user_id', $taker->getKey())->first();
        if (! $profile || (int) $profile->trade_count === 0) {
            return;
        }

        if ((int) $profile->completion_rate_bps < $min) {
            throw new RuntimeException('Your completion rate does not meet this ad’s merchant requirement.');
        }
    }

    /**
     * If the ad restricts countries, the taker's residence (from KYC) must be in
     * the list. When we can't determine the taker's country we don't restrict —
     * the ad's KYC-tier gate already governs who may trade.
     */
    public function assertCountryAllowed(P2pAd $ad, User $taker): void
    {
        $allowed = array_values(array_filter(array_map(
            static fn ($c) => strtoupper(trim((string) $c)),
            $ad->countries ?? [],
        )));
        if (empty($allowed)) {
            return;
        }

        $country = $taker->residenceCountry();
        if ($country === null) {
            return;
        }

        if (! in_array($country, $allowed, true)) {
            throw new RuntimeException('This ad is not available in your country.');
        }
    }

    /** Must be called with a row-locked ad inside the order transaction. */
    public function assertWithinAdDailyLimit(P2pAd $lockedAd, Money $amount): void
    {
        if ($lockedAd->daily_limit === null) {
            return;
        }

        $limit = BigInteger::of((string) $lockedAd->daily_limit);
        if ($limit->isLessThanOrEqualTo(BigInteger::zero())) {
            return;
        }

        $usedToday = P2pOrder::query()
            ->where('ad_id', $lockedAd->getKey())
            ->whereNotIn('status', ['cancelled', 'expired', 'force_cancelled'])
            ->where('created_at', '>=', now()->startOfDay())
            ->pluck('crypto_amount')
            ->reduce(
                static fn (BigInteger $carry, $amt) => $carry->plus(BigInteger::of((string) $amt)),
                BigInteger::zero(),
            );

        if ($usedToday->plus($amount->base)->isGreaterThan($limit)) {
            throw new RuntimeException('This ad has reached its daily trading limit — try again tomorrow.');
        }
    }

    /** @return array{0: string, 1: array<int, array{days: array<int,int>, start: string, end: string}>} */
    private function normaliseTradeHours(array $config): array
    {
        $tz = $config['tz'] ?? config('app.timezone', 'UTC');
        $rawWindows = $config['windows'] ?? (array_is_list($config) ? $config : []);

        $windows = [];
        foreach ($rawWindows as $window) {
            if (! is_array($window)) {
                continue;
            }
            $days = $window['days'] ?? ($window['day'] ?? []);
            $days = is_array($days) ? $days : [$days];

            $windows[] = [
                'days' => array_map('intval', $days),
                'start' => (string) ($window['start'] ?? '00:00'),
                'end' => (string) ($window['end'] ?? '24:00'),
            ];
        }

        return [$tz, $windows];
    }

    /** "HH:MM" → minutes since midnight; "24:00" ⇒ 1440. */
    private function toMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time, 2), 2, '0');

        return ((int) $h) * 60 + (int) $m;
    }
}
