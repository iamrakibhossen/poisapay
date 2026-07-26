# PoisaPay Shop — Complete Audit & Implementation Plan

**Scope:** Notifications, revenue accounting, financial reconciliation, dashboards, code quality, tests.
**Method:** Read-only code audit (Laravel 12, PostgreSQL, immutable double-entry ledger). Every finding cites a file. No code changed.

---

## 0. Executive verdict

The Shop module is **well-architected at the core** and **weak at the edges**:

- **Strong:** clean DDD (Actions / Services / DTOs / Enums / Policies), **ledger-native money** (order settlement, refunds, earnings hold→release are all balanced double-entry postings with idempotency keys, row locks, and DB transactions), owner-scoped authorization with **no IDOR found**, and a genuinely good **notification *infrastructure*** (preferences, templates, queued channels, consumer UI).
- **Weak:** the module barely *uses* its own notification infrastructure (most events fire into the void); revenue has **hidden/unimplemented pieces** (a dead `tax_amount` column, no processing/affiliate/withdrawal fees, shop commission invisible in admin revenue); **sellers cannot withdraw earnings at all**; seller balances are read from a **denormalized column instead of the ledger**; and there is **no reconciliation harness**.

Net: this is a *fix-and-extend* engagement on a solid base, not a rewrite. The two highest-value, highest-risk tracks are **(1) Notifications** (Part 1 — the biggest reliability gap) and **(2) Revenue transparency + reconciliation** (Parts 2/3/5 — the biggest financial risk).

---

## 1. Current architecture analysis

### 1.1 Notification stack (infrastructure is good; wiring is missing)
- `NotificationService` (`app/Domain/Notification/NotificationService.php`) resolves a `NotificationTemplate`, renders it, and **filters channels per `NotificationPreference`** (per-user, per-category: in_app/email/sms/push…). Security category is force-on.
- `UserNotification` (queued; database+mail) and `OperatorNotification` (queued; database+broadcast) + `AdminNotifier` (fan-out to active admins).
- Consumer UI (`NotificationController` + `notifications.blade.php`): mark-read, **bulk mark-read, pagination, unread counter, category filters, deep links, preferences matrix** — all present and good.
- **The gap:** Shop actions call `->notify(new UserNotification(...))` **directly with hardcoded channels**, bypassing `NotificationService` — so templates and user preferences are ignored. And most Shop events have **no notification listener at all** (only `AuditShopEvent` + `NotifyOperatorsOfSellerApplication` are registered).

### 1.2 Revenue & ledger (ledger-native, but incomplete)
- Order paid → one balanced entry (`shop.purchase`): `DR buyer:available`, `CR seller:available|locked` (net), `CR shop:commission_income` (`PlaceOrder.php:186-213`). Commission from `Seller::commissionBps()` (per-seller → plan → global `shop_commission_bps`, default 1000bps).
- Refund (`RefundOrder.php:74-95`): pro-rata reversal — `CR buyer`, `DR seller`, `DR shop:commission_income` — **idempotent** (journal `idempotency_key`), under order row lock, revokes goods on full refund.
- Earnings hold→release (`ReleaseSellerEarnings`): real ledger unlock `DR locked / CR available` after the refund window, idempotent per order.
- `RevenueService` (`app/Domain/Revenue`) **derives revenue from ledger_lines** — but its `REVENUE_TYPES` **excludes `ShopCommissionIncome`**, so platform shop revenue is invisible.

### 1.3 Dashboards
- Merchant: real, order/ledger-derived KPIs (revenue, orders, conversion, refunds, pending/available, top products). Missing on the dashboard: Today, This-Month, revenue chart, top customers. Some placeholder data (domains/funnels/pixels marked frontend-first).
- Admin: ledger-centric revenue + analytics + financial reports (trial balance, income statement, solvency). Missing: GMV, refund-loss, revenue-by-product/category/country, and shop-commission surfaced as revenue.

---

## 2. Bugs found

