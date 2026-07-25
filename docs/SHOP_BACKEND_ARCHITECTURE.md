# Sell Module — Backend Architecture (Phase 1: Foundation)

> Production-grade, isolated commerce module inside PoisaHub. Designed for
> 10M products · 200M orders · 500M order items · 100M customers. Postgres 16.
> All tables prefixed `sell_`. All code under `app/Shop/`. Money stays in the Ledger.

## 1. Five-year analysis (why this schema survives)
- **UUID v7-style ids everywhere** (time-ordered) → index locality on inserts, no
  hotspotting, shard-friendly (shard key = `seller_id` for tenant data, `id` for
  global). We store UUID as native `uuid` (16 bytes), never char(36).
- **Money never lives here.** Orders/items store integer **minor units + `asset_id`**
  as an immutable *record* of what the Ledger already moved. The Ledger is the
  single source of truth; Sell references `ledger_entry_id`. Zero financial
  duplication, zero re-accounting.
- **Append-only hot paths.** Orders, order_items, order_events, analytics_events are
  insert-heavy and never updated in bulk → low lock contention. Status changes are
  small single-row updates; history is an append to `shop_order_events`.
- **Read/write split by table shape.** Transactional tables stay narrow; heavy
  flexible config (page sections, theme, funnel graph, product attributes) lives in
  `jsonb` so the hot row stays small and cacheable. Dashboards read **aggregate
  tables**, never transactional ones.
- **Extensible without migrations.** Product types, order statuses, funnel steps are
  string-keyed + backed by PHP enums implementing a contract — adding a type is a
  code change, not a schema change. `attributes`/`config` jsonb absorb new fields.

## 2. Module layout (isolated)
```
app/Shop/
  Enums/            ProductType, ProductStatus, OrderStatus (state machine), FunnelStepType, …
  Models/           Seller, Product, ProductVariant, SalesPage, Order, OrderItem, Customer, …
  Actions/          single-purpose write use-cases (CreateProduct, PlaceOrder, MarkShipped…)
  Services/         orchestration + reads (CheckoutService, FulfilmentService, PricingService)
  DTOs/  ValueObjects/
  Policies/         per-model authorization (a seller only ever touches their own rows)
  Http/  (Controllers, Requests, Resources, Api/V1)
  Events/  Listeners/  Jobs/
  Repositories/     only where a query is complex/reused (SalesPageReadRepository, cache-first)
  Support/Cache/    tagged, versioned Redis cache-first layer + auto-invalidation
  ShopServiceProvider.php   (binds, registers events, morph map, policies)
```
Sell **consumes** core services via their public contracts (Ledger, Wallet, Kyc,
Withdrawal, Notification, Risk) — never their tables. Coupling is via **domain
events** only. Everything is behind the `sell_enabled` feature flag.

## 3. Index strategy (designed before models)
Principles: index for the **actual access patterns**, composite order = equality → sort,
partial indexes to keep hot indexes tiny, covering indexes for list endpoints.
- Tenant lists: `(seller_id, status, created_at desc)` composite on products/orders.
- Buyer lists: `(customer_id, created_at desc)` on orders.
- Public lookup: **unique** `slug` on sales pages, **unique** `host` on domains.
- Idempotency: **unique** `idempotency_key` on orders (prevents duplicate orders).
- Partial: `WHERE status='published'` on sales pages; `WHERE deleted_at IS NULL`.
- Search: generated `tsvector` column + **GIN** index (products, customers) — Postgres
  FTS now, Meilisearch/OpenSearch later via the same `SearchService` seam.
- Analytics: `(sales_page_id, occurred_at)` BRIN-friendly append; aggregates keyed by day.
- Every FK is indexed; every `_id` used in a join has an index.

## 4. Cache strategy (Redis, mandatory, automatic)
- **Cache-first public reads**: sales page, product, theme, sections, SEO, seller
  profile → assembled once into a single `SalesPageView` DTO, cached under a
  **tagged** key `sell:page:{id}` + a **version** stamp `sell:page:{id}:v`.
- **Automatic invalidation**: model `saved/deleted` events bump the version stamp
  (O(1)); no key scanning, no manual clears. Domain events (`SalesPagePublished`,
  `ProductUpdated`) also trigger **background cache warming** jobs.
- **Hot/cold**: hot object in Redis (short TTL + version), cold fallback rebuilds
  from DB and re-warms. Public HTML is edge/CDN cacheable keyed by page version.
- Target public **TTFB < 100ms**: 0–1 Redis GET, no DB on cache hit.

## 5. Event-driven (no tight coupling)
`ProductCreated/Updated/Published`, `OrderPlaced/Paid/Refunded`, `PaymentSucceeded`,
`DownloadGenerated`, `LicenseIssued`, `ShipmentUpdated`, `ReviewCreated`,
`DomainVerified`, `SalesPagePublished` → listeners fan out to queues:
analytics rollup, emails/notifications, cache warm, search index, download grant,
domain verify + SSL, image optimize. Ledger integration is a listener on
`OrderPlaced` that calls the Ledger service and writes back `ledger_entry_id`.

## 6. Security
UUID ids (no enumeration) + policy on every read/write; `$fillable` allow-lists +
Form Requests (no mass assignment); **DB-level** guards for correctness: unique
`idempotency_key` (no duplicate orders), variant stock decrement under
`SELECT … FOR UPDATE` / atomic `UPDATE … WHERE stock >= qty` (no oversell),
unique `(order_id, product_id)` on reviews (verified-buyer, one review). Signed +
expiring download URLs with per-grant counters. Rate limits on checkout/download/
coupon/login. Audit via the core Audit module on every state change.

## 7. Deliverables status
**Phase 1 (this commit):** module skeleton, full `sell_` schema + indexes (verified
migrating), core enums (extensible state machine), core Eloquent models +
relationships + casts, `sell_enabled` flag. **Next phases** (feature by feature,
each with Action → Service → Event → Job → Policy → Request → Resource → Cache →
tests): Seller onboarding, Catalog+variants+inventory, Sales-page builder+cache,
Checkout+Ledger money-path, Fulfilment (downloads/licenses/shipping), Funnels,
Messaging, Coupons, Analytics pipeline, Domains+SSL, Admin, REST API v1.
