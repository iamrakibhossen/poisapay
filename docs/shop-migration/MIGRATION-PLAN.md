# Sell → Shop Migration Plan

**Status:** Awaiting approval · **Branch:** `feature/shop-migration` · **Author:** migration engineering
**Decisions locked:** no live data → clean rewrite · full physical table rename · in-flight work committed first (`35be22f`) · plan-first before executing.

---

## 1. Executive summary

The "Sell" module is a **122-file DDD module** (`app/Sell/`) backed by **26 Postgres tables**, **~50 named frontend routes**, **26 feature tests**, a service provider, two scheduled commands, and a consumer-facing frontend layer that lives *outside* `app/Sell` (in `app/Http/Controllers/Frontend|Funnel|Marketing`).

Because there is **no live data**, we do a **clean rewrite** rather than an expand/contract zero-downtime dance:
- Physical tables renamed by **editing the create-migrations in place** (`sell_* → shop_*`), then `migrate:fresh`.
- Namespace/directory `App\Sell` → `App\Shop`, `app/Sell/` → `app/Shop/`.
- The handful of `Sell`-prefixed classes renamed (`SellServiceProvider`, `SellException`, `SellDomainEvent`, `AuditSellEvent`, `SellAudit`).
- Semantic rename **Seller → Merchant** (see §5 — the most invasive change; flagged for your veto).
- Routes/URLs `sell.*` → `shop.*`, `/sell` → `/shop`, `api/sell` → `api/shop`.
- Cache keys `sell:*` → `shop:*`, flags/settings `sell_*` → `shop_*`, commands `poisapay:sell-*` → `poisapay:shop-*`.
- Frontend nav: **Seller → Merchant**, dashboard restructured to the requested IA (existing sections wired, net-new sections stubbed and labelled).

We **also deliver** a Schema::rename-based zero-downtime migration script and rollback plan as *artifacts* (§6.2, §11) even though the clean rewrite is what we run — so the same migration is repeatable against an environment that *does* have data later.

### Brief-vs-reality reconciliation (items in the brief that don't map)
| Brief item | Reality | Action |
|---|---|---|
| `config/sell.php` → `config/shop.php` | **No `config/sell.php` exists.** Config lives in the settings engine + `bootstrap/providers.php`. | Rename settings keys (`sell_enabled`, `sell_commission_bps_*`) + settings section `sell` → `shop`. |
| Rename queue names `sell-processing` etc. | **No custom queues** — jobs run on default/sync. | N/A (note in doc). |
| Swagger / OpenAPI | **No Swagger/OpenAPI spec** in repo. Only Laravel API Resources. | Rename the Resource classes; note there's no spec to regen. |
| `SellProduct`, `SellOrder` classes | Classes are **namespaced** (`App\Sell\Models\Product`), not prefixed. | Namespace rename covers it; only true `Sell*`-prefixed classes get renamed. |
| `sell_customers`, `sell_tax_rates`, `sell_shipping_profiles` tables | **Don't exist** (customers are derived from orders; no tax/shipping tables yet). | N/A now; listed as future in §8. |
| Course Sales | Explicitly **not supported** (see memory `sell-no-courses`). | Excluded. |

---

## 2. Current-state inventory

**Code (`app/Sell/`, 122 files):**
`Actions/` (Coupon, Order, Product, Refund, Review, SalesPage, Seller) · `Builder/` (schema-driven block engine + Blocks/Contracts) · `Contracts/` · `DTOs/` (5) · `Enums/` (9) · `Events/` (17) · `Exceptions/` · `Http/{Controllers,Requests,Resources}` · `Listeners/` · `Models/` (20) · `Policies/` (4) · `Services/` (6) · `Support/` · `routes/web.php` · `SellServiceProvider.php`.

**20 models → 26 tables** (models cover 20; 6 tables are model-less: `sell_daily_stats`, `sell_download_events`, `sell_funnels`, `sell_funnel_steps`, `sell_product_media`, `sell_shipments`).

