# PoisaHub Funnel Platform — System Design

> A Gumroad / Lemon Squeezy / ClickFunnels-style **one-product-per-sales-page**
> platform, built **on top of PoisaPay's existing ledger, KYC, wallet, and
> provider-abstraction infrastructure** — not as a greenfield app.
>
> **Not a marketplace.** No public catalog, no discovery, no browsing. Every
> product is a standalone landing page + checkout the seller drives paid traffic
> to. The unit of the system is the **funnel**, not the store.

---

## 0. Guiding principles (and what we reuse, not rebuild)

The single most important design decision: **money never moves outside the
double-entry ledger**, and we reuse the subsystems PoisaPay already ships.

| Need | Reuse existing PoisaPay system | New? |
|---|---|---|
| Money, balances, escrow, commission | `App\Domain\Ledger` (exact `Money`, double-entry, DB-trigger balanced) | + a few `LedgerAccountType` cases |
| Seller identity verification | `App\Domain\Kyc` (`KycTier`, `KycStatus`) | Seller *application* wraps it |
| Wallet checkout | `App\Domain\Wallet` + `UserAvailable` balance | — |
| Card checkout | `App\Card` provider abstraction (Marqeta/Stripe/Mock) | — |
| Crypto / bank checkout | `App\Domain\Deposit` + `App\Domain\Ramp` | — |
| Payouts to sellers | `App\Domain\Withdrawal` (reserve-then-sign, methods) | Seller balance → withdrawal |
| Provider-agnostic gateways | mirror `App\Card` (Manager + Interface + Providers + Inbound) | `App\Payments` |
| Admin ops | admin guard + controllers + Blade (DollarHub navy) | Funnel admin pages |
| Feature gating | settings engine + `feature()` flags | `funnels_*` flags |
| Audit, notifications, risk | `App\Domain\{Audit,Notification,Risk,Compliance}` | — |
| Background work | Laravel queues (same as custody/exchange jobs) | Funnel jobs |

**Seller is not a new auth guard.** A seller is a `User` (the existing `auth`
guard) who owns an **approved `Seller` profile**; access to `/seller/*` is
**policy-gated**, exactly like `User::isMerchant()` gates the merchant console.
Admins stay on the separate `admin` guard.

**Namespaces / layout** (matches the `app/Domain/*` convention):

```
app/Domain/Funnel/            # business logic (actions, services, state machines)
  Seller/                     # application → approval lifecycle
  Product/                    # product + versions + files + license keys
  SalesPage/                  # page model + section renderer
  Checkout/                   # the checkout engine (payment-method-agnostic)
  Funnel/                     # upsell/downsell/order-bump step engine
  Order/                      # order + order-item lifecycle
  Delivery/                   # signed downloads + license issuance
  Coupon/                     # discount engine
  Earnings/                   # sale → escrow → available → payout ledger flows
  Analytics/                  # event pipeline + rollups + pixels/CAPI
app/Payments/                 # provider-agnostic gateway (mirrors App\Card)
  Contracts/PaymentGatewayInterface.php
  PaymentManager.php
  Providers/{Wallet,Stripe,PayPal,Crypto,Bank,Cod}Provider.php
  Inbound/                    # gateway webhooks (idempotent)
  DTOs/  Enums/  Exceptions/
```

---

## 1. UX flows

### 1.1 Buyer (the money path)

```
Facebook/TikTok/Google Ad ──▶ Sales Page  (public, no login, /p/{slug} or custom domain)
   page_view + pixel fire
        │  click Buy Now
        ▼
   Checkout (embedded on the page — no redirect)
   • guest or logged-in • coupon • order bump • tax/shipping
        │  pay (Wallet / Card / Crypto / Bank / COD)
        ▼
   PaymentManager → provider → capture/authorize
        │  success (sync) or webhook (async: crypto/bank)
        ▼
   Order = paid ──▶ [Upsell step 1] accept? ──▶ [Downsell?] ──▶ … ──▶ Thank-You page
        │                    │one-click charge (reuses captured method/wallet)
        ▼                    ▼
   Digital delivery (signed links + license keys) emailed + shown on Thank-You
        ▼
   Customer Portal (My Purchases): re-download, keys, invoices, order tracking, refund request
```

### 1.2 Seller

