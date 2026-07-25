# PoisaHub Creator Platform — Product & UX Design (Phase 1)

> **Design only. No code.** This document is the product vision, user experience,
> information architecture, and system architecture for a **Digital Product
> Funnel Platform** built on top of PoisaHub, for creators selling courses,
> eBooks, software, templates, plugins, AI prompts, memberships, and license keys.
>
> Companion: `docs/FUNNEL_PLATFORM_DESIGN.md` holds the deeper technical/ledger
> design. This doc is the product/UX layer and supersedes it where they overlap.
>
> **Sequence we are following:** Research → UX Design → Architecture → Review →
> Development Plan → Implementation. We are at step 1–3 (this doc). No models,
> migrations, controllers, or components until you approve.

---

## 1. Product Vision

### Business goals
1. Give creators the **shortest path from "I made a thing" to "I got paid"** — a single link they can put in an ad, bio, email, or DM.
2. Monetize PoisaHub's existing **ledger, wallet, KYC, and payout rails** with a high-margin creator product (take-rate + subscriptions), reusing infrastructure already built and hardened.
3. Be **payments-first for emerging markets**: wallet, crypto, bank, and mobile-money (bKash/Nagad) checkout that Gumroad/Lemon Squeezy don't serve well — this is the wedge.

### Target users
Sellers: course creators, indie software developers, designers, freelancers, agencies, and general digital creators. Buyers: their audiences (students, developers, small businesses), often paying from mobile in local currency.

### Revenue model
| Stream | Mechanic |
|---|---|
| **Take-rate** on each sale | commission bps (default ~10%, per-seller override, lower on higher plans) |
| **Payment processing** | pass-through / small markup per method |
| **Seller subscriptions** | Free / Pro / Business (lower take-rate, more funnels, custom domain, API) |
| **Premium add-ons** | premium templates, extra funnel steps, custom domains, advanced analytics, storage tiers, API access |
| **Payout / withdrawal fee** | existing PoisaHub withdrawal fee on creator payouts |

**Positioning:** *"Gumroad's simplicity + ClickFunnels' upsells + PoisaHub's wallet & local payments."*

### Competitor analysis
| Product | Strength | Weakness we exploit |
|---|---|---|
| **Gumroad** | dead-simple, one-link selling | weak funnels/upsells, high flat fee, poor local payments, no memberships depth |
| **Lemon Squeezy** | clean checkout, MoR/tax handling | SaaS/license focus, no course LMS, no local rails |
| **Payhip** | cheap, memberships + courses | dated UX, thin funnel/upsell, limited analytics |
| **ClickFunnels** | best-in-class upsell funnels | expensive, complex, not built for digital delivery/LMS |
| **Kajabi / Teachable** | strong course LMS + community | heavy, pricey, weak one-page funnel selling, no crypto/local pay |

**Our sweet spot:** one-product funnel selling **with** a real course/membership engine **and** wallet/crypto/local checkout — none of the incumbents do all three.

---

## 2. User Personas

Each persona has a distinct "job to be done" and a tailored journey.

1. **Maya — Course Creator.** Sells a video course + workbook. Cares about: course player, drip content, student progress, completion, upsell to a "pro cohort." Journey: create course → modules/lessons → sales page → order bump (workbook) → upsell (1:1 call) → student dashboard.
2. **Dev — Software Developer.** Sells a Laravel SaaS boilerplate + license keys. Cares about: license issuance, version updates, changelog, support period, GitHub-style re-download. Journey: product (license type) → files + versions → license pool → sales page → upsell (extended license).
3. **Nadia — Designer.** Sells UI kits/templates. Cares about: gorgeous gallery, instant download, bundles. Journey: product (digital download) → media-heavy sales page → order bump (bundle) → instant delivery.
4. **Karim — Freelancer.** Sells productized services (e.g. "logo in 48h"). Cares about: intake form, order status, deliverable upload. Journey: product (service) → checkout with brief → order flows pending→delivered.
5. **Studio X — Agency.** Sells multiple products, needs team seats + custom domain + API. Cares about: multiple funnels, brand domains, analytics, payouts to a business account. Higher plan.
6. **Ayaan — Digital Creator.** Sells AI prompt packs / eBooks. Cares about: low-friction impulse buy, mobile checkout, social sharing. Journey: cheap product → mobile sales page → 1-tap wallet checkout → upsell (prompt bundle).
7. **Sara — Student / Customer.** Buys and consumes. Cares about: frictionless checkout (guest, local pay), immediate access, a portal to find purchases, watch courses, re-download, get invoices/support. Journey: ad → sales page → guest checkout → thank-you with access → customer portal.

