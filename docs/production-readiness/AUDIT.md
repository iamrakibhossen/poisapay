# PoisaPay — Production Readiness Audit (Phase 1)

**Date:** 2026-07-22
**Auditor:** Senior FinTech / Crypto Wallet / Card Issuing Solution Architect
**Baseline:** 330 PHP files, 25 domains under `app/Domain`, provider abstraction under `app/Card`, 38 migrations, 69 tests, PostgreSQL + Redis + Horizon + Reverb.
**Method:** Code-grounded review of every module (not documentation-based). Ratings: ✅ Complete · ⚠ Needs Improvement · ❌ Missing.

---

## 0. Executive Summary

PoisaPay has an **enterprise-grade financial core** and a **weak production shell**. The double-entry ledger, exact-money arithmetic, idempotency, coin-pooling, reserve-before-sign withdrawals, and DB-enforced solvency invariant are genuinely production-quality — better than most MVPs. The provider-abstraction pattern (cards, custody, rates, screening) is real and consistently applied.

What is missing is almost everything **between "correct business logic" and "safe to run for real money"**: live vendor integrations (all currently stubbed), real key custody (KMS/HSM), EVM chain liveness, the entire DevOps/observability/DR layer, and the compliance evidence chain (real screening + legal policies + Travel Rule).

**The gap is not architectural — it is integration, infrastructure, and compliance.** That is good news: the hard part (a correct ledger) is done, and the adapter seams to plug real providers into already exist.

| Score | Value | Basis |
|---|---|---|
| **Overall Production Readiness** | **60%** | Strong core, no DevOps/DR, stubbed vendors |
| **Security** | **68%** | MFA/audit/RBAC solid; no passkeys, no login-anomaly detection, env-seed custody |
| **Compliance** | **50%** | Workflows exist; screening/liveness/policies/Travel-Rule all missing or stubbed |
| **Scalability** | **45%** | No containers, no partitioning, no load test, single-region assumptions |

---

## 1. Core Wallet — 70% (Testnet-ready, not production-ready)

| Item | Rating | Evidence |
|---|---|---|
| Multi-chain architecture | ✅ | `AddressDeriver` / `ChainRoutingAddressDeriver` contracts; `ChainType` enum extensible |
| ERC20 USDT | ⚠ | EVM address derivation (EIP-55) correct, but **no live EVM deposit watcher** — simulated |
| TRC20 USDT | ✅ | `Chain/Tron/ScanTronDepositsAction` (real TronGrid) + `Withdrawal/Tron/TronWithdrawalSigner` |
| Future chains | ✅ | New chain = new `AddressDeriver` impl + enum case; callers unchanged |
| Deposit monitoring | ✅ | `ScanTronDepositsAction`; `ChainTickJob`; scheduled `poisapay:chain-tick` every minute |
| Deposit confirmation | ✅ | `min_confirmations` per chain/asset; `AdvanceTronDepositsAction` credits when deep |
| Withdrawal queue | ✅ | `Withdrawal` status machine (Pending→Review→Approved→Signing→Broadcast→Completed) |
| Hot wallet | ✅ | `SignerKeyProvider` path `m/44'/coin'/0'/1/0`; `EnvSeedSignerKeyProvider` |
| Cold wallet | ⚠ | `TreasuryCold` ledger account exists, but **no on-chain sweep-to-cold** logic |
| Treasury / Revenue / Gas wallets | ✅ | Ledger account types + `GasWallet` model + `RevenueService` read-model |
| Internal transfer | ✅ | `ExecuteTransferAction` — atomic, idempotent, zero-fee |
| Balance reconciliation | ✅ | `ReconciliationService` proves treasury ≥ user liability per coin |
| Wallet health monitoring | ✅ | `ChainHealthService` per-chain summary (scheduled every 5 min) |
| Failed transaction retry | ⚠ | `Orphaned`/`Failed` states recorded; **no auto-retry queue** (manual) |
| Blockchain explorer links | ❌ | TxHash stored but no Etherscan/TronScan URL generation |
| Address generation | ✅ | Real BIP32/44 for TRON (`Bip32`, `TronAddressDeriver`); EVM simulated behind flag |
| Address reuse policy | ✅ | One active address per (user, chain), reused idempotently |

