# Checklists

## Production migration checklist

- [ ] `php artisan migrate` — applies `create_card_provider_layer` (driver column +
      `provider_accounts`, `card_provider_logs`, `card_metadata`, card expiry cols)
      and `create_card_webhooks_table`.
- [ ] Existing `card_providers` rows backfilled to `driver = mock` (migration does this);
      set live programs to their real driver.
- [ ] Real provider secrets in `.env` (not committed); `CARD_PROVIDER` set.
- [ ] `CARD_MARQETA_CARD_PRODUCT_TOKEN` set; card product created in the program.
- [ ] Program gateway funding source → `/api/card/jit/marqeta` (Basic auth) registered.
- [ ] Webhook → `/api/card/webhooks/marqeta` (Basic auth + HMAC secret) registered.
- [ ] Queue worker running (`ProcessCardWebhookJob`) + Horizon monitoring.
- [ ] JIT endpoint latency within `card_auth_p99_ms`; provider timeout/retries tuned.
- [ ] Provider Health page green; resolve all `TODO(marqeta)` field mappings.
- [ ] Load/soak test the JIT path; confirm decline paths return 402.

## Security checklist

- [ ] **No raw PAN stored** — `cards.ck_no_pan` CHECK; `pan`/`cvv` only transient on reveal.
- [ ] Provider tokens/PAN/CVV/PIN **redacted** in `card_provider_logs` (`ProviderLogger`).
- [ ] `pin_hash` bcrypt, `$hidden`; secrets never logged.
- [ ] Inbound webhooks/JIT **verified** (HMAC and/or Basic auth) before any effect.
- [ ] Webhook dedupe (`unique(driver, provider_event_id)`) prevents replay.
- [ ] Endpoints rate-limited (`throttle` middleware) and HTTPS-only in production.
- [ ] Reveal (PAN/CVV) is a separate PCI-scoped path; short-lived credentials only.
- [ ] Provider credentials in `.env`/secret store, never in VCS.
- [ ] Least-privilege admin: `view-cards` (read) vs `manage-cards` (mutations/retry).

## Testing checklist

- [ ] **Unit / mapping** — `MarqetaProviderTest` (`Http::fake`): auth, users, cards,
      transitions, reveal, webhook verify, event normalization, JIT parse/format.
- [ ] **Feature — issuance/lifecycle** — `CardGeneratorTest`, `FrontendCardsTest`
      (issue through provider, freeze/unfreeze, controls, replace, close).
- [ ] **Feature — ledger** — `CardAuthorizationTest`, `CardSystemPhase6Test`
      (hold/settle/refund/reverse/dispute money movement + idempotency).
- [ ] **Feature — inbound** — `CardInboundTest` (JIT approve/decline/bad-sig, webhook
      settle, dedupe, bad-sig).
- [ ] **Feature — admin** — `AdminCardMonitorTest` (logs/webhooks/health pages, retry,
      permission gating).
- [ ] Mock provider exercises the full pipeline offline (verify → dedupe → queue → route).
- [ ] Optional: guarded live sandbox integration test once creds + card product exist.

Run: `php artisan test --filter=Card` (49 passing). Note: `AdminConfigCrudTest` has a
**pre-existing**, unrelated intermittent Postgres deadlock (db:seed in `beforeEach` +
RefreshDatabase) — not caused by the card feature.

## Future provider integration

See [adding-a-provider.md](adding-a-provider.md): create `app/Card/Providers/<Name>/`,
implement `CardProviderInterface` (override only supported methods), add a config
entry, set `card_providers.driver`. No other change.
