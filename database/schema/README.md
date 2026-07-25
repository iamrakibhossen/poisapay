# Modular schema snapshot

A **generated**, human-readable snapshot of the PostgreSQL schema, split by
domain module. It is produced from a migrated database with `pg_dump`.

> **Source of truth = Laravel migrations** (`database/migrations/`).
> This directory is documentation/reference. Do not point the app at it and do
> not hand-edit the `*.sql` files — change a migration, then regenerate:
>
> ```bash
> php artisan migrate:fresh            # apply migrations to your dev DB
> database/schema/regenerate.sh poisapay
> ```

## Layout

`main.sql` loads every module in dependency order (parents before children),
then applies **all foreign keys last** (`99_foreign_keys.sql`) so cross-module
references never hit ordering problems. Verified to load cleanly into an empty
database (130 tables, 232 FKs).

| # | Module | Contents |
|---|--------|----------|
| 01 | core_system | migrations, cache, jobs, queue |
| 02 | core_users | users, sessions, devices, otp, login history, notifications |
| 03 | auth_rbac | spatie permissions/roles, admins |
| 04 | registry_wallet | chains, assets, currencies, custody, addresses, gas, on-chain txs |
| 05 | ledger | journal_entries, ledger_lines, ledger_accounts, account_balances |
| 06 | kyc_compliance | kyc_profiles, screening, travel-rule, AML alerts, cases, lists |
| 07 | deposits | deposits, deposit_methods |
| 08 | withdrawals | withdrawals, methods, payout accounts, sweeps, broadcasts |
| 09 | treasury | treasury_moves, cold-refill, reconciliation, profit/revenue payouts |
| 10 | exchange | fx_quotes, conversions, trading_pairs, ramp_orders |
| 11 | transfers | internal transfers |
| 12 | cards | cards, authorizations, disputes, providers, webhooks |
| 13 | merchant_payments | merchants, merchant_invoices (acquiring — distinct from Shop) |
| 14 | p2p | P2P marketplace: ads, orders, escrows, disputes, chat |
| 15 | shop | commerce: sellers, products, sales pages, orders, coupons, refunds |
| 16 | rewards | reward grants/campaigns, referrals |
| 17 | notifications_webhooks | prefs, templates, announcements, webhooks |
| 18 | security_audit | security_events, audit_logs |
| 19 | support | tickets, messages |
| 20 | cms | pages, faqs |
| 21 | analytics_settings | daily metrics rollups, system_settings |

See `docs/database/OPTIMIZATION.md` for the audit + optimization record.
