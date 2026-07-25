# CLAUDE.md

Guidance for Claude Code when working in this repository.

## What PoisaPay is

A production-grade fintech platform: USD + crypto wallet, P2P exchange, virtual cards,
merchant payments, and a commerce/**Shop** module — all backed by a single immutable
double-entry **ledger**. Money never bypasses the ledger; balances are *derived* from
ledger entries, never mutated directly.

## Stack

- **Laravel 12 · PHP 8.4** (composer floor is `^8.2`; target 8.4) · **PostgreSQL** · Redis · Horizon
- Tests: **Pest 3** (`phpunit.xml`, `tests/Pest.php`) on a real Postgres DB
- Frontend is split by surface — see "Frontend conventions" below
- Static analysis: **Larastan/PHPStan**; formatting: **Pint**

## Commands

```bash
composer test          # php artisan test  (Pest)
php artisan test --filter=SomeTest
composer lint          # vendor/bin/pint
composer analyse       # vendor/bin/phpstan analyse
php artisan about      # boot / driver sanity check
php artisan route:list # inspect routes
```

### Test database — important
Tests run against a real Postgres DB (`poisapay_test`) via `RefreshDatabase`.
**Never manually migrate or seed `poisapay_test`** — `RefreshDatabase` owns it.
If the suite fails with "relation does not exist", reset with
`DROP SCHEMA public CASCADE; CREATE SCHEMA public;` (as pg user `rakibhossen`), then re-run.

## Architecture

Domain-Driven, modular. Two families of modules under `app/`:

- **`app/Domain/*`** — the financial core: `Ledger`, `Wallet`, `Transaction`, `Transfer`,
  `Deposit`, `Withdrawal`, `Exchange`, `Ramp`, `Custody`, `Chain`, `Treasury`,
  `Reconciliation`, `Fees`, `Revenue`, `Kyc`, `Compliance`, `Risk`, `Security`, `Audit`,
  `P2p`, `Card`, `Merchant`, `Rewards`, `Notification`, `Webhook`, `Ops`, `Support`, …
- **`app/Shop/*`** — the commerce bounded context (products, sales pages, checkout, orders,
  coupons, refunds, reviews, builder). Registered via `App\Shop\ShopServiceProvider`
  (in `bootstrap/providers.php`). Also `app/Card/*` (provider-agnostic card issuing).

Within a module: `Actions/` (the dominant unit of business logic), `Services/`, `DTOs/`,
`Enums/` (native PHP enums), `Events/` + `Listeners/`, `Models/`, `Policies/`, `Support/`.
Controllers stay thin; business logic lives in Actions/Services, never in controllers or Blade.

### Money & ledger rules (non-negotiable)
- No floats. Use the app-wide **`Money` value object** (integer smallest units).
- Every balance movement writes ledger entries. Balances are derived, never set.
- Money paths are idempotent and audited; state transitions are logged.
- New money-moving behaviour ships behind **default-OFF feature flags**.

### Code style
- **Do not comment code unless a comment is genuinely required** — no narration of what
  the code already says. Only comment non-obvious *why*: an invariant, a gotcha, a
  money/ledger rule, a workaround. Match the surrounding file's comment density.
- Prefer lean, terse code: minimal config, single env credential set, no dead code.
- Strong typing everywhere (return types, typed properties, `readonly` where it fits).

### Foundation to reuse (don't reinvent)
Settings engine + helpers, feature flags, `ActivityLogger`, RBAC (`config/permissions`),
notifications, CMS, theme. Reuse these for every new module.

## Frontend conventions

Three distinct surfaces — match the one you're editing:

- **Consumer frontend** (incl. Shop): server-rendered **Blade MVC** — form `POST` →
  redirect + flash. **No JSON API, no Livewire.** Standalone **Alpine** for light UI only.
  Vite entry `frontend.js`. Premium blue/slate/Inter theme via `body.theme-minimal`.
- **Admin**: fully migrated **off** Livewire to controllers + Blade, separate `admin`
  guard, standalone Alpine via `admin.js`. DollarHub design (gold frontend / navy admin),
  light-only. **No Filament.** In admin Blade/Livewire always use `auth('admin')`.
- **Auth**: Livewire.

Modals: use the restyled `x-ui.modal` (mercury.com look), opened via
`$dispatch('open-modal')`. **Never** native `confirm()`.

UI text is wrapped in `__('English')`; catalogs `en.json` / `bn.json`, `SetLocale`
middleware. Seeders that run in `DatabaseSeeder` must be **faker-free** (they run on
staging/prod with `--no-dev`): use explicit `create()`/`updateOrCreate`, never
`Model::factory()`/`fake()`.

## Shop module — in-progress rename (as of this branch)

`feature/shop-migration` renames the former **Sell** module to **Shop**
(see `docs/shop-migration/MIGRATION-PLAN.md`). Executed in phases:

- ✅ **Phase 1 committed** — namespace/dir `App\Sell` → `App\Shop`, provider →
  `ShopServiceProvider`.
- ⏳ **Pending** — DB tables `sell_*` → `shop_*` (still `sell_*` on disk), routes/URLs
  (`sell.*`, `/sell`), the semantic **Seller → Merchant** rename, cache keys `sell:*`,
  flags/settings `sell_*`, commands `poisapay:sell-*`, ledger account `sell:commission_income`.

Until later phases land you will still see `sell_*` tables, `sell.*` route names, and
`Seller`/`seller_id` naming. Terminology target: **Merchant / Buyer / Shop / Checkout**
(not "Seller"). Note: `app/Domain/Merchant` is a *separate* bounded context — don't conflate
it with the Shop module's merchant model.

The Shop module does **not** sell courses (`ProductType` has no Course case).

## Where to look first

- Ledger / money semantics: `app/Domain/Ledger`, `app/Domain/Wallet`, the `Money` VO.
- Commerce: `app/Shop` (+ consumer controllers under `app/Http/Controllers/Frontend`,
  `Funnel`, `Marketing`).
- Feature flags & settings: the settings engine + `config/`.
- Project memory / conventions: `~/.claude/.../memory/MEMORY.md` (loaded each session).