| # | Bug | Severity | Evidence |
|---|---|---|---|
| B1 | **Most Shop events fire with no notification** — `OrderPlaced`, `OrderStatusChanged`, `ReviewSubmitted`, `ProductStatusChanged` have no notification listener. Buyers get **no order confirmation**; sellers get no new-order/review alert. | **Critical** | `ShopServiceProvider` registers only Audit + SellerApplied; `PlaceOrder.php:230` dispatches `OrderPlaced` unheard |
| B2 | **Shop notifications bypass `NotificationService`** — hardcoded `['database']` channels; ignore user preferences and templates. | **High** | `RequestRefund.php:60`, `ResolveRefundRequest.php:81,90`, `SetSellerStatus.php:51` |
| B3 | **No notification idempotency/dedup** — no `ShouldBeUnique`/`uniqueId`; an event replay or job retry sends duplicates. | **High** | no `ShouldBeUnique` on any notification; `OrderStatusChanged` can re-fire on refund |
| B4 | **`tax_amount` column defined but never populated** — dead field; silent hidden liability if tax is assumed. | **High (financial)** | migration `2026_07_26_000003:60`; `PlaceOrder.php` writes no tax |
| B5 | **Shop commission excluded from admin revenue** — `shop:commission_income` accrues but is invisible in `/admin/revenue`. | **High** | `RevenueService::REVENUE_TYPES` omits `ShopCommissionIncome` |
| B6 | **Seller earnings read from denormalized column, not ledger** — `Order::sum('seller_net_amount')`; drifts from ledger if any correction is posted. | **Medium (financial)** | `SellerController.php:~911` |
| B7 | **Seller balance can go negative** — released earnings spent, then a refund debits `seller:available` below zero; no guard/dunning. | **Medium (financial)** | `RefundOrder.php:80-86` |
| B8 | **Partial-refund accounting can diverge** — `order.refunded_amount` vs Σ`RefundRequest.amount_refunded`; concurrent approvals not globally serialized. | **Medium** | `RefundOrder.php:105-106` |
| B9 | **No custom retry/backoff on notifications** — Laravel default (1 try); a transient channel failure drops the notification. | **Medium** | no `tries`/`backoff` on notification classes |
| B10 | **Duplicated analytics logic** — `dashboardInsights()` duplicates funnel math from `analytics()`. | **Low** | `SellerController.php:147-197` vs `:780-843` |

---

## 3. Missing features

- **Notifications (in-scope, currently absent):** Order Created, New Customer Purchase, Payment Received/Pending, Order Paid, Order Completed, Order Cancelled, New Review, Product Approved/Rejected/Published/Disabled, Shop Created, Low Balance. *(Withdrawal + KYC notifications exist at the platform level — they need to be confirmed to fire for shop sellers, not rebuilt.)*
- **Seller withdrawal/payout** — no action, model, fee, approval, or history. Sellers accumulate earnings they cannot cash out.
- **Revenue engine surface** — a `ShopRevenueService` deriving GMV / platform commission / refund loss / merchant earnings **from the ledger**, feeding both dashboards.
- **Reconciliation harness** — a command/report proving every completed order ↔ ledger ↔ balances reconcile (no missing/dup/negative).
- **Merchant dashboard:** Today, This-Month, revenue chart, top customers.
- **Platform dashboard:** GMV, refund loss, revenue by product/category/country, shop commission revenue.
- **Tax/affiliate/processing-fee** accounting (only if these are real business requirements — see Q1 below).

---

## 4. Security issues

Authorization is genuinely solid (owner-scoping via `ownedOrder()`/seller relations, RBAC on admin, no IDOR, safe fillables). Residual items:

| # | Issue | Severity |
|---|---|---|
| S1 | No rate-limit/throttle on refund + refund-resolve endpoints (idempotency prevents double-ledger, but abuse/DoS surface exists). | Medium |
| S2 | Failed authorization/refund attempts aren't audit-logged. | Low |
| S3 | Auto earnings-release has no manual-approval gate for large amounts (acceptable for scheduled payout, worth a threshold alert). | Low |

---

## 5. Financial risks (the part that must be airtight)

1. **Hidden/dead tax** (B4) — decide: implement tax as a real ledger line (`shop:tax_payable`) **or** drop the column. No middle ground.
2. **Non-ledger-derived seller earnings** (B6) — reports can silently drift from the source of truth. Fix by deriving from ledger + a reconciliation check.
3. **Negative seller balances** (B7) — needs a guard + dunning/monitoring, or an explicit "seller debt" account.
4. **Invisible platform shop revenue** (B5) — commission is earned but unreported; understates platform P&L.
5. **No reconciliation harness** — nothing proves the books are consistent at scale.
6. **No withdrawal fee model** — when payout is built, the fee must be a ledger line from day one, not bolted on.
7. **No chargeback path** — chargebacks would today be manual refunds with no distinct accounting.

---

## 6. Notification flow diagram

### Current (broken)
```
Shop event ──► (AuditShopEvent logs it)
   │
   ├─ OrderPlaced / OrderStatusChanged / ReviewSubmitted / ProductStatusChanged
   │        └─► ✗ NO LISTENER  → no notification
   │
   └─ Refund*/SellerStatus (inline in action)
            └─► ->notify(new UserNotification([...'database'...]))   ✗ bypasses NotificationService
                     └─► ✗ ignores templates + user preferences
                     └─► ✗ no dedup / no retry policy
```

