# Custody Production-Readiness Validation

**Scope:** Validation-only audit of the custody engineering against the code and the staging
environment. No code was modified. Every verdict below cites `file:line` evidence and the
covering test.

**Test evidence:** 74 custody tests pass (39 chain/withdrawal + 16 crypto/primitives + 19
hot-cold/reconcile/e2e). Commands run: `php artisan test` over the custody suites — all green.

**Legend:** ✅ PASS · 🟡 PARTIAL (works; operational/observability gap) · ⛔ BLOCKER

---

## 0. Headline finding (BLOCKER for a staging-validated go-live)

⛔ **The validated custody code is NOT deployed to staging.** The staging server
(`/var/www/poisapay-staging`) is on `main @ 1417248`, a **divergent history** that shares
ancestor `e06f5fe` with the custody line but then split:

- Custody line (local/origin `main @ 3e56466`): sweeps, reconciler, hot↔cold, refill.
- Staging line (`1417248`): admin-monitoring / admin-menu work.

On staging, `app/Domain/Reconciliation/CustodyReconciler.php`, `TronSweepAction.php`,
`RequestColdRefillAction.php`, `ReconcileCommand.php` **do not exist**;
`poisapay:reconcile / sweep / rebalance / resolve-failed-withdrawals` are **absent** from
`artisan list`; and the scheduler runs only `EvmCustodyTickJob` — **reconciliation is not
running on staging.** The two branches must be reconciled (a merge decision, not a reset —
a naive deploy would drop one line of work). This is an ops/owner decision and is the #1
prerequisite before any real-environment validation. All six custody flags in staging's DB
are already `false` and `custody_simulated = REAL`.

---

## 1. Deposit Detection — ✅ PASS

| # | Check | Verdict | Evidence |
|---|-------|---------|----------|
| 1 | Deposits detected (watched addr + contract/asset match) | ✅ | `ScanTronDepositsAction.php:46-59`, `ScanEvmDepositsAction.php:47-87` — validates `is_watched`, `is_active`, contract match · *TronDepositWatcherTest, Erc20DepositTest* |
| 2 | Duplicate deposits cannot be credited twice | ✅ | **Triple guard:** DB unique `uq_onchain_tx (chain_id, tx_hash, log_index)` + `uq_deposit_onchain_tx (onchain_tx_id)` (`create_movement_tables.php:45,64`) **and** ledger `idempotencyKey deposit:{tx_hash}:{log_index}` (`CreditDepositAction.php:49-52`) · *TronDepositWatcherTest "does not double-record"* |
| 3 | Confirmations respected before credit | ✅ | `AdvanceTronDepositsAction.php:73-87`, `AdvanceEvmDepositsAction.php:76-92` gate credit on `confs >= required`; `Asset::requiredConfirmations()` (`Asset.php:79`) = asset override → chain default → 12 · *TronDepositWatcherTest "credits at required depth"* |

---

## 2. Sweep Engine — ✅ PASS (core) · 🟡 sweep retry/dead-letter is manual

| # | Check | Verdict | Evidence |
|---|-------|---------|----------|
| 1 | Swept deposit→hot | ✅ | `TronSweepAction.php:75-96` (TRC20 `transfer` to hot), `EvmSweepAction.php:80-92` (`Abi::erc20Transfer` to hot) · *TronSweepTest, EvmSweepTest* |
| 2 | Ledger NOT updated at broadcast | ✅ | Sweep actions post **zero** ledger entries; only `Settle*` post · *TronSweepTest "broadcasts…without touching the ledger"* |
| 3 | Ledger updated only after required confs | ✅ | `SettleEvmSweepsAction.php:65-68` confirmation-depth gate; `SettleTronSweepsAction` settles on finalized tx info · *EvmSweepTest "settles only after confirmation"* |
| 4 | Retry logic | 🟡 | Failed sweep → `SweepStatus::Failed`; re-attempted on next scheduler tick (no bounded per-sweep counter). Funds are safe (still at deposit address). |
| 5 | RBF handling | ✅ | RBF exists for **withdrawals**: `RebroadcastStuckWithdrawalsAction.php:88` (+25%/attempt), `:93` (same nonce) · *WithdrawalRbfTest*. Sweeps have no RBF (low value; re-sweep instead). |
| 6 | Failed-tx recovery | ✅ | Reverted sweep → `Sweep=Failed`, `OnchainTx=Orphaned`, **ledger untouched** (`SettleTronSweepsAction.php:49-54`, `SettleEvmSweepsAction.php:58-63`) |
| 7 | Dead-letter queue | 🟡 | **Withdrawals:** full DLQ (`deadLetter()`, funds stay locked, admin alert) · *WithdrawalRbfTest "dead-letters…"*. **Sweeps:** no DLQ/attempt-cap; `sweep.failed` is written to the activity log but **no proactive admin alert**. |

