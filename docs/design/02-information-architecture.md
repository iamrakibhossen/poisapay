# PoisaPay — Phase 2: Information Architecture

**Goal:** make 12 surfaces feel like *one ecosystem*, where any core job is ≤3 taps and users never wonder "where am I / where do I go." IA is designed once and shared across mobile (bottom tabs) and desktop (rail), so behavior is identical everywhere.

---

## 1. Navigation model

### 1.1 The two-shell principle

One IA, two shells that expose the *same* destinations:

- **Mobile (primary):** persistent **bottom tab bar** (thumb zone) + a persistent **search affordance** in each screen header. Replaces today's hamburger off-canvas drawer (audit N2).
- **Desktop:** left **rail** (evolves the current sidebar) + a global **command/search bar** in the top bar.

Never design a destination that exists in one shell but not the other.

### 1.2 Bottom tab bar (mobile) — 5 primary destinations

```
┌──────────────────────────────────────────────────────┐
│                                                        │
│                   (screen content)                     │
│                                                        │
├──────────────────────────────────────────────────────┤
│   ⌂        ▤         ⦿         ⇄         ☰            │
│  Home    Wallet    Pay       Market   Account          │
│                  (raised)                               │
└──────────────────────────────────────────────────────┘
```

| Tab | Owns | Why it earns a tab |
|---|---|---|
| **Home** | Dashboard: balance, quick actions, activity, cards, promos, market summary, search entry | The daily landing; answers "what's my state." |
| **Wallet** | Assets list, per-asset detail, deposit/withdraw/swap/transfer entry, history | The money-holding surface; highest-frequency after Home. |
| **Pay** *(raised center)* | Send / Request / Scan-to-Pay / Pay invoice | The single most frequent *money action*, given the thumb-priority center slot. |
| **Market** | P2P marketplace + Exchange + (later) Shop discovery | P2P is a headline differentiator and engagement engine — it must be primary, not buried. |
| **Account** | Cards, Rewards, Merchant/Seller, Settings, Support, KYC | The "me + my tools" hub; also the overflow for secondary surfaces. |

**Design notes**
- **Cards** is surfaced in three high-traffic places (Home card widget, Wallet, Account) even though it's not a bottom tab — a card-first user reaches it in 1 tap from Home. If analytics later show card-centric usage, the tab set is a token swap, not a redesign.
- The **raised center "Pay"** is the app's signature action and its most memorable affordance (Cash App/Wise pattern, executed originally — not cloned).
- Tab bar hides on full-screen flows (checkout, KYC wizard, P2P chat) to maximize focus and signal "you're in a task."

### 1.3 Desktop rail (evolves current sidebar)

Keep the current grouped rail (Overview / Money / Products / Account) but: (a) add a persistent global-search field at the top, (b) mirror the 5 mobile primaries as the top-level order, (c) collapse to icons on narrow widths. No net loss of the existing structure.

---

## 2. Global search (audit N1 — the biggest IA gap)

A single search primitive, reachable from every screen header (mobile) and the command bar (desktop, `⌘K`/`/`).

**Searchable entities & routing:**

| Entity | Example query | Resolves to |
|---|---|---|
| Assets | "USDT", "bitcoin" | Asset detail |
| Transactions | ref, amount, counterparty | Transaction detail |
| Orders (P2P & shop) | order ref, merchant | Order detail |
| Merchants / traders | name | Merchant profile |
| Cards | last-4, nickname | Card manage |
| People (recipients) | ID / email / phone | Pre-filled Send |
| Actions | "deposit", "freeze card" | Deep-link into the flow |
| Help / FAQ | any | FAQ article |

**Behavior:** debounced, grouped results (Assets / Money / People / Actions / Help), recent + suggested when empty (never a blank search — empty-state rule), keyboard-navigable. Scoped search inside list screens (wallet, transactions, P2P) stays, but global search is the cross-surface accelerator.

---

## 3. Feature hierarchy (progressive disclosure)

```
PRIMARY (always visible: tabs / rail)
  Home · Wallet · Pay · Market · Account

SECONDARY (one level in — screen headers & section nav)
  Wallet → Deposit · Withdraw · Swap · Transfer · History
  Market → P2P (Buy/Sell) · Exchange · My ads · My orders
  Account → Cards · Rewards · Merchant · Settings · Support · KYC

TERTIARY (progressive disclosure — "Advanced" expanders, sheets)
  Withdraw → memo, network choice, 2FA, address book
  Card     → spending controls (online/ATM/contactless), limits, geo/MCC
  Ad       → floating price, trade hours, country, counterparty reqs
  Settings → sessions, devices, data export, API
```

Beginners see essentials; power users expand. Every "Advanced" is a disclosure, never a separate page that fragments the flow.

---

## 4. Sitemap

