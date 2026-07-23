# User Deposit Flow — Production Custodial Wallet

> **Status: already implemented, tested, and validated.** This document is the authoritative
> reference for the *existing* production deposit pipeline (detect → confirm → credit → sweep →
> reconcile). It is **not** a proposal for new tables or a parallel flow — re-implementing would
> duplicate tested logic and violate the codebase's "reuse abstractions / no duplicate logic"
> rule. Where the request used idealized names, the real equivalent is mapped below. The only
> genuine deltas (a thin named-event layer and auto-sweep-on-confirm) are called out at the end.

---

## A. Requirement → implementation mapping

| Spec requirement | Where it lives today | Status |
|---|---|---|
| Unique per-user address per network/token | `deposit_addresses` + `CustodyXpub` (xpub), `DepositAddress` model | ✅ |
| HD-derived from xpub, **no private keys on server** | `AddressDeriver` (`TronAddressDeriver`/EVM) derives *public* addresses from xpub; `SignerKeyProvider::derivePrivateKey()` reconstructs a key **in-memory only** for a sweep, from an env/KMS seed | ✅ |
| Background listener + confirmation tracking + dedup + reorg | `ScanTronDepositsAction` / `ScanEvmDepositsAction` (detect) + `AdvanceTronDepositsAction` / `AdvanceEvmDepositsAction` (confirm/reorg), scheduled by `TronCustodyTickJob` / `EvmCustodyTickJob` | ✅ |
| Pending deposit record (no credit yet) | `deposits` row, status `detected`→`confirming`; `onchain_txs` holds tx_hash/block/confirmations | ✅ |
| Credit after N confirmations + immutable ledger + event + notify | `CreditDepositAction` → double-entry `journal_entries`/`ledger_lines`, `DepositCredited` event → `HandleDepositCredited` listener | ✅ |
| Automatic sweep, settle only after on-chain confirm, retry, no double-credit | `TronSweepAction`/`EvmSweepAction` (broadcast) + `SettleTronSweepsAction`/`SettleEvmSweepsAction` (settle) | ✅ (sweep is a flag-gated command, **not** auto-queued on confirm — see §Deltas) |
| Separate user / hot / cold ledgers, double-entry | `LedgerAccountType` (UserAvailable, UserLocked, TreasuryHot, TreasuryCold, TreasuryPending) via `LedgerService` | ✅ |
| Reconciliation worker, alert on drift, never silently mutate | `poisapay:reconcile` (every 5 min) → `ReconciliationService` + `CustodyReconciler` → `security_events` + `notifyAdmins`; read-only | ✅ |
| Idempotency everywhere | DB uniques (`uq_onchain_tx`, `uq_deposit_onchain_tx`, `uq_sweep_nonce_context`) + ledger `idempotency_key` | ✅ |
| Full audit trail + transactional transitions | `ActivityLogger`, `security_events`, every state change wrapped in `DB::transaction` | ✅ |

### Table-name mapping (idealized → real)

| Spec name | Real table |
|---|---|
| `deposit_addresses` | `deposit_addresses` *(identical)* |
| `deposits` | `deposits` *(identical)* |
| `blockchain_transactions` | `onchain_txs` |
| `sweep_jobs` | `sweeps` (+ `broadcast_attempts` for per-attempt history) |
| `treasury_wallets` | `ledger_accounts` (TreasuryHot/Cold/Pending) + `custody_xpubs` (on-chain addresses) |
| `treasury_movements` | `treasury_moves` (hot↔cold) + the ledger `journal_entries` |
| `ledger_entries` | `journal_entries` (header) + `ledger_lines` (double-entry lines) + `account_balances` (materialized) |
| `reconciliation_logs` | `reconciliation_runs` (+ `security_events` for breaches) |

### Event mapping (spec → real)

| Spec event | Real mechanism |
|---|---|
| `DepositDetected` | status transition `onchain_txs`/`deposits` → `detected` + `ActivityLogger` (no Event object) |
| `DepositConfirmed` | **`DepositCredited` event** (`CreditDepositAction`), dispatched to `HandleDepositCredited` |
| `SweepRequested` / `SweepBroadcasted` / `SweepConfirmed` / `SweepFailed` | `sweeps.status` machine (`pending`→`broadcast`→`swept`/`failed`) + `ActivityLogger` (`sweep.broadcast`, `sweep.failed`); **no Event objects** |

---

## 1. Deposit sequence diagram

