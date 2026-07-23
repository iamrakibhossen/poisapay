# PoisaPay — MVP → Production Roadmap (Phase 2)

Sequenced so that **every step is backward-compatible**, ships behind a feature flag where it touches live behavior, and lands with tests + docs. Provider work always goes through an adapter interface — never hardcoded. Ordering respects dependencies (custody before EVM liveness, screening interface before vendor, etc.).

Legend — **Effort:** S ≤0.5d · M ≤2d · L ≤1w · XL >1w. **Risk:** 🟢 low · 🟡 medium · 🔴 high.

---

## Wave 0 — Guardrails first (so nothing breaks later)
*Goal: make change safe before changing money-handling code.*

| # | Item | Effort | Risk | Why first |
|---|---|---|---|---|
| 0.1 | Dockerfile + docker-compose (app, pgsql, redis, horizon, reverb) | M | 🟢 | Reproducible env for everything after |
| 0.2 | GitHub Actions CI: install → migrate → Pest → PHPStan → Pint | M | 🟢 | Catch regressions from every later wave |
| 0.3 | PHPStan (level 5→8) + Pint config, baseline the debt | M | 🟢 | Type-safety net |
| 0.4 | Coverage reporting in CI (fail under threshold on core domains) | S | 🟢 | Protect the ledger |
| 0.5 | Horizon supervisor + scheduler process definitions | S | 🟡 | Queues/cron must auto-respawn |
| 0.6 | `.env.production.example` + config hardening (SESSION_ENCRYPT=true, secure cookies) | S | 🟢 | Prod defaults |

## Wave 1 — Custody & key management (Tier-1 blocker)
| # | Item | Effort | Risk |
|---|---|---|---|
| 1.1 | Define `SignerKeyProvider` KMS binding (AWS KMS / Vault) behind existing interface | L | 🔴 |
| 1.2 | Key ceremony + rotation runbook; remove raw-hex env seed path | M | 🔴 |
| 1.3 | Cold-wallet sweep policy: hot→cold threshold job + on-chain move stub via signer | L | 🟡 |
| 1.4 | Gas-wallet low-balance alert + refill SOP | S | 🟡 |

## Wave 2 — EVM liveness (Tier-1 blocker)
| # | Item | Effort | Risk |
|---|---|---|---|
| 2.1 | `EvmRpcClient` adapter (Infura/Alchemy) with failover + retry | L | 🟡 |
| 2.2 | ERC20 USDT deposit watcher (log polling / filter) → same `CreditDepositAction` path | L | 🟡 |
| 2.3 | EVM withdrawal signer (EIP-1559) behind `WithdrawalSigner` contract | L | 🔴 |
| 2.4 | Orphan/reorg auto-retry queue for on-chain tx | M | 🟡 |
| 2.5 | Blockchain explorer URL generation (per chain, on models) | S | 🟢 |

## Wave 3 — Market data & payments completion
| # | Item | Effort | Risk |
|---|---|---|---|
| 3.1 | `RateProvider` live impl (CoinGecko/Binance) + cache + staleness guard; keep Stub for tests | M | 🟡 |
| 3.2 | Off-ramp: `RequestOffRampAction` + state machine + PSP adapter interface + webhook | L | 🟡 |
| 3.3 | Wire `WithdrawalMethod` rail selection + fee computation into `RequestWithdrawalAction` | M | 🟡 |
| 3.4 | Merchant settlement/cash-out flow | M | 🟡 |
| 3.5 | Tiered withdrawal limits by KYC tier + asset | S | 🟢 |
| 3.6 | P2P Payment Requests domain (create/pay/cancel) | M | 🟢 |