**Tables (26):** `sell_sellers`, `sell_seller_applications`, `sell_products`, `sell_product_variants`, `sell_product_files`, `sell_product_media`, `sell_sales_pages`, `sell_page_revisions`, `sell_saved_blocks`, `sell_domains`, `sell_funnels`, `sell_funnel_steps`, `sell_orders`, `sell_order_items`, `sell_order_events`, `sell_coupons`, `sell_downloads`, `sell_download_events`, `sell_licenses`, `sell_reviews`, `sell_messages`, `sell_message_attachments`, `sell_shipments`, `sell_refund_requests`, `sell_analytics_events`, `sell_daily_stats`.

**Migrations (12):** `2026_07_26_000001..05` (foundation/page/commerce/fulfilment/analytics) + `2026_07_27_*` (logo, offers) + `2026_07_28_*` (builder, earnings-hold, refund, refund-requests). Plus `2026_07_24_160000_add_production_readiness_indexes.php` references sell tables.

**Frontend layer outside `app/Sell`** (consumes the domain): `app/Http/Controllers/Frontend/{SellerController,PurchasesController}`, `Funnel/PublicSalesController`, `Marketing/ProductController`; views `resources/views/frontend/seller/*`, `funnel/*`, `marketing/*`, `admin/sell/sellers/*`.

**Routes:** `routes/frontend/seller.php` (`/sell*`, `sell.*` names) · `app/Sell/routes/web.php` (`api/sell`, `api/admin/sell/*`) · `routes/admin.php` (`sellers`, `sell-refunds`, settings section `sell`) · `routes/console.php` (2 scheduled commands) · `routes/channels.php` (`$order->seller_id`).

**Cache keys:** `sell:order:`, `sell:refund:order:`, `sell:refund:req:`, `sell:release:`, `sell:upsell:`, `sell:commission_income`.
**Flags/settings:** `sell_enabled`, `sell_commission_bps`, `sell_commission_bps_free`, `sell_commission_bps_pro`, `sell_earnings_hold`; settings section `sell`.
**Commands:** `poisapay:sell-release-earnings`, `poisapay:sell-escalate-refunds`.
**Ledger:** `App\Enums\LedgerAccountType` has a `sell:commission_income` account reference (⚠ money path — §9 risk).
**Tests:** `tests/Feature/Sell/` (26 files) + `P2pDisputeTest` (touches `seller_id`).

---

## 3. Target folder structure

```
app/Shop/
  ShopServiceProvider.php
  Actions/{Coupon,Order,Product,Refund,Review,SalesPage,Merchant}/
  Builder/{Blocks,Contracts}/                 # unchanged engine, App\Shop namespace
  Contracts/
  DTOs/            # ...MerchantApplicationData
  Enums/           # ...MerchantStatus, RefundRequestStatus, etc.
  Events/          # ShopDomainEvent, Merchant*
  Exceptions/      # ShopException
  Http/{Controllers/{Admin},Requests,Resources}/
  Listeners/       # AuditShopEvent
  Models/          # Merchant, MerchantApplication, Product, Order, ...
  Policies/        # MerchantPolicy, ...
  Services/        # MerchantService, ...
  Support/         # ShopAudit
  routes/web.php
```
Consumer frontend controllers stay where they are but are renamed for terminology: `Frontend/SellerController → Frontend/MerchantController`, views `frontend/seller/ → frontend/merchant/`, `admin/sell/ → admin/shop/`.

---

## 4. Rename mapping (authoritative)

**Namespace / dirs:** `App\Sell\*` → `App\Shop\*`; `app/Sell/` → `app/Shop/`.
**Prefixed classes:** `SellServiceProvider→ShopServiceProvider`, `SellException→ShopException`, `SellDomainEvent→ShopDomainEvent`, `AuditSellEvent→AuditShopEvent`, `SellAudit→ShopAudit`.
**Tables:** every `sell_*` → `shop_*` (26). `sell_sellers → shop_merchants`, `sell_seller_applications → shop_merchant_applications` (§5). Columns `seller_id → merchant_id`, `seller_net_amount`, `seller_reply*`, `seller_unread` → `merchant_*`.
**Routes/URLs:** `sell.* → shop.*` names; `/sell → /shop`; `api/sell → api/shop`; admin `sellers → merchants`, `sell-refunds → shop-refunds`.
**Cache:** `sell: → shop:`. **Flags/settings:** `sell_ → shop_`, section `sell → shop`. **Commands:** `poisapay:sell-* → poisapay:shop-*`.
**Ledger:** `sell:commission_income → shop:commission_income` (⚠ see §9).

