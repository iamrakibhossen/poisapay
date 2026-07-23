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
| `4e5bfd3` | EVM hot→cold on-chain execution (parity) |
| `0da8ef8` | `poisapay:rebalance` command (hot→cold trigger, TRON + EVM) |
| `b55fd13` | Cold→hot refill workflow (request + settle, offline-signed) |
| `e16f83b` | End-to-end custody validation (sweep → settle → reconcile) |

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

### 2. Hot → Cold on-chain execution — ✅ DONE (TRON + EVM)
- **Shipped** (`b5d2bfe`, `4e5bfd3`, `0da8ef8`): `TronHotColdMoveAction`/`EvmHotColdMoveAction` broadcast a hot→cold transfer (hot key → cold-watch xpub's derived address; EVM scales decimals + shared `NonceManager`); `Settle{Tron,Evm}HotColdMovesAction` post `treasury:hot → treasury:cold` (debit cold / credit hot) ONLY after confirmation. `treasury_moves` table (idempotent, one in-flight move per asset). Excess = `treasury:hot` − `custody.watermark.high.<SYMBOL>`. `poisapay:rebalance` command triggers it (opt-in, not scheduled). Behind `hot_cold_move_enabled` (default OFF).
- **⚠️ Before enabling in prod:** VERIFY the derived cold address matches the offline wallet (`AddressDeriver::derive(chain, coldXpub, 0)` vs the cold device) — a wrong cold address is irrecoverable.

### 3. Cold → Hot refill workflow — ✅ DONE (code core; signing is external by design)
- **Shipped** (`b55fd13`): `cold_refill_requests` table + `RequestColdRefillAction` (raises a `requested` row + alert when treasury:hot < low-watermark; amount tops hot up to high-watermark; idempotent one-per-asset) + `SettleColdRefillAction` (posts `treasury:cold → treasury:hot` after the operator's offline-signed tx confirms; routes TRON/EVM; reverted → back to `approved`). Wired into `poisapay:rebalance`. Behind `hot_cold_refill_enabled` (default OFF).
- **Remaining = NON-CODE (by design):** the offline/MPC/air-gapped signing of the cold→hot tx, and an admin approval UI to move `requested → approved → broadcast` + record the tx hash. Both are ops/infra, not custody logic.

### 4. E2E integration + reconciliation validation — ✅ DONE (test); readiness review below
- **Shipped** (`e16f83b`): end-to-end test — sweep broadcasts, settles to the ledger only after confirmation, reconciler confirms on-chain hot == ledger treasury:hot (zero drift).
- **Failure-recovery covered by unit tests:** reverted sweep/move/refill → `failed`/reset; RBF replaces stuck tx; DLQ after exhaustion; reconciler flags drift + insolvency.
- **Remaining validation = ops:** run the full flow on **live testnet** with flags on (real nodes), chaos-test worker/RPC outage, load-profile — needs infra (nodes), not code.

---

## Production-readiness / enablement runbook (flip flags in THIS order, one asset at a time)
All money paths are OFF by default. To go live, per chain/asset, smallest amounts first:
1. **Reconciliation is already live** (scheduled) — watch it clean for the asset before anything else.
2. **Verify the hot & cold addresses** on-chain match the offline/hardware wallets (`AddressDeriver::derive`). A wrong address is irrecoverable.
3. **Fund the hot wallet** with native gas (TRX/ETH) → enable `gas_sponsoring_enabled` → watch a `gas_sponsorships` row fund + confirm.
4. **Enable `onchain_sweep_enabled`** → run `poisapay:sweep` for one small deposit → confirm the sweep settles and the reconciler stays zero-drift.
5. **Enable `withdrawal_batching_enabled`** (RBF/DLQ) once withdrawals broadcast cleanly.
6. **Set `custody.watermark.high/low.<SYMBOL>`**, seed cold, enable `hot_cold_move_enabled` → run `poisapay:rebalance` → confirm the move settles to `treasury:cold`.
7. **Enable `hot_cold_refill_enabled`** → confirm an under-watermark raises a request + alert; wire the offline-signing/approval ops process.
- **Kill-switch:** flip any flag OFF to halt that path instantly; the reconciler keeps watching. A drift/insolvency alert should trigger an immediate freeze + investigation.

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
