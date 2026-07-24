# PoisaPay Ledger Accounting

Institutional double-entry model for a **custodial** exchange acting as **principal
/ dealer**. Every value event is a balanced `journal_entry` of `ledger_lines`
(`app/Domain/Ledger`). Amounts are integer **base units**; each asset is its own
self-balancing sub-ledger.

## 1. The dual book

A custodial exchange holds two parallel sets of accounts per currency:

- **Assets** — crypto/fiat we actually control on-chain or in a bank.
- **Liabilities** — what we owe customers.
- **Equity** — the house's own stake (dealer position) and retained earnings.

The fundamental identity holds **per asset**:

```
CustodyAssets(asset)  =  CustomerLiabilities(asset)  +  Equity(asset)

where Equity(asset) = TradingInventory(asset) + RetainedEarnings(asset)
      TreasuryHot(+Cold+Pending) = UserFunds + TradingInventory + Income
```

Custody accounts (`treasury:*`) move **only** on real chain/bank events, so they
reconcile 1:1 to the wallets. Internal conversions never touch custody — they move
customer liabilities and the dealer position (`dealer:inventory`).

## 2. Chart of accounts

| Account (`LedgerAccountType`) | Value | Class | Normal | Meaning |
|---|---|---|---|---|
| `TreasuryHot` | `treasury:hot` | **Asset** | Dr | On-chain hot wallet (live signing) |
| `TreasuryCold` | `treasury:cold` | **Asset** | Dr | Cold storage |
| `TreasuryPending` | `treasury:pending` | **Asset** | Dr | Funds in transit / unconfirmed |
| `TreasuryOut` | `treasury:out` | **Asset** | Dr | Outbound custody (withdrawal in flight) |
| `RampClearing` | `ramp:clearing` | **Asset** (clearing) | Dr | Fiat on/off-ramp suspense |
| `CreditPrincipal` | `credit:principal` | **Asset** | Dr | Loan principal receivable |
| `CreditAccruedFee` | `credit:accrued_fee` | **Asset** | Dr | Accrued interest receivable |
| `UserAvailable` | `user:available` | **Liability** | Cr | Customer spendable balance |
| `UserLocked` | `user:locked` | **Liability** | Cr | Customer funds on hold |
| `UserCardHold` | `user:card_hold` | **Liability** | Cr | Card authorization hold |
| `UserCollateralLocked` | `user:collateral:locked` | **Liability** | Cr | Credit collateral hold |
| `UserP2pEscrow` | `user:p2p_escrow` | **Liability** | Cr | P2P trade escrow (seller) |
| `LiabilityUserFunds` | `liability:user-funds` | **Liability** | Cr | Aggregate customer-funds control |
| `CardProgramSettlement` | `card_program:settlement` | **Liability** | Cr | Payable to card program |
| `Rewards` | `rewards:pool` | **Liability** | Cr | Rewards pool payable |
| **`TradingInventory`** | **`dealer:inventory`** | **Equity** | **Cr** | **House dealer position / working capital** |
| `OwnerPayout` | `owner:payout` | **Equity** | Cr | Cumulative owner distributions |
| `FeeIncome` | `fee:income` | **Income** | Cr | Platform fees |
| `FeeCard` | `fee:card` | **Income** | Cr | Card fees / interchange |
| `FxSpreadIncome` | `fx:spread_income` | **Income** | Cr | Swap spread |
| `P2pFeeIncome` | `p2p:fee_income` | **Income** | Cr | P2P taker fee |
| `GasExpense` | `gas:expense` | **Expense** | Dr | Network gas cost |
| `CardProgramLoss` | `card_program:loss` | **Expense** | Dr | Chargeback / program loss |

### TradingInventory is EQUITY, not inventory

Despite the name, `dealer:inventory` is **not** an IAS 2 inventory *asset* — the coins
themselves are already the asset (`TreasuryHot`). Booking a second asset would
double-count. `TradingInventory` is the **residual equity claim** on custody:
`TreasuryHot − CustomerLiabilities − RetainedEarnings` = the house's own stake in
the pooled wallet. It is credit-normal (equity side) so it goes **up** when the
house acquires a currency and **down** when it releases one.

## 3. Posting matrix

Every entry balances **within each asset**. `(F)` = from-asset, `(T)` = to-asset.

