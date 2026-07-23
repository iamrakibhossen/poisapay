# PoisaPay Custody-Correctness Roadmap & Handoff

Living handoff for the custody-hardening effort (simulated custody → real, reconciled,
RedotPay-grade custody). Read this first when resuming.

---

## Status — shipped to `main` (all tested, PHPStan level-6 clean, backward-compatible)

| Commit | Increment |
|---|---|
| `1e42f1e` | Guard simulated `ChainTickJob` to no-op under live custody (stop fake-settling real withdrawals) |
| `f3bc2c1` | On-chain custody reconciler + `TronGridClient::tokenBalance` |
| `0b404b3` | Integrate on-chain leg into `ReconciliationService`; make solvency check continuous |
| `711f868` | Release reserve on never-broadcast failed withdrawals (opt-in) |
| `f40481b` | Real TRON sweep — ledger settles only after confirmation |
| `61828f8` | Chain-agnostic gas/energy sponsoring engine (TRON) |
| `af88b21` | EVM sweep parity (sweep + gas sponsor + settle) |
| `2994152` | Hot-wallet watermark monitor (hot↔cold decision layer) |
| `1c72c37` | Persist withdrawal broadcast nonce/block/attempts (RBF foundation) |
| `13502bf` | Replace-By-Fee for stuck EVM withdrawals (opt-in) |
| `50c80ce` | Dead-letter queue for stuck withdrawals after RBF exhaustion |
| `2d4af55` | Wire RBF/DLQ into `EvmCustodyTickJob` (flag-gated) |
| `b5d2bfe` | TRON hot→cold on-chain execution (opt-in) |

### Design invariants now enforced
- **Ledger follows the chain, never leads it** — sweep/settle post `treasury:pending → treasury:hot` ONLY after on-chain confirmation.
- **Reconciliation is the safety net** — `poisapay:reconcile` (every 5 min) checks ledger solvency (treasury ≥ liability), on-chain hot backing (chain vs `treasury:hot`), and hot watermarks. Alerts + `insolvency` SecurityEvent on breach.
- **Deliberate safety preserved** — post-broadcast withdrawal failures (carry an `onchain_tx`) STAY locked for reconciliation; only definitively-never-broadcast ones auto-release. A test asserts this — do not "fix" it into a blanket refund (double-pay risk).

### Feature flags — ALL DEFAULT OFF (settings `features` group)
| Flag | Gates | Enable when |
|---|---|---|
| `onchain_sweep_enabled` | real sweeps (`poisapay:sweep`, not scheduled) | hot wallet + gas ready, reconciler watched |
| `gas_sponsoring_enabled` | native-gas top-ups to deposit addrs from hot wallet | hot wallet funded with native coin |
| `withdrawal_auto_release_failed` | `poisapay:resolve-failed-withdrawals` reserve release | after validating the failed-withdrawal set |
Watermarks: settings `custody.watermark.high.<SYMBOL>` / `.low.<SYMBOL>` (base units; `0` disables).

### Known pre-existing failures (NOT from this effort)
`tests/Feature/CurrencyLayerTest.php` — 3 failures ("7 is identical to 3", USDT asset-count). Verified failing at the commit *before* this effort; unrelated to custody. Fix separately.

---

## Remaining CODE work — implement in THIS order

### 1. Withdrawal batching + RBF + dead-letter queue — ✅ DONE (RBF + DLQ); multisend deferred
- **Shipped** (`1c72c37`, `13502bf`, `50c80ce`, `2d4af55`): broadcast nonce/block/attempts persisted; `RebroadcastStuckWithdrawalsAction` does RBF (same recorded nonce + fee bumped 25%/attempt) up to `withdrawal_max_broadcast_attempts`, then dead-letters (Failed + alert, funds stay locked for reconciliation); wired into `EvmCustodyTickJob`, all behind `withdrawal_batching_enabled` (default OFF).
- **Nonce risk resolved:** `NonceManager` is already a DB-locked per-`(chain, hot-address)` monotonic allocator shared by the gas sponsor + withdrawal signer — RBF reuses the recorded nonce, so no collision.
- **Config:** `poisapay.custody.withdrawal_stuck_blocks` (default 30), `withdrawal_max_broadcast_attempts` (default 3).
- **DEFERRED — true multisend batching (fewer txs / less gas):** needs a deployed multisend/disperse contract (an on-chain **infra** step + a contract address in config). The reliability layer (coordinated shared-nonce sequencing + RBF + DLQ) is done; the gas-saving aggregation is an infra follow-up, not blocking.
- **TODO (nice-to-have):** TRON RBF equivalent (rebroadcast the same ref-block tx) — lower priority; TRON txs rarely stick.