```
Register (normal user) ──▶ Apply as seller (form + KYC) ──▶ status: pending_review
        ▼ admin approves (KYC verified)
Seller profile: approved ──▶ /seller dashboard unlocked
        ▼
Create Product ──▶ Sales Page auto-generated (default template, slug) ──▶ customize sections/theme
        ▼ publish ──▶ page live at /p/{slug}
Share URL in ads ──▶ sales ──▶ earnings accrue (pending → available after refund window)
        ▼
Request payout ──▶ (admin/auto approve) ──▶ withdrawal to wallet/bank/crypto
```

### 1.3 Admin

Applications review → KYC/KYB verification → seller approve/reject/suspend →
product moderation (optional flag) → payout approval → refund/dispute handling →
gateway config → fee/tax config → marketplace-wide analytics.

---

## 2. Data model (schema)

UUID primary keys throughout (matches PoisaPay). Money stored as integer **minor
units** with an asset/currency reference, read/written via `Money` — never floats.

### 2.1 Seller & application

```
sellers
  id (uuid, pk)
  user_id (uuid, fk users, unique)         -- one seller per user
  display_name, brand_name (nullable)
  bio, website (nullable)
  country (iso2)
  status enum(draft, pending_review, approved, rejected, suspended)
  kyc_reference (nullable)                  -- links to Kyc submission
  commission_bps (int, nullable)            -- per-seller override; else platform default
  subscription_plan_id (fk, nullable)       -- Free/Pro/Business
  settlement_asset_id (fk assets)           -- currency earnings accrue in
  payout_method_default (nullable)
  reviewed_by (fk admins, nullable), reviewed_at, rejection_reason
  timestamps
  index(status)

seller_applications                          -- immutable audit trail of each submission
  id, seller_id (fk), snapshot (jsonb: all submitted fields)
  status, submitted_at, decided_by, decided_at, notes
```

### 2.2 Product, versions, files, license keys

```
products
  id (uuid, pk)
  seller_id (fk)
  type enum(digital, license_key, physical, external, subscription, service, course)
  name, slug (unique, indexed), summary, description (long)
  status enum(draft, published, archived)
  price_amount (bigint minor), price_asset_id (fk), compare_price_amount (nullable)
  sku (nullable), stock (int, nullable)      -- null = unlimited (digital)
  support_period_days (nullable), license_type (nullable)
  requires_shipping (bool), weight_grams (nullable)   -- physical
  external_url (nullable)                     -- external type
  fulfillment jsonb                            -- type-specific config
  meta jsonb (seo: title/description/og_image)
  published_at, timestamps
  index(seller_id, status)

product_versions                              -- version history + changelog
  id, product_id (fk), version (semver), changelog (text), is_current (bool), created_at

product_files                                 -- the actual downloadable artifacts
  id, product_id (fk), product_version_id (fk, nullable)
  disk, path (PRIVATE disk — never public), original_name, size_bytes, checksum_sha256
  created_at

product_media                                 -- gallery images / video (public CDN ok)
  id, product_id (fk), kind enum(image, video), url, sort

license_key_pool                              -- pre-loaded or generated keys
  id, product_id (fk), key_ciphertext, status enum(available, reserved, delivered, revoked)
  order_item_id (fk, nullable), delivered_at
  index(product_id, status)
```

### 2.3 Sales page (builder)

The page is **data, not code**: an ordered list of typed sections + a theme
blob. A single server-rendered Blade renderer walks the sections.

```
sales_pages
  id (uuid, pk)
  product_id (fk, unique)                     -- 1 product : 1 page
  slug (unique, indexed)                       -- /p/{slug}
  custom_domain (nullable, unique, indexed)    -- premium: pay.brand.com
  theme jsonb (colors, typography, button style, layout)
  custom_css (text, nullable)                  -- premium only
  sections jsonb                               -- ordered [{type, props}]  (hero, gallery,
                                               --   video, features, benefits, pricing,
                                               --   testimonials, faq, guarantee, countdown,
                                               --   cta, contact)
  seo jsonb, tracking jsonb                     -- pixels/GA/GTM/TikTok/custom scripts, UTM cfg
  status enum(draft, published)
  published_at, timestamps
```

### 2.4 Funnel (upsell/downsell/bump)

```
funnels
  id (uuid, pk), seller_id, product_id (fk, the front-end product), name, is_active

funnel_steps
  id, funnel_id (fk), kind enum(upsell, downsell, cross_sell, order_bump)
  offer_product_id (fk products)
  position (int)                               -- ordering of post-purchase steps
  parent_step_id (nullable)                    -- downsell hangs off an upsell's "skip"
  price_override_amount (nullable)             -- special funnel price
  headline, copy, config jsonb
  index(funnel_id, position)
```

