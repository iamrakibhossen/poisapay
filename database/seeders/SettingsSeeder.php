<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/** Default admin-configurable settings across all groups (nothing hardcoded). */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'general' => [
                'site_name' => 'PoisaPay',
                'site_slogan' => 'The multi-chain wallet for Bangladesh',
                'base_currency' => 'BDT',
                'maintenance_mode' => false,
                'support_email' => 'support@poisapay.test',
            ],
            'branding' => [
                'primary_color' => '#FFC107',
                'secondary_color' => '#1F2937',
                'site_logo' => null,
                'site_favicon' => null,
            ],
            'auth' => [
                'allow_registration' => true,
                'email_verification_required' => true,
                'phone_verification_required' => false,
                'two_factor_required' => false,
                // OTP policy (defaults mirror config/poisapay.php).
                'otp_ttl_seconds' => 300,
                'otp_length' => 6,
                'otp_daily_cap' => 10,
            ],
            'features' => [
                'deposit_enabled' => true,
                'withdrawal_enabled' => true,
                'transfer_enabled' => true,
                'exchange_enabled' => true,
                'cards_enabled' => true,
                'merchant_enabled' => true,
                'rewards_enabled' => true,
                'referral_enabled' => true,
                'exchange_restrict_pairs' => true,
            ],
            'fees' => [
                'exchange_spread_bps' => 75,
                'exchange_fee_bps' => 0,   // optional platform swap fee on top of spread (0 = spread-only)
                // withdrawal_fee_percent_coin / _fiat are intentionally NOT seeded — they
                // fall back to the legacy withdrawal_fee_percent then config default (1%),
                // matching prior behaviour until an operator sets them explicitly.
                'withdrawal_auto_approve_limit' => 50000,
                'card_fee_bps' => 100,
                'merchant_fee_bps' => 100,
            ],
            'merchant' => [
                'merchant_auto_approve' => true,
                'merchant_invoice_ttl_minutes' => 60,
                'merchant_allow_refunds' => true,
            ],
            'compliance' => [
                'aml_screening_enabled' => true,
                'aml_large_amount_minor' => 100000,   // $1,000.00 flags a large-amount alert
                'aml_velocity_window_hours' => 24,
                'aml_auto_open_case' => true,
                // High-risk countries (ISO-3166 alpha-2); mirrors config fallback.
                'security_high_risk_countries' => ['KP', 'IR', 'SY', 'CU'],
            ],
            'cards' => [
                'card_default_daily_limit' => 500000,   // $5,000.00 minor units
                'card_default_per_tx_limit' => 200000,  // $2,000.00 minor units
                'card_dispute_window_days' => 60,
                'card_allow_physical' => true,
                'card_reveal_enabled' => true,
            ],
            'kyc' => [
                // Rolling-24h withdrawal ceilings in USD minor units (0 = unlimited).
                // Defaults mirror the KycTier enum fallbacks (Basic $1,000 / Full $25,000).
                'kyc_basic_daily_withdrawal_ceiling' => 100000,
                'kyc_full_daily_withdrawal_ceiling' => 2500000,
                'kyc_basic_can_issue_card' => false,
                'kyc_full_can_issue_card' => true,
            ],
            'risk' => [
                // Withdrawal auto-approve risk scoring (defaults reproduce prior hardcoded values).
                'risk_weight_large_amount' => 40,
                'risk_weight_velocity' => 25,
                'risk_weight_new_account' => 20,
                'risk_weight_new_destination' => 10,
                'risk_velocity_count' => 5,
                'risk_velocity_window_hours' => 24,
                'risk_fresh_account_hours' => 24,
                'risk_band_critical' => 80,
                'risk_band_high' => 50,
                'risk_band_medium' => 25,
            ],
            'limits' => [
                'min_withdrawal_usd' => 1,
                'daily_withdrawal_count' => 10,
                // Swap guardrails (user-initiated only). Permissive defaults:
                // 'unverified' = no KYC gate, 0 = unlimited daily swap notional.
                'exchange_min_kyc' => 'unverified',
                'exchange_daily_limit_usd' => 0,
            ],
            'announcement' => [
                'header_announcement_enabled' => false,
                'header_announcement_text' => '',
                'header_announcement_link' => '',
            ],
            'localization' => [
                'default_locale' => 'en',
                'available_locales' => ['en', 'bn'],
            ],
        ];

        foreach ($defaults as $group => $pairs) {
            foreach ($pairs as $key => $value) {
                SystemSetting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
            }
        }
    }
}
