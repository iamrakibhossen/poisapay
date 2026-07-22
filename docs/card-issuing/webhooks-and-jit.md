# Inbound: Webhooks & JIT Gateway

Both endpoints are **provider-agnostic** — the `{provider}` segment selects the
adapter that verifies and parses the request. Defined in `routes/api.php`
(unauthenticated; each provider verifies its own requests).

## API endpoints

| Method | Path | Handler | Purpose |
|--------|------|---------|---------|
| POST | `/api/card/webhooks/{provider}` | `CardInboundController@webhook` | async provider events |
| POST | `/api/card/jit/{provider}` | `CardInboundController@jit` | synchronous funding decision |

Admin (operator guard, `view-cards`):

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/admin/card-logs` | provider API call log |
| GET | `/admin/card-webhooks` | webhook deliveries |
| POST | `/admin/card-webhooks/{id}/retry` | re-queue (needs `manage-cards`) |
| GET | `/admin/card-health` | live provider health |

User-facing card routes live in `routes/frontend/cards.php` (list, manage, generate,
freeze, controls, pin, replace, close, dispute).

## Webhook pipeline

```
POST /api/card/webhooks/{provider}
  1. adapter = CardManager::driver(provider)          (404 if unknown)
  2. adapter.verifyWebhook(rawBody, headers)          (401 if invalid) → HMAC / Basic auth
  3. events = adapter.processWebhook(rawBody, headers) → NormalizedWebhookEvent[]
  4. for each event:
       CardWebhook::firstOrCreate(driver, provider_event_id)   ← DEDUPE (unique index)
       if new → ProcessCardWebhookJob::dispatch(id)            ← QUEUE (retry, backoff)
  5. 200 { received }                                          ← ack fast
```

`ProcessCardWebhookJob` (5 tries, backoff `[10,30,120,600]s`):
- reconstructs the `NormalizedWebhookEvent`, calls `WebhookEventRouter::handle`,
  marks the row `processed` / `ignored`; on throw marks `failed` and rethrows so the
  queue retries. Failed rows are retryable from the admin webhooks page.

Everything heavy runs off-request; the endpoint only verifies, dedupes, and queues.

## JIT gateway (synchronous)

```
POST /api/card/jit/{provider}
  1. adapter = CardManager::driver(provider); require supportsJitFunding()
  2. adapter.verifyWebhook(rawBody, headers)          (401 if invalid)
  3. request = adapter.parseFundingRequest(...)        → CardAuthorizationRequest
  4. result  = AuthorizeCardAction::authorize(request) → ledger decides (hold or decline)
  5. response = adapter.formatFundingResponse(result)  → { status, body }
  6. return JSON body with status 200 (approve) / 402 (decline)
```

This is latency-critical — it blocks the network. Keep the ledger auth within the
`card_auth_p99_ms` budget (`config/poisapay.php`). `config('card.inbound.jit_timeout_ms')`
documents our own answer budget.

## Verification per provider

| Provider | Webhook | JIT |
|----------|---------|-----|
| Mock | HMAC-SHA256 of raw body vs `X-Mock-Signature` | same |
| Marqeta | Basic auth (`inbound_username`/`password`) + optional HMAC-SHA256 | Basic auth |

`TODO(marqeta)`: confirm the exact signature header name and the webhook event
grouping keys against your program's dashboard.

## Canonical event mapping

Providers map their native events onto `App\Card\Enums\WebhookEventType`:
`card.created|updated|frozen|unfrozen|replaced|closed`,
`transaction.authorized|cleared|refunded|reversed`, `provider.error`, `unknown`.

Marqeta clearing/refund/reversal events carry the **original** auth token via
`preceding_related_transaction_token` → matched to our `network_auth_id`.
