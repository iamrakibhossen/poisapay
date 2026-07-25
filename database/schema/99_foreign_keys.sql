-- ============================================================
-- Module: foreign_keys (applied last — all tables exist)
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

ALTER TABLE ONLY public.account_balances
    ADD CONSTRAINT account_balances_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.ledger_accounts(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.address_book_entries
    ADD CONSTRAINT address_book_entries_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.address_book_entries
    ADD CONSTRAINT address_book_entries_chain_id_foreign FOREIGN KEY (chain_id) REFERENCES public.chains(id);
ALTER TABLE ONLY public.address_book_entries
    ADD CONSTRAINT address_book_entries_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.aml_alerts
    ADD CONSTRAINT aml_alerts_case_id_foreign FOREIGN KEY (case_id) REFERENCES public.compliance_cases(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.aml_alerts
    ADD CONSTRAINT aml_alerts_resolved_by_foreign FOREIGN KEY (resolved_by) REFERENCES public.admins(id);
ALTER TABLE ONLY public.aml_alerts
    ADD CONSTRAINT aml_alerts_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.announcements
    ADD CONSTRAINT announcements_sent_by_foreign FOREIGN KEY (sent_by) REFERENCES public.admins(id);
ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_chain_id_foreign FOREIGN KEY (chain_id) REFERENCES public.chains(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.card_authorizations
    ADD CONSTRAINT card_authorizations_card_id_foreign FOREIGN KEY (card_id) REFERENCES public.cards(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.card_authorizations
    ADD CONSTRAINT card_authorizations_funding_asset_id_foreign FOREIGN KEY (funding_asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.card_authorizations
    ADD CONSTRAINT card_authorizations_hold_entry_id_foreign FOREIGN KEY (hold_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.card_authorizations
    ADD CONSTRAINT card_authorizations_quote_id_foreign FOREIGN KEY (quote_id) REFERENCES public.fx_quotes(id);
ALTER TABLE ONLY public.card_authorizations
    ADD CONSTRAINT card_authorizations_settle_entry_id_foreign FOREIGN KEY (settle_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.card_disputes
    ADD CONSTRAINT card_disputes_authorization_id_foreign FOREIGN KEY (authorization_id) REFERENCES public.card_authorizations(id);
ALTER TABLE ONLY public.card_disputes
    ADD CONSTRAINT card_disputes_entry_id_foreign FOREIGN KEY (entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.card_metadata
    ADD CONSTRAINT card_metadata_card_id_foreign FOREIGN KEY (card_id) REFERENCES public.cards(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.card_provider_logs
    ADD CONSTRAINT card_provider_logs_card_id_foreign FOREIGN KEY (card_id) REFERENCES public.cards(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.card_provider_logs
    ADD CONSTRAINT card_provider_logs_card_provider_id_foreign FOREIGN KEY (card_provider_id) REFERENCES public.card_providers(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.cards
    ADD CONSTRAINT cards_card_provider_id_foreign FOREIGN KEY (card_provider_id) REFERENCES public.card_providers(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.cards
    ADD CONSTRAINT cards_frozen_by_foreign FOREIGN KEY (frozen_by) REFERENCES public.users(id);
ALTER TABLE ONLY public.cards
    ADD CONSTRAINT cards_replaced_by_foreign FOREIGN KEY (replaced_by) REFERENCES public.cards(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.cards
    ADD CONSTRAINT cards_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.cold_refill_requests
    ADD CONSTRAINT cold_refill_requests_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.admins(id);
ALTER TABLE ONLY public.cold_refill_requests
    ADD CONSTRAINT cold_refill_requests_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.cold_refill_requests
    ADD CONSTRAINT cold_refill_requests_chain_id_foreign FOREIGN KEY (chain_id) REFERENCES public.chains(id);
ALTER TABLE ONLY public.cold_refill_requests
    ADD CONSTRAINT cold_refill_requests_settle_entry_id_foreign FOREIGN KEY (settle_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.compliance_cases
    ADD CONSTRAINT compliance_cases_assigned_to_foreign FOREIGN KEY (assigned_to) REFERENCES public.admins(id);
ALTER TABLE ONLY public.compliance_cases
    ADD CONSTRAINT compliance_cases_opened_by_foreign FOREIGN KEY (opened_by) REFERENCES public.admins(id);
ALTER TABLE ONLY public.compliance_cases
    ADD CONSTRAINT compliance_cases_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.compliance_list_entries
    ADD CONSTRAINT compliance_list_entries_added_by_foreign FOREIGN KEY (added_by) REFERENCES public.admins(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.conversions
    ADD CONSTRAINT conversions_entry_id_foreign FOREIGN KEY (entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.conversions
    ADD CONSTRAINT conversions_quote_id_foreign FOREIGN KEY (quote_id) REFERENCES public.fx_quotes(id);
ALTER TABLE ONLY public.conversions
    ADD CONSTRAINT conversions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.custody_xpubs
    ADD CONSTRAINT custody_xpubs_chain_id_foreign FOREIGN KEY (chain_id) REFERENCES public.chains(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.deposit_addresses
    ADD CONSTRAINT deposit_addresses_chain_id_foreign FOREIGN KEY (chain_id) REFERENCES public.chains(id);
ALTER TABLE ONLY public.deposit_addresses
    ADD CONSTRAINT deposit_addresses_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.deposit_addresses
    ADD CONSTRAINT deposit_addresses_xpub_id_foreign FOREIGN KEY (xpub_id) REFERENCES public.custody_xpubs(id);
ALTER TABLE ONLY public.deposit_methods
    ADD CONSTRAINT deposit_methods_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.deposits
    ADD CONSTRAINT deposits_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.deposits
    ADD CONSTRAINT deposits_credit_entry_id_foreign FOREIGN KEY (credit_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.deposits
    ADD CONSTRAINT deposits_deposit_address_id_foreign FOREIGN KEY (deposit_address_id) REFERENCES public.deposit_addresses(id);
ALTER TABLE ONLY public.deposits
    ADD CONSTRAINT deposits_deposit_method_id_foreign FOREIGN KEY (deposit_method_id) REFERENCES public.deposit_methods(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.deposits
    ADD CONSTRAINT deposits_onchain_tx_id_foreign FOREIGN KEY (onchain_tx_id) REFERENCES public.onchain_txs(id);
ALTER TABLE ONLY public.deposits
    ADD CONSTRAINT deposits_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.fx_quotes
    ADD CONSTRAINT fx_quotes_from_asset_id_foreign FOREIGN KEY (from_asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.fx_quotes
    ADD CONSTRAINT fx_quotes_to_asset_id_foreign FOREIGN KEY (to_asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.fx_quotes
    ADD CONSTRAINT fx_quotes_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);
ALTER TABLE ONLY public.gas_sponsorships
    ADD CONSTRAINT gas_sponsorships_chain_id_foreign FOREIGN KEY (chain_id) REFERENCES public.chains(id);
ALTER TABLE ONLY public.gas_wallets
    ADD CONSTRAINT gas_wallets_chain_id_foreign FOREIGN KEY (chain_id) REFERENCES public.chains(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.journal_entries
    ADD CONSTRAINT journal_entries_reverses_entry_id_foreign FOREIGN KEY (reverses_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.kyc_profiles
    ADD CONSTRAINT kyc_profiles_reviewed_by_foreign FOREIGN KEY (reviewed_by) REFERENCES public.admins(id);
ALTER TABLE ONLY public.kyc_profiles
    ADD CONSTRAINT kyc_profiles_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.ledger_accounts
    ADD CONSTRAINT ledger_accounts_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.ledger_accounts
    ADD CONSTRAINT ledger_accounts_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.ledger_lines
    ADD CONSTRAINT ledger_lines_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.ledger_accounts(id);
ALTER TABLE ONLY public.ledger_lines
    ADD CONSTRAINT ledger_lines_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.ledger_lines
    ADD CONSTRAINT ledger_lines_entry_id_foreign FOREIGN KEY (entry_id) REFERENCES public.journal_entries(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.login_histories
    ADD CONSTRAINT login_histories_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.merchant_invoices
    ADD CONSTRAINT merchant_invoices_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.merchant_invoices
    ADD CONSTRAINT merchant_invoices_entry_id_foreign FOREIGN KEY (entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.merchant_invoices
    ADD CONSTRAINT merchant_invoices_merchant_id_foreign FOREIGN KEY (merchant_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.merchant_invoices
    ADD CONSTRAINT merchant_invoices_payer_id_foreign FOREIGN KEY (payer_id) REFERENCES public.users(id);
ALTER TABLE ONLY public.merchants
    ADD CONSTRAINT merchants_settlement_asset_id_foreign FOREIGN KEY (settlement_asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.merchants
    ADD CONSTRAINT merchants_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.notification_preferences
    ADD CONSTRAINT notification_preferences_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.onchain_txs
    ADD CONSTRAINT onchain_txs_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.onchain_txs
    ADD CONSTRAINT onchain_txs_chain_id_foreign FOREIGN KEY (chain_id) REFERENCES public.chains(id);
ALTER TABLE ONLY public.otp_codes
    ADD CONSTRAINT otp_codes_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_ad_payment_methods
    ADD CONSTRAINT p2p_ad_payment_methods_ad_id_foreign FOREIGN KEY (ad_id) REFERENCES public.p2p_ads(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_ad_payment_methods
    ADD CONSTRAINT p2p_ad_payment_methods_payment_method_id_foreign FOREIGN KEY (payment_method_id) REFERENCES public.p2p_payment_methods(id);
ALTER TABLE ONLY public.p2p_ads
    ADD CONSTRAINT p2p_ads_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.p2p_ads
    ADD CONSTRAINT p2p_ads_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_blocks
    ADD CONSTRAINT p2p_blocks_blocked_id_foreign FOREIGN KEY (blocked_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_blocks
    ADD CONSTRAINT p2p_blocks_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_dispute_evidence
    ADD CONSTRAINT p2p_dispute_evidence_dispute_id_foreign FOREIGN KEY (dispute_id) REFERENCES public.p2p_disputes(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_dispute_notes
    ADD CONSTRAINT p2p_dispute_notes_admin_id_foreign FOREIGN KEY (admin_id) REFERENCES public.admins(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_dispute_notes
    ADD CONSTRAINT p2p_dispute_notes_dispute_id_foreign FOREIGN KEY (dispute_id) REFERENCES public.p2p_disputes(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_disputes
    ADD CONSTRAINT p2p_disputes_assigned_admin_id_foreign FOREIGN KEY (assigned_admin_id) REFERENCES public.admins(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.p2p_disputes
    ADD CONSTRAINT p2p_disputes_opened_by_foreign FOREIGN KEY (opened_by) REFERENCES public.users(id);
ALTER TABLE ONLY public.p2p_disputes
    ADD CONSTRAINT p2p_disputes_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.p2p_orders(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_disputes
    ADD CONSTRAINT p2p_disputes_resolved_by_foreign FOREIGN KEY (resolved_by) REFERENCES public.admins(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.p2p_escrows
    ADD CONSTRAINT p2p_escrows_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.p2p_escrows
    ADD CONSTRAINT p2p_escrows_lock_entry_id_foreign FOREIGN KEY (lock_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.p2p_escrows
    ADD CONSTRAINT p2p_escrows_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.p2p_orders(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_escrows
    ADD CONSTRAINT p2p_escrows_release_entry_id_foreign FOREIGN KEY (release_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.p2p_escrows
    ADD CONSTRAINT p2p_escrows_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);
ALTER TABLE ONLY public.p2p_favorites
    ADD CONSTRAINT p2p_favorites_merchant_id_foreign FOREIGN KEY (merchant_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_favorites
    ADD CONSTRAINT p2p_favorites_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_merchant_profiles
    ADD CONSTRAINT p2p_merchant_profiles_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_order_events
    ADD CONSTRAINT p2p_order_events_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.p2p_orders(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_order_messages
    ADD CONSTRAINT p2p_order_messages_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.p2p_orders(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_order_messages
    ADD CONSTRAINT p2p_order_messages_sender_id_foreign FOREIGN KEY (sender_id) REFERENCES public.users(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.p2p_orders
    ADD CONSTRAINT p2p_orders_ad_id_foreign FOREIGN KEY (ad_id) REFERENCES public.p2p_ads(id);
ALTER TABLE ONLY public.p2p_orders
    ADD CONSTRAINT p2p_orders_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.p2p_orders
    ADD CONSTRAINT p2p_orders_buyer_id_foreign FOREIGN KEY (buyer_id) REFERENCES public.users(id);
ALTER TABLE ONLY public.p2p_orders
    ADD CONSTRAINT p2p_orders_payment_method_id_foreign FOREIGN KEY (payment_method_id) REFERENCES public.p2p_payment_methods(id);
ALTER TABLE ONLY public.p2p_orders
    ADD CONSTRAINT p2p_orders_seller_id_foreign FOREIGN KEY (seller_id) REFERENCES public.users(id);
ALTER TABLE ONLY public.p2p_reviews
    ADD CONSTRAINT p2p_reviews_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.p2p_orders(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_reviews
    ADD CONSTRAINT p2p_reviews_ratee_id_foreign FOREIGN KEY (ratee_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_reviews
    ADD CONSTRAINT p2p_reviews_rater_id_foreign FOREIGN KEY (rater_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.p2p_user_payment_methods
    ADD CONSTRAINT p2p_user_payment_methods_payment_method_id_foreign FOREIGN KEY (payment_method_id) REFERENCES public.p2p_payment_methods(id);
ALTER TABLE ONLY public.p2p_user_payment_methods
    ADD CONSTRAINT p2p_user_payment_methods_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.payout_accounts
    ADD CONSTRAINT payout_accounts_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.payout_accounts
    ADD CONSTRAINT payout_accounts_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.payout_accounts
    ADD CONSTRAINT payout_accounts_withdrawal_method_id_foreign FOREIGN KEY (withdrawal_method_id) REFERENCES public.withdrawal_methods(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.profit_payouts
    ADD CONSTRAINT profit_payouts_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.profit_payouts
    ADD CONSTRAINT profit_payouts_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.admins(id);
ALTER TABLE ONLY public.profit_payouts
    ADD CONSTRAINT profit_payouts_entry_id_foreign FOREIGN KEY (entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.provider_accounts
    ADD CONSTRAINT provider_accounts_card_provider_id_foreign FOREIGN KEY (card_provider_id) REFERENCES public.card_providers(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.provider_accounts
    ADD CONSTRAINT provider_accounts_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.ramp_orders
    ADD CONSTRAINT ramp_orders_entry_id_foreign FOREIGN KEY (entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.ramp_orders
    ADD CONSTRAINT ramp_orders_fiat_asset_id_foreign FOREIGN KEY (fiat_asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.ramp_orders
    ADD CONSTRAINT ramp_orders_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.reconciliation_runs
    ADD CONSTRAINT reconciliation_runs_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.referrals
    ADD CONSTRAINT referrals_referee_id_foreign FOREIGN KEY (referee_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.referrals
    ADD CONSTRAINT referrals_referrer_id_foreign FOREIGN KEY (referrer_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.referrals
    ADD CONSTRAINT referrals_reward_entry_id_foreign FOREIGN KEY (reward_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.revenue_withdrawals
    ADD CONSTRAINT revenue_withdrawals_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.admins(id);
ALTER TABLE ONLY public.revenue_withdrawals
    ADD CONSTRAINT revenue_withdrawals_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.revenue_withdrawals
    ADD CONSTRAINT revenue_withdrawals_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.admins(id);
ALTER TABLE ONLY public.revenue_withdrawals
    ADD CONSTRAINT revenue_withdrawals_entry_id_foreign FOREIGN KEY (entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.revenue_withdrawals
    ADD CONSTRAINT revenue_withdrawals_reversal_entry_id_foreign FOREIGN KEY (reversal_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.reward_campaigns
    ADD CONSTRAINT reward_campaigns_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.reward_grants
    ADD CONSTRAINT reward_grants_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.reward_grants
    ADD CONSTRAINT reward_grants_entry_id_foreign FOREIGN KEY (entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.reward_grants
    ADD CONSTRAINT reward_grants_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.rpc_endpoints
    ADD CONSTRAINT rpc_endpoints_chain_id_foreign FOREIGN KEY (chain_id) REFERENCES public.chains(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.screening_results
    ADD CONSTRAINT screening_results_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.security_events
    ADD CONSTRAINT security_events_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_analytics_events
    ADD CONSTRAINT shop_analytics_events_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.shop_orders(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_analytics_events
    ADD CONSTRAINT shop_analytics_events_sales_page_id_foreign FOREIGN KEY (sales_page_id) REFERENCES public.shop_sales_pages(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_analytics_events
    ADD CONSTRAINT shop_analytics_events_seller_id_foreign FOREIGN KEY (seller_id) REFERENCES public.shop_sellers(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_coupons
    ADD CONSTRAINT shop_coupons_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.shop_products(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_coupons
    ADD CONSTRAINT shop_coupons_seller_id_foreign FOREIGN KEY (seller_id) REFERENCES public.shop_sellers(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_daily_stats
    ADD CONSTRAINT shop_daily_stats_sales_page_id_foreign FOREIGN KEY (sales_page_id) REFERENCES public.shop_sales_pages(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_daily_stats
    ADD CONSTRAINT shop_daily_stats_seller_id_foreign FOREIGN KEY (seller_id) REFERENCES public.shop_sellers(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_domains
    ADD CONSTRAINT shop_domains_sales_page_id_foreign FOREIGN KEY (sales_page_id) REFERENCES public.shop_sales_pages(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_domains
    ADD CONSTRAINT shop_domains_seller_id_foreign FOREIGN KEY (seller_id) REFERENCES public.shop_sellers(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_download_events
    ADD CONSTRAINT shop_download_events_download_id_foreign FOREIGN KEY (download_id) REFERENCES public.shop_downloads(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_downloads
    ADD CONSTRAINT shop_downloads_buyer_user_id_foreign FOREIGN KEY (buyer_user_id) REFERENCES public.users(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_downloads
    ADD CONSTRAINT shop_downloads_order_item_id_foreign FOREIGN KEY (order_item_id) REFERENCES public.shop_order_items(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_downloads
    ADD CONSTRAINT shop_downloads_product_file_id_foreign FOREIGN KEY (product_file_id) REFERENCES public.shop_product_files(id);
ALTER TABLE ONLY public.shop_funnel_steps
    ADD CONSTRAINT shop_funnel_steps_funnel_id_foreign FOREIGN KEY (funnel_id) REFERENCES public.shop_funnels(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_funnel_steps
    ADD CONSTRAINT shop_funnel_steps_offer_product_id_foreign FOREIGN KEY (offer_product_id) REFERENCES public.shop_products(id);
ALTER TABLE ONLY public.shop_funnel_steps
    ADD CONSTRAINT shop_funnel_steps_parent_step_id_foreign FOREIGN KEY (parent_step_id) REFERENCES public.shop_funnel_steps(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_funnels
    ADD CONSTRAINT shop_funnels_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.shop_products(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_funnels
    ADD CONSTRAINT shop_funnels_seller_id_foreign FOREIGN KEY (seller_id) REFERENCES public.shop_sellers(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_licenses
    ADD CONSTRAINT shop_licenses_order_item_id_foreign FOREIGN KEY (order_item_id) REFERENCES public.shop_order_items(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_licenses
    ADD CONSTRAINT shop_licenses_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.shop_products(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_message_attachments
    ADD CONSTRAINT shop_message_attachments_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.shop_messages(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_messages
    ADD CONSTRAINT shop_messages_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.shop_orders(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_order_events
    ADD CONSTRAINT shop_order_events_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.shop_orders(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_order_items
    ADD CONSTRAINT shop_order_items_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.shop_orders(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_order_items
    ADD CONSTRAINT shop_order_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.shop_products(id);
ALTER TABLE ONLY public.shop_order_items
    ADD CONSTRAINT shop_order_items_variant_id_foreign FOREIGN KEY (variant_id) REFERENCES public.shop_product_variants(id);
ALTER TABLE ONLY public.shop_orders
    ADD CONSTRAINT shop_orders_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.shop_orders
    ADD CONSTRAINT shop_orders_buyer_user_id_foreign FOREIGN KEY (buyer_user_id) REFERENCES public.users(id);
ALTER TABLE ONLY public.shop_orders
    ADD CONSTRAINT shop_orders_coupon_id_foreign FOREIGN KEY (coupon_id) REFERENCES public.shop_coupons(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_orders
    ADD CONSTRAINT shop_orders_funnel_id_foreign FOREIGN KEY (funnel_id) REFERENCES public.shop_funnels(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_orders
    ADD CONSTRAINT shop_orders_ledger_entry_id_foreign FOREIGN KEY (ledger_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_orders
    ADD CONSTRAINT shop_orders_parent_order_id_foreign FOREIGN KEY (parent_order_id) REFERENCES public.shop_orders(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_orders
    ADD CONSTRAINT shop_orders_sales_page_id_foreign FOREIGN KEY (sales_page_id) REFERENCES public.shop_sales_pages(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_orders
    ADD CONSTRAINT shop_orders_seller_id_foreign FOREIGN KEY (seller_id) REFERENCES public.shop_sellers(id);
ALTER TABLE ONLY public.shop_page_revisions
    ADD CONSTRAINT shop_page_revisions_author_user_id_foreign FOREIGN KEY (author_user_id) REFERENCES public.users(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_page_revisions
    ADD CONSTRAINT shop_page_revisions_sales_page_id_foreign FOREIGN KEY (sales_page_id) REFERENCES public.shop_sales_pages(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_product_files
    ADD CONSTRAINT shop_product_files_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.shop_products(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_product_media
    ADD CONSTRAINT shop_product_media_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.shop_products(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_product_variants
    ADD CONSTRAINT shop_product_variants_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.shop_products(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_products
    ADD CONSTRAINT shop_products_price_asset_id_foreign FOREIGN KEY (price_asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.shop_products
    ADD CONSTRAINT shop_products_seller_id_foreign FOREIGN KEY (seller_id) REFERENCES public.shop_sellers(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_refund_requests
    ADD CONSTRAINT shop_refund_requests_buyer_user_id_foreign FOREIGN KEY (buyer_user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_refund_requests
    ADD CONSTRAINT shop_refund_requests_ledger_entry_id_foreign FOREIGN KEY (ledger_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_refund_requests
    ADD CONSTRAINT shop_refund_requests_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.shop_orders(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_refund_requests
    ADD CONSTRAINT shop_refund_requests_seller_id_foreign FOREIGN KEY (seller_id) REFERENCES public.shop_sellers(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_reviews
    ADD CONSTRAINT shop_reviews_buyer_user_id_foreign FOREIGN KEY (buyer_user_id) REFERENCES public.users(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_reviews
    ADD CONSTRAINT shop_reviews_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.shop_orders(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_reviews
    ADD CONSTRAINT shop_reviews_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.shop_products(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_reviews
    ADD CONSTRAINT shop_reviews_seller_id_foreign FOREIGN KEY (seller_id) REFERENCES public.shop_sellers(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_sales_pages
    ADD CONSTRAINT shop_sales_pages_bump_product_id_foreign FOREIGN KEY (bump_product_id) REFERENCES public.shop_products(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_sales_pages
    ADD CONSTRAINT shop_sales_pages_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.shop_products(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_sales_pages
    ADD CONSTRAINT shop_sales_pages_seller_id_foreign FOREIGN KEY (seller_id) REFERENCES public.shop_sellers(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_sales_pages
    ADD CONSTRAINT shop_sales_pages_upsell_product_id_foreign FOREIGN KEY (upsell_product_id) REFERENCES public.shop_products(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_saved_blocks
    ADD CONSTRAINT shop_saved_blocks_seller_id_foreign FOREIGN KEY (seller_id) REFERENCES public.shop_sellers(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_seller_applications
    ADD CONSTRAINT shop_seller_applications_decided_by_foreign FOREIGN KEY (decided_by) REFERENCES public.admins(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_seller_applications
    ADD CONSTRAINT shop_seller_applications_seller_id_foreign FOREIGN KEY (seller_id) REFERENCES public.shop_sellers(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_sellers
    ADD CONSTRAINT shop_sellers_reviewed_by_foreign FOREIGN KEY (reviewed_by) REFERENCES public.admins(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.shop_sellers
    ADD CONSTRAINT shop_sellers_settlement_asset_id_foreign FOREIGN KEY (settlement_asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.shop_sellers
    ADD CONSTRAINT shop_sellers_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.shop_shipments
    ADD CONSTRAINT shop_shipments_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.shop_orders(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.support_messages
    ADD CONSTRAINT support_messages_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.support_messages
    ADD CONSTRAINT support_messages_ticket_id_foreign FOREIGN KEY (ticket_id) REFERENCES public.support_tickets(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.support_tickets
    ADD CONSTRAINT support_tickets_assigned_to_foreign FOREIGN KEY (assigned_to) REFERENCES public.admins(id);
ALTER TABLE ONLY public.support_tickets
    ADD CONSTRAINT support_tickets_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.sweeps
    ADD CONSTRAINT sweeps_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.sweeps
    ADD CONSTRAINT sweeps_deposit_address_id_foreign FOREIGN KEY (deposit_address_id) REFERENCES public.deposit_addresses(id);
ALTER TABLE ONLY public.sweeps
    ADD CONSTRAINT sweeps_onchain_tx_id_foreign FOREIGN KEY (onchain_tx_id) REFERENCES public.onchain_txs(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.sweeps
    ADD CONSTRAINT sweeps_settle_entry_id_foreign FOREIGN KEY (settle_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.trading_pairs
    ADD CONSTRAINT trading_pairs_from_asset_id_foreign FOREIGN KEY (from_asset_id) REFERENCES public.assets(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.trading_pairs
    ADD CONSTRAINT trading_pairs_to_asset_id_foreign FOREIGN KEY (to_asset_id) REFERENCES public.assets(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.transfers
    ADD CONSTRAINT transfers_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.transfers
    ADD CONSTRAINT transfers_entry_id_foreign FOREIGN KEY (entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.transfers
    ADD CONSTRAINT transfers_recipient_id_foreign FOREIGN KEY (recipient_id) REFERENCES public.users(id);
ALTER TABLE ONLY public.transfers
    ADD CONSTRAINT transfers_sender_id_foreign FOREIGN KEY (sender_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.travel_rule_records
    ADD CONSTRAINT travel_rule_records_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.travel_rule_records
    ADD CONSTRAINT travel_rule_records_withdrawal_id_foreign FOREIGN KEY (withdrawal_id) REFERENCES public.withdrawals(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.treasury_moves
    ADD CONSTRAINT treasury_moves_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.treasury_moves
    ADD CONSTRAINT treasury_moves_chain_id_foreign FOREIGN KEY (chain_id) REFERENCES public.chains(id);
ALTER TABLE ONLY public.treasury_moves
    ADD CONSTRAINT treasury_moves_onchain_tx_id_foreign FOREIGN KEY (onchain_tx_id) REFERENCES public.onchain_txs(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.treasury_moves
    ADD CONSTRAINT treasury_moves_settle_entry_id_foreign FOREIGN KEY (settle_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.user_devices
    ADD CONSTRAINT user_devices_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.user_favorite_assets
    ADD CONSTRAINT user_favorite_assets_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.user_favorite_assets
    ADD CONSTRAINT user_favorite_assets_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.user_push_tokens
    ADD CONSTRAINT user_push_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.user_spending_priority
    ADD CONSTRAINT user_spending_priority_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.user_spending_priority
    ADD CONSTRAINT user_spending_priority_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_referred_by_foreign FOREIGN KEY (referred_by) REFERENCES public.users(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.webhook_deliveries
    ADD CONSTRAINT webhook_deliveries_endpoint_id_foreign FOREIGN KEY (endpoint_id) REFERENCES public.webhook_endpoints(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.webhook_endpoints
    ADD CONSTRAINT webhook_endpoints_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.withdrawal_methods
    ADD CONSTRAINT withdrawal_methods_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id) ON DELETE CASCADE;
ALTER TABLE ONLY public.withdrawals
    ADD CONSTRAINT withdrawals_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.admins(id);
ALTER TABLE ONLY public.withdrawals
    ADD CONSTRAINT withdrawals_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES public.assets(id);
ALTER TABLE ONLY public.withdrawals
    ADD CONSTRAINT withdrawals_lock_entry_id_foreign FOREIGN KEY (lock_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.withdrawals
    ADD CONSTRAINT withdrawals_onchain_tx_id_foreign FOREIGN KEY (onchain_tx_id) REFERENCES public.onchain_txs(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.withdrawals
    ADD CONSTRAINT withdrawals_settle_entry_id_foreign FOREIGN KEY (settle_entry_id) REFERENCES public.journal_entries(id) ON DELETE SET NULL;
ALTER TABLE ONLY public.withdrawals
    ADD CONSTRAINT withdrawals_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
