<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Admin platform settings (DollarHub structure — controller + Blade forms, not
 * Livewire). Each section is a plain `<form method="POST" @method('PUT')>` that
 * validates against {@see self::sections()} and persists via the settings engine.
 */
class SettingController extends Controller
{
    /** Section key => [group, keys, rules]. Shared by the view and the updater. */
    public static function sections(): array
    {
        return [
            'general' => [
                'group' => 'general',
                'rules' => [
                    'site_name' => 'required|string|max:80',
                    'site_slogan' => 'nullable|string|max:160',
                    'base_currency' => 'required|string|max:8',
                    'support_email' => 'required|email|max:160',
                    'maintenance_mode' => 'boolean',
                ],
                'booleans' => ['maintenance_mode'],
            ],
            'branding' => [
                'group' => 'branding',
                'rules' => [
                    'primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                    'secondary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                    'site_logo' => 'nullable|string|max:255',
                    'site_favicon' => 'nullable|string|max:255',
                ],
                'booleans' => [],
            ],
            'auth' => [
                'group' => 'auth',
                'rules' => [
                    'allow_registration' => 'boolean',
                    'email_verification_required' => 'boolean',
                    'phone_verification_required' => 'boolean',
                    'two_factor_required' => 'boolean',
                    'otp_ttl_seconds' => 'required|integer|min:30|max:3600',
                    'otp_length' => 'required|integer|min:4|max:10',
                    'otp_daily_cap' => 'required|integer|min:1|max:100',
                ],
                'booleans' => ['allow_registration', 'email_verification_required', 'phone_verification_required', 'two_factor_required'],
            ],
            'deposit' => [
                'group' => 'deposit',
                'rules' => [
                    'deposit_enabled' => 'boolean',
                    'deposit_fee_percent' => 'required|numeric|min:0|max:100',
                ],
                'booleans' => ['deposit_enabled'],
            ],
            'withdrawal' => [
                'group' => 'withdrawal',
                'rules' => [
                    'withdrawal_enabled' => 'boolean',
                    'withdrawal_fee_percent_coin' => 'required|numeric|min:0|max:100',
                    'withdrawal_fee_percent_fiat' => 'required|numeric|min:0|max:100',
                    'withdrawal_auto_approve_limit' => 'required|integer|min:0',
                    'min_withdrawal_usd' => 'required|integer|min:0',
                    'daily_withdrawal_count' => 'required|integer|min:0|max:1000',
                ],
                'booleans' => ['withdrawal_enabled'],
            ],
            'transfer' => [
                'group' => 'transfer',
                'rules' => [
                    'transfer_enabled' => 'boolean',
                ],
                'booleans' => ['transfer_enabled'],
            ],
            'kyc' => [
                'group' => 'kyc',
                'rules' => [
                    'kyc_basic_daily_withdrawal_ceiling' => 'required|integer|min:0',
                    'kyc_full_daily_withdrawal_ceiling' => 'required|integer|min:0',
                    'kyc_basic_can_issue_card' => 'boolean',
                    'kyc_full_can_issue_card' => 'boolean',
                ],
                'booleans' => ['kyc_basic_can_issue_card', 'kyc_full_can_issue_card'],
            ],
            'risk' => [
                'group' => 'risk',
                'rules' => [
                    'risk_weight_large_amount' => 'required|integer|min:0|max:100',
                    'risk_weight_velocity' => 'required|integer|min:0|max:100',
                    'risk_weight_new_account' => 'required|integer|min:0|max:100',
                    'risk_weight_new_destination' => 'required|integer|min:0|max:100',
                    'risk_velocity_count' => 'required|integer|min:1|max:1000',
                    'risk_velocity_window_hours' => 'required|integer|min:1|max:168',
                    'risk_fresh_account_hours' => 'required|integer|min:0|max:8760',
                    'risk_band_critical' => 'required|integer|min:0|max:100',
                    'risk_band_high' => 'required|integer|min:0|max:100',
                    'risk_band_medium' => 'required|integer|min:0|max:100',
                ],
                'booleans' => [],
            ],
            'exchange' => [
                'group' => 'exchange',
                'rules' => [
                    'exchange_enabled' => 'boolean',
                    'exchange_spread_bps' => 'required|integer|min:0|max:10000',
                    'exchange_restrict_pairs' => 'boolean',
                ],
                'booleans' => ['exchange_enabled', 'exchange_restrict_pairs'],
            ],
            'cards' => [
                'group' => 'cards',
                'rules' => [
                    'cards_enabled' => 'boolean',
                    'card_price_virtual' => 'required|numeric|min:0|max:100000',
                    'card_price_physical' => 'required|numeric|min:0|max:100000',
                    'card_fee_bps' => 'required|integer|min:0|max:10000',
                    'card_default_daily_limit' => 'required|integer|min:0',
                    'card_default_per_tx_limit' => 'required|integer|min:0',
                    'card_dispute_window_days' => 'required|integer|min:0|max:365',
                    'card_allow_physical' => 'boolean',
                    'card_reveal_enabled' => 'boolean',
                ],
                'booleans' => ['cards_enabled', 'card_allow_physical', 'card_reveal_enabled'],
            ],
            'merchant' => [
                'group' => 'merchant',
                'rules' => [
                    'merchant_enabled' => 'boolean',
                    'merchant_fee_bps' => 'required|integer|min:0|max:10000',
                    'merchant_invoice_ttl_minutes' => 'required|integer|min:1|max:10080',
                    'merchant_auto_approve' => 'boolean',
                    'merchant_allow_refunds' => 'boolean',
                ],
                'booleans' => ['merchant_enabled', 'merchant_auto_approve', 'merchant_allow_refunds'],
            ],
            'shop' => [
                'group' => 'shop',
                'rules' => [
                    'shop_enabled' => 'boolean',
                    'shop_commission_bps_free' => 'required|integer|min:0|max:10000',
                    'shop_commission_bps_pro' => 'required|integer|min:0|max:10000',
                    'shop_commission_bps_business' => 'required|integer|min:0|max:10000',
                    'shop_refund_window_days' => 'required|integer|min:0|max:365',
                    'shop_earnings_hold' => 'boolean',
                    'shop_custom_domains' => 'boolean',
                    'shop_download_limit' => 'required|integer|min:1|max:100',
                    'shop_download_ttl_days' => 'required|integer|min:1|max:365',
                ],
                'booleans' => ['shop_enabled', 'shop_earnings_hold', 'shop_custom_domains'],
            ],
            'p2p' => [
                'group' => 'p2p',
                'rules' => [
                    'p2p_enabled' => 'boolean',
                    'p2p_taker_fee_bps' => 'required|integer|min:0|max:10000',
                    'p2p_order_expiry_minutes' => 'required|integer|min:5|max:180',
                    'p2p_require_full_kyc' => 'boolean',
                    'p2p_auto_match' => 'boolean',
                    'p2p_risk_enabled' => 'boolean',
                    'p2p_daily_limit_basic' => 'required|integer|min:0',
                    'p2p_daily_limit_full' => 'required|integer|min:0',
                    'p2p_max_orders_per_hour' => 'required|integer|min:0|max:1000',
                    'p2p_high_value_usdt' => 'required|integer|min:0',
                ],
                'booleans' => ['p2p_enabled', 'p2p_require_full_kyc', 'p2p_auto_match', 'p2p_risk_enabled'],
            ],
            'rewards' => [
                'group' => 'rewards',
                'rules' => [
                    'rewards_enabled' => 'boolean',
                    'referral_enabled' => 'boolean',
                ],
                'booleans' => ['rewards_enabled', 'referral_enabled'],
            ],
            'compliance' => [
                'group' => 'compliance',
                'rules' => [
                    'aml_large_amount_minor' => 'required|integer|min:0',
                    'aml_velocity_window_hours' => 'required|integer|min:1|max:168',
                    'aml_screening_enabled' => 'boolean',
                    'aml_auto_open_case' => 'boolean',
                    'aml_sanctions_denylist' => 'nullable|string|max:5000',
                    'aml_watchlist' => 'nullable|string|max:5000',
                    'security_high_risk_countries' => 'nullable|string|max:1000',
                ],
                'booleans' => ['aml_screening_enabled', 'aml_auto_open_case'],
                'arrays' => ['aml_sanctions_denylist', 'aml_watchlist', 'security_high_risk_countries'],
            ],
            'localization' => [
                'group' => 'localization',
                'rules' => [
                    'default_locale' => 'required|string|max:8',
                    'available_locales' => 'nullable|string|max:255',
                ],
                'booleans' => [],
                'arrays' => ['available_locales'],
            ],
            'announcement' => [
                'group' => 'announcement',
                'rules' => [
                    'header_announcement_enabled' => 'boolean',
                    'header_announcement_text' => 'nullable|string|max:255',
                    'header_announcement_link' => 'nullable|string|max:255',
                ],
                'booleans' => ['header_announcement_enabled'],
            ],
        ];
    }