**Note (non-blocking):** a chronically stuck sweep (e.g. persistent gas shortage) retries
indefinitely and is only visible in the activity log + indirectly via reconciliation drift.
No fund-loss risk. **Operational mitigation** (no code change): alert on `sweeps.status =
'failed'` older than N minutes as a monitoring query; reconciliation will also surface the
resulting hot-balance shortfall.

---

## 3. Treasury Ledger — ✅ PASS

| # | Check | Verdict | Evidence |
|---|-------|---------|----------|
| 1 | treasury:hot == on-chain | ✅ | `CustodyReconciler.php:96,104-112` compares on-chain hot vs ledger treasury:hot; `drift = onchain − ledger` · *CustodyReconcileTest* |
| 2 | Treasury never negative | ✅ (by convention) | Treasury accounts are **debit-normal** (`LedgerAccountType.php`), stored positive when holding assets. There is **no per-account overdraw guard**; the guarantee is the double-entry invariant + solvency reconciliation. A true overdraw would be an invariant violation caught by the insolvency check, not silently allowed. |
| 3 | Accounting consistent after every sweep | ✅ | Balanced-entry enforced **twice**: `EntryData::assertBalanced()` (`EntryData.php:34-45`) app-side + deferred DB trigger `trg_entry_balanced` · *LedgerServiceTest "rejects an unbalanced entry"* |

---

## 4. Reconciliation — ✅ PASS

| # | Check | Verdict | Evidence |
|---|-------|---------|----------|
| 1 | Full reconciliation | ✅ | `ReconcileCommand.php` runs 3 legs: ledger solvency (`runAll()`), on-chain hot backing (`CustodyReconciler::reconcile()`), watermarks (`HotColdWatermarkMonitor::evaluate()`) |
| 2 | Zero-drift confirmation | ✅ | `drift = bcsub(onchain, ledger)`; E2E proves `drift='0'` end-to-end · *CustodyE2eTest* |
| 3 | Insolvency detection | ✅ | `ReconciliationService.php:39,63-86` — `treasury >= liability`; on breach → critical `SecurityEvent` + `Log::critical` + `notifyAdmins` · *OpsWave7Test "raises a critical insolvency signal"* |
| 4 | Drift detection | ✅ | `CustodyReconciler.php:113` `breached = abs(drift) > tolerance` (tolerance default 0), alerts admins · *CustodyReconcileTest "flags drift"* |
| 5 | Reconciliation reports | ✅ | `ReconciliationRun` rows (asset, onchain_controlled, ledger_treasury, ledger_liability, drift, is_solvent, status) + `SecurityEvent` on breach |

**Runs scheduled every 5 min** (`routes/console.php:22`) — **once deployed.**

---

## 5. Withdrawals — ✅ PASS