---

## 3. End-to-end User Journey

```
SELLER                                   CUSTOMER
──────                                    ────────
Register (PoisaHub user)
  └─ Apply as seller (+KYC)
       └─ Admin approves
Create Product (pick type)
  └─ Sales Page auto-generated
       └─ Customize (blocks/theme)
            └─ Build Funnel (bump/upsell/downsell)
                 └─ Publish → /p/{slug}
                      └─ Share URL in ads ───────────▶  See ad → Sales Page
                                                          └─ Buy Now → Checkout
                                                               └─ (guest or login)
                                                               └─ coupon + order bump
                                                               └─ Pay (wallet/card/crypto/bank/COD)
                                                          Payment success
                                                          └─ Upsell offer → accept/skip
                                                          └─ (Downsell) → Thank-You
                                                          └─ Digital delivery (access/download/keys)
Earnings accrue (pending→available)  ◀───── sale + commission split (ledger)
  └─ Request payout → withdrawal                     Customer Portal
Analytics: visitors, conv, upsell, revenue           └─ My Purchases / Courses / Downloads
                                                       └─ Invoices, support, refund request
                                                       └─ Repeat purchase / membership renewal
```

The loop that matters commercially: **ad → page → checkout → upsell → delivery →
portal → repeat**. Every design decision optimizes conversion and retention along it.

---

## 4. Information Architecture

### Public (no login, conversion-optimized, off the app shell)
- **Sales Page** `/p/{slug}` (or custom domain) — the product's landing page.
- **Checkout** — embedded on the sales page (no redirect).
- **Upsell / Downsell** — post-payment funnel steps.
- **Thank-You** — access + receipt + next step.
- **Customer login / magic-link** — to reach the portal.

### Seller (`/seller/*`, unlocked after approval, PoisaHub app shell)
Dashboard · Products · Funnels · Sales Pages · Orders · **Students** (course learners) · Customers · Coupons · Analytics · Earnings · Payouts · Settings (tax, payout method, plan, domains, team).

### Customer (`/purchases/*`, in the app shell)
My Purchases · **My Courses** (player + progress) · Downloads · License Keys · **Memberships** (status/renewal) · Billing (invoices/subscriptions) · Support.

### Admin (admin guard, DollarHub navy)
Sellers (applications + KYC/KYB) · Products (moderation) · Orders · Payment Gateways · Payouts · Reports · Analytics (GMV, revenue, sellers) · Settings (fees, taxes, plans, flags).

---

## 5. Funnel Design — every screen

1. **Sales Page.** Single-goal landing page: hero (headline + subhead + hero media + primary CTA), social proof, product visuals, benefits, features, pricing, testimonials, FAQ, guarantee, optional countdown, final CTA, footer. No nav, no external links — every element funnels to Buy.
2. **Checkout.** Opens inline (drawer/modal or embedded section) on CTA click. Fields: email (guest) or logged-in, name, country; payment method selector; coupon field; **order bump** (a checkbox offer, e.g. "+ add the workbook for $9"); tax/VAT line; total; Pay button. Trust badges, money-back note. Minimal fields, remembers logged-in users.
3. **Order Bump.** Rendered inside checkout as a single high-contrast checkbox card; toggling it adds a line item live to the total before payment. One bump per checkout (conversion best practice).
4. **Upsell (post-payment, one-click).** After payment succeeds, a full-screen offer: "Wait — add X at a special price." One-click **Yes** charges the same method (no re-entry); **No thanks** advances. Genuinely one-click because the payment method/wallet is already captured.
5. **Downsell.** If the upsell is skipped, optionally offer a cheaper/alternative version. Same one-click mechanic.
6. **Thank-You.** Confirmation + immediate access (course "Start learning" / download buttons / license keys), order summary, receipt/invoice link, "create a password / go to portal," and a soft next-step (join community, follow creator).

Funnel steps are **ordered and branch on accept/skip**; each accepted offer becomes another line item on the **same order** (one invoice, one customer relationship).

---

## 6. Product Types — behavior

