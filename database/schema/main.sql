-- main.sql — loads the modular schema in dependency order.
-- GENERATED snapshot for documentation/reference. The AUTHORITATIVE
-- schema is the Laravel migrations in database/migrations/.

\ir 01_core_system.sql
\ir 02_core_users.sql
\ir 03_auth_rbac.sql
\ir 04_registry_wallet.sql
\ir 05_ledger.sql
\ir 06_kyc_compliance.sql
\ir 07_deposits.sql
\ir 08_withdrawals.sql
\ir 09_treasury.sql
\ir 10_exchange.sql
\ir 11_transfers.sql
\ir 12_cards.sql
\ir 13_merchant_payments.sql
\ir 14_p2p.sql
\ir 15_shop.sql
\ir 16_rewards.sql
\ir 17_notifications_webhooks.sql
\ir 18_security_audit.sql
\ir 19_support.sql
\ir 20_cms.sql
\ir 21_analytics_settings.sql
\ir 99_foreign_keys.sql
