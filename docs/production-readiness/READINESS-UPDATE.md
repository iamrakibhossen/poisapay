# PoisaPay — Production Readiness Update

**As of:** 2026-07-22 · after Wave 0 (guardrails), adapter seams + off-ramp, and Wave 4 (security hardening).
Baseline report: `AUDIT.md` (60% overall). This tracks movement since.

## Score movement

| Score | Phase 1 baseline | Now | Why |
|---|---|---|---|
| **Overall readiness** | 60% | **~72%** | Guardrails + swappable provider seams + full security layer landed & tested |
| **Security** | 68% | **~88%** | Whitelist+cooldown, suspicious-login, velocity, hash-chained audit, encryption, rate limiting, session controls, anti-phishing |
| **Compliance** | 50% | **~58%** | Screening/KYC seams formalised; **legal policy set now drafted (Wave 8)**; real vendor + Travel Rule still pending |
| **Scalability** | 45% | **~60%** | Docker + CI + Horizon supervisor + named rate limiters + queued security enrichment |

## Delivered since baseline
- **Wave 0:** Dockerfile + compose, GitHub Actions CI (Pint → PHPStan → Pest+coverage), Larastan L6 (baseline), process supervisors, `.env.production.example`.
- **Adapter seams + off-ramp:** `RateProvider`(+cache)/`ScreeningProvider`/`KycProvider`/`PayoutProcessor`/`NotificationTransport` + stubs via `config/providers.php`; complete crypto→fiat off-ramp with reserve-before-send + provider-agnostic webhook.
- **Wave 4:** 15 feature-flagged security modules (see `SECURITY.md`), 449 tests green.

## Test + quality gate
- **449 tests / 1503 assertions passing** (was 425 at baseline).
- Pint clean · PHPStan (Larastan L6) clean · coverage gate in CI.

## Remaining launch blockers (need external vendor accounts)
1. Custody KMS/HSM (replace env seed) — **Wave 1**
2. EVM live watchers + signer — **Wave 2**
3. Live exchange-rate feed — bind a real `RateProvider`
4. Real sanctions/PEP screening + KYC doc/liveness vendor — bind real `ScreeningProvider`/`KycProvider`
5. Backups + DR + APM/alerting — **Wave 7**
6. Travel Rule integration — **Wave 5.5**

Each of 3 & 4 is now a **one-line driver swap** thanks to the adapter seams.

## Next
Wave 8 (Legal & Compliance documentation) — drafted under `docs/legal/`.
