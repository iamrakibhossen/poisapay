# PoisaPay — Phase 4: Low-Fidelity Wireframes

Mobile-first (the primary shell). Each canonical screen is annotated with the five required lenses — **Layout** (why this arrangement), **Friction** (how it reduces effort), **Conversion**, **Trust**, **Scale**. Three reusable templates (List→Detail, Money-Form, Wizard) cover the long tail of screens not drawn individually.

Legend: `[ ]` button · `(•)` selected · `▸` disclosure · `≈` skeleton · `●` live/online · `⌂▤⦿⇄☰` tab bar.

---

## Global shell (mobile)

```
┌───────────────────────────────┐
│ ☰?  PoisaPay        🔍  ●3  ◑ │  ← header: search (always), notif badge, theme
├───────────────────────────────┤
│                               │
│         screen content        │
│                               │
├───────────────────────────────┤
│  ⌂     ▤     ⦿     ⇄     ☰   │  ← bottom tabs; ⦿ Pay is raised
│ Home  Wallet Pay  Market Acct │
└───────────────────────────────┘
```
- **Layout:** persistent search + tabs make every core surface reachable in 1 tap (fix N1/N2). **Trust:** connection dot + notif badge signal a live, watched account. **Scale:** tab set is tokenized; adding surfaces = Account overflow, not a redesign.

---

## 1. Home

