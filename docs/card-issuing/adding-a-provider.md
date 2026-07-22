# Adding a New Provider

Adding Stripe Issuing, Lithic, Highnote, Galileo, Nium, Wallester, Adyen, etc.
touches **only** `app/Card/Providers/<Name>/` plus one config entry. Nothing in
controllers, the ledger, routes, the wallet, or the schema changes.

## Steps

1. **Create the adapter folder** `app/Card/Providers/Lithic/` and a
   `LithicProvider extends AbstractCardProvider`.
2. **Implement the contract.** Override only the methods the provider supports and
   declare them in `capabilities()`. Everything else inherits the base's
   `FeatureNotSupportedException`, and `CardService` degrades gracefully.
   ```php
   class LithicProvider extends AbstractCardProvider {
       public function __construct(private string $key, private array $config = []) {}
       public function key(): string { return $this->key; }
       public function capabilities(): array { return [ProviderCapability::VirtualCards, ...]; }
       public function createVirtualCard(CardIssueRequest $r): CardData { /* map */ }
       // freeze/unfreeze/terminate/getCard/webhooks/JIT/healthCheck...
   }
   ```
3. **Map to neutral DTOs.** Return `CardData`, `CardholderResult`,
   `ProviderTransactionData`, `NormalizedWebhookEvent`, `ProviderHealth`. Never leak
   provider-specific fields past the adapter (stash extras in the DTO `raw` array or
   `card_metadata`).
4. **Add config** to `config/card.php` `providers`:
   ```php
   'lithic' => ['driver' => LithicProvider::class, 'api_url' => env('CARD_LITHIC_URL'),
                'api_key' => env('CARD_LITHIC_KEY'), 'webhook_secret' => env('CARD_LITHIC_WEBHOOK_SECRET')],
   ```
5. **Point a program at it:** set `card_providers.driver = 'lithic'`.

That's it — the factory resolves the driver, `CardManager` picks it per card, and the
inbound endpoints (`/api/card/webhooks/lithic`, `/api/card/jit/lithic`) work
immediately.

## Contract checklist

| Group | Methods |
|-------|---------|
| Identity | `key`, `capabilities`, `supports` |
| Cardholders | `createCardholder`, `updateCardholder` |
| Cards | `createVirtualCard`, `createPhysicalCard`, `getCard(reveal)`, `listCards` |
| Lifecycle | `freezeCard`, `unfreezeCard`, `terminateCard`, `replaceCard` |
| Controls | `setSpendControls` |
| Transactions | `getTransactions`, `syncTransactions`, `syncBalance` |
| Money (simulate/init) | `authorize`, `capture`, `refund`, `reverse` |
| Inbound | `verifyWebhook`, `processWebhook`, `supportsJitFunding`, `parseFundingRequest`, `formatFundingResponse` |
| Ops | `healthCheck` |

## Conventions

- **Use `MarqetaClient` as a template** for a logged, retrying HTTP client — pass
  `ProviderLogger` so every call lands in `card_provider_logs` (secrets redacted).
- **Funding model:** if the provider supports gateway/JIT funding, implement the JIT
  methods so **our ledger stays authoritative**. If it is pre-funded only, implement
  `syncBalance`/`syncTransactions` and load the provider from the ledger instead.
- **Sensitive data:** only return `pan`/`cvv` on an explicit reveal call; never
  persist them (the `cards.ck_no_pan` CHECK enforces this).
- **Idempotency:** normalize each event with a stable `providerEventId`; keep
  clearing/refund/reversal keyed to the **original** authorization token so it maps
  to our `network_auth_id`.
- **Tests:** add an `Http::fake` mapping test (see `tests/Feature/MarqetaProviderTest.php`)
  — no live credentials needed.