| Type | What the buyer gets | Delivery behavior |
|---|---|---|
| **Course** | Modules → lessons (video/text/quiz/attachments), progress, completion, certificate | Access granted in portal → "My Courses"; supports **drip** (unlock over time), completion tracking |
| **Digital Download** | One or more files (zip, pdf, assets) | Signed, expiring, count-limited download links; re-downloadable from portal |
| **License Key** | A unique key + downloadable software + version updates | Key issued from a pool/generated on payment; version history + changelog; support-period gating |
| **Membership** | Ongoing access to gated content/community | Recurring entitlement; access while active; pauses/cancels; dunning on failed renewal |
| **Subscription** | Recurring billing for a product/plan | Same as membership but product-centric; upgrade/downgrade/proration |
| **Service** | A delivered outcome (design, dev, consult) | Order carries an intake brief; status pending→processing→delivered; deliverable upload + messaging |

A single product has exactly one type; the sales page and checkout adapt (e.g.
a Service checkout shows a brief field; a Membership shows billing cadence).

---

## 7. Sales Page Builder

- **Templates** — a starter gallery per goal: "Course launch," "Software/SaaS," "eBook impulse," "Template showcase," "Webinar/VSL," "Minimal one-pager." Each is a pre-arranged block set + theme.
- **Blocks** (drag/reorder, each configurable): Hero, Video/VSL, Gallery, Features, Benefits, Pricing/Plans, Testimonials, Logos, FAQ, Guarantee, Countdown, Bonuses, Instructor/About, Rich text, Embed, CTA, Contact/Footer. Adding a block type is additive (data-driven).
- **Themes** — color palette, typography, button style, corner radius, spacing density; CSS-variable driven so themes are data, not forks. Premium: custom CSS + fonts.
- **SEO** — per-page title/description/OG image/social card; clean slug; sitemap opt-in (creators can keep pages unindexed for ad-only traffic).
- **Mobile UX** — mobile-first; sticky mobile "Buy" bar; tap-sized CTAs; lazy media; fast first paint (pages are cacheable static-ish HTML).
- **CTA placement** — repeated primary CTA after hero, after benefits, after testimonials, and sticky on mobile; single action, single color.
- **Conversion optimization** — countdown/scarcity, guarantee badge, social proof, exit-intent (premium), A/B page variants (premium), autofilled/remembered checkout.

---

## 8. Checkout Experience (friction is the enemy)

- **Guest checkout** by default (email only); a lightweight customer account is provisioned silently so the portal works — buyer sets a password later via magic link. Never block the sale on account creation.
- **Logged-in checkout** — one-tap for existing PoisaHub users (wallet balance shown, saved methods, prefilled details).
- **Coupon** — inline apply with instant total update and clear error states.
- **VAT/Tax** — computed by buyer country + seller tax settings; shown as a line item; supports tax-inclusive or added.
- **Payment methods** — **Wallet** (instant, ledger), Card, Crypto, Bank transfer, **mobile money (bKash/Nagad)**, COD (physical only). Method list adapts to product type and seller config.
- **Order bump** — one checkbox, live total.
- **Upsell flow** — one-click post-payment (method already captured).
- **Trust & speed** — minimal fields, autofill, saved methods, visible guarantee, security badges, sub-second interactions (Alpine islands, no full reloads).

---

## 9. Digital Delivery

- **Download experience** — Thank-You + portal show clear "Download" buttons; links are signed, expiring, and download-count limited; large files stream; re-download available for the support period; download history visible.
- **Course access** — instant enrollment; a clean course player (sidebar of modules/lessons, video, resources, mark-complete, progress bar, next-lesson); optional drip schedule; completion → certificate.
- **License delivery** — key shown on Thank-You + emailed + stored in portal; tied to the buyer; revocable on refund; software file + version list.
- **Product updates** — creators publish new versions; buyers within the support window get the update + changelog; "new version available" notification.
- **Version history** — every version retained with changelog; buyers can fetch current or past versions per policy.

---

## 10. Analytics

### Seller dashboard
- **Traffic:** visitors, unique sessions, traffic sources (UTM/referrer), device split.
- **Conversion:** page → checkout-start → purchase rates; drop-off funnel.
- **Revenue:** gross, net (after commission), refunds, AOV, revenue over time.
- **Funnel performance:** order-bump take rate, **upsell/downsell accept rate**, revenue-per-visitor.
- **Product/course:** best sellers, course completion, student engagement.
- **Payouts:** available/pending/withdrawn, next vesting.

### Admin dashboard
- **Platform revenue** (commission + subscriptions + fees), **GMV**, take-rate.
- **Active sellers**, new applications, approval funnel.
- **Top products/sellers**, category mix.
- **Payment success rate** by method/gateway, refund/chargeback rate, dispute load.
- **Payout volume** and float.

