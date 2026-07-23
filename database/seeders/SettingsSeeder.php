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
            ],
            'cards' => [
                'card_default_daily_limit' => 500000,   // $5,000.00 minor units
                'card_default_per_tx_limit' => 200000,  // $2,000.00 minor units
                'card_dispute_window_days' => 60,
                'card_allow_physical' => true,
                'card_reveal_enabled' => true,
            ],
            'limits' => [
                'min_withdrawal_usd' => 1,
                'daily_withdrawal_count' => 10,
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