| Event | Dr | Cr |
|---|---|---|
| **Crypto/Fiat deposit** | `TreasuryHot` (gross) | `UserAvailable` (net) · `FeeIncome` (fee) |
| **Internal transfer** | `UserAvailable` (sender) | `UserAvailable` (recipient) |
| **Withdrawal — lock** | `UserAvailable` | `UserLocked` |
| **Withdrawal — settle** | `UserLocked` (total) | `TreasuryOut` (amount) · `FeeIncome` (fee) |
| **Withdrawal — fail/release** | `UserLocked` | `UserAvailable` |
| **Internal swap / ramp / card-settle FX** | `UserAvailable` (F) · `TradingInventory` (T) | `TradingInventory` (F, net) · `FxSpreadIncome` (F) · `FeeIncome` (F) · `UserAvailable` (T) |
| **Dealer inventory injection** | `TreasuryHot` | `TradingInventory` |
| **Card authorization (hold)** | `UserAvailable` | `UserCardHold` |
| **Card settlement** | `UserCardHold` (held) | `CardProgramSettlement` (net) · `FeeCard` (fee) · `UserAvailable` (over-hold) |
| **Card refund** | `CardProgramSettlement` | `UserAvailable` |
| **Card auth reversal** | `UserCardHold` | `UserAvailable` |
| **P2P escrow — lock** | `UserAvailable` (seller) | `UserP2pEscrow` |
| **P2P escrow — release** | `UserP2pEscrow` | `UserAvailable` (buyer, net) · `P2pFeeIncome` (fee) |
| **P2P escrow — refund** | `UserP2pEscrow` | `UserAvailable` (seller) |
| **Hot → Cold sweep** | `TreasuryCold` | `TreasuryHot` |
| **Cold → Hot refill** | `TreasuryHot` | `TreasuryCold` |
| **Gas spend** | `GasExpense` (native) | `TreasuryHot` (native) |
| **Revenue / profit withdrawal** | `FeeIncome` / `FxSpreadIncome` / `OwnerPayout` | `TreasuryHot` |
| **Correction** | mirror of the original | mirror (linked via `reverses_entry_id`) |

### The internal swap in full (example: BDT → USDT)

```
BDT sub-ledger:  DR UserAvailable(BDT)        1,000
                 CR TradingInventory(BDT)       992.5
                 CR FxSpreadIncome(BDT)           7.5
USDT sub-ledger: DR TradingInventory(USDT)       9.925
                 CR UserAvailable(USDT)           9.925
```

Custody (`TreasuryHot`) is **untouched**. The house goes long BDT / short USDT in
`TradingInventory` (nets flat at mid); the margin is booked to income. A pre-post
guard rejects the swap unless `TradingInventory(T) ≥ toAmount` — this is the
per-asset solvency gate.

## 4. Multi-currency & spread rules

1. **Per-asset balance** — `Σ debit = Σ credit` for **each** `asset_id`, never across
   assets. Enforced by the deferred DB trigger `assert_entry_balanced()`
   (`GROUP BY asset_id`, migration `2026_07_24_150000`).
2. **Cross-currency = ≥2 balanced legs** bridged by `TradingInventory` — the only
   account a single trade touches in two currencies, and it does so as two
   independent single-asset legs.
3. **Spread recognition** — booked in the from-asset to `FxSpreadIncome`/`FeeIncome`
   at execution; `TradingInventory` nets flat at mid, so P&L = pure spread. FX
   drift on the standing position is realised on rebalance.

## 5. IFRS / GAAP notes

- **Customer balances are liabilities**, measured at amount owed. They are never
  revenue and never equity.
- **Custody = assets** at fair value; crypto held is an intangible/financial asset
  per the entity's policy (IAS 38 / ASC 350, or fair-value for broker-dealers).
- **Dealer inventory injection** (`DR TreasuryHot / CR TradingInventory`) is a
  **non-monetary capital contribution in kind** — DR asset / CR contributed equity
  at fair value on the date (IFRS Conceptual Framework, IAS 32; US GAAP ASC 505-10).
  It is **equity**, not IAS 2 inventory, not a liability. If coins were already
  recognised in a separate corporate-holdings asset account, the injection would
  instead be an asset-to-asset reclassification (no account exists for that today).
- **Spread & fees** are revenue (IFRS 15 / ASC 606) recognised when the conversion
  completes.
- **Gas / chargebacks** are expenses.

## 6. Invariants & controls

| Control | Where |
|---|---|
| Per-asset balance | DB trigger `assert_entry_balanced()` |
| Custody = chain | `poisapay:reconcile` → `CustodyReconciler` (chain vs `treasury:hot`) |
| Proof-of-reserve: `CustodyAssets ≥ CustomerLiabilities` per asset | `LedgerReportService::solvency()` |
| Dealer liquidity: `TradingInventory(T) ≥ toAmount` before swap | `ExchangeService::execute()` |
| Immutable audit | append-only lines; corrections via `reverses_entry_id` |
| Idempotency | `journal_entries.idempotency_key` (unique) |

Treasury/liquidity metrics (on-chain assets, customer liabilities, TradingInventory
by asset, available liquidity, reserve ratio, dealer long/short, liquidity alerts)
surface on the **Treasury Analytics** dashboard (`/admin/analytics/treasury`).