```
┌───────────────────────────────┐
│ Good evening, Rakib      🔍 ◑ │
│ ┌───────────────────────────┐ │
│ │ Total balance      ●Live 👁│ │  ← hero: gradient accent, hide toggle
│ │  $ 12,480.50              │ │
│ │  3 assets · $200 locked   │ │
│ │ [Deposit][Withdraw][Send] │ │  ← quick actions (≥44px)
│ │ [Swap][Cards][P2P]        │ │
│ └───────────────────────────┘ │
│ 30-day   ▲ In 4.2k  ▼ Out 1.1k│  ← stat row
│ ┌─Assets───────────┐┌─Card──┐ │
│ │ ₮ USDT 8,300 +0.1%││ ▓▓▓▓ ││  ← 24h change (fix D2); card widget
│ │ ◎ TRX  1,200 -1.3%││ ••42 ││    with [Freeze] inline (2-tap freeze)
│ │ ＄ USD  2,980     ││[Frz] ││
│ └──────────────────┘└───────┘ │
│ ┌─Recent activity──────────┐  │
│ │ �“ Deposit  +500 ₮  ✓ 2m  │  │  ← live-updating
│ │ ⇄ Swap     -100 ₮  ✓ 1h  │  │
│ └──────────────────────────┘  │
│ [Promo: Invite & earn]        │  ← promos/market module (fix D3)
├───────────────────────────────┤
│  ⌂  ▤  ⦿  ⇄  ☰               │
└───────────────────────────────┘
```
- **Layout:** balance-first (the #1 question), then act, then review — matches user priority. **Friction:** 6 quick actions = 1-tap to every money job; Freeze on the card widget = 2-tap. **Conversion:** promo + empty-state CTAs drive activation. **Trust:** Live badge + real 24h change. **Scale:** modular blocks; new widgets slot in without layout change.

---

## 2. Wallet (list)

```
┌───────────────────────────────┐
│ Wallet                    🔍 ◑ │
│ [Deposit][Withdraw][Send][Swap]│
│ (•)All ( )Crypto ( )Fiat  ⇅Sort│  ← segmented-control + sort (fix W2)
│ 🔎 Search assets     ☑ Hide $0 │  ← hide-zero (fix W2)
│ ┌──────────────────────────┐  │
│ │★ ₮ USDT   8,300.00  +0.1%▸│  │  ← favorite, 24h (fix W1)
│ │  ◎ TRX    1,200.00  -1.3%▸│  │
│ │  ＄ USD   2,980.00        ▸│  │
│ └──────────────────────────┘  │
│         (≈ skeleton on load)  │
├───────────────────────────────┤
```
- **Layout:** actions pinned top, list scrolls. **Friction:** search + favorites + hide-zero keep long lists usable. **Trust:** locked-balance shown inline. **Scale:** virtualized list handles hundreds of assets; sort/filter server-side.

---

## 3. Asset detail

```
┌───────────────────────────────┐
│ ‹ USDT · Tether          ◑    │  ← back affordance (fix N3)
│  ₮  Stablecoin · TRON/ETH     │
│   8,300.00 USDT  ≈ $8,300     │
│ [Deposit][Send][Swap][Withdraw]│
│ Avail 8,300 · Locked 0 · Tot  │
│ ┌ Price  $1.00  +0.02% 24h ──┐ │
│ │      ╱╲   sparkline         │ │
│ └────────────────────────────┘ │
│ Activity                       │
│  ↓ Deposit +500  ✓  Jul 24    │
│  (≈ or empty-state + CTA)     │
```
- **Layout:** identity→balance→act→history. **Trust:** available vs locked explicit. **Scale:** one template for every asset.

---

## 4. Money-Form template — Withdraw (fixes M1: ≤3 taps)

```
STEP 1 (tap: Withdraw)        STEP 2 — one screen
┌─────────────────┐          ┌───────────────────────────┐
│ Withdraw    ◑   │          │ ‹ Withdraw USDT           │
│ Last used ▸     │          │  Amount                   │
│ ₮ USDT   8,300 ▸│  ──tap──▶ │   [ 1,000.00 ]  ₮  MAX   │  ← amount-field
│ ◎ TRX    1,200 ▸│          │  Balance 8,300 · Fee 1₮   │
│ ＄ USD (cash-out)│          │  To: (•)TRON default addr │  ← smart default
│                 │          │      ▸ Change / New addr  │    (disclosure)
└─────────────────┘          │  ▸ Advanced (memo, 2FA)   │
                             │  You send 999 ₮ → review  │
                             │        [ Review ]         │
                             └───────────────────────────┘
                                        │
                             REVIEW (irreversible confirm — fix M4)
                             ┌───────────────────────────┐
                             │ Confirm withdrawal        │
                             │ 999 ₮ → TXyz…8f (TRON)    │
                             │ Fee 1 ₮ · Total 1,000 ₮   │
                             │ ⚠ Irreversible. 2FA ▢▢▢▢  │
                             │      [ Confirm ]          │
                             └───────────────────────────┘
```
- **Layout:** collapse network+account into the amount screen via defaults; Review is a deliberate gate. **Friction:** returning user = asset preselected → **amount + confirm = 3 taps**. **Trust:** explicit irreversible review + 2FA for crypto out. **Scale:** the same `amount-field` + Review pattern powers Send, Swap, Pay. **Feedback:** submit → optimistic "Processing" skeleton → success toast + live balance (fix M2).

---

## 5. Send / Pay (⦿ center tab, ≤3 taps — fix M3)

```
┌───────────────────────────────┐
│ Pay                       ◑   │
│ [Send] [Request] [Scan]        │  ← segmented
│ To:  🔎 ID / email / phone     │
│ Recent:  (Ⓐ)(Ⓑ)(Ⓒ) ★fav      │  ← recent/favorite chips (1-tap)
│  Amount  [ 250.00 ] ₮  MAX     │
│  Note (optional)               │
│  They get 250 ₮ · Fee Free     │
│        [ Send 250 ₮ ]          │
```
- **Friction:** recent/favorite chip = recipient in 1 tap (no retyping). **Trust:** "they get / fee" shown before send. **Conversion:** Request + Scan expand P2P/merchant use from the same surface.

---

## 6. Global search / ⌘K (fix N1)

```
┌───────────────────────────────┐
│ 🔎 Search PoisaPay        ✕   │
│ Recent:  USDT · Order #P2P… · │
│ ─ Assets ──────────────────── │
│  ₮ USDT   ◎ TRX               │
│ ─ Money ───────────────────── │
│  Deposit  Withdraw  Freeze card│  ← action deep-links
│ ─ People ──────────────────── │
│  Ⓐ Alice (recent)             │
│ ─ Orders / Merchants / Help ─ │
└───────────────────────────────┘
```
- **Layout:** grouped, recents-first (never blank — rule 3). **Friction:** any entity/action reachable by typing. **Scale:** new entity types = new group, no IA change.

---

## 7. P2P marketplace (trust-first — fix trust primitives)

```
┌───────────────────────────────┐
│ Market                    🔍 ◑ │
│ (•)Buy ( )Sell   ⇅ Best price │  ← segmented + sort
│ Amount [___] · Method ▾ · ★Fav │  ← server-side filters
│ ┌──────────────────────────┐  │
│ │ Ⓜ SellerCo ✔●            │  │  ← verified, online dot
│ │ 4.9★ · 99.2% · ⏱2m release│  │  ← completion, avg release
│ │ 1.00 USDT = 121.5 BDT     │  │
│ │ Avail 5,000 · 100–50k BDT │  │
│ │ bKash · Nagad · Bank      │  │
│ │            [ Buy ]        │  │
│ └──────────────────────────┘  │
```
- **Trust:** rating, completion, avg release, verified, online — the exact signals that make a stranger-trade feel safe — are on the card *before* commit. **Friction:** server-side sort/filter (already shipped) + amount prefilter. **Conversion:** best-price default surfaces the winning offer first. **Scale:** cursor pagination + merchant-card component.

---

## 8. P2P order (escrow + countdown + chat)

```
┌───────────────────────────────┐
│ ‹ Order #P2P8F2A          ◑   │
│ ●─●─○─○  Escrow locked ✓       │  ← order-timeline
│ ⏱ Pay within 14:32            │  ← countdown (escalates color)
│ Buy 100 ₮ for 12,150 BDT      │
│ Pay to: bKash 017… (default)  │  ← seller default account first
│ [ I've paid ]  [ Dispute ]    │
│ ┌ Chat ──────── ● online ───┐ │  ← live (Reverb), read receipts
│ │ system: escrow locked      │ │
│ │ seller: pay to bKash…      │ │
│ └────────────────────────────┘ │
```
- **Trust:** escrow-locked badge + timeline + countdown remove all "is my money safe?" doubt. **Feedback:** state changes push live (fix F1); no reload. **Scale:** one timeline component across all order states.

---

## 9. Cards (list → manage)

```
LIST                          MANAGE
┌─────────────────┐          ┌───────────────────────────┐
│ Cards       ◑ + │          │ ‹ Virtual · ••42          │
│ ┌ ▓▓▓▓▓▓▓▓▓ ●─┐ │          │   ▓▓▓ animated card ▓▓▓    │
│ │  •• 4242    │ │  ──tap──▶ │ [Freeze] [Reveal] [Limits]│  ← controls surfaced
│ │  Rakib 08/29│ │          │ Controls:                 │
│ └─────────────┘ │          │  Online ☑  ATM ☑  Cntls ☑ │  ← was hidden (fix CA1)
│ Spent $420 · 12tx│          │ Recent auths  …           │
└─────────────────┘          │ ▸ Advanced (geo, MCC)     │  ← disclosure
                             └───────────────────────────┘
```
- **Friction:** freeze/reveal/limits are primary, not buried. **Trust:** visible spending controls + step-up on reveal. **Scale:** controls are a disclosure list; new controls append.

---

## 10. Checkout (guest-first — fixes CK1/CK2/CK3)

```
┌───────────────────────────────┐
│ MerchantCo            🔒Secure │
│  Pay  12,150 BDT              │
│ ┌ Summary ─────────────────┐  │
│ │ Product · variant ▾       │  │
│ │ Coupon [____] Apply ✓-10% │  │  ← inline validate (no reload, fix CK3)
│ │ Total 10,935 BDT          │  │
│ └──────────────────────────┘  │
│ Pay with:                     │
│  (•)Wallet  ( )Card ( )Mobile │  ← options (fix CK4)
│  Email (guest) [__________]   │  ← guest, minimal fields (fix CK1/CK2)
│  ▸ Shipping (only if physical)│  ← saved address auto-fill, disclosure
│═══════════════════════════════│
│      [ Pay 10,935 BDT ]  🔒   │  ← sticky bar
└───────────────────────────────┘
```
- **Conversion:** guest + fewest fields + inline coupon + sticky pay = the highest-leverage conversion screen in the product. **Trust:** merchant identity, secure marks, buyer protection. **Scale:** payment methods are pluggable.

---

## 11. Settings → Appearance (new — fix S1/S2)

```
┌───────────────────────────────┐
│ ‹ Appearance                  │
│ Theme:  ( )System (•)Light ( )Dark│  ← persisted, SSR-set (no flash)
│ Language: English ▾  বাংলা     │  ← also in settings (fix S2)
│ Reduce motion: ☑ (follows OS)  │
└───────────────────────────────┘
```
- Adds the mandated dark-mode control + language in the expected place.

---

## 12. Notifications (grouped — keep, add push)

```
┌───────────────────────────────┐
│ Notifications   Mark all ✓  ⚙ │
│ (•)All ( )Unread (Trading 2)…  │  ← category chips (exists)
│ Today                          │
│  ● Order paid  #P2P…    2m ▸  │  ← unread dot, deep-link
│  ○ Deposit credited +500 ✓ 1h │
│ This week …                    │
└───────────────────────────────┘
```
- Keep the good grouping; add push/SMS channel (fix NT1) + `aria-live` announce.

---

## Reusable templates (cover the remaining screens)

| Template | Powers |
|---|---|
| **List → Detail** | Wallet, Transactions, Deposits/Withdrawals/Transfers/Swaps history, P2P ads/orders, Purchases, Seller products/orders/customers, Notifications |
| **Money-Form (amount-field + Review)** | Deposit, Withdraw, Send, Swap, Pay, P2P place-order |
| **Wizard (stepper)** | KYC (add liveness step — fix K1), Seller onboarding, Deposit/Withdraw multi-network |

Each template carries the state machine (idle→submitting→success/error), skeletons, empty-states, and a11y contract from Phase 3 — so an un-drawn screen inherits correct behavior by construction.

---

## Wireframe → hi-fi handoff (Phase 5 preview, on approval)

Phase 5 will render these at high fidelity in both light and dark using the Phase 3 tokens, with the raised-Pay motion, real illustrations in empty/success states, and the merchant/card/order components fully specified. No new IA or tokens are introduced in hi-fi — it is a faithful visual resolution of these frames.
