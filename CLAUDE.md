# CLAUDE.md

Guidance for Claude Code when working in this repository. This is a **living document** —
the permanent knowledge base and engineering rulebook for PoisaPay. Keep it current
(see [Maintaining this file](#maintaining-this-file)).

---

## Project Overview

PoisaPay is a **production-grade fintech super-app**: USD + crypto wallet, P2P USDT
exchange, virtual cards, merchant payments, and a commerce/**Shop** module — all backed
by a single immutable double-entry **ledger**. Money never bypasses the ledger; balances
are *derived* from ledger entries, never mutated directly.

**Status:** active MVP→production hardening engagement (audit → roadmap → implement).
Baseline was ~330 files, 25+ domains, no Docker/CI; Docker + GitHub Actions CI now exist.

### Stack
- **Laravel 12 · PHP 8.4** (composer floor `^8.2`; target 8.4) · **PostgreSQL** · **Redis** · **Horizon**
- Realtime: **Laravel Reverb** (websockets) — used for live P2P marketplace broadcast
- Tests: **Pest 3** (`phpunit.xml`, `tests/Pest.php`) on a real Postgres DB (~159 test files)
- Static analysis: **Larastan/PHPStan**; formatting: **Pint**
- Frontend split by surface (see [Frontend / UI Conventions](#frontend--uiux-conventions))

### Commands
```bash
composer test          # config:clear + php artisan test (Pest)
php artisan test --filter=SomeTest
composer lint          # vendor/bin/pint
composer analyse       # vendor/bin/phpstan analyse
composer ci            # pint --test + phpstan + test  (mirror of CI)
composer dev           # serve + queue + pail + vite, concurrently
php artisan about      # boot / driver sanity check
php artisan route:list # inspect routes
```

---

## Architecture

Domain-Driven, modular. Controllers stay thin; **business logic lives in `Actions/` and
`Services/`, never in controllers or Blade.**

Two families of modules under `app/`:

- **`app/Domain/*`** — the financial core (28 bounded contexts): `Ledger`, `Wallet`,
  `Transaction`, `Transfer`, `Deposit`, `Withdrawal`, `Exchange`, `Ramp`, `Custody`,
  `Chain`, `Treasury`, `Reconciliation`, `Fees`, `Revenue`, `Kyc`, `Compliance`, `Risk`,
  `Security`, `Audit`, `Auth`, `P2p`, `Card`, `Merchant`, `Rewards`, `Notification`,
  `Webhook`, `Analytics`, `Ops`, `Support`, `Transaction`.
- **`app/Shop/*`** — the commerce bounded context (products, sales pages, checkout,
  orders, coupons, refunds, reviews, builder). Registered via `App\Shop\ShopServiceProvider`
  (in `bootstrap/providers.php`).
- **`app/Card/*`** — provider-agnostic card issuing (namespace `App\Card`), distinct from
  `app/Domain/Card`.
- **Landing module** — the public marketing/landing surface (`/`, `/prices`, `/help-center`,
  `/products/{p}`, `/status`, `/pages/{slug}`, `/merchants`) is a **fully isolated module**,
  see [Landing module](#landing-module-isolated).

**Note:** `app/Domain/Merchant` is a *separate financial* bounded context — do **not**
conflate it with the Shop module's merchant/seller model.

### Module anatomy
Within a module: `Actions/` (dominant unit of business logic), `Services/`, `DTOs/`,
`Enums/` (native PHP enums), `Events/` + `Listeners/`, `Models/`, `Policies/`, `Support/`.

### Key money-path collaborators
- `app/Domain/Ledger`: `LedgerService`, `AccountResolver` (pools user accounts by
  canonical asset), `ReverseEntryAction`, `WithdrawProfitAction`, `LedgerReportService`.
- `ExchangeService::execute` is **shared** by Swap / Ramp / CardSettle — user-facing
  policy lives in `SwapPolicy` + `ExecuteSwapAction` wrappers, not in the shared engine.

### Where to look first
- Ledger / money semantics: `app/Domain/Ledger`, `app/Domain/Wallet`, the `Money` VO.
- Commerce: `app/Shop` (+ consumer controllers under `app/Http/Controllers/Frontend`,
  `Funnel`, `Marketing`).
- Feature flags & settings: the settings engine + `config/`.
- Project memory / conventions: `~/.claude/.../memory/MEMORY.md` (loaded each session).

---

## Coding Standards

- **Comments:** do **not** comment code unless genuinely required — no narration of what
  the code already says. Only comment non-obvious *why*: an invariant, a gotcha, a
  money/ledger rule, a workaround. Match the surrounding file's comment density.
- **Terse, lean code:** minimal config, single env credential set, no dead code (user
  preference).
- **Strong typing everywhere:** return types, typed properties, `readonly` where it fits,
  native PHP enums for closed sets.
- Reuse the foundation (below); don't reinvent it.

### Foundation to reuse (don't reinvent)
Settings engine + helpers, feature flags, `ActivityLogger`, RBAC (`config/permissions`),
notifications, CMS, theme. Reuse these for **every** new module.

---

## Folder Structure (`app/`)

```
Card/         provider-agnostic card issuing (App\Card)
Console/      artisan commands (poisapay:*)
Domain/       financial core — 28 bounded contexts (see Architecture)
Enums/        cross-cutting native enums
Events/ Listeners/ Jobs/ Notifications/ Policies/ Providers/
Helpers/ Support/ Utilities/   shared helpers & value objects (Money VO lives here)
Http/         thin controllers, middleware (SetLocale, admin guard), requests
Models/       Eloquent models
Shop/         commerce bounded context (App\Shop, ShopServiceProvider)
```

---

## Database Standards / PostgreSQL Rules

- **PostgreSQL only** (no MySQL/SQLite in prod; tests run on real Postgres). ~69 migrations.
- **Index every foreign-key column.** FK-index + FK constraints are folded into the
  create-migrations (DB-opt work W1/W2/W5).
- Use **`jsonb`**, never `json`.
- Money is stored as **integer smallest units** — never float/decimal for balances.
  (`Assets.withdrawal_min` is a decimal *entry* convenience, not a balance.)
- Balances are **derived from the ledger**, never a stored mutable column.
- Schema snapshot + ERD live under the DB-opt records (`docs/`).

---

## Laravel Conventions

- Actions are the dominant unit of business logic (`SomethingAction::execute()`).
- Feature flags gate all new money-moving behaviour (default-OFF).
- Settings engine drives admin-configurable values (KYC ceilings, risk weights, security,
  OTP) — **but money-state enums stay authoritative** (don't move state machines to settings).
- Two permission config files exist: `config/permission.php` (Spatie package) and
  `config/permissions.php` (app RBAC catalog) — don't confuse them.
- Realtime broadcasting via **Reverb** (`config/reverb.php`, `broadcasting.php`).

---

## API Standards

- **Consumer frontend has NO JSON API** — it is server-rendered Blade (form POST →
  redirect + flash). Do not add JSON endpoints for consumer flows.
- Sanctum is present (`config/sanctum.php`) for token/programmatic surfaces only.
- Provider integrations (chain, card issuers) sit behind **contracts/interfaces** so the
  simulated driver can be swapped for the real one via feature flag.

---

## Event & Queue Standards

- Queues run on **Redis via Horizon** (`config/horizon.php`, `queue.php`).
- Domain behaviour emits `Events/` consumed by `Listeners/`; keep listeners thin.
- Money-moving jobs must be **idempotent** (safe to retry) and **audited**.
- Scheduled work (e.g. hourly earnings release, analytics rollup) runs via artisan
  commands (`poisapay:*`) on the scheduler.

---

## Notification Standards

- Reuse the built-in notification foundation — don't hand-roll delivery.
- User-facing text goes through i18n (`__()`), including notification strings where shown.

---

## Ledger & Financial Rules (non-negotiable)

- **No floats.** Use the app-wide **`Money` value object** (integer smallest units).
- **Every balance movement writes ledger entries.** Balances are derived, never set.
- Money paths are **idempotent and audited**; state transitions are logged.
- New money-moving behaviour ships behind **default-OFF feature flags**.
- **Coin pooling:** one coin = one pooled user balance (RedotPay model). `AccountResolver`
  pools user accounts by canonical asset; treasury stays per-chain; network matters only
  at deposit/withdraw. Swap spread → `FxSpreadIncome`.
- Refunds/reversals go through `ReverseEntryAction` — never delete ledger entries.

---

## Revenue Engine Rules

- Platform profit is realized as ledger income accounts (e.g. swap spread → `FxSpreadIncome`,
  Shop commission → `sell:commission_income`, P2P taker fee → `p2p:fee_income`).
- **The Revenue Wallet has no table** — its balance and every "revenue transaction" are
  *derived* from ledger credits to a single account set: `RevenueService::REVENUE_TYPES`.
  **When you add a new income `LedgerAccountType`, add it in three places or the money is
  invisible/stuck:** `RevenueService::REVENUE_TYPES` (balance/stats/transactions), the draw
  list `ProcessRevenueWithdrawalAction::FEE_ACCOUNTS` (else the balance is un-withdrawable),
  and `RevenueService::feeTypeLabel()` + `RevenueTransactionsController::feeTypeOptions()`
  (human label + filter). These three lists must stay in sync. (P2P fee income was posted
  to the ledger since the P2P build but omitted from all four → never surfaced as revenue;
  now included.)
- Revenue withdrawals go through `RequestRevenueWithdrawalAction` →
  `ProcessRevenueWithdrawalAction` (`app/Domain/Revenue`) and `WithdrawProfitAction` —
  profit leaves via the ledger like any other money path.
- **Withdrawal fee model:** platform % split coin/fiat (central settings, legacy fallback).
  Per-network flat fee was **removed** — crypto = % only, platform absorbs gas.
- Analytics: `/admin/analytics` uses declarative Report → generic renderer, 11 USD-valued
  ledger reports, 1h cache + hourly rollup table, Chart.js.

---

## Wallet Rules

- USD + crypto wallets; balances derived from ledger.
- Frontend display currency via `App\Support\BaseCurrency` (user choice → admin default);
  a currency only works if its fiat asset exists in `RegistrySeeder`.
- Swaps trade against house `TradingInventory` (starts at 0, no seeder). Empty inventory →
  "Insufficient X liquidity" and Confirm silently fails. Fund via
  `php artisan poisapay:inject-inventory {assetId} {amount}` (or `DealerInventorySeeder`).

---

## Shop Rules (commerce module)

- Terminology target: **Merchant / Buyer / Shop / Checkout** — but code still uses
  `Seller`/`seller_id` (rename pending; see [Shop rename](#shop-module--in-progress-rename)).
- Does **not** sell courses. `ProductType`: Digital / Physical / License / Membership /
  Subscription / Service / Bundle (no Course case; "Students" module dropped).
- **Earnings hold→release:** flag `sell_earnings_hold`, hourly release command.
- **Refunds:** `RefundOrder` reverses the ledger and revokes access (full money-path).
- **Digital file delivery:** the seller's product `file` (digital/license) is stored on a
  **private** disk (`config/shop.php → files.disk`) as a checksummed, versioned
  `ProductFile` via `ProductFileService` (new upload supersedes the current version),
  `scan_status = Pending`. A queued `ScanProductFile` runs a `Contracts\FileScanner`
  (`simulated` = EICAR-only default | `clamav` for prod, `files.scanner`) → `Clean`
  (deliverable) or `Infected` (quarantined: dropped as current, never served); an
  inconclusive scan throws → retry. `FileScanStatus` enum is cast on `ProductFile`.
  `PurchasesController::download` serves **only the current Clean file**, enforces the
  count/expiry `Download` grant issued by `PlaceOrder::grantDigitalDelivery`, and excludes
  refunded orders; buyers see "Preparing your download…" while a file scans (was a bare
  "File pending"). **Wire ClamAV before trusting uploads in prod.**
- **Funnel offers:** each sales page carries a server-authoritative **order bump**
  (pre-checkout, same balanced order) + **1-click upsell** (post-purchase, child order via
  `parent_order_id`) — columns on `shop_sales_pages`, edited in the builder **Settings** tab
  (`PageBuilderController::applyOffers`). Offer products **must share the front product's
  currency** and can't be the page's own product (checkout settles both in one entry) — the
  builder offer dropdowns are pre-filtered to eligible products (`$offerProducts`), and an
  ineligible POST is **rejected without wiping** the stored offer (flashes a `warning`).
  `/shop/funnels` (`SellerController::funnels`) is the real overview: every published page's
  offers + live 30-day take-rate/extra-revenue from orders, deep-linking to the builder via
  `?tab=settings` (read in `resources/js/builder/index.js`). No downsell backend exists yet.
- **Custom domains** (`shop_domains`, one per sales page, flag `shop_custom_domains`, default
  OFF, `config/shop.php`): merchant connects a domain → **DNS verify (TXT ownership +
  CNAME routing)** → **auto-SSL** → route domain to `/p/{slug}`. **CNAME-only** (subdomain
  recommended; apex needs CNAME-flattening/ALIAS). Hosts are normalized (`Support\DomainName`:
  scheme/path/port/case stripped, IDN→punycode, leading `www.` stripped → apex; www served as
  alias). `Support\PlatformHost` blocks connecting platform/reserved hosts. Verify/SSL run in
  queued Actions (`Actions\Domain\*` + `Jobs\*`, auto-retry to a ceiling); DNS/SSL sit behind
  `Contracts\DnsResolver` + `Contracts\SslProvisioner` (SSL driver `simulated`|`acme` — **wire
  `acme` before enabling in prod**). Routing = global middleware `ResolveShopDomain` rewrites
  the custom host onto the funnel routes (zero route dup); `DomainResolver` caches host→page
  (pos+neg), invalidated on any `Domain` write. Merchant UI `/shop/domains`, operator UI
  `/admin/shop-domains`. Events `Domain{Created,Verified,VerificationFailed,Removed}` +
  `Ssl{Issued,Failed}` (auto-audited `shop.domain.*`). See `docs/shop-audit/02-custom-domains.md`.
- **Media Library** (`shop_media`, **one table** — no folders/usage tables by design,
  `config/media.php`): merchant-scoped image library for the **page builder**. Replaces every
  manual image-URL input with a **Choose Image / Choose Images** picker (`Field::image()` now
  renders the picker in `builder-field.blade.php`; the background `bgImage` control too), so
  **every** section that uses an image field is upgraded centrally — no per-block edits. Model
  `App\Shop\Models\ShopMedia` (soft-deletes; the storage **disk is config-resolved via
  `StorageDisk::media()`, never a column**). Four focused services: `MediaUploadService`
  (dedup by sha256 `checksum`, permanent path `media/{seller}/{Ym}/…`, sync original store +
  queued variants, replace-in-place keeping the URL, rename/alt), `MediaVariantService`
  (intervention/image GD: thumb/medium/large **downscale-only** + a **WebP sibling** each,
  metadata stripped, **SVG sanitised** before store), `MediaDeleteService` (soft delete →
  restore → purge), `MediaUrlService` (URL→media resolve, cached; emits responsive
  `<picture>`+`srcset`+WebP+lazy via the `<x-builder.image>` component). Variants generated on
  the queue (`ProcessMediaImage`); the original is usable immediately. JSON API under
  `/shop/media` (`MediaController`, `web`+`auth`, consistent with the builder's other JSON
  endpoints) + standalone manager page `/shop/media`; picker UI in `resources/js/builder/media.js`
  (drag-drop, multi-upload + progress, infinite scroll, search, sort, rename, replace, delete,
  restore, copy URL, multi-select) merged into `pageBuilder` via `mediaMixin`. **Backward-compat:
  image props still store a URL string** — legacy/external URLs render as a plain lazy `<img>`,
  so existing pages are byte-identical; the picker is just a nicer way to produce that URL.
  **Gallery block** enhanced: grid/masonry/carousel layouts, lightbox (zoom), captions, category
  filter tabs, load-more. `intervention/image ^3` (GD) is the one added dependency.
- **Tracking & Pixels** (`app/Shop/Tracking/*`, see its `README.md`): **per-sales-page**
  marketing pixels — Meta / TikTok / GA4 / GTM — stored in `shop_sales_pages.tracking`
  (jsonb). **Adapter pattern:** `TrackingManager` composes providers (registered in
  `ShopServiceProvider`), each implementing `Contracts\TrackingProvider` — it declares
  `fields()` (single source of truth for the builder UI **and** validation) and emits a
  `headScript()` that self-registers `{key,init,fire(type,payload)}` into
  `window.__ppTrackers`. One consent-gated runtime fans a single provider-agnostic
  `TrackingEvent`/`TrackingEventType` (14 cases) out to all providers → **adding a network
  = 1 new adapter + 1 registration line**, nothing else changes. Injected via
  `layouts/sales.blade.php` (`:tracking`+`:trackingEvents`); `PublicSalesController` fires
  PageView/ViewContent/InitiateCheckout/Purchase server-side; interaction events are
  declarative `data-pp-track="cta_click"` + `window.ppTrack()`. Builder **Settings →
  "Tracking & Pixels"** tab (generated from `providers()`) with toggle/status/validation/
  **test-event** (`/shop/sales-pages/{slug}/tracking-test`) + privacy (cookies / wait-for-
  consent / anonymize-IP). **Meta server-side (CAPI)** is opt-in per page via an access
  token: **Purchase-only, queued** (`OrderPlaced` → `SendMetaCapiPurchaseEvent` →
  `Jobs\SendMetaCapiPurchase`), **deduped** with the browser pixel via `event_id = order id`,
  **PII SHA-256 hashed**; driver behind `Contracts\MetaCapiClient` (`simulated`|`http`,
  `config/shop.php → tracking.meta_capi`, **wire `http` before prod**). Zero markup when a
  page has no tracking configured.
- **Sales-page builder v2:** schema-driven block-tree (`app/Shop/Builder/*`), one renderer
  for public + editor iframe, draft/publish + revisions. Adding a block = 1 `BlockLibrary`
  entry + 1 partial. v1→v2 auto-migration exists.
  - **Section variants:** a section can offer multiple full **layout** variants (not just
    colours). Add `Field::variant(['a'=>'A', …], 'a')` as the first content field → it renders a
    segmented "Layout" picker in the panel (`variant` field type in `builder-field.blade.php`),
    writes `props.variant`, and the partial `@switch($props['variant'])`es its whole markup. The
    **first option must equal the current design** so existing pages stay byte-identical (defaults
    fill `variant`). Marquee sections done (each + a `dark` toggle): **hero** (centered/split/
    minimal/gradient/showcase), **features** (cards/iconTop/iconLeft/alternating), **cta-banner**
    (gradient/simple/dark/card/split), **faq** (accordion/cards/split), **pricing** (cards/minimal/
    compact + monthly/yearly toggle + `cols`), **testimonials** (cards/carousel/minimal/single).
    Rolling the pattern to more sections is mechanical.
  - **Fatal-safe icon/glyph components:** `<x-builder.icon name="bolt">` (curated Heroicon
    whitelist → sparkle fallback, so a hand-typed icon can never 500 a page) and
    `<x-builder.social-icon platform="instagram">` (brand SVGs, globe fallback). Repeater
    sub-fields now also support `select` (per-row icon/platform pickers).
  - **Universal section controls** (Phase 1) live in `StyleCompiler` — every block gets the
    full control set for free via scoped `#id` CSS, no partial edits: per-side padding/margin,
    width/maxWidth/minHeight, border+radius, shadow presets (sm/md/lg/xl), opacity, z-index,
    layered background (solid + image + overlay + gradient + parallax), device-independent
    entrance **animation** (fade/up/down/left/right/zoom + duration/delay, load-triggered
    keyframes) and **custom CSS** (scoped, declarations-only). Node-level **custom class** +
    **anchor id** are injected onto the block root centrally in `Renderer::chrome()`.
  - **Style buckets are `base`/`tablet`/`mobile`** — the editor's **desktop** device maps to
    **`base`** (via `styleBucket` in `resources/js/builder/index.js`). Writing desktop edits to
    a `style.desktop` bucket is a bug — the compiler never reads it.
  - **Security:** all editor-supplied style/CSS strings are scrubbed (`{}<>;` stripped, image
    URLs restricted to http(s)/root-relative/`data:image`) so a hostile document can't break
    out of its scoped rule. `DocumentSanitizer` also caps `meta` free-text (className/anchor).
  - **Section library** (Phase 2) = **48 blocks** across 7 categories incl. a new **Forms**
    category. Added: pricing, offer-banner, cta-banner, sticky-cta, comparison, feature-tabs,
    accordion, before-after, timeline, icon-list/checklist, team, story, progress, gallery,
    carousel, quote, video-testimonials, case-studies, announcement-bar, lead-capture, contact.
    Form blocks post to a seller-supplied external `action` URL (no internal lead endpoint yet).
    The shared `_buy` partial takes an optional `style` param for white-on-accent buttons.
    A smoke test renders every registered block from its defaults (catches bad partials/icons).
  - **Header + Footer builders** (Phase 3) = **49 blocks**. The `header` block gained layout
    presets (left/center/minimal), a transparent-overlay mode, a mobile drawer, and a secondary
    link. The `footer` block is deliberately **minimal** — a single centered column: brand,
    tagline, a flat `links[]` list, `socialLinks[]`, copyright, `darkMode` (multi-column layout,
    newsletter, and payment badges were removed). Prop keys follow the builder's **camelCase**
    convention (`brandName`/`links`/`socialLinks`/`darkMode`); legacy footer props
    (`brand`/`columns`/`social`/`href`/`dark`) are still read as emptiness-based fallbacks in the
    partial (schema defaults for `links`/`socialLinks` are empty so an old key falls through), and
    `DocumentSanitizer` preserves unknown props on save — so already-published footers keep
    rendering. **Chrome hand-off:** when the built page
    contains a `header`/`footer` block, `PublicSalesController::pageViewModel` sets
    `hasHeader`/`hasFooter` and `funnel/sales.blade.php` suppresses its default chrome
    header/footer — so the seller's block becomes the page's real one (no double header/footer).
  - **Editor UX** (Phase 4, `resources/js/builder/index.js`): **inline editing** (double-click a
    canvas text → contentEditable → writes back to props; partials mark editable text with
    `data-edit="propKey"` / `data-edit-rich="propKey"` — currently heading/text/hero/cta),
    **multi-select** (`selectedIds`; shift/⌘-click on canvas + layers), **copy/cut/paste**
    across pages (localStorage `pp:clipboard`), expanded **shortcuts** (⌘Z/⇧Z, ⌘C/X/V, ⌘D,
    ⌘A, ⌫, Esc, ?) with a help overlay. `refreshPreview` is suppressed while inline-editing so
    it can't rip out the contentEditable. **Style edits are CSS-only** — `setStyle`/`setBase`
    keys all compile to scoped CSS (no DOM change), so `refreshPreview({styleOnly:true})` swaps
    just the `<style x-ref="previewStyle">` and skips the `innerHTML` repaint: instant,
    flicker-free, and selection/scroll/focus stay put (a full repaint stays for prop/meta/
    structure edits, which do change the HTML). Adding a block scrolls it into view (`_scrollTo`).
  - **Entrance animations are now scroll-triggered** (was load-triggered): `StyleCompiler`
    emits the hidden state under `html.pp-anim` + reveal under `.pp-in`; `Renderer` marks
    animated nodes with `data-anim`; `frontend.js` adds `pp-anim` (JS-present gate → no-JS shows
    content) and an `IntersectionObserver` that reveals on scroll, honouring
    `prefers-reduced-motion`. The builder canvas shows the final state (no reveal while editing).
  - **Product grid** = **50 blocks**. `product-grid` (Commerce) renders the store's published
    catalogue as cards (image, name, price/compare, summary, link to each product's sales page).
    `RenderContext::catalogFor()` loads the seller's published products (limit 24) into
    `RenderContext->catalog`. Products gained a real **`image_url`** column (migration +
    `ProductData->imageUrl` + Create/UpdateProduct + product-form "Cover image URL"). NOTE:
    `RenderContext` is legacy Eloquent-typing debt (Larastan can't resolve this project's model
    props) — the catalogue code adds ~3 baseline-class PHPStan entries; regenerate
    `phpstan-baseline.neon` on commit (already needed for the unrelated in-tree Domain feature).
  - **Template packs** (Phase 5): 15 premium starters in `app/Shop/Builder/Templates/`
    (`TemplateLibrary` + a terse `Tpl` node DSL). Each is a full v2 document built from the
    50 blocks, overriding only tone-setting props (rest fall back to schema defaults) + a brand
    colour. Applied in the editor via a gallery modal → `POST shop.sales-page.template`
    (`PageBuilderController::applyTemplate`) which sanitises + writes the draft and returns it;
    the client swaps its in-memory doc (undoable). Adding a template = one entry in
    `TemplateLibrary::all()`. A test builds every template and asserts it only uses registered
    blocks.

### Shop module — in-progress rename
`feature/shop-migration` renames the former **Sell** module to **Shop**
(`docs/shop-migration/MIGRATION-PLAN.md`), in phases:
- ✅ **Phase 1** — namespace/dir `App\Sell` → `App\Shop`, provider → `ShopServiceProvider`.
- ⏳ **Pending** — DB tables `sell_*` → `shop_*`, routes/URLs (`sell.*`, `/sell`), semantic
  **Seller → Merchant** rename, cache keys `sell:*`, flags/settings `sell_*`, commands
  `poisapay:sell-*`, ledger account `sell:commission_income`.

Until later phases land you will still see `sell_*` tables, `sell.*` routes, and
`Seller`/`seller_id` naming.

---

## Landing module (isolated)

The public marketing/landing surface is a **self-contained module**, deliberately isolated so
nothing in it touches (or is touched by) Shop/Wallet/P2P/Dashboard/Admin/Checkout/API/Auth.

- **Boundaries:** `App\Providers\LandingServiceProvider` registers the `landing::` view
  namespace, `routes/landing.php` (its own file, wrapped in the `web` middleware group), and
  `config/landing.php` (product catalog + nav/footer link maps). Controllers live only in
  `App\Http\Controllers\Landing\*` (Home/Prices/Rates/Status/Product/Faq/Page). Views/chrome
  live under `resources/landing/views/**`; static media under `public/landing/**`.
- **Assets:** dedicated Vite entries `resources/landing/css/landing.css` +
  `resources/landing/js/landing.js` (own Tailwind build scoped via `@source '../views/**'`,
  the `.lp-*` design system, Inter, **its own bundled Alpine**). The master layout
  (`<x-landing::layouts.master>`) loads **only** the landing bundle — never `app.css`/
  `frontend.js` — so landing pages ship zero app/admin CSS/JS and vice-versa.
- **Namespacing:** every landing style is `.lp-*` (or nested under `.lp-wrapper`); no bare/
  global selectors. Components are namespaced Blade: `<x-landing::navbar>`, `<x-landing::footer>`,
  `<x-landing::converter>`, `<x-landing::wallet-card>`, `<x-landing::asset-icon>` →
  `landing::components.*`. JS is ES-module + `window.Landing` (only Alpine on window, its
  convention). The legacy `.pp-*/.glass/.reveal/poisa-landing` set was renamed 1:1 → `.lp-*`.
- **Stable route names:** the marketing route **names** are unchanged (`home`, `help-center`,
  `page.show`, `marketing.prices`, `marketing.rates`, `status`, `products.show`, `merchants`) —
  Auth/Admin/app footer+topbar link to them. Routes moved out of `web.php` into `routes/landing.php`.
- **Left untouched (shared, still used by Auth + 404):** `components/layouts/marketing.blade.php`
  (`<x-layouts.marketing>` → `errors/404`), `components/marketing/{nav,footer,converter,wallet-card}`
  (`<x-marketing.*>` → `guest.blade.php` auth pages), `partials/marketing-styles.blade.php`. These
  keep the OLD `.pp-*` system; landing has its own copy. Two chrome sets is the intentional price of
  isolation. NOTE: `components/marketing/{converter,wallet-card}` are now orphaned (only the deleted
  marketing home used them) — safe to prune later; kept to avoid touching auth-adjacent shared code.
- Tests: `tests/Feature/Landing/LandingRenderTest.php` (renders each page + asserts the isolated
  bundle loads and app bundles do NOT).

---

## P2P Rules

- Binance-style USDT marketplace, flag-gated **`p2p_enabled`** (`config/p2p.php`).
- **Escrow = card-hold pattern on the ledger** (funds held, not moved out of ledger).
- Phases 1–7 done; enterprise-hardening P0.1–P0.3 + P1.1–P1.6 done: ad-guard enforcement,
  reviews, auto-pause, marketplace sorts/filters, live broadcast (Reverb), ad
  duplicate/soft-delete/auto-reply, favourites + blocks, default payout, dispute
  notes + resolution-notify. Full P2P suite: 99 green.
- ⚠️ **P2P is excluded from PHPStan** (larastan crashes on it) — re-enabling is P1.7,
  pending. See [Technical Debt](#technical-debt).

---

## Frontend / UI/UX Conventions

Three distinct surfaces — **match the one you're editing**:

- **Consumer frontend** (incl. Shop): server-rendered **Blade MVC** — form `POST` →
  redirect + flash. **No JSON API, no Livewire.** Standalone **Alpine** for light UI only.
  Vite entry `frontend.js`. Premium blue/slate/Inter theme via `body.theme-minimal`
  (CSS-var overrides; theme-driven `x-ui.*` kit: combobox, drawer, modal sizes, skeleton,
  dismissible alert). `FlowAnalytics` is a shared service.
- **Admin**: fully migrated **off** Livewire to controllers + Blade, separate `admin`
  guard, standalone Alpine via `admin.js`. **DollarHub** design (gold frontend / navy
  admin), light-only. **No Filament.** In admin Blade/Livewire always use `auth('admin')`.
  Nav is grouped into workflow sections (Stripe/Wise-style) with `AdminAttention` badges,
  `Route::has` guard; `AdminNavSmokeTest` guards dead links.
- **Auth**: Livewire. Livewire route classes must exist *before* the route is registered.

**Modals:** use the restyled `x-ui.modal` (mercury.com look), opened via
`$dispatch('open-modal')`. **Never** native `confirm()`.

**i18n:** all Blade UI text wrapped in `__('English')`; catalogs `en.json` / `bn.json`
(bn falls back to en), `SetLocale` middleware (en/bn). PHP-side strings are a follow-up.

**Design engagement:** active super-app UX redesign, phased (audit→IA→design system→
wireframes→hi-fi→code) in `docs/design/`. Decided: enhance-in-place → PWA (not rewrite);
adds bottom tab bar + global search + dark mode + ≤3-tap withdraw + guest checkout.

---

## Security Checklist

- All money-moving behaviour behind **default-OFF feature flags** until verified.
- Money paths **idempotent + audited**; every state transition logged (`ActivityLogger`).
- Admin uses a **separate guard** (`auth('admin')`) — never mix with the user guard.
- Custody keys: env-seeded signer, swappable for KMS/HSM; real TRON custody uses BIP32/
  secp256k1 HD derivation gated by `custody_simulated`.
- Custody hardening landed (reconciliation, real TRON+EVM sweeps, gas engine,
  verify-then-release) — all money paths flag-gated.
- KYC withdrawal ceiling is **enforced** (was previously dead); tiers/limits from settings.
- Run `/security-review` on pending changes before shipping money-path work.

---

## Performance Checklist

- Index every FK column; use `jsonb`; avoid N+1 (eager-load in Actions/Services).
- Analytics/report reads are cached (1h) + backed by an hourly rollup table — don't
  compute heavy reports inline per request.
- Queue heavy/slow work to Horizon; keep request path lean.
- `enum whereIn` gotcha: pass backing values, not enum instances, in query builder `whereIn`.

---

## Testing Standards

- **Pest 3** on a **real Postgres** DB (`poisapay_test`) via `RefreshDatabase`.
- **Never manually migrate or seed `poisapay_test`** — `RefreshDatabase` owns it. If the
  suite fails with "relation does not exist", reset with
  `DROP SCHEMA public CASCADE; CREATE SCHEMA public;` (as pg user `rakibhossen`), re-run.
- **Seeders that run in `DatabaseSeeder` must be faker-free** — they run on staging/prod
  with `--no-dev` (no faker → "Call to undefined function fake()"). Use explicit
  `create()`/`updateOrCreate` keyed for idempotency, never `Model::factory()`/`fake()`.
- Run `composer ci` locally to mirror CI (pint --test + phpstan + test).

---

## Deployment Notes

- **Docker** present: `Dockerfile`, `docker-compose.yml`, `docker/`, `.dockerignore`.
- **CI**: GitHub Actions `.github/workflows/ci.yml`.
- **Staging + prod server:** Ubuntu 24.04 @ `138.252.198.229` (ssh `rakib`):
  PHP 8.4 / nginx / pg16 / redis / supervisor; domains `pay.` + `pstaging.rakibhossen.com`.
  Provisioned; deploy pipeline is the remaining step.
- Seeders in `DatabaseSeeder` run on staging/prod with `--no-dev` (faker-free rule above).

---

## Known Limitations

- Consumer surface is Blade-only — no JSON API for third-party consumer integrations yet.
- Chain layer is **simulated** by default; real custody is flag-gated (`custody_simulated`,
  `custody_*`). Anvil E2E (`php artisan poisapay:anvil-e2e`) exercises real local EVM.
- Shop rename is mid-flight — `sell_*` names persist across DB/routes/flags.
- i18n covers Blade UI text only; PHP-side strings not yet extracted.

---

## Technical Debt

| Item | Recommended action |
|------|--------------------|
| **P2P excluded from PHPStan** (larastan crash) | P1.7: isolate the crash, re-enable analysis for `app/Domain/P2p`. |
| Shop rename incomplete | Land pending phases: `sell_*`→`shop_*` tables, routes, Seller→Merchant, flags, commands, ledger account. |
| PHP-side i18n strings unextracted | Extract to catalogs after Blade pass. |
| Dual permission configs (`permission.php` vs `permissions.php`) | Document/consolidate to avoid confusion. |
| Shop custom-domain SSL uses the **`simulated`** driver (no real cert) | Wire the `acme`/edge integration + the `cname_target` edge (TLS termination, Host forwarding) before enabling `shop_custom_domains` in prod. |

When you find duplicate code, inconsistent naming, outdated architecture, missing docs,
tech debt, or perf/security concerns: **fix if safe**, otherwise **record here** with a
recommended action.

---

## Lessons Learned / Best Practices

- Keep controllers thin; put logic in Actions/Services — makes money paths testable + idempotent.
- Gate every money change behind a default-OFF flag; verify, then flip.
- Derive balances from the ledger; never trust or store a mutable balance column.
- Reuse the foundation (settings/flags/RBAC/notifications/CMS/theme) instead of reinventing.
- Match the frontend surface you're in (consumer Blade vs admin Blade vs auth Livewire).
- Seeders that ship to prod must be faker-free and idempotent.

---

## Decision Log

- **Enhance-in-place → PWA** (not a frontend rewrite) for the redesign engagement.
- **No Filament**; admin migrated fully off Livewire to controllers + Blade (separate guard).
- **Consumer frontend = Blade MVC**, no JSON API / no Livewire.
- **Coin pooling** (RedotPay model): one pooled balance per coin, treasury per-chain.
- **Sell → Shop** rename; **keeps "Seller"** in code because "Merchant" is reserved for the
  `app/Domain/Merchant` financial context.
- **Shop sells no courses**; "Students" module dropped.
- **Per-network flat withdrawal fee removed** — crypto = % only, platform absorbs gas.
- **Card issuing** provider-agnostic (`app/Card`, `config/card.php`), Marqeta via Gateway JIT.
- Chart.js (not ApexCharts) for analytics.
- **Shop custom domains = CNAME-only** (TXT ownership + CNAME routing); subdomain
  recommended, apex via CNAME-flattening/ALIAS. Routing via global middleware that rewrites
  the custom host onto the funnel routes (no route duplication). SSL provider-agnostic
  (`simulated`|`acme`), default OFF flag `shop_custom_domains`.

---

## Future Improvements

- Complete Shop DB/route/flag rename and Seller→Merchant semantic pass.
- Re-enable PHPStan for P2P.
- Finish deploy pipeline to staging/prod; wire CI → deploy.
- Extract PHP-side i18n strings.
- Progress the frontend redesign (hi-fi → code) and PWA work.

---

## Maintaining this file

After **every** completed task, decide whether this file needs updating — do **not** wait
for permission. Update it when you discover a new business rule, coding convention,
architecture decision, reusable pattern, DB/API/queue/notification/security/performance
standard, ledger/revenue/payment rule, migration strategy, or testing pattern.

Rules for edits: **merge intelligently, never overwrite good knowledge, eliminate
duplicates, keep the structure clean, improve sections rather than rewriting.**

End each task with a short report:
- CLAUDE.md Updated: Yes/No
- Sections Updated
- New Rules Added
- Technical Debt Added
- Future Recommendations
