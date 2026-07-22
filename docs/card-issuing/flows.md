# Flows & Sequence Diagrams

## 1. Card issuance

```mermaid
sequenceDiagram
    participant U as User
    participant C as Frontend\CardsController
    participant S as CardService
    participant A as Provider adapter
    participant DB as DB (cards, provider_accounts)

    U->>C: POST /cards (providerId, cardType)
    C->>S: issueCard(user, provider, type)
    S->>S: guard: active + supports type
    S->>A: ensureCardholder → createCardholder()
    A-->>S: CardholderResult(providerRef)
    S->>DB: upsert provider_accounts
    S->>A: createVirtualCard(CardIssueRequest)
    A-->>S: CardData(providerCardRef, last4, expiry)
    S->>DB: insert cards (status=Inactive)
    S-->>C: Card
    C-->>U: redirect + flash
```

## 2. Authorization — Gateway JIT (ledger is source of truth)

```mermaid
sequenceDiagram
    participant N as Provider (Marqeta)
    participant I as CardInboundController /api/card/jit/{p}
    participant A as Provider adapter
    participant AZ as AuthorizeCardAction
    participant L as LedgerService

    N->>I: POST JIT funding request
    I->>A: verifyWebhook(raw, headers)
    A-->>I: true (Basic auth / HMAC)
    I->>A: parseFundingRequest → CardAuthorizationRequest
    I->>AZ: authorize(request)
    AZ->>AZ: card active? limits? controls?
    AZ->>L: lock available balance (FOR UPDATE)
    alt sufficient funds
        AZ->>L: post card.hold (available → card_hold)
        AZ-->>I: approve(auth)
        I->>A: formatFundingResponse
        I-->>N: 200 (approve)
    else insufficient / blocked
        AZ-->>I: decline(reason)
        I-->>N: 402 (decline)
    end
```

## 3. Clearing / settlement — async webhook

```mermaid
sequenceDiagram
    participant N as Provider
    participant I as CardInboundController /api/card/webhooks/{p}
    participant A as Provider adapter
    participant DB as card_webhooks
    participant Q as ProcessCardWebhookJob (queue)
    participant R as WebhookEventRouter
    participant SET as SettleCardAuthAction
    participant L as LedgerService

    N->>I: POST webhook (transaction.cleared)
    I->>A: verifyWebhook → true
    I->>A: processWebhook → [NormalizedWebhookEvent]
    I->>DB: firstOrCreate (driver, provider_event_id)  %% dedupe
    I->>Q: dispatch(webhookId)
    I-->>N: 200 {received}
    Q->>R: handle(event)
    R->>SET: execute(auth)  %% status Approved → Settled
    SET->>L: post card.settle (card_hold → settlement + fee:card + available overhold)
    Q->>DB: status = processed
```

## 4. Refund & reversal

```mermaid
sequenceDiagram
    participant R as WebhookEventRouter
    participant REF as RefundCardAuthAction
    participant REV as ReverseCardAuthAction
    participant L as LedgerService

    alt transaction.refunded (auth Settled)
        R->>REF: execute(auth)
        REF->>L: post card.refund (settlement → available); status → Reversed on full
    else transaction.reversed (auth Approved, pre-settle)
        R->>REV: execute(auth)
        REV->>L: post card.reverse (card_hold → available); status → Reversed
    end
```

## 5. Wallet integration (money movement)

```
Authorization ──▶ HOLD    : user:available  →  user:card_hold
Settlement    ──▶ CAPTURE : user:card_hold  →  card_program:settlement (+ fee:card, + user:available overhold)
Refund        ──▶ CREDIT  : card_program:settlement → user:available
Reversal      ──▶ RELEASE : user:card_hold  →  user:available
Chargeback(lost) ─▶ LOSS  : card_program:loss → user:available
```

Every leg is a balanced double-entry posting via `LedgerService::post` with an
idempotency key. The provider never holds the balance — it only asks (JIT) or
notifies (webhooks).

## 6. Event flow (webhook → outcome)

| Canonical `WebhookEventType` | Router action | Ledger effect |
|------------------------------|---------------|---------------|
| `transaction.authorized` | `AuthorizeCardAction` (non-JIT only) | hold |
| `transaction.cleared` | `SettleCardAuthAction` | capture |
| `transaction.refunded` | `RefundCardAuthAction` | credit |
| `transaction.reversed` | `ReverseCardAuthAction` | release |
| `card.frozen/unfrozen/closed` | mirror local `Card.status` | none |
| others / unknown | ignored (recorded) | none |

Domain events (`card.generated`, `card.frozen`, `card.settled`, `card.refunded`,
`card.reversed`, …) are written to the audit log via `ActivityLogger`.
