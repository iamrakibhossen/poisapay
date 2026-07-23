<?php

declare(strict_types=1);

namespace App\Domain\Compliance;

use App\Domain\Audit\ActivityLogger;
use App\Models\Admin;
use App\Models\ComplianceListEntry;

/**
 * Persistent sanctions / watch / whitelist management + country-risk (Wave 5).
 * The single source of truth for operator-maintained lists, consulted by
 * screening (names) and KYT (addresses). Country risk combines the config
 * high-risk list with any denylisted country entries.
 */
class ComplianceListService
{
    public function has(string $list, string $kind, string $value): bool
    {
        $needle = mb_strtolower(trim($value));
        if ($needle === '') {
            return false;
        }

        return ComplianceListEntry::where('list', $list)
            ->where('kind', $kind)
            ->whereRaw('LOWER(value) = ?', [$needle])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }

    public function isDenied(string $kind, string $value): bool
    {
        return $this->has('denylist', $kind, $value);
    }

    public function isWatched(string $kind, string $value): bool
    {
        return $this->has('watchlist', $kind, $value);
    }

    public function isWhitelisted(string $kind, string $value): bool
    {
        return $this->has('whitelist', $kind, $value);
    }

    /** 'high' when a country is config-flagged high-risk or explicitly denylisted; else null. */
    public function countryRisk(?string $country): ?string
    {
        if (! $country) {
            return null;
        }
        $upper = strtoupper($country);
        $configHigh = array_map('strtoupper', (array) config('poisapay.security.high_risk_countries', []));

        if (in_array($upper, $configHigh, true) || $this->isDenied('country', $upper)) {
            return 'high';
        }

        return null;
    }

    public function add(string $list, string $kind, string $value, ?string $reason = null, ?string $source = 'manual', ?Admin $by = null): ComplianceListEntry
    {
        $entry = ComplianceListEntry::create([
            'list' => $list, 'kind' => $kind, 'value' => trim($value),
            'reason' => $reason, 'source' => $source, 'added_by' => $by?->id,
        ]);

        ActivityLogger::log('compliance.list.added', $entry, [
            'list' => $list, 'kind' => $kind, 'value' => $value,
        ]);

        return $entry;
    }

    public function remove(string $id): void
    {
        $entry = ComplianceListEntry::find($id);
        if ($entry) {
            $entry->delete();
            ActivityLogger::log('compliance.list.removed', null, [
                'list' => $entry->list, 'kind' => $entry->kind, 'value' => $entry->value,
            ]);
        }
    }
}
