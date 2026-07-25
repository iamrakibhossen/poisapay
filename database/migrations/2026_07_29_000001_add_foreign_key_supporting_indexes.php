<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Production-readiness pass: PostgreSQL does not auto-create an index for a
 * foreign key. Every FK below lacked a leading index — hurting joins, lookups,
 * and (critically) locking on parent-row UPDATE/DELETE. One B-tree per FK.
 */
return new class extends Migration
{
    /** @var list<array{0:string,1:string}> [table, fk_column] */
    private array $fks = [
        ['address_book_entries', 'asset_id'],
        ['address_book_entries', 'chain_id'],
        ['aml_alerts', 'resolved_by'],
        ['announcements', 'sent_by'],
        ['assets', 'currency_id'],
        ['audit_logs', 'user_id'],
        ['card_authorizations', 'hold_entry_id'],
        ['card_authorizations', 'quote_id'],
        ['card_authorizations', 'settle_entry_id'],
        ['card_disputes', 'authorization_id'],
        ['card_disputes', 'entry_id'],
        ['card_provider_logs', 'card_provider_id'],
        ['cards', 'card_provider_id'],
        ['cards', 'frozen_by'],
        ['cold_refill_requests', 'approved_by'],
        ['cold_refill_requests', 'chain_id'],
        ['compliance_cases', 'opened_by'],
        ['compliance_cases', 'user_id'],
        ['conversions', 'entry_id'],
        ['custody_xpubs', 'chain_id'],
        ['deposit_addresses', 'user_id'],
        ['deposits', 'credit_entry_id'],
        ['deposits', 'deposit_address_id'],
        ['deposits', 'deposit_method_id'],
        ['journal_entries', 'reverses_entry_id'],
        ['kyc_profiles', 'user_id'],
        ['ledger_accounts', 'asset_id'],
        ['merchant_invoices', 'asset_id'],
        ['merchant_invoices', 'entry_id'],
        ['merchant_invoices', 'payer_id'],
        ['merchants', 'settlement_asset_id'],
        ['onchain_txs', 'asset_id'],
        ['otp_codes', 'user_id'],
        ['p2p_ad_payment_methods', 'payment_method_id'],
        ['p2p_ads', 'asset_id'],
        ['p2p_dispute_evidence', 'dispute_id'],
        ['p2p_disputes', 'assigned_admin_id'],
        ['p2p_disputes', 'opened_by'],
        ['p2p_disputes', 'resolved_by'],
        ['p2p_escrows', 'asset_id'],
        ['p2p_escrows', 'lock_entry_id'],
        ['p2p_escrows', 'release_entry_id'],
        ['p2p_escrows', 'user_id'],
        ['p2p_orders', 'asset_id'],
        ['p2p_orders', 'payment_method_id'],
        ['p2p_reviews', 'rater_id'],
        ['p2p_user_payment_methods', 'payment_method_id'],
        ['payout_accounts', 'asset_id'],
        ['payout_accounts', 'withdrawal_method_id'],
        ['profit_payouts', 'created_by'],
        ['profit_payouts', 'entry_id'],
        ['provider_accounts', 'card_provider_id'],
        ['ramp_orders', 'entry_id'],
        ['ramp_orders', 'fiat_asset_id'],
        ['referrals', 'referee_id'],
        ['referrals', 'reward_entry_id'],
        ['revenue_withdrawals', 'approved_by'],
        ['revenue_withdrawals', 'created_by'],
        ['revenue_withdrawals', 'entry_id'],
        ['revenue_withdrawals', 'reversal_entry_id'],
        ['reward_campaigns', 'asset_id'],
        ['reward_grants', 'asset_id'],
        ['reward_grants', 'entry_id'],
        ['reward_grants', 'user_id'],
        ['role_has_permissions', 'role_id'],
        ['shop_analytics_events', 'order_id'],
        ['shop_coupons', 'product_id'],
        ['shop_downloads', 'buyer_user_id'],
        ['shop_downloads', 'product_file_id'],
        ['shop_funnel_steps', 'offer_product_id'],
        ['shop_funnels', 'product_id'],
        ['shop_order_items', 'variant_id'],
        ['shop_orders', 'asset_id'],
        ['shop_orders', 'coupon_id'],
        ['shop_orders', 'funnel_id'],
        ['shop_orders', 'parent_order_id'],
        ['shop_orders', 'sales_page_id'],
        ['shop_page_revisions', 'author_user_id'],
        ['shop_products', 'price_asset_id'],
        ['shop_reviews', 'buyer_user_id'],
        ['shop_reviews', 'seller_id'],
        ['shop_sales_pages', 'bump_product_id'],
        ['shop_sales_pages', 'upsell_product_id'],
        ['shop_sellers', 'settlement_asset_id'],
        ['support_messages', 'author_id'],
        ['support_messages', 'ticket_id'],
        ['support_tickets', 'assigned_to'],
        ['support_tickets', 'user_id'],
        ['sweeps', 'deposit_address_id'],
        ['sweeps', 'onchain_tx_id'],
        ['sweeps', 'settle_entry_id'],
        ['trading_pairs', 'to_asset_id'],
        ['transfers', 'entry_id'],
        ['travel_rule_records', 'asset_id'],
        ['user_favorite_assets', 'asset_id'],
        ['user_spending_priority', 'asset_id'],
        ['users', 'referred_by'],
        ['webhook_deliveries', 'endpoint_id'],
        ['webhook_endpoints', 'user_id'],
        ['withdrawals', 'approved_by'],
        ['withdrawals', 'lock_entry_id'],
        ['withdrawals', 'onchain_tx_id'],
        ['withdrawals', 'settle_entry_id'],
    ];

    public function up(): void
    {
        foreach ($this->fks as [$table, $col]) {
            Schema::table($table, fn (Blueprint $t) => $t->index($col));
        }
    }

    public function down(): void
    {
        foreach ($this->fks as [$table, $col]) {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex([$col]));
        }
    }
};