| # | Check | Verdict | Evidence |
|---|-------|---------|----------|
| 1 | Lifecycle + reserve lock/release | ✅ | `WithdrawalStatus` Pending→Review→Approved→Signing→Broadcast→Completed (Failed/Cancelled); lock at `RequestWithdrawalAction.php:119`; release on never-broadcast failure |
| 2 | Batching | 🟡 (by design) | `withdrawal_batching_enabled` gates **RBF + dead-letter rebroadcast**, *not* multi-recipient batching. True multisend is deferred to infra (needs deployed contract). Documented in `CUSTODY_ROADMAP.md`. |
| 3 | Nonce management | ✅ | `NonceManager.php:29` `lockForUpdate()`, `:40` `max(onchain, next_nonce)` monotonic reconcile; **shared** by `EvmWithdrawalSigner.php:63` + `EvmGasSponsor.php:87` (no collision) |
| 4 | Rollback/refund on failure | ✅ | **No double-pay:** `ResolveFailedWithdrawalsAction.php:45` `whereNull('onchain_tx_id')` — never-broadcast → release; post-broadcast failure stays locked for reconciliation · *ResolveFailedWithdrawalsTest "never releases a post-broadcast failure"* |
| 5 | Retry logic | ✅ | Bounded attempts (`custody.withdrawal_max_broadcast_attempts`, default 3), strictly increasing fee, then DLQ · *WithdrawalRbfTest* |

---

## 6. Hot → Cold Transfers — ✅ PASS

| # | Check | Verdict | Evidence |
|---|-------|---------|----------|
| 1 | Watermark triggering | ✅ | Excess = `treasury:hot − custody.watermark.high.<SYM>`; watermark `0` = inert (`TronHotColdMoveAction.php:44,53-56`) · *TronHotColdMoveTest, HotColdWatermarkTest* |
| 2 | Destination addresses | ✅ (⚠️ verify once) | Sends to cold-watch xpub derived at index 0 (`:69-73`). **Operational prerequisite:** verify the derived cold address matches the offline wallet before enabling — a wrong cold address is irrecoverable (code cannot self-verify an offline device). |
| 3 | Confirmation flow | ✅ | `Settle*HotColdMovesAction` posts only after confirmation; reverted → `failed` + `Orphaned` · *TronHotColdMoveTest "settles…only after confirmation"* |
| 4 | Ledger consistency | ✅ | Debit cold / credit hot; `idempotencyKey move:settle:{id}`; one in-flight move per asset (`:65`) |

---

## 7. Cold → Hot Refill — ✅ PASS (code) · 🟡 approval is manual (by design)

| # | Check | Verdict | Evidence |
|---|-------|---------|----------|
| 1 | Approval workflow | 🟡 | `requested` is code-driven (`RequestColdRefillAction.php:76`, on under-watermark, + admin alert). `requested→approved→broadcast` are **operator/offline** (air-gapped/MPC signing) — **no approval UI/command exists** (intentional infra gap). `broadcast→settled` and `broadcast→approved` (revert) are code-driven. |
| 2 | Refill execution | ✅ | `SettleColdRefillAction.php:52-70` posts treasury:cold→hot (debit hot/credit cold) after confirmation; reverted → back to `approved` · *ColdRefillTest "settles cold → hot"* |
| 3 | Treasury accounting | ✅ | `amount = high − hot`; idempotent one-open-request-per-asset (`:56`); ledger `idempotencyKey refill:settle:{id}` · *ColdRefillTest "is idempotent"* |

---

## 8. Kill Switches — ✅ PASS

| # | Check | Verdict | Evidence |
|---|-------|---------|----------|
| 1 | All flags default OFF, checked at entry | ✅ | `onchain_sweep_enabled`, `gas_sponsoring_enabled`, `withdrawal_batching_enabled`, `hot_cold_move_enabled`, `hot_cold_refill_enabled`, `withdrawal_auto_release_failed` — each `feature(..., false)` early-return at the top of its action. Verified `false` in staging DB. |
| 2 | Disabling stops broadcasts immediately | ✅ | Flag is checked **before** any signing/broadcast, so the next tick performs no new on-chain action · *TronHotColdMoveTest "does nothing when the flag is off"* |
| 3 | No inconsistent ledger state mid-flight | ✅ | **All 5 `Settle*` actions contain 0 `feature()` calls** — in-flight broadcast txs still settle when they confirm even after the flag is switched off. Ledger only ever moves on confirmation. |