**Blockers:** live EVM watchers + EVM withdrawal signing; KMS/HSM for custody seed; explorer links; auto-retry for orphaned tx; cold-wallet sweep; RPC failover.

---

## 2. Payment System — 65%

| Item | Rating | Evidence |
|---|---|---|
| Fiat → Crypto (on-ramp) | ✅ | `Ramp/CreditOnRampAction`; `ramp_orders` direction=`on` |
| Crypto → Fiat (off-ramp) | ⚠ | Schema supports direction=`off` but **no `RequestOffRampAction`, no PSP, no payout state machine** |
| Internal transfers | ✅ | `ExecuteTransferAction`, idempotent |
| Merchant payments | ✅ | `Merchant/PayInvoiceAction` with fee split |
| QR payments | ✅ | `MerchantInvoice` + `Support/Qr` |
| Payment requests (P2P) | ❌ | No `PaymentRequest` model/action |
| Card funding | ✅ | `Card/AuthorizeCardAction` JIT hold → `SettleCardAuthAction` |
| Auto conversion | ✅ | `Exchange/ExchangeService` quote+execute with spread, idempotent |
| Exchange rate engine | ⚠ | Only `StubRateProvider` (hardcoded). Interface exists; **no live feed bound** |
| Fee engine | ✅ | `Fees/PlatformFees` + per-merchant bps + card bps |
| Revenue engine | ✅ | `RevenueService` read-model over FeeIncome/FeeCard/FxSpreadIncome |

**Blockers:** live rate provider; complete off-ramp flow (action + API + PSP + webhook); wire `WithdrawalMethod` rail selection; merchant settlement/payout (merchants accrue balance but cannot cash out); tiered withdrawal limits.

---

## 3. Card System — 73%

| Item | Rating | Evidence |
|---|---|---|
| Provider abstraction / per-card driver | ✅ | `CardProviderInterface`, `CardProviderFactory`, `driver` column, `provider_accounts` |
| Marqeta adapter | ⚠ | Feature-complete implementation but **~7 `TODO(marqeta)` unverified field paths** |
| Visa / Mastercard issuer adapters | ❌ | Network selected inside Marqeta; no standalone Visa/MC drivers (framework ready) |
| Future providers | ✅ | New driver = implement interface + declare capabilities + config entry |
| Virtual / Physical card | ✅ | `GenerateCardAction`; `supports_physical` gate |
| Freeze / Unfreeze | ✅ | `CardService` + `cardtransitions` mapping; enforced in `AuthorizeCardAction` |
| Replace card | ✅ | `ReplaceCardAction` (inherits controls, `replaced_by` pointer) |
| Spending limits | ✅ | `daily_limit` + `per_tx_limit`, rolling daily sum enforced |
| ATM limits | ⚠ | `atm_enabled` toggle only; **no separate ATM spend limit** |
| MCC block | ✅ | `blocked_mccs` enforced in auth |
| Country block | ✅ | `allowed_countries` enforced |
| Online/offline control | ⚠ | Channel booleans gated locally; provider offline semantics not mapped |
| Card PIN | ⚠ | `SetCardPinAction` hashes PIN; **no verify path** |
| Card status lifecycle | ✅ | `CardStatus` enum full lifecycle |
| Card funding | ✅ | JIT hold model, idempotent by `network_auth_id` |
| Card transaction history | ✅ | `CardStatementService`; provider `syncTransactions()` |
| JIT authorization | ✅ | `AuthorizeCardAction` + `CardInboundController` (signed, idempotent, sub-2s SLA) |

**Blockers:** verify Marqeta field paths against sandbox; velocity-control sync is best-effort only; dispute resolution action missing; no periodic transaction-sync cron (webhook-only); funding asset hardcoded to USDT.

---

## 4. Security — 68%

