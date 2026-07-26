# Shop Custom Domains — Architecture & Operations

Merchants connect their own domain (e.g. `shop.brand.com`) to a single Shop sales
page. The platform verifies ownership over DNS, provisions SSL, and routes the
domain straight to the page — no separate deploy, no per-domain config.

**Namespace:** `App\Shop` (commerce bounded context). **Feature flag:**
`shop_custom_domains` (settings, default **off**). **DNS model:** CNAME-only.

---

## 1. Data model

`shop_domains` (one row per connected domain; **one domain per sales page**):

| Column | Notes |
|---|---|
| `id` (uuid) | PK |
| `seller_id` → `shop_sellers` | FK, cascade delete, indexed |
| `sales_page_id` → `shop_sales_pages` | FK, **unique** (one domain per page), cascade |
| `host` | normalized FQDN, **globally unique** (a host belongs to one shop) |
| `status` | `DomainStatus`: pending · verifying · verified · failed |
| `ssl_status` | `DomainSslStatus`: pending · issuing · active · failed |
| `dns_record_type` | detected routing record (`cname`) |
| `verification_token` | TXT ownership challenge (minted at connect) |
| `verify_attempts` / `ssl_attempts` | retry counters (drive the auto-retry ceiling) |
| `last_error` | last failure reason (shown to merchant/operator) |
| `last_checked_at` / `verified_at` / `disabled_at` | timestamps; `disabled_at` = operator kill-switch |

Indexes: `host` unique, `sales_page_id` unique, `(seller_id, status)`, `status`
(routing filters on serviceable rows). All FK columns indexed.

---

## 2. Normalization & validation

`Support\DomainName::normalize()` canonicalizes arbitrary input to a bare,
lowercase, punycode FQDN: strips scheme / userinfo / path / query / port, lowercases,
IDN→punycode, and **strips a leading `www.`** (www is served as an alias of the apex).
So `HTTP://WWW.Example.COM/` → `example.com`.

`Services\Domain\DomainValidator` gatekeeps a normalized host, throwing a
merchant-facing `DomainException` on the first failure:

1. **Invalid format** — `DomainName::isValidFormat()` (labels, TLD, ≤253, not an IP).
2. **Platform domain** — `Support\PlatformHost::is()` (app URL host, configured
   `platform_hosts`, any subdomain of `platform_apexes`, localhost, IPs). Blocks
   host-header / takeover attempts against our own hostnames.
3. **Reserved** — configured `reserved_hosts` + `reserved_suffixes` (`.local`,
   `.test`, `.internal`, RFC-2606 examples, …).
4. **Duplicate** — host already taken by another domain row.

---

## 3. Verification flow (ownership + routing)

Two independent DNS checks, **both required** (`Services\Domain\DomainVerifier`):

1. **Ownership** — a TXT record at `_poisapay-challenge.<host>` carrying
   `poisapay-domain-verification=<token>`. Proves the merchant controls the DNS
   zone → blocks connecting a domain you don't own and defends against takeover.
   Compared with `hash_equals` (constant-time; anti-forgery).
2. **Routing** — the host **CNAMEs** to `config('shop.custom_domains.cname_target')`.

State machine (`Actions\Domain\VerifyDomain`, run inside `VerifyDomainJob`):

```
connect ─▶ Pending ─▶ (job) Verifying ─▶ Verified ──▶ dispatch ProvisionSslJob + warm cache
                                     └─▶ Failed  ──▶ re-queue with backoff (until ceiling)
```

- Success: `status=Verified`, `dns_record_type`, `verified_at`, clears `last_error`,
  fires `DomainVerified`, warms the routing cache, queues SSL.
- Failure: increments `verify_attempts`, sets `last_error`, fires
  `DomainVerificationFailed`. If under `verify_max_attempts` (default 10) it
  **re-queues itself** with `verify_backoff` (default 120s); otherwise stops and
  the merchant is notified.
- Disabled domains are skipped. Once `Verified`, no automatic re-checks run (no
  flapping); the merchant/operator can re-verify on demand (`ReverifyDomain`,
  which resets the attempt counter).

---

## 4. SSL flow

Provider-agnostic behind `Contracts\SslProvisioner`, chosen by
`config('shop.custom_domains.ssl.driver')`:

- `simulated` (default) — marks the cert active without a CA (dev/test + until the
  edge/ACME integration lands).
- `acme` — placeholder that throws until wired (records Failed + retries rather
  than reporting HTTPS that doesn't exist).

`Actions\Domain\ProvisionSsl` (run inside `ProvisionSslJob`): only issues for a
`Verified`, non-disabled domain (a cert for a domain we don't control would be a
takeover vector); idempotent (no-op if already `Active`).

```
Pending ─▶ Issuing ─▶ Active ──▶ fire SslIssued + warm cache
                  └─▶ Failed ──▶ re-queue with backoff (until ssl.max_attempts)
```

---

## 5. Routing

`Http\Middleware\ResolveShopDomain` runs as **global** (pre-routing) middleware so
it can rewrite a custom domain onto the existing funnel routes with **zero route
duplication**:

```
example.com/            → /p/{slug}
www.example.com/checkout → /p/{slug}/checkout
```

- Platform hosts (`PlatformHost::is()`) pass straight through untouched.
- A non-platform host is resolved via `Services\Domain\DomainResolver` (cached).
  If the feature is off, or the host isn't a **serviceable** (verified + enabled +
  published-page) domain → **404** (never serve platform content under an
  unrecognized Host header).
