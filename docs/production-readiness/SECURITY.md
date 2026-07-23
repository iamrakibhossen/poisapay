# PoisaPay — Security Hardening (Wave 4)

Every module is **feature-flagged** (settings-based `feature('security_*')`, defaults in
`config/poisapay.php` → `security.flags`), **backward compatible** (enforcement gates
default off / permissive), and **provider-independent** where it touches the outside world.
Toggle any module live from the admin Security dashboard (`/admin/security`).

## Modules

| # | Feature | Where | Flag |
|---|---|---|---|
| 1 | Withdrawal address whitelist | `AddressBookService::assertWithdrawable()` in `RequestWithdrawalAction` | `security_withdrawal_whitelist` (off) |
| 2 | Address cooldown (24–48h) | `AddressBookService::add()` → `pending` + `cooldown_until`; `promoteMatured()` | `security_address_cooldown` (on) |
| 3 | Suspicious login detection | `SuspiciousLoginDetector` (sync) + `EnrichLoginSecurityJob` (queued) | `security_suspicious_login` (on) |
| 4 | Device fingerprinting | `DeviceService::fingerprint()` (UA+IP SHA-256); new-device judged pre-record | — |
| 5 | IP reputation (adapter) | `IpReputationProvider` + `StubIpReputationProvider` (admin denylist) | `security_ip_reputation` (on) |
| 6 | Geo-location risk (adapter) | `GeoLocator` + `StubGeoLocator`; country-risk + impossible-travel | `security_geo_risk` (on) |
| 7 | Risk scoring | `RiskEngine` (withdrawals) + login risk composed in the enrich job | — |
| 8 | Velocity limits | `VelocityGuard` → forces manual review past the rolling-24h cap | `security_velocity_limits` (on) |
| 9 | Withdrawal approval workflow | risk **or** velocity **or** whitelist → `WithdrawalStatus::Review` (existing admin queue) | — |
| 10 | Immutable audit logs | `AuditChain` hash-chains `audit_logs`; `poisapay:audit-verify` | `security_audit_hash_chain` (on) |
| 11 | Session security | `/security` → "sign out other sessions"; `SESSION_ENCRYPT=true` in prod | — |
| 12 | Secret rotation | `APP_PREVIOUS_KEYS` + `poisapay:reencrypt`; anti-phishing code | — |
| 13 | Encryption review | `withdrawals.payout_details` → `encrypted:array` at rest | — |
| 14 | API rate limiting | named limiters (`api`, `sensitive`, `auth`) in `AppServiceProvider`; per-user/IP | — |
| 15 | Security monitoring dashboard | `/admin/security` — KPIs, events, flag toggles, IP denylist, chain verify | — |

## Data model (new, all additive)
- `security_events` — durable signals (new_device, new_location, impossible_travel, ip_flagged, velocity_exceeded, whitelist_block, address_added).
- `login_histories` — per-user sign-in log (closes the prior "users have no login history" gap).
- `address_book_entries` + `status` / `cooldown_until` / `whitelisted_at` / `blocked_at`.
- `audit_logs` + `sequence` / `prev_hash` / `hash` (Postgres sequence `audit_logs_seq`).
- `users.anti_phishing_code`.

## Provider adapters (swap a stub for a real vendor via config/providers.php)
- `ip_reputation` — stub screens an admin denylist; real: IPQualityScore / MaxMind / AbuseIPDB.
- `geo` — stub returns unknown (no fabricated data); real: MaxMind GeoIP2 / ipapi.

## Enforcement flow (withdrawals)
```
RequestWithdrawalAction
  ├─ whitelist (on-chain only, if enabled)  → AddressBookService::assertWithdrawable → block + whitelist_block event
  ├─ risk scoring (RiskEngine)              → score/level
  ├─ velocity (VelocityGuard, if enabled)   → velocity_exceeded event, force review
  └─ status = Review when risk OR velocity OR (AML monitor) demands it → admin approval queue
```

## Audit chain
Each `audit_logs` row is sealed on insert with a monotonic `sequence`, the prior row's
`hash`, and a SHA-256 over its immutable payload. Editing/deleting an earlier row breaks
the linkage. Verify anytime:
```bash
php artisan poisapay:audit-verify        # exit 1 if broken (CI/cron-friendly)
```
Also surfaced in the admin Security dashboard ("Verify now").

## Secret rotation runbook
1. `php artisan key:generate --show` → set as new `APP_KEY`; move the old key to `APP_PREVIOUS_KEYS`.
2. Deploy (old data still decrypts via the previous key).
3. `php artisan poisapay:reencrypt` → rewrites encrypted columns under the new key.
4. Remove the old key from `APP_PREVIOUS_KEYS`.

## Endpoints
- User UI: `/security` (addresses, activity, anti-phishing, sessions) + `/admin/security`.
- REST API: `GET/POST/DELETE /api/v1/security/addresses`, `GET /api/v1/security/events`, `GET /api/v1/security/login-history`.
- PSP-style webhook auth for payout unchanged; sensitive routes use `throttle:sensitive`.

## Honest tradeoffs / not done
- `payout_accounts.account_number` is **not** encrypted: it sits in a functional unique
  index (dedup on save) and a 64-char column — deterministic encryption or a separate
  blind-index column would be required. Tracked, not shortcut.
- IP reputation / geo stubs make **no external calls**; they are correct no-ops until a real
  vendor is bound. Impossible-travel needs coordinates, so it activates only with a real geo provider.
- Passkeys/WebAuthn (roadmap Wave 4.6) remain optional and are not included here.

## Tests
`tests/Feature/SecurityHardeningTest.php` — 12 tests covering whitelist enforcement, cooldown
maturation, velocity review, suspicious-login + new-device, IP denylist enrichment, adapter
resolution, audit-chain verify + tamper detection, user UI, REST API, and the admin dashboard.