---

## 5. Seller → Merchant (the invasive decision — VETO POINT)

The brief says "Use **Merchant** instead of Seller. Buyer remains Buyer." This is the single most semantically invasive change and touches: `Seller` model → `Merchant`, `sell_sellers → shop_merchants`, `seller_id → merchant_id` (FK on ~10 tables + `channels.php` + P2P test), `SellerService/Policy/Status/Resource/Application*`, routes `sellers`, views `seller/*`, enum `SellerStatus → MerchantStatus`.

**Recommendation:** do the **full rename** (model, table, columns, code, UI) now while there's no data — half-measures leave a confusing `seller_id` column under a `Merchant` model forever.
⚠ **Caveat:** P2P module also uses "seller/merchant" terminology (`p2p/merchant.blade.php`, `$order->seller_id` in P2P). I will scope the rename to the **Shop domain only** and leave P2P's own `seller_id` columns untouched to avoid cross-module bleed. `channels.php` order-channel check will be updated only for shop orders.

---

## 6. Database plan

### 6.1 What we run (clean rewrite — chosen)
Edit the 12 create/alter migrations in place: table names, `constrained()` targets, `foreignId` column names, index names, the production-readiness-indexes file. Then `php artisan migrate:fresh` (dev) / suite uses `RefreshDatabase`. **Audit improvements applied in the same pass** (§7): UUIDv7 defaults where PKs are UUID, `deleted_at` soft-deletes on `shop_products/shop_orders/shop_sales_pages/shop_merchants`, explicit composite indexes on `(merchant_id, status)` and `(order_id)` hot paths, JSONB (not JSON) columns, `created_at/updated_at` audit columns verified on all tables.

### 6.2 What we deliver but don't run (zero-downtime artifact)
`docs/shop-migration/zero-downtime-rename.sql` + a `Schema::rename()` Laravel migration implementing expand→contract (rename table, keep a compatibility `VIEW sell_x AS SELECT * FROM shop_x` for one deploy, dual-read window, drop view). For the future case where an environment has data.

### 6.3 ERD
`docs/shop-migration/ERD.md` (Mermaid) regenerated with `shop_*` names + `merchant_id` FKs.

---

## 7. Architecture improvements (scoped, honest)
Applied **opportunistically during the rename**, not as a speculative rewrite:
- **Enums:** already native PHP enums — keep; add `->values()` helpers where `whereIn` is used (known enum/`whereIn` gotcha in memory).
- **DTOs / Value Objects:** existing DTOs kept; Money already a value object app-wide.
- **Actions:** already the dominant pattern — keep, no CQRS bus added (would be over-engineering for this codebase; noted as future).
- **Repository pattern:** **not** introduced — Eloquent models + Services are the established convention; adding repositories now would fight the codebase. Documented as a deliberate non-goal.
- **Strong typing / PHP 8.4:** add missing return types and `readonly` promotion where trivially safe during file moves.
- **Service boundaries:** `ShopServiceProvider` remains the single composition root.

> I will **not** silently re-architect money paths. Ledger/refund/earnings logic is moved verbatim (only namespace/table strings change) and re-verified by the existing 26 tests.

---

## 8. Commerce capability matrix (have vs future)
**Have today:** digital downloads, licenses, services (product types), single/multi-product stores, landing pages (builder), funnels + steps, upsells, order bumps, coupons, checkout (single-page), orders + items + events, reviews, order messaging, refund + dispute/escalation workflow, merchant earnings hold/release, ledger + escrow integration, analytics events + daily stats, custom domains (table), SEO (page meta), merchant wallet/payout.
**Future (stub/label only, not built now):** subscriptions, physical shipping (table exists, no rates), gift cards, affiliate system, tax engine, pixel tracking UI, webhooks, public API tokens, Team, Integrations. Nav will show these as clearly-marked "coming soon" rather than dead links.

---