```
PoisaPay
├─ Home ......................... balance · quick actions · activity · cards · promos · market · [search]
├─ Wallet
│   ├─ Assets (search · filter · favorites · hide-zero · sort · 24h)
│   ├─ Asset detail (balance breakdown · price/24h · activity · actions)
│   ├─ Deposit (crypto: coin→network→address · fiat: method→details)
│   ├─ Withdraw (crypto: coin→network→amount+address · fiat: account→amount)
│   ├─ Swap / Exchange (quote → confirm)
│   ├─ Transfer / Send (recipient → amount → confirm)
│   └─ History (transactions · deposits · withdrawals · transfers · swaps)
├─ Pay
│   ├─ Send / Request
│   ├─ Scan to pay
│   └─ Pay invoice
├─ Market
│   ├─ P2P marketplace (filters · sort · merchant cards)
│   ├─ P2P order (timeline · escrow · chat · countdown · dispute)
│   ├─ My ads / My orders
│   └─ Exchange
├─ Account
│   ├─ Cards (list → manage: freeze · reveal · limits · controls · history)
│   ├─ Rewards / Referrals
│   ├─ Merchant / Seller (products · orders · earnings · analytics · payouts · refunds · coupons · reviews)
│   ├─ Purchases (buyer orders · messages)
│   ├─ Settings (profile · security · verification/KYC · devices · sessions · appearance · language · limits · privacy)
│   └─ Support
└─ Global search (cross-surface, from every header)
```

---

## 5. Core journey maps (each ≤3 taps for the key action)

### 5.1 First-run: land → value

```
Sign up ─▶ KYC (3 steps, stepper) ─▶ Home (guided) ─▶ Fund ─▶ Buy USDT ─▶ Get paid ─▶ Spend/Withdraw
   │           │                         │              │         │
 phone/     personal→doc→review      empty-state    Deposit or   P2P best price /
 email      liveness (new)           CTAs guide     P2P/Exchange  Exchange instant
```
Empty Home is a **guided activation surface** (Deposit · Buy USDT · Explore P2P), never blank (rule 3).

### 5.2 Deposit (target ≤3 taps)
```
Home ⟶ [Deposit] ⟶ pick coin ⟶ (network if multi) ⟶ Address+QR (copy)
  tap1     tap2        tap3           auto/tap          done
```
Smart default: remember last coin+network → returning user is **2 taps** to their USDT/TRON address.

### 5.3 Withdraw (fix audit M1: was 4–5 → target 3)
```
Home ⟶ [Withdraw] ⟶ asset (last used pre-selected) ⟶ amount + saved account/address (default) ⟶ Confirm
  tap1    tap2          tap3 (or skipped by default)        one screen, MAX chip           tap
```
Collapse network+account into the amount screen with smart defaults (last network, default payout account/whitelisted address). Advanced (new address, memo, 2FA) is progressive disclosure. A distinct **Review** state before an irreversible crypto send (fix M4).

### 5.4 Send / Pay (≤3 taps)
```
Pay ⟶ recipient (recent/favorite chip, or scan) ⟶ amount (MAX) ⟶ Send
 tap1        tap2 (1 tap if favorite)               tap3
```
Fix M3: recent-recipient chips + favorites eliminate retyping.

### 5.5 Buy USDT via P2P (trust-first)
```
Market ⟶ pick merchant (sorted best/verified/online) ⟶ amount + method ⟶ Place order
  tap1              tap2                                   tap3
        ⟶ Order screen: escrow-locked badge · countdown · pay instructions · chat · Mark paid
```
Every step shows the trust primitive (escrow state, merchant rating/completion, countdown) so the user never wonders if funds are safe.

### 5.6 Sell USDT via P2P
```
Market ⟶ Sell toggle ⟶ pick buyer ad ⟶ amount ⟶ Place → escrow locks your USDT → await payment → Release
```

### 5.7 Checkout (guest-first, ≤ minimal fields) — fix CK1/CK2/CK3
```
Pay link ⟶ Order summary + [Pay with wallet | card | mobile-money] ⟶ (guest: email only) ⟶ Confirm
                 saved address auto-filled · coupon inline-validated · sticky pay bar
```

### 5.8 Freeze card (≤2 taps)
```
Home/Account ⟶ card ⟶ [Freeze]  (instant optimistic toggle, confirm toast)
   tap1         tap2      tap3
```
Surface Freeze on the card widget itself so it's **2 taps** from Home.

### 5.9 Release P2P payment (seller, ≤2 taps + step-up)
```
Order (buyer paid) ⟶ [Release] ⟶ confirm sheet (amount, irreversible) ⟶ Released
```

---

## 6. State & feedback IA (applies to every flow)

Every action moves through an explicit, consistent state machine so users are never in the dark (rules 4–6):

```
idle ─▶ submitting (optimistic + skeleton) ─▶ success (toast + live update)
                     │
                     └─▶ error ─▶ retry (inline, non-destructive)
```

- **Live by default:** balances, activity, notifications, order status push over Reverb (fix D1/F1) with a subtle connection indicator; no manual refresh.
- **Skeletons, not spinners**, on every data load (fix DS2).
- **Optimistic** on reversible actions (freeze, favorite, mark-read); **explicit confirm** on irreversible money (withdraw, release).

---

## 7. What changes vs today (IA delta)

| Change | Fixes |
|---|---|
| Add mobile bottom tab bar (5) + raised Pay | N2 |
| Add global search from every header + `⌘K` | N1 |
| Add consistent back/breadcrumb on deep pages | N3 |
| Add "Discover" surface for gated features | N4 |
| Collapse Withdraw to ≤3 taps via smart defaults + disclosure | M1 |
| Recent/favorite recipients & addresses | M3 |
| Guest checkout + fewer fields + inline coupon | CK1–CK3 |
| Card controls surfaced (not hidden) | CA1 |
| Live push for balances/notifications/orders | D1, F1, NT1 |

Everything else in the current IA (grouped sections, feature flags, empty-state discipline) is **preserved**.