### 2. Hot → Cold on-chain execution — 🟡 TRON DONE (`b5d2bfe`); EVM parity + trigger-wiring remain
- **Shipped (TRON):** `TronHotColdMoveAction` broadcasts a TRC20 transfer hot→cold (hot key → cold-watch xpub's derived address); `SettleTronHotColdMovesAction` posts `treasury:hot → treasury:cold` (debit cold / credit hot) ONLY after confirmation. New `treasury_moves` table (idempotent, one in-flight move per asset). Excess = `treasury:hot` − `custody.watermark.high.<SYMBOL>`. Behind `hot_cold_move_enabled` (default OFF).
- **REMAINING:**
  1. **EVM parity** — mirror as `EvmHotColdMoveAction` + `SettleEvmHotColdMovesAction` (EIP-1559 ERC-20 hot→cold, decimal scaling, receipt-depth settle). Direct mirror of the EVM sweep + the TRON move; low risk.
  2. **Trigger wiring** — call the move action for `over`-watermark assets from the tick/a `poisapay:rebalance` command (flag-gated).
- **⚠️ Before enabling:** VERIFY the derived cold address matches the offline wallet (`AddressDeriver::derive(chain, coldXpub, 0)` vs the cold device) — a wrong cold address is irrecoverable.
- **Tests done (TRON):** over-watermark → broadcast; ledger moves to `treasury:cold` only after confirmation; flag-off no-op.

### 3. Cold → Hot refill workflow
- **Objective:** when the monitor flags `under`, drive an operator-approved, offline-signed refill from cold.
- **Architecture:** CANNOT be fully automated — the cold key is offline by design. Build a request/approval record (`cold_refill_requests`): monitor creates a `requested` row + alert; admin approves; an unsigned tx is built online, carried to the air-gapped/MPC signer, signed offline, pasted back; a `broadcast + settle` step posts `treasury:cold → treasury:hot` after confirmation. This is mostly workflow/UI + a settle action; the signing is external (MPC/HSM).
- **Risks:** 🟡 process/human-in-the-loop; ensure dual-control + audit. Low code risk.
- **Dependencies:** #2's settle machinery; ties into MPC/HSM (infra) for the offline signature.
- **Rollback:** the request/approval is inert until an operator acts.

### 4. Final end-to-end integration test + production-readiness review
- Full flow on testnet: deposit → sweep (gas-sponsored) → withdrawal (batched) → hot→cold → reconcile clean.
- Verify every reconciliation invariant holds end-to-end; chaos-test worker/RPC failure; confirm all flags flip cleanly; load profile.

---

## After the above, ONLY non-code work remains
MPC/HSM custody, KMS/Vault secrets, own blockchain nodes, Chainalysis/Elliptic AML,
Sumsub/Onfido KYC, PCI DSS scoping, multi-region HA, licensing/VASP, node operations,
third-party vendor integrations. (Specced in the architecture docs; procurement/ops, not commits.)

---

## Definition of Done (custody implementation complete when ALL hold)
- All remaining code tasks (1–4 above) implemented, tested, documented, and protected by feature flags where appropriate.
- **Reconciliation passes under both normal AND failure scenarios** (stuck/reverted/dropped txs, gas starvation, worker/RPC outage) — no undetected ledger⇄chain drift.
- Failure-recovery paths tested: RBF replaces stuck txs; DLQ + reserve-release only after confirmed on-chain absence; sweep/move settle only after confirmation.
- No custody-related code work remains — only external infra / compliance / MPC-HSM / KMS-Vault / licensing / node-ops / vendor integrations.
- Repository left clean & reproducible: all work committed, tests passing, docs (this file + memory) updated, flags OFF by default.

---

## Resume checklist
1. `git pull` → HEAD should be `2994152` or later.
2. Read `poisapay-custody-hardening` memory + this file.
3. Start with **task 1 (withdrawal batching)**: audit `NonceManager` + the two signers, design the batcher, keep it behind `withdrawal_batching_enabled` (default off), test, commit one logical change at a time.
4. Keep the discipline: extend existing abstractions, default-off flags, additive/zero-downtime migrations, tests + PHPStan + Pint after each change, no regression.