## 9. Risk analysis
| # | Risk | Sev | Mitigation |
|---|---|---|---|
| R1 | Ledger account string `sell:commission_income` renamed → breaks reconciliation if any ledger rows exist | High | No live data confirmed; grep-verify zero rows; rename in same commit as reconciliation config; covered by earnings/refund tests. |
| R2 | Missed reference → runtime `class not found` / route error | Med | Post-rename `grep -rn "Sell\|sell_\|seller"` gate must return only P2P/intended hits; full test suite + route:list. |
| R3 | Seller→Merchant bleeds into P2P module | Med | Scope rename to Shop domain; leave P2P `seller_id` alone (§5). |
| R4 | Migration edited in place breaks an env that already ran it (staging) | Med | Memory: not yet deployed. Confirm staging DB unmigrated before pushing; else use §6.2 rename migration there. |
| R5 | Composer autoload cache / `bootstrap/providers.php` stale | Low | `composer dump-autoload`, clear config/route cache in checklist. |
| R6 | Frontend consumer controllers (outside app/Sell) missed | Low | Explicitly in touch-list (§2); grep gate covers `resources/views`. |

## 10. Zero-downtime plan (for the data-bearing future case)
Expand→contract: (1) deploy code that reads both names via compatibility VIEWs; (2) `Schema::rename` tables in a transaction, create `sell_*` VIEWs aliasing `shop_*`; (3) deploy code using only `shop_*`; (4) drop VIEWs. Full script in `zero-downtime-rename.sql`.

## 11. Rollback plan
- **Code:** the migration is isolated commits on `feature/shop-migration`; rollback = `git revert` range or don't merge.
- **DB (clean rewrite path):** `migrate:fresh` from the pre-rename commit restores `sell_*` schema.
- **DB (data path):** inverse `Schema::rename` migration `down()` + reinstate `sell_*` VIEWs; script provided.

## 12. Production deployment checklist
- [ ] Confirm all shop tables empty in every target env (`SELECT count(*)`).
- [ ] `composer dump-autoload -o`
- [ ] `php artisan migrate:fresh` (fresh env) **or** run §6.2 rename migration (data env).
- [ ] `php artisan config:clear route:clear cache:clear view:clear` + re-cache.
- [ ] `php artisan route:list | grep shop` sane; no `sell.` names remain.
- [ ] Full test suite green (`php artisan test`).
- [ ] Update `bootstrap/providers.php` provider ref (done in code).
- [ ] Update supervisor/cron: command names `poisapay:shop-*` (console.php scheduled — no external cron entries to change).
- [ ] Feature flag `shop_enabled` verified in admin settings.
- [ ] Smoke: `/shop`, `/shop/products`, checkout, refund request, admin merchants + shop-refunds.

## 13. Phased execution plan (each phase = reviewable commit, tests run between)
1. **Scaffold & namespace** — `git mv app/Sell app/Shop`; rewrite `namespace`/`use App\Sell` → `App\Shop`; rename the 5 `Sell*` classes; update `bootstrap/providers.php`; `composer dump-autoload`. *Gate: autoload + `php artisan about`.*
2. **Domain rename Seller→Merchant** — models/enums/services/policies/DTOs/events/actions class + reference rename (code only). *Gate: static grep + boot.*
3. **Database** — rewrite migrations (tables, FKs, columns `seller_id→merchant_id`) + audit improvements; `migrate:fresh`. *Gate: migrate + schema dump.*
4. **Routes / URLs / controllers** — `sell.*→shop.*`, `/sell→/shop`, `api/sell→api/shop`, admin `sellers→merchants`; frontend `SellerController→MerchantController`, views moved. *Gate: `route:list`.*
5. **Frontend terminology & nav IA** — Seller→Merchant labels, dashboard nav to requested IA (existing wired, future stubbed), i18n `__()` strings updated. *Gate: render tests.*
6. **Cache / flags / commands / ledger** — `sell:→shop:`, `sell_→shop_`, `poisapay:sell-*→shop-*`, ledger string. *Gate: earnings/refund tests.*
7. **Tests & docs** — move `tests/Feature/Sell→Shop`, rename test refs; update ERD/README/architecture docs. *Gate: full suite green.*
8. **Final sweep** — grep gate returns only intended P2P hits; `route:list`, `migrate:fresh`, full `php artisan test`.

---
**Approval requested to begin Phase 1.** The Seller→Merchant full rename (§5) and the "don't introduce Repository pattern / CQRS bus" architecture stance (§7) are the two judgment calls I most want you to confirm or veto before I start.