Both read from **pre-aggregated hourly rollups** (raw events streamed cheaply,
rolled up by a job) — the same pattern PoisaHub's analytics dashboard already uses.

---

## 11. Database Concept (entities only — no migrations)

Entities, ownership, and relationships (not schemas):

- **Seller** — owned by a **User** (1:1). Has many Products, Funnels, Coupons, Orders (as merchant), Payout requests. Has a status lifecycle and a KYC link.
- **SellerApplication** — belongs to Seller; immutable submission/decision trail.
- **Product** — owned by Seller; has a Type; has ProductVersions, ProductFiles (private), Media, and (for courses) CourseModules → CourseLessons; (for licenses) a LicenseKey pool.
- **SalesPage** — 1:1 with Product; owns ordered Blocks (data), Theme, SEO, and Tracking config; has a slug / optional custom domain.
- **Funnel** — owned by Seller, tied to a front Product; has ordered FunnelSteps (order-bump / upsell / downsell / cross-sell) each pointing at an offer Product.
- **Order** — belongs to Seller + (optional) buyer User/guest email; has many OrderItems (main + bumps + accepted upsells); references a Coupon, a payment method, and a **ledger entry**; has a lifecycle and an append-only OrderEvent log; Shipment for physical.
- **OrderItem** — belongs to Order + Product/version; carries commission and seller-net; owns a Download grant / LicenseKey / Enrollment as appropriate.
- **Entitlement** (access) — a buyer's right to a Product: **Enrollment** (course), **Membership/Subscription** (recurring, with status + renewal), **Download grant** (signed, limited), **LicenseKey** (issued). Owned by the buyer, granted by an Order.
- **Coupon** — owned by Seller; scoped to a product or seller-wide.
- **Review** — by a verified buyer, tied to an Order (proves purchase) and Product.
- **Earnings ledger accounts** — seller-owned `pending`/`available` balances + platform commission income, all **inside the existing double-entry ledger** (not separate money tables).
- **FunnelEvent / stats rollup** — analytics stream + aggregates, owned by SalesPage.

**Data flow (money):** Order paid → ledger split (buyer → commission + seller:pending)
→ refund window → seller:pending → seller:available → payout via Withdrawal.
**Data flow (access):** Order paid → Entitlement granted → Portal reads entitlements.
Ownership rule: **a seller can only ever read/write rows scoped to their own Seller id; a buyer only their own entitlements/orders** — enforced by policies.

---

## 12. System Architecture

