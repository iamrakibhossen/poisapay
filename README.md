# PoisaPay

A custodial, multi-chain crypto wallet for the Bangladesh market — Ethereum · BNB Smart Chain · Tron — with fiat (BDT) accounts, instant P2P, exchange, cards and rewards. Built to the [PoisaPay Technical Design Document](docs/PoisaPay-Technical-Design-Document.pdf).

> **Design posture.** This is an original, self-contained implementation of the TDD's architecture. Signing/custody, blockchain monitoring, card issuance, fiat rails and lending are **gated on legal + licensed partners** (TDD §F0/§10.4) and are implemented here as clearly-marked, swappable stand-ins behind interfaces — never as claims that these activities are live.

## Stack

Laravel 12 · PHP 8.3+ · PostgreSQL · Redis · Livewire 3 · Alpine · TailwindCSS v4 · Chart.js · Laravel Reverb · Horizon · Sanctum · Pest.

The admin console is a **native Livewire panel** (no Filament), sharing the same design system as the user app.

## The financial core (the part that must be exactly right)

Everything money-related flows through **one double-entry, append-only ledger** (`app/Domain/Ledger`). This is the load-bearing decision (TDD D1–D3):

- **`Money`** (`app/Support/Money.php`) — immutable, integer base-unit arithmetic via `brick/math`. No floats, ever.
- **`LedgerService::post(EntryData)`** — the only path value moves. Balanced entries (Σdebit = Σcredit), row-locked balance updates, idempotency keys so queue retries collapse to no-ops (D5).
- **DB-enforced balance** — a deferred PostgreSQL constraint trigger rejects any unbalanced journal entry at commit. Balance is guaranteed by the database itself, not just the app.
- **`lock` / `unlock`** — reserve-before-sign for withdrawals (A3); a user "wallet" is just `user:available` + `user:locked` ledger accounts.

Money movements built on it: **deposit credit** (§6.1), **internal transfer** (§6.4), **withdrawal reserve** (§6.3), **exchange/JIT conversion** (§F2), **card authorisation hold** (§F3.3), **fiat on-ramp** (§F1), **reconciliation/solvency** (§5.4).

## Layout

```
app/
  Enums/            Backed enums (chains, statuses, tiers, ledger types) with label/color meta
  Support/Money.php Exact-money value object
  Domain/           Feature-based domain: Ledger, Wallet, Custody, Deposit, Withdrawal,
                    Transfer, Exchange, Card, Ramp, Risk, Kyc, Compliance, Rewards, Reconciliation, Auth
  Models/           Eloquent models (UUID keys across the money domain)
  Livewire/         User app pages + Livewire\Admin/* operator console
  Http/Controllers/Api/V1  REST API (§8) — Sanctum, idempotency, rate limits
  Events/ Listeners/ Notifications/ Policies/
database/migrations Full schema incl. the balanced-entry trigger
resources/views/
  components/ui     Reusable design-system components (button, card, stat-card, table, modal, badge…)
  components/layouts app / admin / guest shells
  livewire/         Page views (user + admin)
tests/              Pest — ledger invariants, money math, movement flows, exchange, card auth, API
```

## Getting started

```bash
# 1. Databases
createdb poisapay && createdb poisapay_test        # PostgreSQL
redis-server                                         # Redis

# 2. Install
composer install && npm install

# 3. Configure (.env already targets pgsql `poisapay` + redis)
php artisan key:generate

# 4. Migrate + seed (chains, assets, roles, demo users, funded demo wallet)
php artisan migrate:fresh --seed

# 5. Build assets + serve
npm run build         # or: npm run dev
php artisan serve
```

Open http://localhost:8000.

Consumers and operators are **fully separate auth surfaces** (DollarHub-style): consumers live in `users`; operators live in a dedicated `admins` table with their own `admin` session guard, login at `/admin/login`, and RBAC roles scoped to the `admin` guard. Admin routes are isolated in `routes/admin.php`.

| Console | Login | Access |
| --- | --- | --- |
| User app (`/`) | `demo@poisapay.test` / `password` | funded wallets (USDT, ETH, TRX, BDT) |
| Operator (`/admin/login`) | `admin@poisapay.test` / `password` | super-admin |
| Operator (`/admin/login`) | `compliance@poisapay.test` / `password` | compliance (KYC only) |

## Simulated chain layer (no live nodes needed)

Deposits and withdrawals move end-to-end via a simulated Blockchain Monitor / Signer:

```bash
# Simulate an inbound on-chain deposit (detection only)
php artisan poisapay:simulate-deposit demo@poisapay.test USDT 250

# Advance confirmations → credit deposits, and broadcast+settle approved withdrawals
php artisan poisapay:chain-tick

# Accrue interest on credit lines + liquidate any breaching maintenance LTV
php artisan poisapay:accrue-credit
```

`poisapay:chain-tick` is scheduled every minute (see `routes/console.php`), and the admin
**Simulation** page exposes both as buttons. Real nodes/signers drop in behind the same
interfaces (`AddressDeriver`, the settlement actions) without touching callers.

## Tests

```bash
./vendor/bin/pest
```

Covers: exact-money arithmetic, double-entry balancing, idempotency (no double-credit), lock/unlock,
solvency invariant, deposit/transfer/withdrawal flows, KYC-gated withdrawals, quote-and-swap,
card-auth hold + idempotency, and the REST API surface.

## REST API (v1)

Bearer token (Sanctum). Mutating money endpoints accept an `Idempotency-Key` header; a consistent
`{ error: { code, message, details } }` envelope; per-endpoint rate limits.

```
POST /api/v1/auth/register | login | 2fa/verify
GET  /api/v1/assets | chains | wallets | wallets/{symbol} | deposits | transfers | withdrawals | kyc/status
POST /api/v1/deposit-addresses | transfers | withdrawals | swaps/quote | swaps | kyc/submit
POST /api/v1/merchant/invoices | merchant/invoices/{id}/pay      GET /api/v1/merchant/invoices/{id}
```

**Outbound webhooks (§8.3):** HMAC-SHA256 signed (`X-PoisaPay-Signature`), retried with
exponential backoff, delivery-logged — `deposit.confirmed`, `withdrawal.completed`, `invoice.paid`, `swap.completed`.
Manage endpoints + API tokens on the in-app **Developer** page.

## Security & compliance notes

- **Trust zones (§3.2):** the online zone holds no private keys. Address derivation is public (xpub-only);
  signing is isolated. The `AddressDeriver` interface has a deterministic stand-in; a real HD/signer service
  drops in behind it.
- **Compliance is a hard gate (A6):** tiered KYC governs withdrawals and card issuance; withdrawals are
  risk-scored into auto-approve vs manual review; sanctions screening is recorded.
- **RBAC:** operator roles (super-admin, admin, compliance, treasury, support) gate the admin console and actions.
