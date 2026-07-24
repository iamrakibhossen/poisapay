<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\KycTier;

/**
 * Admin-configurable KYC tier policy. The numbers still ship in {@see KycTier}
 * as fallback defaults, so behaviour is identical until an operator edits the
 * "KYC & Limits" settings section. Keys are flat + underscored per tier, e.g.
 * `kyc_basic_daily_withdrawal_ceiling`.
 */
class KycPolicy
{
    /**
     * Daily withdrawal ceiling for a tier, in USD minor units.
     * '0' means unlimited for a tier that is otherwise allowed to withdraw.
     */
    public static function dailyWithdrawalCeilingMinor(KycTier $tier): string
    {
        return (string) getSetting(
            "kyc_{$tier->value}_daily_withdrawal_ceiling",
            $tier->dailyWithdrawalCeiling(),
        );
    }

    /** Whether a tier may issue cards (highest tier only, by default). */
    public static function canIssueCard(KycTier $tier): bool
    {
        return (bool) getSetting(
            "kyc_{$tier->value}_can_issue_card",
            $tier->canIssueCard(),
        );
    }
}
