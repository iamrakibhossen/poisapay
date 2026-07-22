# Database

All card tables use UUID primary keys. Generic naming — no provider-specific
tables. Migrations: `..._create_card_tables`, `..._create_card_providers_table`,
`..._add_card_controls`, `2026_07_21_100000_create_card_provider_layer`,
`2026_07_21_100100_create_card_webhooks_table`.

## ERD

```mermaid
erDiagram
    users ||--o{ cards : owns
    users ||--o{ provider_accounts : "has cardholder token"
    card_providers ||--o{ cards : issues
    card_providers ||--o{ provider_accounts : "per program"
    card_providers ||--o{ card_provider_logs : logs
    cards ||--o{ card_authorizations : has
    cards ||--o{ card_metadata : has
    cards ||--o{ card_provider_logs : "may reference"
    card_authorizations ||--o{ card_disputes : disputed_by
    card_authorizations }o--|| assets : funded_by
    card_authorizations }o--o| journal_entries : "hold / settle entry"
    journal_entries ||--o{ ledger_lines : contains
    ledger_accounts ||--o{ ledger_lines : posts
    ledger_accounts ||--|| account_balances : materializes

    cards {
      uuid id PK
      uuid user_id FK
      uuid card_provider_id FK
      string program
      string type
      string network
      string issuer_card_ref UK "provider card token (never a PAN)"
      string cardholder_ref "provider cardholder token"
      char last4
      int exp_month
      int exp_year
      string status
      decimal daily_limit
      decimal per_tx_limit
      json allowed_countries
      json blocked_mccs
      text pin_hash "bcrypt, hidden"
    }
    provider_accounts {
      uuid id PK
      uuid user_id FK
      uuid card_provider_id FK
      string driver
      string provider_ref "cardholder token"
      string status
      json metadata
    }
    card_providers {
      uuid id PK
      string slug UK
      string driver "mock|marqeta|…"
      string network
      bool is_active
      json config
    }
    card_webhooks {
      uuid id PK
      string driver
      string provider_event_id "UK with driver"
      string event_type
      string provider_card_ref
      string provider_tx_ref
      json payload
      bool signature_valid
      string status "pending|processed|ignored|failed"
      int attempts
    }
    card_provider_logs {
      uuid id PK
      uuid card_provider_id FK
      uuid card_id FK
      string driver
      string direction "outbound|inbound"
      string operation
      json request "redacted"
      json response "redacted"
      int status_code
      int latency_ms
      bool success
    }
    card_metadata {
      uuid id PK
      uuid card_id FK
      string key "UK with card_id"
      text value
    }
```

## Key constraints & indexes

| Table | Uniqueness / index | Why |
|-------|--------------------|-----|
| `cards` | unique `issuer_card_ref`; `ck_no_pan` CHECK | one provider token per card; **structurally forbids storing a raw PAN** |
| `card_authorizations` | unique `network_auth_id` | idempotent authorization (no double-hold) |
| `provider_accounts` | unique `(user_id, card_provider_id)`, `(driver, provider_ref)` | one cardholder per user per program |
| `card_webhooks` | unique `(driver, provider_event_id)` | **dedupe** — a replayed event is dropped |
| `card_provider_logs` | index `(driver, created_at)`, `card_id`, `operation` | fast admin filtering; immutable (no `updated_at`) |
| `journal_entries` | unique `idempotency_key` | ledger posting is idempotent (`card:hold:*`, `card:settle:*`, `card:refund:*`, `card:reverse:*`) |

## Idempotency (three layers)

1. **Authorization** — `card_authorizations.network_auth_id` unique; a re-sent auth returns the existing decision.
2. **Ledger** — `journal_entries.idempotency_key` unique; re-posting a hold/settle/refund/reverse is a no-op.
3. **Webhooks** — `card_webhooks (driver, provider_event_id)` unique; duplicate deliveries never reprocess.

## Ledger accounts used by cards

Cards move money through the existing double-entry ledger (`LedgerService`); no
balance lives at the provider (JIT model).

| Account type | Role |
|--------------|------|
| `user:available` | spendable balance |
| `user:card_hold` | authorized-but-unsettled hold |
| `card_program:settlement` | realized card spend (treasury) |
| `fee:card` | card fee income (bps of settlement) |
| `card_program:loss` | chargeback losses |