- The rewrite duplicates the request with a mutated `REQUEST_URI` (Symfony
  recomputes path/method), so all funnel routes (`/`, `/buy`, `/checkout`,
  `/account`, `/thank-you`, `/upsell`) work on the custom domain. Sessions are
  per-host (`SESSION_DOMAIN` null), so the on-domain checkout flow is self-contained.

---

## 6. Caching

`DomainResolver` caches the host→page lookup (`shop:domain:<host>`), **positive and
negative** (a `false` sentinel stops unknown Host headers flooding the DB). Keyed
by the normalized apex host, so `example.com` and `www.example.com` share one entry.

Invalidation is automatic: `Domain::saved`/`deleted` (wired in `ShopServiceProvider`)
forget the entry on any status / SSL / disable / removal change. `warm()` primes the
cache after verification/SSL so the first live request is warm; `WarmDomainCacheJob`
does it off the request path.

---

## 7. Queues

All heavy work runs on Redis/Horizon, unique-per-domain (`ShouldBeUnique`) so bursts
collapse to one in-flight job:

- `VerifyDomainJob` — one DNS verification pass (Action owns the retry schedule).
- `ProvisionSslJob` — cert issuance (Action owns the retry schedule).
- `WarmDomainCacheJob` — prime the routing cache.

Jobs load the domain by id and no-op if it was removed (safe to retry).

---

## 8. Events, audit & notifications

Every state change fires a `ShopDomainEvent` subclass → **auto-audited** via
`AuditShopEvent` (action `shop.domain.*`):

| Event | When |
|---|---|
| `DomainCreated` | domain connected |
| `DomainVerified` | ownership + routing confirmed |
| `DomainVerificationFailed` | a verification pass failed (`exhausted` flag) |
| `SslIssued` | certificate active |
| `SslFailed` | issuance failed (`exhausted` flag) |
| `DomainRemoved` | disconnected |

Operator disable/enable is audited directly (`shop.domain.disabled` / `.enabled`).
Merchant notifications (`ShopNotificationSubscriber`, templated + preference-aware):
`shop.domain.verified`, and — only once auto-retries are **exhausted** —
`shop.domain.failed` / `shop.domain.ssl_failed`.

---

## 9. Dashboards

- **Merchant** (`/shop/domains`, `Frontend\DomainController`): connect a domain,
  copy the DNS records (TXT + CNAME, with copy buttons), see verification + SSL
  status, last check, last error; **Verify again** / **Remove** (modal-confirmed).
- **Operator** (`/admin/shop-domains`, `Admin\DomainAdminController`): search by
  host/owner, filter by status, view owner + verification + SSL; **Reverify**,
  **Disable/Enable**.

Authorization: `DomainPolicy` (seller owns their domains; operators via
`view-sellers` / `manage-sellers`).

---

## 10. Failure recovery

| Situation | Recovery |
|---|---|
| DNS not propagated yet | `VerifyDomainJob` auto-retries with backoff to the ceiling; merchant can re-verify (resets attempts). |
| SSL issuance fails | `ProvisionSslJob` auto-retries to `ssl.max_attempts`; on exhaustion the merchant is notified. |
| Compromised / abusive domain | Operator **Disable** → `disabled_at` set, routing cache dropped, host stops resolving instantly. |
| Domain freed | **Remove** deletes the row and frees the host for reuse; cache dropped first. |
| Stale routing cache | Any domain write invalidates the entry; TTL (`cache_ttl`, default 1h) backstops. |

---

## 11. Configuration (`config/shop.php` → `custom_domains`)

`cname_target`, `dns_ttl`, `txt_name`, `txt_prefix`, `verify_max_attempts`,
`verify_backoff`, `ssl.{driver,max_attempts,backoff}`, `cache_ttl`,
`platform_hosts`, `platform_apexes`, `reserved_hosts`, `reserved_suffixes`.
Enable per environment with `shop_custom_domains` (admin → Settings → Shop).

## 12. Production notes

- The `simulated` SSL driver reports HTTPS without a real certificate. **Wire the
  `acme` driver (edge/ingress ACME)** before enabling `shop_custom_domains` in prod.
- The `cname_target` / edge must terminate TLS and forward the original Host so the
  router resolves the domain. Apex domains require the DNS provider's
  CNAME-flattening / ALIAS (CNAME-only by design).
- The `shop_domains` schema change is folded into the create-migration
  (`2026_07_26_000002`). If it has already been applied to an environment, add an
  `alter` migration for the new columns instead of editing the create-migration.