```mermaid
sequenceDiagram
    actor U as User (RedotPay)
    participant BC as Blockchain (TRON/EVM)
    participant SC as Scan*DepositsAction
    participant AD as Advance*DepositsAction
    participant CR as CreditDepositAction
    participant LG as LedgerService
    participant EV as DepositCredited event
    participant SW as Sweep*Action (poisapay:sweep)
    participant ST as Settle*SweepsAction
    participant RC as poisapay:reconcile

    U->>BC: send USDT to user's deposit address
    loop every custody tick (job)
        SC->>BC: fetch inbound transfers for watched addresses
        BC-->>SC: transfer (tx_hash, log_index, amount, block)
        SC->>SC: INSERT onchain_txs (uq_onchain_tx) + deposits(status=detected)
        Note over SC: duplicate tx ⇒ unique index rejects ⇒ ignored
        AD->>BC: latest block height
        AD->>AD: confirmations = head - block + 1
        alt reverted / disappeared (reorg)
            AD->>AD: deposit=orphaned, onchain_tx=orphaned (no ledger effect)
        else confs >= required_confirmations
            AD->>CR: credit(deposit)
            CR->>LG: post(debit Treasury:Pending, credit User:Available)  [idem: deposit:{tx}:{log}]
            CR->>CR: deposit.status=credited, credit_entry_id, credited_at
            CR->>EV: DepositCredited::dispatch
            EV-->>U: notification "deposit credited"
        end
    end

    Note over SW: onchain_sweep_enabled = true
    SW->>BC: read token balance at deposit address
    SW->>BC: (optional) gas-sponsor native fee from hot wallet
    SW->>BC: sign (in-memory deposit key) + broadcast transfer -> HOT wallet
    SW->>SW: INSERT sweeps(status=broadcast) + onchain_txs(direction=out)  — NO ledger post
    loop settle tick
        ST->>BC: tx receipt / info
        alt confirmed (depth reached)
            ST->>LG: post(debit Treasury:Hot, credit Treasury:Pending)  [idem: sweep:settle:{id}]
            ST->>ST: sweep.status=swept
        else reverted
            ST->>ST: sweep=failed, onchain_tx=orphaned (ledger untouched; re-sweep next tick)
        end
    end

    loop every 5 min
        RC->>BC: on-chain hot balance
        RC->>LG: ledger treasury:hot + solvency
        alt drift or insolvency
            RC-->>U: (ops) security_event + notifyAdmins — NEVER mutates balances
        end
    end
```

---

## 2. State machines

**Deposit / on-chain tx** (`DepositStatus`, `OnchainTxStatus`):

```
          detect                 confs<required            confs>=required
 (none) ──────────▶ detected ───────────────▶ confirming ─────────────────▶ credited
                        │                          │                            │
                        │  tx reverted/reorg        │  tx reverted/reorg          │ (terminal, immutable
                        └──────────────┬───────────┘                            │  ledger entry written)
                                       ▼
                                    orphaned  (no ledger effect if never credited)
```

**Sweep** (`SweepStatus`) — the Detected→Pending→Confirmed→Sweeping→Swept lifecycle you asked for,
in the code's vocabulary:

```
 pending ──▶ gassing ──▶ signing ──▶ broadcast ──▶ swept        (ledger: hot↑ / pending↓, only here)
    │  (top up native gas if needed)      │
    │                                     ├──▶ failed  (broadcast rejected OR on-chain revert)
    └─────────────────────────────────────┘        │
                                                    └──▶ re-swept on next tick (funds still at deposit addr)
```

Mapping to the requested names: **Detected→Pending** = deposit `detected`/`confirming`;
**Confirmed** = deposit `credited`; **Sweeping** = sweep `broadcast`; **Swept** = sweep `swept`.

---

## 3. Database schema (real, from migrations)

`deposit_addresses` — one HD address per (user, chain, index)
- `id` uuid, `user_id`, `chain_id`, `xpub_id`→`custody_xpubs`, `derivation_index`, `address(64)`, `is_watched`
- **uniques:** `uq_addr_chain_address (chain_id,address)`, `uq_addr_xpub_index (xpub_id,derivation_index)`

`onchain_txs` — every observed on-chain movement (your `blockchain_transactions`)
- `id` uuid, `chain_id`, `tx_hash(80)`, `log_index`, `from/to_address`, `asset_id`, `amount NUMERIC(78,0)`, `block_number`, `confirmations`, `status`, `direction(in|out)`
- **unique:** `uq_onchain_tx (chain_id,tx_hash,log_index)` ← duplicate-event guard

`deposits` — user-facing pending/credited deposit
- `id` uuid, `user_id`, `deposit_address_id`, `asset_id`, `onchain_tx_id`, `amount`, `confirmations`, `required_confirmations`, `status`, `credit_entry_id`→`journal_entries`, `credited_at`
- **unique:** `uq_deposit_onchain_tx (onchain_tx_id)` ← **one credit per tx (no double-credit)**