Order bumps render **inside** the checkout (a checkbox); upsell/downsell steps
render **after** payment as one-click offers.

### 2.5 Orders

```
orders
  id (uuid, pk)
  seller_id (fk), buyer_user_id (fk, nullable)  -- null = guest
  buyer_email, buyer_name, buyer_country
  sales_page_id (fk), funnel_id (fk, nullable)
  status enum(pending, paid, processing, shipped, delivered, completed, cancelled, refunded, partially_refunded)
  subtotal, discount, tax, shipping, total (bigint minor), asset_id (fk)
  coupon_id (fk, nullable)
  payment_gateway (string), gateway_ref (nullable)
  ledger_entry_id (fk journal_entries, nullable)   -- the capture entry
  refund_window_ends_at                             -- when pending earnings vest
  ip, user_agent, utm jsonb, tracking jsonb
  idempotency_key (unique)
  timestamps
  index(seller_id, status), index(buyer_user_id), index(buyer_email)

order_items                                        -- front product + bumps + accepted upsells
  id, order_id (fk), product_id (fk), product_version_id (fk)
  kind enum(main, order_bump, upsell, downsell, cross_sell)
  unit_amount, quantity, line_total (bigint minor)
  commission_amount, seller_net_amount (bigint minor)   -- computed at capture
  fulfillment_status enum(pending, delivered, shipped, completed, refunded)
  license_key_id (fk, nullable)

order_events                                       -- append-only lifecycle log (like p2p_order_events)
  id, order_id, type, actor, data jsonb, created_at

shipments                                          -- physical only
  id, order_id, carrier, tracking_number, status, shipped_at, delivered_at, address jsonb
```

### 2.6 Delivery, coupons, reviews, analytics

```
downloads                                          -- one grant per purchased file
  id, order_item_id (fk), product_file_id (fk), buyer_user_id/email
  token (unique, signed-URL subject), max_downloads (int), download_count (int)
  expires_at, last_downloaded_at, revoked (bool)
  index(token)

download_events   id, download_id, ip, ua, created_at   -- audit each hit

coupons
  id, seller_id, product_id (nullable = seller-wide), code (unique per seller)
  type enum(percent, fixed), value, currency_asset_id
  min_order_amount (nullable), usage_limit (nullable), used_count
  per_customer_limit (nullable), starts_at, ends_at, is_active
  index(seller_id, code)

reviews                                            -- verified-buyer only
  id, product_id, order_id (fk, proves purchase), buyer_user_id
  rating (1..5), title, body, media jsonb, status enum(pending, published, hidden)
  seller_reply, seller_replied_at, created_at
  unique(order_id, product_id)                      -- one review per purchase

funnel_events                                      -- raw analytics stream (append-only, partitionable)
  id, sales_page_id, order_id (nullable), session_id
  type enum(page_view, checkout_start, add_bump, purchase, upsell_view, upsell_accept, upsell_skip)
  utm jsonb, referrer, ip_hash, created_at

funnel_stats_hourly                                -- rollup table (mirrors analytics rollup pattern)
  sales_page_id, hour, visitors, checkouts, orders, revenue_minor, upsell_accepts
  pk(sales_page_id, hour)
```

### 2.7 New ledger account types

Add to `App\Enums\LedgerAccountType` (seller-owned + platform income):

```php
case SellerPending   = 'seller:pending';     // earnings held during refund window
case SellerAvailable = 'seller:available';   // vested, withdrawable
case MarketplaceCommissionIncome = 'marketplace:commission_income';
```