    public function index(string $section = 'general'): View
    {
        $this->authorizeSettings();
        abort_unless(array_key_exists($section, self::sections()), 404);

        return view('admin.settings.index', ['section' => $section]);
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        $this->authorizeSettings();

        $config = self::sections()[$section] ?? abort(404);

        // Unchecked checkboxes are absent from the payload — default them to false.
        foreach ($config['booleans'] as $key) {
            $request->merge([$key => $request->boolean($key)]);
        }

        $data = $request->validate($config['rules']);

        $arrayKeys = $config['arrays'] ?? [];
        foreach ($data as $key => $value) {
            // Comma/newline lists persist as clean arrays for the settings engine.
            if (in_array($key, $arrayKeys, true)) {
                $value = collect(preg_split('/[\n,]+/', (string) $value))
                    ->map(fn ($v) => trim($v))
                    ->filter()
                    ->values()
                    ->all();
            }
            updateSetting($key, $value, $config['group']);
        }

        ActivityLogger::log('settings.updated', null, ['section' => $section, 'keys' => array_keys($data)]);

        return redirect()
            ->route('admin.settings', $section)
            ->with('success', ucfirst($section).' settings updated.');
    }

    private function authorizeSettings(): void
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin && ($admin->can('manage-settings') || $admin->hasRole('super-admin')), 403);
    }
}