- **Domains** (business logic modules, mirroring PoisaHub's `app/Domain/*`):
  `Funnel/Seller`, `Funnel/Product`, `Funnel/Course`, `Funnel/SalesPage`,
  `Funnel/Checkout`, `Funnel/Funnel` (steps), `Funnel/Order`, `Funnel/Delivery`,
  `Funnel/Entitlement`, `Funnel/Coupon`, `Funnel/Earnings`, `Funnel/Analytics`.
- **Payment layer** — a **provider-agnostic gateway** (`App\Payments`) modeled on
  the existing `App\Card` abstraction (Manager + interface + drivers +
  idempotent inbound webhooks). Checkout depends on the interface, never a
  concrete gateway; adding Stripe/PayPal/bKash = a new adapter, no checkout change.
- **Services / boundaries** — Checkout orchestrates pricing → payment →
  order → delivery, but each concern is its own service; the **ledger** is the
  single source of truth for money; **entitlements** are the single source of
  truth for access. Public sales pages are a separate, cache-friendly read path.
- **Events** (domain events → listeners/jobs): `SellerApproved`, `OrderPaid`,
  `UpsellAccepted`, `OrderRefunded`, `EarningsVested`, `PayoutRequested`,
  `EnrollmentGranted`, `VersionPublished`.
- **Queue jobs**: settle async payment (crypto/bank), vest earnings after refund
  window, fire Meta/TikTok Conversion API, roll up analytics hourly, generate
  invoice PDF, issue/deliver license keys, deliver digital goods, expire
  downloads, process payout, dunning for failed subscription renewals.
- **Integrations**: payment gateways (Stripe, PayPal, bKash/Nagad, crypto via
  existing deposit rails, bank via ramp), email/notifications (existing engine),
  storage (S3 private for artifacts, CDN for media), ad pixels + server-side CAPI
  (Meta/TikTok/Google), optional external webhooks out for sellers.

Reused wholesale from PoisaHub: **Ledger, Wallet, KYC, Withdrawal, Notification,
Audit, Risk/Compliance, Settings/Feature-flags, Admin guard, RBAC.**

---

## 13. UI / UX — per surface (layout, states, mobile)

**Sales Page (public).** Layout: single column, hero-first, sticky mobile buy bar.
Components: block renderer, media, testimonials, FAQ accordion, checkout drawer.
Empty state: n/a (always has content when published; unpublished → 404/preview).
Loading: skeleton hero + instant static shell (CDN). Error: friendly "page
unavailable" + creator contact. Mobile: primary experience — big CTAs, lazy media.

**Checkout (public).** Layout: compact drawer/section, summary on top, fields
below, pay button pinned. Components: method selector, coupon, order-bump card,
tax line. Loading: inline spinner on Pay (never a full reload); async methods show
"waiting for payment" with a status poller. Error: field-level + a clear decline
reason ("insufficient wallet balance," "card declined") with a retry/alternate
method. Mobile: full-height sheet, autofill, wallet 1-tap.

**Seller Dashboard.** Layout: PoisaHub app shell + left nav (the IA modules).
Components: KPI cards (revenue/available/pending), sales chart, recent orders,
top products. Empty states: friendly first-run ("Create your first product"),
zero-sales coaching. Loading: skeleton cards. Error: non-blocking banners.

**Product / Funnel / Page editors.** Layout: two-pane (config left, live preview
right). Components: block list (reorder), block settings, theme picker, funnel
step builder. Empty: "Add your first block/step." Loading: preview shimmer.
Error: inline validation, autosave with a saved/failed indicator.

**Customer Portal.** Layout: app shell, tabs (Purchases/Courses/Downloads/
Licenses/Memberships/Billing/Support). Components: purchase cards, course player,
download buttons, key reveal + copy, membership status, invoice list. Empty:
"No purchases yet." Loading: skeletons. Error: retry + support link. Mobile:
course player is mobile-optimized (portrait video, resume playback).

**Admin.** Layout: DollarHub navy admin shell. Components: application review
queue, KYC viewer, order/payout tables, gateway config, analytics. Empty: clean
"nothing pending." Loading/error: standard admin patterns already in PoisaHub.

Consistent conventions: PoisaHub's Mercury-style modals, badge/status colors,
history-table + detail-modal pattern, form POST → redirect + flash, and standalone
Alpine for light interactivity (no SPA).

---

## 14. Security

- **Seller verification (KYC/KYB).** Approval gated on the existing KYC domain;
  business sellers provide KYB; payouts inherit KYC-tier limits + 2FA. Suspension
  path for abuse.
- **Digital delivery protection.** Artifacts on a **private** disk, never public;
  delivered only via **signed, expiring, count-limited** links bound to the buyer;
  every download audited; license keys encrypted at rest, revocable on refund.
- **Fraud prevention.** Reuse `Risk/Compliance`: velocity caps on orders/refunds,
  sanctions screening on payout, chargeback/refund-abuse scoring, coupon-abuse
  limits, disposable-email and card-testing detection at checkout, rate limiting
  on checkout/download/login.
- **Refund workflow.** Buyer requests → seller/admin reviews → within refund
  window the ledger reverses the split (commission clawed back), entitlement +
  downloads revoked; partial refunds prorate; vested-then-refunded handled as a
  seller clawback via Risk. Disputes escalate to admin.
- **Permission model.** Buyer = existing `auth` user (only their own entitlements/
  orders). Seller = user with an approved Seller profile, **policy-gated** to their
  own rows. Admin = separate `admin` guard with `config/permissions.php` abilities
  (`funnel.sellers.review`, `funnel.payouts.approve`, `funnel.products.moderate`).
  All state changes audited via `ActivityLogger`. Idempotency on orders, ledger
  entries, and webhooks prevents double-charge/double-delivery.

---

## Next (gated on your approval)

- **Phase 2 — Review:** walk every module above, find inconsistencies, cut
  complexity, tighten the journey, and produce the final architecture doc.
- **Phase 3 — Implementation plan:** roadmap, milestones, feature priority,
  technical task breakdown, testing strategy, and build order — still no code.
- **Phase 4 — Development:** only after explicit approval, incremental phases
  (scope → DB → backend → frontend → tests → docs → review checklist), starting
  with seller onboarding behind a default-off `funnels_enabled` flag, and Wallet
  as the first payment method (pure ledger, no external dependency).

**No code will be written until you approve this design.**