`sweeps` — deposit→hot transfer (your `sweep_jobs`)
- `id` uuid, `deposit_address_id`, `asset_id`, `amount`, `gas_cost`, `status`, `nonce_context`, `settle_entry_id`, `onchain_tx_id`
- **unique:** `uq_sweep_nonce_context` ← duplicate-sweep guard

`broadcast_attempts` — per-attempt history for sweeps & withdrawals
- `subject_type(withdrawal|sweep)`, `subject_id`, `tx_hash`, `attempt`, `outcome`, `provider_response`

Ledger (your `ledger_entries`):
- `ledger_accounts` (UserAvailable/UserLocked/TreasuryHot/TreasuryCold/TreasuryPending per asset),
  `journal_entries` (header, `idempotency_key` unique), `ledger_lines` (double-entry debit/credit
  lines; balanced by app assertion **and** deferred DB trigger `trg_entry_balanced`),
  `account_balances` (materialized signed balance).

Treasury & reconciliation:
- `treasury_moves` (hot↔cold, your `treasury_movements`), `cold_refill_requests`,
  `reconciliation_runs` (your `reconciliation_logs`), `security_events`, `gas_sponsorships`,
  `custody_xpubs`.

---

## 4. Laravel jobs / events / services involved

**Scheduled entry points** (`routes/console.php`):
- `TronCustodyTickJob`, `EvmCustodyTickJob` — `scan → advanceDeposits → withdrawals (+RBF)` per tick
- `poisapay:reconcile` (5 min), `poisapay:resolve-failed-withdrawals` (5 min)
- `poisapay:sweep`, `poisapay:rebalance` — opt-in (flag-gated), run on schedule/manually

**Services / actions (the real "jobs" of the pipeline):**
- Detect: `ScanTronDepositsAction`, `ScanEvmDepositsAction`
- Confirm/reorg: `AdvanceTronDepositsAction`, `AdvanceEvmDepositsAction`
- Credit: `CreditDepositAction` (+ `CreditManualDepositAction`, `SubmitManualDepositAction`)
- Ledger: `LedgerService`, `AccountResolver`, `EntryData`, `PostingLine`
- Sweep: `TronSweepAction`/`EvmSweepAction` + `SettleTronSweepsAction`/`SettleEvmSweepsAction`
- Gas: `TronGasSponsor`, `EvmGasSponsor`, `GasEstimationService`, `NonceManager`
- Keys: `SignerKeyProvider` (in-memory only), `AddressDeriver`, `Bip32`, `Secp256k1Signer`
- Reconcile: `ReconciliationService`, `CustodyReconciler`, `HotColdWatermarkMonitor`

**Events / listeners today:** `DepositCredited` → `HandleDepositCredited` (user notification).
The other spec events (`DepositDetected`, `Sweep*`) are modeled as `sweeps`/`deposits` status
transitions + `ActivityLogger`, not dispatched Event objects.

---

## 5. Failure recovery scenarios

| Scenario | Behavior | Why it's safe |
|---|---|---|
| **Duplicate detection event** (scan runs twice, RPC replays) | `uq_onchain_tx` rejects the second insert; `QueryException` swallowed | No duplicate deposit row |
| **Credit worker retried** after posting | `journal_entries.idempotency_key = deposit:{tx}:{log}` returns the existing entry | No double-credit |
| **Chain reorg before credit** | tx no longer confirmed/reverted → deposit + onchain_tx → `orphaned`; no ledger entry ever written | User never credited on a rolled-back tx |
| **Chain reorg after credit** | credit is confirmation-gated at `required_confirmations` (finality depth), so a reorg deep enough to reverse is beyond the safety window; if it ever occurred it surfaces as reconciliation drift → alert, never a silent balance edit | Confirmations = replay/reorg defense |
| **Sweep broadcast fails** | `sweep.status = failed`; funds remain at the deposit address; next tick re-sweeps | Ledger untouched; recoverable |
| **Sweep reverts on-chain** | `SettleSweepsAction` sets `failed` + onchain_tx `orphaned`; **no** ledger post | No phantom treasury credit |
| **Sweep settle worker retried** | `idempotency_key = sweep:settle:{id}` + `uq_sweep_nonce_context` | No double treasury credit, no duplicate broadcast |
| **Insufficient gas** | gas sponsor tops up (idempotent, bounded retry, dead-letters + alerts) or sweep stays `gassing` | No stuck-without-visibility beyond the alert |
| **Ledger vs chain drift** | `poisapay:reconcile` flags `breached`/insolvent → `security_events` + `notifyAdmins` | Never auto-corrects; humans investigate |
| **Process crash mid-transition** | every transition is `DB::transaction`-wrapped | Atomic; partial state impossible |