Seller balances are **user-scoped** ledger accounts (`user_id` = seller's user),
resolved via the existing `AccountResolver::forUser`. Commission is a system
account like the existing `FeeIncome`.

---

## 3. Money flow (double-entry, exact)

Everything below is a single balanced `EntryData` posted through `LedgerService`
with an **idempotency key** (so a replayed webhook never double-books).

### 3.1 Purchase — wallet payment, $100 product, 10% commission

```
DR  buyer  user:available            $100        (asset A)
CR  platform marketplace:commission_income  $10
CR  seller seller:pending            $90         (asset A, user_id = seller)
memo: order {id}  idem: mkt:capture:{order_id}
```

- **Card / Stripe / PayPal**: the gateway captures external funds. Two-phase:
  (1) provider capture (money into `treasury:pending` via the existing
  deposit/settlement rails), (2) the ledger split above. Reuses the
  deposit-credit pattern so custody always reconciles.
- **Crypto / bank**: async — order stays `pending` until the deposit watcher /
  ramp callback confirms, then the same split posts and the order → `paid`.
- **COD (physical)**: no capture at checkout; order → `processing`. Commission
  and seller-net are booked on **delivery confirmation**.

### 3.2 Refund-window vesting (queue job, e.g. after 14 days, no dispute)

```
DR seller seller:pending    $90
CR seller seller:available  $90
idem: mkt:vest:{order_item_id}
```

### 3.3 Refund (full, within window)

```
DR seller seller:pending                 $90
DR platform marketplace:commission_income $10   (commission clawed back)
CR buyer  user:available                 $100
idem: mkt:refund:{order_id}
```

Partial refunds prorate; if earnings already vested, debit `seller:available`
(and guard against negative — a vested-then-refunded seller goes to a clawback/
owed state handled by the Risk domain).

### 3.4 Payout — reuses the Withdrawal domain

```
seller:available ──▶ RequestWithdrawalAction (reserve-then-sign)
                     minus withdrawal fee → fee:income
                     → bank / crypto / internal wallet
```

Seller payout = a withdrawal whose source account is `seller:available`. No new
payout rail needed — inherits limits, KYC gates, and 2FA.

### 3.5 Revenue streams → ledger mapping

| Stream | Booking |
|---|---|
| Transaction/commission fee | `marketplace:commission_income` at capture |
| Payment processing fee | passed through / `fee:income` |
| Seller subscription (Pro/Business) | recurring charge → `fee:income` |
| Premium templates / funnels / custom domain / storage / API | one-off or recurring → `fee:income` |
| Withdrawal fee | existing `fee:income` on payout |

---

## 4. State machines

**Seller:** `draft → pending_review → approved → suspended → approved` /
`pending_review → rejected`. Only `approved` may publish products. Guard in a
`SellerPolicy`.

**Product:** `draft → published → archived` (and back to `draft`). Publishing
requires: seller approved, ≥1 file (digital) or stock (physical), a price, a
published sales page.

**Order:** `pending → paid → processing → (shipped → delivered) → completed`,
with `cancelled` / `refunded` / `partially_refunded` branches. Digital orders
jump `paid → completed` on delivery; physical orders traverse shipping.

**Funnel session:** `main_paid → upsell_offered → (accepted | skipped) →
[downsell] → thank_you`. Each upsell accept creates a new `order_item` on the
**same order** (so one invoice, one buyer relationship).

Each transition appends an `order_events` / seller audit row via `ActivityLogger`.

---

## 5. Sales-page builder architecture

- **Data-driven, server-rendered.** `sales_pages.sections` is an ordered JSON
  array of `{type, props}`. A `SectionRenderer` maps each `type` to a Blade
  partial (`resources/views/funnel/sections/{type}.blade.php`). Adding a section
  type = one partial + one schema entry; no page-model changes.
- **Public, unauthenticated, fast.** Served outside the app shell by a
  `PublicSalesPageController` on `/p/{slug}` (and `custom_domain` via a domain
  middleware that resolves host → page). Aggressively cacheable HTML; the only
  dynamic bits (countdown, bump toggle, checkout state) are Alpine islands —
  consistent with PoisaPay's "Blade + standalone Alpine for light UI" rule.
- **Theme** is CSS-variable driven (same technique as the consumer
  `theme-minimal` override), so themes are data, not forks. `custom_css` is a
  premium, sanitized escape hatch.
- **SEO + social**: `seo`/`tracking` JSON emits meta/OG tags and the pixel/GTM
  snippets in the head. High-conversion defaults: single CTA, no nav, no
  external links off the page.

---

## 6. Checkout engine + payment abstraction

### 6.1 Provider-agnostic gateway (mirror `App\Card`)

```
App\Payments\Contracts\PaymentGatewayInterface
  authorize(ChargeRequest): ChargeResult          // may be sync or pending
  capture(chargeRef): ChargeResult
  refund(chargeRef, Money): RefundResult
  supportsOneClick(): bool                          // for upsells
  chargeSavedMethod(token, Money): ChargeResult

App\Payments\PaymentManager  ->  resolves driver from config/payments.php + settings
App\Payments\Providers\{Wallet, Stripe, PayPal, Crypto, Bank, Cod}Provider extends AbstractProvider
App\Payments\Inbound\*  ->  signed, idempotent webhook handlers (async settlement)
config/payments.php  ->  driver registry (default_provider, per-provider secrets), exactly like config/card.php
```

The **checkout flow never references a concrete gateway** — it asks
`PaymentManager->driver($method)`. New gateway = new provider class + config
entry, zero checkout changes. This is the same pattern already proven by
`App\Card` (Marqeta/Stripe/Mock) and the Exchange/Screening `driver` seams.

### 6.2 Checkout responsibilities

Coupon apply → tax/VAT calc (per buyer country, seller tax settings) → shipping
calc (physical) → order-bump add → build `order` + `order_items` (status
`pending`, `idempotency_key`) → `PaymentManager->authorize()` →
- **sync success** (wallet/card): post the capture ledger entry, order → `paid`,
  fire delivery + enter the funnel.
- **async** (crypto/bank): order stays `pending`; the Inbound webhook/deposit
  watcher completes it later (idempotent).

Guest checkout: order carries `buyer_email`; a lightweight customer account is
provisioned on first purchase so the Customer Portal works (magic-link login).

### 6.3 One-click upsell

After the main order is `paid`, the funnel offers step N. Accept → charge the
**same** saved method (`chargeSavedMethod`) or wallet, append an `order_item` to
the existing order, deliver, advance to the next step. Skip → downsell (if any) →
Thank-You. All within the funnel session; each charge is its own idempotent
ledger entry.

---

## 7. Digital delivery service

- Files live on a **private disk** (S3 private / local private) — never web-served.
- On `paid`, `DeliveryService` creates a `downloads` grant per file with a random
  `token`, `max_downloads`, and `expires_at`.
- Download route: `GET /d/{token}` → validates grant (not expired, under limit,
  not revoked, buyer matches) → streams via a **short-lived signed URL**
  (`Storage::temporaryUrl` for S3, or Laravel signed route for local) → increments
  count, logs `download_events`. Rate-limited.
- **License keys**: on `paid`, atomically reserve a key from `license_key_pool`
  (or generate one), mark `delivered`, attach to the `order_item`, show + email it.
- **Version history**: buyers always get the current version; changelog visible;
  re-download available from the portal for the support period.

---

## 8. Seller dashboard & customer portal

**Seller** (`/seller/*`, policy-gated, Blade + Alpine): Dashboard (revenue,
available/pending/withdrawn, sales graph, top products), Products, Sales Pages
(builder), Orders, Customers, Coupons, Funnels, Analytics (per-page conversion,
AOV, sources, upsell rate), Earnings, Payouts (request → withdrawal), Settings
(tax, payout method, subscription, custom domain).

**Customer Portal** (`/purchases`, reuses the authenticated app shell): My
Purchases, re-download, license keys, invoices (PDF), order/shipment tracking,
refund requests, contact seller.

**Admin** (admin guard, DollarHub navy): Seller Applications & KYC/KYB review,
product moderation (optional `funnels_product_approval` flag), Orders, Gateway
config, Payout approval, Platform fees/taxes, Coupons oversight, Refunds/Disputes,
Featured (n/a — no discovery, but "spotlight" for internal promo), Marketplace
analytics. Guarded by new `config/permissions.php` abilities
(`funnel.sellers.review`, `funnel.payouts.approve`, …).

---

## 9. Analytics pipeline

Raw `funnel_events` are written cheaply (queued, `ip` hashed) on every page view,
checkout start, purchase, and upsell interaction. An hourly job rolls them into
`funnel_stats_hourly` (same rollup pattern as the existing analytics dashboard),
so seller dashboards read pre-aggregated rows, not the raw stream. Server-side
**Meta Conversion API / TikTok Events API** fire from the queue on `purchase`
(deduped with the browser pixel via `event_id`) for ad-attribution accuracy.
UTM params captured on the order power source breakdowns.

---

## 10. Security

- **KYC/KYB** for sellers via the existing Kyc domain; payouts inherit tier gates + 2FA.
- **Signed, expiring, count-limited downloads**; private storage; per-token buyer binding; download-event audit.
- **Idempotency everywhere**: order `idempotency_key`, ledger entry keys, webhook dedupe — no double charges/deliveries on retries.
- **Webhook auth**: signature-verified inbound handlers (mirrors `App\Card\Inbound`).
- **Rate limiting** on checkout, download, coupon-try, and login routes.
- **Fraud/risk**: reuse `App\Domain\Risk`/`Compliance` — velocity caps, sanctions screening on payout, chargeback/refund-abuse scoring, coupon-abuse limits.
- **RBAC + Policies**: `SellerPolicy`, `ProductPolicy`, `OrderPolicy` (a seller only ever touches their own rows); admin abilities in `config/permissions.php`.
- **Audit logs** via `ActivityLogger` on every seller/admin state change.
- **Custom CSS / tracking scripts** sanitized + sandboxed (CSP) to prevent XSS on public pages.

---

## 11. API design

- **Internal** (drives the app; traditional Blade POST→redirect for humans):
  `/seller/products`, `/seller/funnels`, `/checkout`, `/d/{token}`, `/purchases`.
- **Public sales/checkout** stays server-rendered for conversion + SEO; the
  checkout submits a normal form / minimal JSON to `POST /p/{slug}/checkout`.
- **External REST API** (premium tier, token-auth, versioned `/api/v1/funnel/*`):
  products, orders (read), create-coupon, fetch-analytics, issue license keys —
  for sellers integrating their own funnels/tools. Rate-limited per key.
- **Webhooks out** (seller-configurable): `order.paid`, `order.refunded`,
  `download.delivered` — signed, retried with backoff.

---

## 12. Queue jobs & notifications

**Jobs**: `SettleAsyncPaymentJob` (crypto/bank), `VestSellerEarningsJob` (refund
window), `FireConversionApiJob` (Meta/TikTok), `RollupFunnelStatsJob` (hourly),
`GenerateInvoicePdfJob`, `IssueLicenseKeyJob`, `DeliverDigitalGoodsJob`,
`ExpireDownloadsJob`, `ProcessPayoutJob` (→ withdrawal).

**Notifications** (existing notification engine + templates): buyer
purchase-receipt + download links, license key, refund processed, shipment
update; seller new-sale, payout processed, application decision, low-stock,
new review.

---

## 13. File storage strategy

- **Product artifacts** → private disk (`s3-private`), never public URLs; served
  only through signed download grants.
- **Media (gallery/video)** → public CDN disk (safe to cache).
- **Invoices/exports** → private, signed, short-lived links.
- Large uploads via multipart + checksum; virus-scan hook on ingest (queued).

---

## 14. Scalability

- Public sales pages are cacheable static-ish HTML (CDN/edge) — handles ad-spike
  traffic without touching the DB; only checkout hits the app.
- `funnel_events` is append-only and partitionable by month; reads come from
  `funnel_stats_hourly`, keeping seller dashboards fast at millions of orders.
- Ledger is the proven bottleneck-safe core (already handles custody/exchange);
  seller balances are just more accounts.
- Stateless app tier + queues for all non-critical-path work (delivery,
  analytics, CAPI, PDFs, payouts).

---

## 15. Implementation roadmap (phased, flag-gated — matches PoisaPay's build style)

Everything ships behind `funnels_enabled` (default OFF), same as custody/P2P.

- **Phase 1 — Seller onboarding**: `sellers` + `seller_applications`, apply form,
  admin review + KYC gate, `SellerPolicy`, `/seller` shell. Flag: `funnels_enabled`.
- **Phase 2 — Product + Sales Page**: products/versions/files/media, sales-page
  model + `SectionRenderer`, public `/p/{slug}`, private file storage.
- **Phase 3 — Checkout + Wallet payment**: checkout engine, `App\Payments`
  abstraction with the **Wallet** provider first (pure ledger, no external dep),
  order lifecycle, capture ledger split + commission.
- **Phase 4 — Digital delivery**: signed downloads, license-key pool, receipts,
  Customer Portal (My Purchases).
- **Phase 5 — Earnings & payouts**: `seller:pending/available` accounts,
  `VestSellerEarningsJob`, payout via Withdrawal domain, seller earnings UI.
- **Phase 6 — External gateways**: Stripe + PayPal + Crypto + Bank + COD providers
  and their idempotent Inbound webhooks (async settlement).
- **Phase 7 — Funnels**: order bumps, one-click upsell/downsell engine, Thank-You.
- **Phase 8 — Analytics + marketing**: event pipeline, hourly rollups, pixels +
  Meta/TikTok CAPI, UTM/source reports.
- **Phase 9 — Coupons, reviews, refunds/disputes**, seller subscriptions & premium
  add-ons (templates, custom domains, API, storage tiers).
- **Phase 10 — Hardening/scale**: CDN for pages, partitioning, fraud rules, rate
  limits, load tests, full audit coverage.

Each phase is independently shippable and testable (pgsql feature tests, the same
discipline as the existing 100+ money-path tests), and reuses the ledger so money
correctness is guaranteed by the same DB-trigger invariants already in place.
