# Shop Tracking & Pixels

Per-sales-page marketing pixels (Meta, TikTok, GA4, GTM) with **zero** global
dependency — every page carries its own config, and different pages can use
different accounts. Isolated module: nothing here touches the builder engine,
checkout money-path, or existing Shop features.

## Architecture

```
TrackingManager                      composes providers → head/body/validation + runtime
 ├─ MetaProvider     (fbq)           each implements TrackingProvider
 ├─ TikTokProvider   (ttq)
 ├─ Ga4Provider      (gtag)
 └─ GtmProvider      (dataLayer + noscript)
TrackingEvent / TrackingEventType    one internal, provider-agnostic event vocabulary
```

Each provider is a self-contained **adapter**: it declares its config `fields()`
(the single source of truth for the builder UI *and* server-side validation) and
emits a browser snippet from `headScript()` that self-registers a tracker into a
shared runtime:

```js
window.__ppTrackers.push({ key, init() {/* load pixel */}, fire(type, payload) {/* map → native */} });
```

The runtime (rendered by `TrackingManager::head`) calls each `init()` once, then
fans every `TrackingEvent` out to all trackers via `fire()`. Because ALL
cookie-setting lives inside `init()`, the runtime can defer it until consent.

### Adding a network (Snapchat, Pinterest, Clarity, …)

1. Add one class under `Providers/` extending `AbstractTrackingProvider`.
2. Register it in `ShopServiceProvider` (the `TrackingManager` singleton).

That's it — the builder UI, validation, persistence, injection, and test-event
flow all pick it up automatically.

## Internal events (`TrackingEventType`)

`page_view · view_content · cta_click · add_to_cart · initiate_checkout ·
purchase · purchase_failed · lead · form_submitted · coupon_applied ·
upsell_accepted · upsell_rejected · downsell_accepted · order_bump_added`

Each adapter maps these to its own native names (e.g. `purchase` → Meta
`Purchase`, TikTok `CompletePayment`, GA4 `purchase`). Payloads use canonical
keys — `order_id, value, currency, product_id, product_name, quantity` — reshaped
per provider.

## Firing events

- **Server (load-time):** `PublicSalesController` passes a `list<TrackingEvent>`
  to the sales layout — PageView + ViewContent (sales page), InitiateCheckout
  (checkout), Purchase (thank-you).
- **Client (interaction):** declarative `data-pp-track="cta_click"` on any element
  or form; the runtime also exposes `window.ppTrack(type, data)` for ad-hoc calls,
  and `window.ppTrackingConsent()` to release deferred tracking.

## Privacy

Per page: allow/deny cookies, **wait for consent** (defers all `init()` until
`ppTrackingConsent()`), and anonymize-IP where the network supports it. When a
page has no configured provider, **nothing** is rendered (zero overhead).

## Server-side (Meta Conversions API)

Browser pixels lose events to iOS/ad-blockers, so **Purchase** is *also* sent
server-side via Meta's Conversions API — but only when a page opts in by saving a
**Conversions API token** (the Meta card's second field). Design choices that keep
request volume tiny and the data correct:

- **Purchase only.** The 13 other events stay browser-only. ~90% less server traffic.
- **Queued, never inline.** `OrderPlaced` → `SendMetaCapiPurchaseEvent` listener →
  `SendMetaCapiPurchase` job (Horizon). Checkout never waits on Meta; retries with
  backoff (`shop.tracking.meta_capi.max_attempts`/`backoff`).
- **Deduped with the pixel.** Both send `event_id = order id`, so Meta counts one
  conversion. The browser `fbq('track','Purchase',…,{eventID})` and the CAPI event
  share it; retries stay idempotent.
- **PII hashed.** Email is SHA-256'd (lowercased/trimmed); ip/ua/`_fbp`/`_fbc` are
  captured at dispatch and sent raw as Meta expects.
- **Driver behind a contract.** `MetaCapiClient` → `simulated` (default, no network,
  logs intent) or `http` (`HttpMetaCapiClient`, posts a batch to
  `graph.facebook.com/{ver}/{pixel}/events`). Set `SHOP_META_CAPI_DRIVER=http` +
  per-page tokens before enabling in prod. `config/shop.php → tracking.meta_capi`.

Adding another network's server-side API (TikTok Events API, GA4 Measurement
Protocol) follows the same shape: a `*CapiClient` contract + driver + an
`OrderPlaced` listener — no change to the browser layer.

## Test events

Builder → Settings → Tracking & Pixels → *Send test event* opens
`/shop/sales-pages/{slug}/tracking-test?provider={key}`, a barebones page that
loads only that provider (from saved config) and fires a sample PageView +
Purchase so the merchant can confirm delivery in their Events Manager.
