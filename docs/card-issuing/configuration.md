# Configuration & Sandbox Setup

## `config/card.php`

```php
'default_provider' => env('CARD_PROVIDER', 'mock'),   // fallback driver
'providers' => [
    'mock'    => ['driver' => MockCardProvider::class, 'webhook_secret' => ...],
    'marqeta' => ['driver' => MarqetaProvider::class, 'api_url', 'application_token',
                  'admin_token', 'card_product_token', 'network', 'webhook_secret',
                  'inbound_username', 'inbound_password'],
],
'http'    => ['timeout', 'retry_attempts', 'retry_sleep_ms'],
'inbound' => ['tolerate_seconds', 'jit_timeout_ms'],
```

Only `app/Card/**` reads these values. Controllers/ledger never see credentials.

## Environment variables

| Var | Default | Purpose |
|-----|---------|---------|
| `CARD_PROVIDER` | `mock` | default driver for new programs |
| `CARD_MOCK_WEBHOOK_SECRET` | `mock-webhook-secret` | HMAC secret the mock signs with |
| `CARD_MARQETA_URL` | sandbox URL | Marqeta base URL |
| `CARD_MARQETA_APP_TOKEN` | — | Basic-auth username |
| `CARD_MARQETA_ADMIN_TOKEN` | — | Basic-auth password |
| `CARD_MARQETA_CARD_PRODUCT_TOKEN` | — | required to issue cards |
| `CARD_MARQETA_NETWORK` | `visa` | display network |
| `CARD_MARQETA_WEBHOOK_SECRET` | — | HMAC secret |
| `CARD_MARQETA_INBOUND_USER` / `_PASS` | — | Basic auth Marqeta uses to call our webhook/JIT endpoints |
| `CARD_HTTP_TIMEOUT` / `_RETRY_ATTEMPTS` / `_RETRY_SLEEP_MS` | `15` / `2` / `200` | outbound HTTP |
| `CARD_WEBHOOK_TOLERANCE` / `CARD_JIT_TIMEOUT_MS` | `300` / `1500` | inbound |

> Move real secrets to `.env` for production — do not commit live tokens even for
> sandbox. The `env()` fallbacks in `config/card.php` are for local convenience only.

## Local development (Mock — zero dependencies)

1. `CARD_PROVIDER=mock` (default).
2. Seed a provider row with `driver = mock` (see `CardProviderSeeder`), or create one
   in **Admin → Cards → Card Providers**.
3. Issue, freeze, replace, close cards in the app; simulate money with the Mock
   adapter's signed JIT/webhook bodies (see `tests/Feature/CardInboundTest.php`).

Everything works offline — no external calls.

## Marqeta sandbox setup

1. In the Marqeta dashboard, note the **application token** and **admin access token**
   and your sandbox **base URL**.
2. Create a **Card Product** and copy its `token` → `CARD_MARQETA_CARD_PRODUCT_TOKEN`.
3. Create a **program gateway funding source** (`POST /fundingsources/programgateway`)
   with `url = https://<you>/api/card/jit/marqeta` and a Basic-auth user/pass →
   `CARD_MARQETA_INBOUND_USER` / `_PASS`. Reference it from the card product's
   `jit_funding` config (`refunds_destination: GATEWAY`).
4. Create a **webhook** (`POST /webhooks`) with `config.url =
   https://<you>/api/card/webhooks/marqeta`, the same Basic-auth creds, and
   `config.secret` + `signature_algorithm = HMAC_SHA_256` →
   `CARD_MARQETA_WEBHOOK_SECRET`.
5. Set the program's `card_providers.driver = 'marqeta'`.
6. Verify in **Admin → Cards → Provider Health** (pings `GET /ping`).

`TODO(marqeta)` items to confirm against your program before go-live: exact JIT
message field paths + approval echo, webhook event grouping keys + signature header,
`/simulate` payloads, and spend-control mapping (velocity/auth controls + MCC groups).

## Switching providers

Change one column: `card_providers.driver`. No controller, ledger, route, schema, or
wallet change. Different programs may use different drivers at the same time.
