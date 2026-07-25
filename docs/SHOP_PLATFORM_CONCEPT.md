# PoisaHub "Sell" — Full Concept (as built, frontend)

## What it is
A **one-product funnel platform** built into PoisaHub — Gumroad × ClickFunnels,
payments-first. A seller turns any product into a **standalone sales page + checkout**
they drive paid traffic to (Facebook/TikTok/Google/email). Every payment runs
through **PoisaPay** (its wallet, ledger and payout rails) — no external gateway.

**Not a marketplace.** No catalog, no browsing. The unit is the **sales page**.

```
Ad ─▶ Sales page ─▶ PoisaPay checkout ─▶ (order bump / upsell / downsell) ─▶ Thank-you
                                                                              │
Seller: earnings ◀── ledger split ──┘        Buyer: download / license / track / message
```

## Who uses it
- **Seller** — a PoisaHub user who applies, gets approved (KYC), and sells.
- **Buyer** — anyone; pays with PoisaPay; gets a customer portal.
- **Admin** — reviews sellers, moderates, handles payouts/disputes.

---

## The seller journey
1. **Apply** (`/seller/apply`) — profile, categories, country, settlement currency, KYC, terms.
2. **Dashboard** (`/seller`) — onboarding, KPIs, workspace grid.
3. **Create a product** — pick a type; digital/physical/etc.
4. **Create a sales page** for that product, then **customize** it in the builder.
5. **Build a funnel** — order bump, upsell, downsell.
6. **Connect a custom domain** to a page (optional).
7. **Share the URL** in ads → sales roll in.
8. **Fulfill & support** — ship physical orders, reply to buyers, watch analytics.
9. **Get paid** — earnings vest, withdraw via PoisaPay.

## The seller workspace (all built)
| Module | Route | What it does |
|---|---|---|
| Dashboard | `/seller` | onboarding + KPIs + module grid |
| Products | `/seller/products` (+ `/create`) | list + create; **physical variations** (Size×Color matrix, price/stock per variant) |
| Sales pages | `/seller/sales-pages` (+ `/{slug}/edit`) | **list of pages** (multiple, even several per product) → pick one to **customize** in the WYSIWYG builder |
| Funnels | `/seller/funnels` | order bump → upsell → downsell + performance |
| Orders | `/seller/orders` (+ `/{id}`) | list; **order detail** with fulfillment (carrier + tracking, mark shipped), timeline, address, earnings, **order messages** |
| Inbox | `/seller/inbox` | two-pane buyer↔seller conversations |
| Reviews | `/seller/reviews` | verified-buyer reviews + reply |
| Customers | `/seller/customers` | buyers + spend |
| Coupons | `/seller/coupons` | % / fixed, usage, expiry |
| Analytics | `/seller/analytics` | visitors, conversion funnel, sources, pixels |
| Earnings | `/seller/earnings` | available/pending/withdrawn + payout history |
| Custom domains | `/seller/domains` | **one domain per sales page**, DNS + auto-SSL |

## The buyer experience
- **Sales page** (`/p/{slug}`) — hero, features, benefits, testimonials, FAQ, guarantee, CTA; sticky buy bar; conversion-first, off the app shell.
- **Checkout drawer** — email, coupon, **order bump**, and **Pay with PoisaPay** (single method).
- **Thank-you** — delivery + one-time upsell.
- **My Purchases** (`/purchases`) — download files, reveal license keys, **track shipments** (timeline + carrier), **contact seller**, invoices, refund requests.

---

## Key concepts

**Product types:** Digital download · Physical product · License key · Membership ·
Subscription · Service. Physical products support **variations** (Size, Color, …)
→ an auto-generated variant matrix with per-variant price + stock, plus weight,
SKU, shipping fee, and cash-on-delivery.

**Sales pages:** a product can have **many** pages (e.g. one per ad campaign). Each
is created against a product, then customized in the builder — **toggle/reorder/edit
sections**, live theme (accent, buttons, font), and a **live WYSIWYG preview**.

**Funnels:** order bump inside checkout; one-click upsell/downsell after payment;
each accepted offer is another line on the same order.

**Custom domains:** connect a domain **per sales page** → add one CNAME →
auto-verify + free SSL → the domain routes straight to that page (instead of `/p/{slug}`).

**Payments — PoisaPay only:** checkout offers a single "Pay with PoisaPay" option;
PoisaPay internally handles wallet/card/crypto/bank/mobile-money. No Stripe/PayPal.

**Delivery:** digital → signed, expiring, count-limited download links; license →
issued key; physical → shipping with carrier + tracking; course/membership →
entitlement/access.

**Fulfillment (physical):** on the order page the seller sees the shipping address,
picks a carrier, adds a tracking number, marks shipped → buyer notified + tracks it.

**Messaging is order-centric:** the conversation lives **on the order page**, shared
by buyer & seller (admin can join on disputes). Buyers open it from "Contact seller";
sellers from the order page or the Inbox.

**Earnings & payouts:** each sale splits in the ledger — buyer → platform commission
+ seller balance; held for a refund window, then withdrawable via PoisaPay's
withdrawal rails (bank / crypto / wallet).

---

## How it plugs into PoisaPay
Reuses, not rebuilds: **ledger** (money + commission + seller balances), **wallet**
(checkout), **KYC** (seller verification), **withdrawal** (payouts), **notifications**,
**risk/compliance** (fraud, refunds), **audit**, **admin guard**, **feature flags**.
Seller = a normal user with an approved Seller profile (policy-gated, like the
existing merchant console). Everything sits behind a default-off `funnels_enabled` flag.

## Revenue model
Take-rate (commission) per sale · payment processing · seller subscriptions
(Free/Pro/Business) · premium add-ons (templates, extra funnels, custom domains,
analytics, storage, API) · withdrawal fees.

---

## Status
**Frontend/UX is complete and clickable end-to-end** (seller onboarding → products
+ variations → sales-page builder → funnels → public page → PoisaPay checkout →
thank-you → fulfillment → messaging → earnings → domains → customer portal).
It is **preview only** — forms flash confirmations, nothing persists, no DB/backend
yet. Backend (migrations, models, ledger money-paths, delivery, real payments) is the
next phase, to be built incrementally behind `funnels_enabled` starting with seller
onboarding + Wallet checkout.