---

## 9. Monitoring — 🟡 PARTIAL

| Alert | Status | Evidence |
|-------|--------|----------|
| Reconciliation drift | ✅ | `CustodyReconciler.php:116` `notifyAdmins` |
| Insolvency | ✅ | `ReconciliationService.php:77` `notifyAdmins` + critical `SecurityEvent` |
| Stuck transactions (withdrawals) | ✅ | `RebroadcastStuckWithdrawalsAction.php:139` |
| Insufficient gas | ✅ | `TronGasSponsor.php:65`, `EvmGasSponsor.php:71`, `HotWalletManager.php:41` |
| Excessive retries (withdrawals) | ✅ | dead-letter alert after max attempts |
| Watermark over/under | ✅ | `HotColdWatermarkMonitor.php:43,51` |
| Refill requested | ✅ | `RequestColdRefillAction.php:81` |
| **Failed / stuck sweeps** | 🟡 GAP | activity log only (`sweep.failed`), no `notifyAdmins` |
| **Refill settlement revert** | 🟡 GAP | `SettleColdRefillAction` silently reverts `broadcast→approved`, no alert |
| **Nonce conflict** | 🟡 N/A | conflicts are *prevented* by `lockForUpdate` + monotonic reconcile; no residual-collision alert (nothing to alert on by design) |

None of the gaps risk funds or ledger integrity; they are observability improvements.
They can be covered operationally by monitoring queries (`sweeps.status='failed'`,
`cold_refill_requests` reverting to `approved`) without code changes.

---

## Operational Runbook — enabling custody in production

Flip flags **one asset at a time, smallest amounts first.** Flags live in settings group
`features`; watermarks in group `custody`. Toggle via the admin settings UI or
`updateSetting($key, $value, $group)`. **Kill-switch for every phase = set the phase's flag
`false`** (takes effect on the next tick; in-flight txs still settle safely — §8).

### Phase 0 (prerequisite) — Deploy the custody code
- **Prereq:** resolve the staging/custody branch divergence (§0) and deploy `main @ 3e56466`
  (or later) to the target environment; run `php artisan migrate`; `config:cache`; restart
  Horizon; confirm `php artisan list | grep poisapay:` shows `reconcile/sweep/rebalance/
  resolve-failed-withdrawals`.
- **Expected:** custody commands present; `schedule:list` shows `poisapay:reconcile` (5 min),
  `resolve-failed-withdrawals` (5 min), `EvmCustodyTickJob` (1 min).
- **Rollback:** redeploy the previous release; custody flags already OFF means no on-chain effect.
- **Kill-switch:** n/a (nothing enabled yet).

### Phase 1 — Enable reconciliation only
- **Prereq:** Phase 0 done; scheduler cron running.
- **Commands:** `php artisan poisapay:reconcile` (manual first run), then rely on the 5-min schedule.
- **Expected:** per-asset `ReconciliationRun` rows with `is_solvent=true`, `drift=0`; no drift/
  insolvency alerts. All money flags remain OFF.
- **Rollback / kill-switch:** reconciliation is read-only — nothing to roll back; stop the
  scheduled entry if needed.

### Phase 2 — Verify all treasury / cold addresses + signer config
- **Prereq:** Phase 1 clean.
- **Commands / checks:** compare `AddressDeriver::derive(chain, hotXpub, …)` and
  `derive(chain, coldXpub, 0)` against the physical hot signer and the offline cold device;
  confirm `SignerKeyProvider::hotWalletAddress()` resolves; confirm the seed is present only
  where required and cold keys are **offline**.
- **Expected:** derived hot/cold addresses **exactly** match the real wallets.
- **Rollback / kill-switch:** do not proceed if any address mismatches — a wrong cold address
  is irrecoverable. No flags flipped yet.