| Item | Rating | Evidence |
|---|---|---|
| MFA (TOTP) | ✅ | `Auth/TwoFactorService` (encrypted secrets + recovery codes) |
| Passkeys / WebAuthn | ❌ | None |
| Device management | ✅ | `UserDevice` + `DeviceService` (fingerprint, last_used) |
| Session management | ✅ | Redis sessions, httpOnly, SameSite=lax, regenerate on login |
| Login history | ⚠ | Admin only; users have device records but no login-history table |
| Withdrawal confirmation | ✅ | 2FA gate on withdrawals when enabled |
| Risk engine | ✅ | `Risk/RiskEngine` 4-factor score |
| IP monitoring | ✅ | Captured per login + every audit entry |
| Geo detection | ⚠ | Card-level only; no login geo-anomaly |
| Velocity limits | ✅ | RiskEngine velocity + card limits |
| Fraud detection | ✅ | `Compliance/TransactionMonitor` |
| Suspicious login alerts | ❌ | No new-device / impossible-travel alerts |
| Address whitelist | ⚠ | `AddressBookEntry` exists but **no first-use enforcement / cooldown** |
| Anti-phishing code | ❌ | None |
| Secrets management | ⚠ | Env-seed custody + env API keys; **no KMS/HSM** |
| Encryption at rest | ✅ | 2FA/recovery encrypted (AES-256-CBC) |
| Audit log | ✅ | `Audit/ActivityLogger` + `AuditLog` |
| Immutable activity logs | ✅ | `AuditLog` insert-only (no updated_at) |

**Also:** Bcrypt-12, auth rate-limited, CSRF via Sanctum, admin guard fully separated. `SESSION_ENCRYPT=false` by default (enable for prod).

**Blockers:** withdrawal new-address cooldown/whitelist enforcement; suspicious-login detection + alert; email verification on sensitive changes; KMS/HSM for custody; enable session encryption.

---

## 5. KYC / AML / KYT / Compliance — 50%

| Item | Rating | Evidence |
|---|---|---|
| Multiple KYC providers | ⚠ | No provider interface; manual review only |
| Tiered KYC (0/1/2/3) | ✅ | `KycTier` enum (Unverified/Basic/Full) with gating |
| Document verification | ⚠ | Paths stored; no OCR/validation (manual) |
| Face verification / Liveness | ⚠ | `liveness_passed` flag only; no provider |
| PEP / Sanctions / AML screening | ⚠ | `Compliance/ScreeningService` is an explicit **stub** (admin denylist CSV only) |
| KYT monitoring | ✅ | `TransactionMonitor` (risk + velocity + screening) |
| Transaction risk scoring | ✅ | `RiskEngine` |
| SAR workflow | ✅ | `ComplianceCaseService::fileSar()` (free-text, not FinCEN template) |
| Manual review / Case management | ✅ | `ComplianceController`, `ComplianceCase`, `AmlAlert` |
| Account freeze | ✅ | `User.is_frozen` enforced at withdrawal gate |
| Blacklist | ⚠ | Admin setting, not persistent model |
| Whitelist | ❌ | None |
| Red-flag detection | ⚠ | Ad-hoc alert types; no rules engine |
| OFAC support | ❌ | No real list ingestion |
| Travel Rule ready | ❌ | No originator/beneficiary schema, no VASP hooks |
| Legal/compliance policy docs | ❌ | **None** (no Terms, Privacy, Cookie, Refund, KYC/AML/KYT/Risk/Card policies) |

**Blockers (regulatory):** real screening provider (ComplyAdvantage/Trulioo/etc.); document+liveness vendor (Onfido/SumSub/Jumio); the full legal/compliance policy set; Travel Rule architecture; persistent blacklist + list sync; country-risk checks; freeze enforcement audited at all touchpoints.

---

## 6. Merchant / Admin / User / Notifications — 72%

**Merchant:** onboarding ✅, verification ✅, dashboard ✅, settlements ✅ (but no merchant cash-out ⚠), fees ✅, API ⚠ (no invoice/merchant endpoints), webhooks ✅ (HMAC + retry), invoices ✅, payouts ⚠.

**Admin panel:** wallets/users/cards/transactions/deposits/withdrawals/revenue/fees/exchange/support/risk/compliance/KYC/AML/KYT/approvals/reports/analytics/notifications/provider-settings/RPC/maintenance — **all present as real controllers+views** ✅. Feature flags exist as settings (no dedicated toggle UI ⚠). Settings changes not audit-logged ⚠.

