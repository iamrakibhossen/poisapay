<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Models\P2pMerchantProfile;

/**
 * Merchant presence automation. Activity (any P2P request) marks a merchant
 * online and stamps last_seen_at; a periodic sweep flips inactive merchants
 * offline. Only existing merchants (with a profile) are tracked — browsing
 * buyers never gain a profile.
 */
class P2pPresenceService
{
    public function markActive(string $userId): void
    {
        $profile = P2pMerchantProfile::query()->where('user_id', $userId)->first();
        if ($profile === null) {
            return;
        }

        // Throttle writes — one update per minute is plenty for presence.
        if ($profile->last_seen_at !== null && $profile->last_seen_at->gt(now()->subMinute())) {
            return;
        }

        $profile->update([
            'last_seen_at' => now(),
            // A merchant on vacation stays hidden/offline even while active.
            'is_online' => ! $profile->vacation_mode,
        ]);
    }

    /** Flip merchants offline after the inactivity window. Returns rows updated. */
    public function sweepOffline(): int
    {
        $minutes = max(1, (int) getSetting('p2p_presence_timeout_minutes', 10));

        return P2pMerchantProfile::query()
            ->where('is_online', true)
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subMinutes($minutes)))
            ->update(['is_online' => false]);
    }
}