### Phase 3 — Fund gas wallets
- **Prereq:** Phase 2 verified.
- **Commands:** fund the hot wallet with native gas (TRX / ETH); enable
  `gas_sponsoring_enabled`.
- **Expected:** on a gas-needing deposit, a `gas_sponsorships` row funds + confirms; insufficient-
  gas alert clears.
- **Rollback / kill-switch:** set `gas_sponsoring_enabled=false` — no new top-ups; in-flight
  top-ups settle.

### Phase 4 — Enable the sweep engine
- **Prereq:** Phase 3; reconciliation clean; gas available.
- **Commands:** set `onchain_sweep_enabled=true`; `php artisan poisapay:sweep` for one small
  confirmed deposit.
- **Expected:** sweep broadcasts (ledger untouched) → settles after confirmation → `treasury:hot`
  credited → reconciler stays `drift=0`.
- **Rollback / kill-switch:** `onchain_sweep_enabled=false` — no new sweeps; in-flight sweeps
  settle. **Watch:** `sweeps.status='failed'` (no auto-alert — §2/§9).

### Phase 5 — Observe production deposits
- **Prereq:** Phase 4.
- **Commands:** none; watch a full deposit→detect→confirm→sweep→settle cycle at low volume.
- **Expected:** each real deposit credits once (idempotent), sweeps cleanly, reconciler zero-drift.
- **Rollback / kill-switch:** `onchain_sweep_enabled=false` (deposits still detect/credit;
  they simply won't sweep).

### Phase 6 — Enable withdrawal batching (RBF/DLQ)
- **Prereq:** withdrawals broadcasting cleanly; nonce reconcile healthy.
- **Commands:** set `withdrawal_batching_enabled=true` (enables RBF rebroadcast of stuck txs +
  dead-letter). Optionally `withdrawal_auto_release_failed=true` to release never-broadcast
  failures (post-broadcast stay locked — deliberate).
- **Expected:** a stuck withdrawal is rebroadcast with the same nonce + bumped fee; after max
  attempts it dead-letters (funds locked, admin alert).
- **Rollback / kill-switch:** `withdrawal_batching_enabled=false` — no rebroadcasts; existing
  withdrawals unaffected.

### Phase 7 — Enable hot→cold watermarks
- **Prereq:** cold address verified (Phase 2); `custody.watermark.high/low.<SYM>` set.
- **Commands:** set watermarks; `hot_cold_move_enabled=true`; `php artisan poisapay:rebalance`.
- **Expected:** excess over the high-watermark moves to cold; settles to `treasury:cold` after
  confirmation; reconciler zero-drift; over/under watermark alerts fire correctly.
- **Rollback / kill-switch:** `hot_cold_move_enabled=false` — no new moves; in-flight settle.

### Phase 8 — Enable cold→hot refill
- **Prereq:** Phase 7; offline/MPC signing + approval process staffed (infra).
- **Commands:** set `hot_cold_refill_enabled=true`.
- **Expected:** under-watermark raises a `cold_refill_requests` row + admin alert; operator
  approves, offline-signs, records `tx_hash`; `poisapay:rebalance` settles cold→hot after
  confirmation. **Watch:** a revert flips the request back to `approved` (no auto-alert — §9).
- **Rollback / kill-switch:** `hot_cold_refill_enabled=false` — no new requests; requests are
  inert until an operator acts.

### Phase 9 — Final production validation
- **Prereq:** Phases 1–8 exercised at low volume.
- **Checks:** full cycle deposit → sweep (gas-sponsored) → withdrawal (RBF-covered) → hot→cold →
  reconcile clean; confirm every flag toggles cleanly; confirm each alert path fires (force a
  gas shortage, a drift, a dead-letter in a controlled test).
- **Expected:** all reconciliation invariants hold end-to-end; no unexpected alerts; kill-switches
  verified live.
- **Rollback / kill-switch:** flip any/all flags OFF; reconciliation keeps watching. A drift or
  insolvency alert = immediate freeze (all flags OFF) + investigation.

---

## Production Readiness Report

**Software (repository) custody engineering: production-ready** for the core fund-safety
invariants, with three non-blocking observability gaps and one deployment blocker.

- **Fund-safety invariants — all validated & tested:** idempotent deposit crediting (DB unique +
  ledger idempotency key); ledger settles **only** after on-chain confirmation; no double-pay on
  post-broadcast withdrawal failures; double-entry balance enforced at app + DB layers; solvency +
  drift reconciliation with alerting; kill-switches default-OFF and effective, with in-flight
  settlement decoupled from the flags.
- **74 custody tests pass.** Repo is clean; custody work committed (`3e56466`).
- **Non-blocking gaps (observability, no fund risk):** (1) no proactive alert on failed/stuck
  sweeps; (2) no alert on cold-refill settlement revert; (3) no residual nonce-collision alert
  (collisions are prevented, not merely detected). All three are coverable by monitoring queries
  with zero code change.
- **Deployment blocker:** custody code is not on staging (branch divergence — §0).

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Custody code never reaches prod due to branch divergence | High (present now) | High | Reconcile branches (merge, not reset) before go-live — §0 |
| Wrong cold address enabled | Low | Critical (irrecoverable) | Phase-2 mandatory address verification vs offline device |
| Chronically stuck sweep goes unnoticed | Medium | Low (funds safe, recoverable) | Monitoring query on `sweeps.status='failed'`; reconciliation surfaces shortfall |
| Post-broadcast withdrawal ambiguity (double-pay) | Low | High | Already mitigated: funds stay locked, manual reconciliation path |
| Cold-refill revert unnoticed | Low | Low (request reverts to `approved`) | Monitoring query; operator retries |
| Offline/MPC signing process failure | Medium | Medium | Dual-control approval + audit (ops process, outside repo) |
| Enabling flags too fast / too large | Medium | High | Follow the phased runbook, one asset, smallest amounts first |

## Remaining Infrastructure, Compliance & Operational Requirements (outside the repository)

1. **Branch reconciliation + deployment** of the custody code to staging then production (§0).
2. **MPC / HSM signing** for cold→hot refills and an **approval UI/CLI** for the
   `requested→approved→broadcast` transitions (intentionally not in code).
3. **KMS / Vault** for hot-signer key material (replace env-seed signer).
4. **Own / dedicated blockchain nodes** (TronGrid / EVM RPC) for testnet + mainnet, and a
   **live-testnet validation run** with real nodes (chaos-test worker/RPC outage, load profile).
5. **Monitoring/alerting integration** (e.g. route `notifyAdmins` + the two gap queries to
   PagerDuty/Slack), dashboards, and on-call runbook.
6. **Watermark tuning** per asset from real volume; gas-wallet funding + auto-top-up policy.
7. **Compliance:** AML screening (Chainalysis/Elliptic), KYC (Sumsub/Onfido), PCI-DSS scoping,
   VASP/licensing, travel rule.
8. **HA / DR:** multi-region, DB failover, backup/restore drills, key-recovery ceremony.

## Go / No-Go Recommendation

**Software custody engineering: GO** — the repository's custody logic is production-ready from a
software perspective. The fund-safety invariants are implemented, tested, and flag-protected;
the identified gaps are observability-only and carry no fund-loss risk.

**Production go-live: NO-GO until the following (all outside custody logic) are done:**
1. Resolve the branch divergence and **deploy** the custody code (§0) — hard blocker.
2. Complete Phase-2 **address/signer verification** and stand up **MPC/HSM + KMS/Vault**.
3. Wire the **monitoring gaps** (sweep-failure, refill-revert) to the alerting stack.
4. Execute the **phased runbook** on live testnet, then mainnet at minimal amounts.

No further custody **code** work is required to reach GO; the remaining items are deployment,
infrastructure, compliance, and operational.