**User panel:** wallet/cards/deposit/withdraw/transfer/history/limits/notifications/security/documents/rewards/settings ✅. Support tickets ⚠ (models, no UI). Referrals ⚠ (reward processing, no sharing UI).

**Notifications:** Email ✅; SMS ⚠ (scaffold, no Twilio/Nexmo); Push ⚠ (prefs only, no FCM/APNs); WhatsApp ❌; Telegram ❌; Webhook ✅. **No formal channel-adapter interface** ⚠.

**Blockers:** notification channel adapter + real SMS/Push transports; support-ticket UI; merchant cash-out; feature-flag admin UI; audit-log settings changes.

---

## 7. DevOps / Database / API / Testing / Reports — 40%

**DevOps:** Docker ❌, CI/CD ❌, staging ❌, blue-green ❌, backups ❌, DR ❌, supervisor ❌, auto-scaling ❌. Health checks ✅, logging config ✅, Redis ✅, Horizon queues ✅, cron ✅. Monitoring/alerting ⚠ (no Sentry/APM/PagerDuty).

**Database:** pgsql ✅, 38 migrations ✅, indexes ✅, FKs ✅, balance trigger ✅. Soft deletes ⚠ (only RevenueWithdrawal), archiving ❌, partitioning ❌, retention ❌.

**API:** REST v1 ✅, rate limiting ✅, Sanctum tokens + Idempotency-Key ✅, webhooks ✅. Versioning ⚠, OAuth ❌, OpenAPI/Swagger ❌, CORS ⚠.

**Testing:** 66 feature tests ✅, integration ✅, smoke/UAT ✅, 1 unit test ⚠. Load ❌, security-scan ⚠, pen-test ❌, coverage reporting ⚠, static analysis (PHPStan) ❌.

**Reports:** financial ✅, revenue ✅, transaction ✅, P&L ✅, risk ✅, daily reconciliation ✅, flow analytics ✅. Compliance export ⚠, user-growth ❌, merchant analytics ⚠.

**Blockers:** Docker + compose; CI/CD; backup/restore + DR runbook; process manager (Horizon supervisor + scheduler); APM + error tracking + alert escalation; load test; table partitioning + retention; OpenAPI; static analysis in CI.

---

## 8. Consolidated Launch Blockers

**Tier 1 — Money-safety / regulatory (cannot go live without):**
1. Real custody: replace `EnvSeedSignerKeyProvider` with KMS/HSM (AWS KMS / Vault / Fireblocks).
2. EVM liveness: real Ethereum/BSC deposit watchers + withdrawal signing.
3. Live exchange rate provider (replace `StubRateProvider`).
4. Real sanctions/PEP/AML screening + document/liveness KYC vendor.
5. Legal + compliance policy set (Terms, Privacy, Cookie, Refund, Card Terms, KYC/AML/KYT/Risk policies).
6. Backups + disaster recovery + tested restore.

**Tier 2 — Operational safety (before real users):**
7. Docker + CI/CD + process manager + APM/alerting.
8. Withdrawal new-address cooldown/whitelist + suspicious-login detection.
9. Complete off-ramp (crypto→fiat) + merchant cash-out.
10. Auto-retry for orphaned/failed on-chain tx; RPC failover; explorer links.

**Tier 3 — Completeness (before scale):**
11. Notification channel adapter + real SMS/Push; Travel Rule architecture; table partitioning/retention; OpenAPI; load & pen testing; dispute resolution; payment requests; support-ticket UI.

---

## 9. What is genuinely strong (do not rebuild)

- Double-entry ledger with **DB-enforced Σdebit=Σcredit** deferred trigger.
- Exact money (`NUMERIC(38,0)` base units, `brick/math`, no floats).
- Idempotency on every money movement (deposit, withdrawal, transfer, exchange, card hold/settle, on-ramp, invoice).
- Coin-pooling `AccountResolver` (RedotPay model) — one balance per coin, treasury per chain.
- Reserve-before-sign withdrawal pattern (funds locked before broadcast).
- Consistent adapter seams (cards, custody, rates, screening) — real providers plug in without touching callers.
- Immutable audit log that never blocks business operations.

These are the parts most teams get wrong. They are right here.