## Wave 4 — Security hardening
| # | Item | Effort | Risk |
|---|---|---|---|
| 4.1 | Withdrawal new-address cooldown + email/2FA confirm on first use | M | 🟡 |
| 4.2 | Suspicious-login detection (new device / geo / impossible travel) + alert | M | 🟡 |
| 4.3 | User login-history table + active-sessions view | S | 🟢 |
| 4.4 | Email verification (OTP) on sensitive changes (email, 2FA disable, address save) | S | 🟢 |
| 4.5 | Anti-phishing code; GeoIP (MaxMind) enrichment | M | 🟢 |
| 4.6 | Passkeys / WebAuthn (nice-to-have, optional) | L | 🟢 |

## Wave 5 — Compliance depth (Tier-1 regulatory)
| # | Item | Effort | Risk |
|---|---|---|---|
| 5.1 | `KycProvider` + `ScreeningProvider` adapter interfaces (formalize the seam) | M | 🟡 |
| 5.2 | Real screening vendor adapter (ComplyAdvantage/Trulioo) + daily list sync | L | 🔴 |
| 5.3 | Document + liveness vendor adapter (Onfido/SumSub/Jumio) | L | 🔴 |
| 5.4 | Persistent blacklist/whitelist models + country-risk checks | M | 🟡 |
| 5.5 | Travel Rule architecture (originator/beneficiary schema + VASP adapter e.g. Notabene) | L | 🔴 |
| 5.6 | Structured SAR template + compliance export for regulators | M | 🟡 |
| 5.7 | Freeze enforcement audited at all touchpoints (deposit/transfer/card) | S | 🟡 |
| 5.8 | **Generate legal + compliance policy set** (see Wave 8) | M | 🟢 |

## Wave 6 — Notifications & completeness
| # | Item | Effort | Risk |
|---|---|---|---|
| 6.1 | `NotificationChannel` adapter interface | S | 🟢 |
| 6.2 | Real SMS (Twilio/Vonage) + Push (FCM/APNs) transports | M | 🟢 |
| 6.3 | WhatsApp + Telegram channels (optional) | M | 🟢 |
| 6.4 | Support-ticket admin + user UI | M | 🟢 |
| 6.5 | Referral sharing/tracking UI | S | 🟢 |
| 6.6 | Feature-flag admin toggle UI; audit-log settings changes | S | 🟢 |
| 6.7 | Card: dispute resolution action, PIN verify, tx-sync cron, configurable funding asset | M | 🟡 |

## Wave 7 — Scale & observability
| # | Item | Effort | Risk |
|---|---|---|---|
| 7.1 | APM + error tracking (Sentry) + metrics (Pulse/Datadog) | M | 🟢 |
| 7.2 | Alert escalation (PagerDuty/Slack) on insolvency, RPC down, queue backlog | M | 🟡 |
| 7.3 | Backups (daily pg_dump + PITR) + restore drill | M | 🔴 |
| 7.4 | Table partitioning (ledger_lines, onchain_txs) + retention/archiving | L | 🟡 |
| 7.5 | Load testing (k6) on ledger trigger + concurrent withdrawals | M | 🟡 |
| 7.6 | OpenAPI/Swagger spec + published docs | M | 🟢 |
| 7.7 | Soft deletes + archival on financial entities | S | 🟡 |

## Wave 8 — Legal & compliance documents (generated deliverables)
Terms of Service · Privacy Policy · Cookie Policy · Refund Policy · Card Terms · KYC Policy · AML/CTF Policy · KYT Policy · Risk Policy · Compliance overview. Versioned, user-acceptance tracked, admin-editable via CMS.

## Wave 9 — Go-live
Pen test → security review → licensed-partner contracts → staging soak → production cutover checklist (see CHECKLIST.md §Go-Live).

---

## Implementation principles (apply to every wave)
1. **Never break existing code** — additive migrations, nullable columns, feature flags.
2. **Provider-independent** — new integrations implement an interface; the Stub/Mock stays as the test binding.
3. **Test + doc with each change** — Pest test + doc note; update this folder.
4. **Explain architectural decisions** — short ADR note in the PR/commit.
5. **Money paths stay idempotent and ledger-balanced** — no exceptions.