---

## 6. End-to-end: from "user sends USDT" to "hot wallet funded + balance credited"

1. **Address issuance (earlier).** The user was given a deposit address derived from our
   `custody_xpubs` xpub at their `derivation_index` via `AddressDeriver`. Only the **public**
   address is stored (`deposit_addresses`); no private key exists on the server.
2. **User sends USDT** from RedotPay to that address. It lands on-chain.
3. **Detection.** On the next custody tick, `Scan*DepositsAction` queries inbound transfers for
   watched addresses, matches the token contract/asset, and inserts an `onchain_txs` row
   (`status=detected`, `direction=in`) plus a `deposits` row (`status=detected`,
   `required_confirmations` from the asset). The `uq_onchain_tx` unique index means a replayed
   event can never create a second row. **No balance is credited.**
4. **Confirmation tracking.** Each tick, `Advance*DepositsAction` recomputes
   `confirmations = head − block + 1`. Below the threshold → `confirming`. If the tx reverts or
   disappears (reorg) → `orphaned`, with zero ledger effect.
5. **Credit.** Once `confirmations ≥ required_confirmations`, `CreditDepositAction` runs inside a
   `DB::transaction`: it posts a **double-entry** journal entry — **debit `Treasury:Pending`**
   (the asset is now in our custody, awaiting sweep) and **credit `User:Available`** (the user's
   internal balance) — keyed by `idempotency_key = deposit:{tx}:{log}`. It stamps the deposit
   `credited` with `credit_entry_id` + `credited_at`, then dispatches **`DepositCredited`**;
   `HandleDepositCredited` notifies the user. The user's balance is now spendable.
6. **Sweep (custody consolidation).** With `onchain_sweep_enabled`, `poisapay:sweep` runs
   `Tron/EvmSweepAction`: it reads the on-chain balance at the deposit address, optionally
   gas-sponsors the native fee from the hot wallet, reconstructs the deposit key **in memory**
   via `SignerKeyProvider`, signs a token transfer to the **hot wallet**, and broadcasts it. It
   writes a `sweeps` row (`status=broadcast`) and an outbound `onchain_txs` row — **and posts
   nothing to the ledger yet.**
7. **Sweep settlement.** After the sweep tx reaches confirmation depth, `Settle*SweepsAction`
   posts the second double-entry — **debit `Treasury:Hot` / credit `Treasury:Pending`** (keyed
   `sweep:settle:{id}`) — moving the asset from "pending" to "hot" on the books, and marks the
   sweep `swept`. If the sweep reverted, it marks `failed` and leaves the ledger untouched; the
   next tick re-sweeps. The net books: **User:Available (liability) ↑, Treasury:Hot (asset) ↑,
   Treasury:Pending nets to zero** — user credited exactly once, treasury reflects real custody.
8. **Reconciliation (continuous).** Every 5 minutes `poisapay:reconcile` compares on-chain hot
   balance vs ledger `Treasury:Hot`, and total user liabilities vs treasury assets. Zero drift +
   solvent → silent. Any drift/insolvency → `security_events` + `notifyAdmins`; balances are
   **never** silently changed.

---

## Genuine deltas vs. the literal spec (the only things not already present)

These are **optional, additive** — nothing here is a bug, and none of it changes the tested logic:

1. **Named event objects.** Spec lists `DepositDetected`, `SweepRequested`, `SweepBroadcasted`,
   `SweepConfirmed`, `SweepFailed`. Today only `DepositCredited` is a real Event; the rest are
   status transitions + activity logs. Adding the thin event classes (dispatched from the
   existing transition points, no logic change) would satisfy the spec literally and give
   listeners/webhooks a clean hook. **Additive, low-risk.**
2. **Auto-sweep on confirmation.** Spec says "immediately queue a sweep after confirmation."
   Today the sweep is a separate flag-gated command (`poisapay:sweep`) run on schedule/manually,
   deliberately decoupled so custody consolidation stays operator-controlled. Wiring
   `CreditDepositAction` to dispatch a `SweepRequested`/queue a sweep job (still behind
   `onchain_sweep_enabled`) would match the spec — a small, contained change.
3. **Table naming.** Purely cosmetic; renaming tested tables (`onchain_txs`→`blockchain_transactions`
   etc.) would be churn with migration risk and **is not recommended**.

The monitoring gaps from the readiness report (no proactive alert on failed sweeps / refill
reverts) also apply here and remain the higher-value hardening than any renaming.