### Target
```
Shop domain event ──► ShopNotificationSubscriber (one listener class, handle* per event)
        │
        ▼
   NotificationService::send(recipient, key, category, data)
        │   • resolves NotificationTemplate (order.created, review.submitted, …)
        │   • filters channels per NotificationPreference (in_app/email/…; security forced)
        │   • dedup key = hash(event_key + subject_id + recipient)  → ShouldBeUnique
        │   • queued (redis) with tries=3 + backoff; failure → failed_jobs + retry
        ▼
   database · mail · broadcast   ──►  consumer bell/UI (already built)
   admins via AdminNotifier (with a minimal admin-pref gate)
```

---

## 7. Revenue flow diagram

### Current
```
Gross sale (subtotal − discount + shipping)
   │  DR buyer:available
   ├──────────────► CR shop:commission_income      (commission bps)   ✗ excluded from admin revenue
   ├─ tax ──────────► ✗ column exists, never posted
   ├─ affiliate ────► ✗ not implemented
   └──────────────► CR seller:available|locked     (net; shipping folded in)
                          │  hold→release (ledger unlock) after refund window
                          ▼
                     seller earnings ──► ✗ withdrawal not implemented
                          (reported via denormalized column, not ledger)
```

### Target (single ShopRevenueService, 100% ledger-derived)
```
Gross ─► Platform commission ─► [Tax → shop:tax_payable] ─► [Affiliate → shop:affiliate_payable]
      ─► Processing fee (if any) ─► Merchant receivable (available|held) ─► Net platform revenue
                                                     │
   ShopRevenueService.derive()  ◄── ledger_lines ──┘   (GMV, commission, refund-loss, earnings — all from ledger)
                                                     │
                              Withdrawal (ledger path + fee + admin approval + history)
```

---

## 8. Implementation plan (small, reviewable commits, tests each)

**Phase A — Notification reliability (Part 1)** ← recommended first
- A1. `ShopNotificationSubscriber` + route all Shop notifications through `NotificationService` (prefs + templates); migrate the 3 refund + seller-status notifications off hardcoded channels.
- A2. Seed Shop templates (`order.created/paid/completed/cancelled`, `purchase.new`, `refund.*`, `review.submitted`, `product.approved/rejected/published/disabled`, `shop.created/verified/suspended`, `balance.low`).
- A3. Wire the missing events → notifications (order lifecycle, new purchase→seller, review→seller, product status→seller). Confirm platform KYC/withdrawal notifications fire for sellers.
- A4. Idempotency (`ShouldBeUnique` + `uniqueId` dedup key) and retry/backoff (`tries=3`, exponential `backoff`).
- A5. Low-balance monitor (threshold setting → notification).
- A6. Tests: delivery per channel, prefs respected, dedup on replay, queue retry.

**Phase B — Revenue transparency + reconciliation (Parts 2/3/5)**
- B1. `ShopRevenueService` (ledger-derived): GMV, platform commission, refund loss, merchant earnings; add `ShopCommissionIncome` to admin revenue.
- B2. Resolve tax (implement `shop:tax_payable` ledger line *or* remove the column) — per Q1.
- B3. Derive seller earnings from the ledger; add `shop:reconcile` command (order ↔ ledger ↔ balances; flag missing/dup/negative) + a solvency check.
- B4. Seller withdrawal flow (ledger path + fee line + admin approval + history + notifications).
- B5. Tests: commission accuracy, refund reversal, reconciliation, withdrawal, negative-balance guard.

**Phase C — Dashboards (Part 4)**
- C1. Merchant: Today, This-Month, revenue chart, top customers (reuse the P2P/analytics chart components).
- C2. Platform: GMV, refund loss, revenue by product/category/country, shop commission revenue.

**Phase D — Code quality + hardening (Part 6/7)**
- D1. Extract duplicated analytics to `AnalyticsService`; throttle + audit-log refund endpoints.
- D2. Backfill edge-case tests (concurrent refund, partial-refund cumulative, coupon exhaustion).

---

## Open questions (need answers before Phase B; Phase A can start immediately)

- **Q1 — Tax:** Is VAT/tax a real requirement now? If yes, jurisdiction/rate source? If no, I remove the dead column.
- **Q2 — Affiliate/referral & processing fee:** in scope now, or defer? (No infra exists today.)
- **Q3 — Seller withdrawal:** build the full payout flow now (fee %, min amount, admin approval, rail = internal wallet vs external)?
- **Q4 — Broadcast:** enable live (Reverb) delivery for buyer/seller notifications, or database+mail only for now?
