# Card Issuing — Provider-Agnostic Architecture

PoisaPay issues virtual/physical cards through pluggable providers behind one
contract. **Marqeta** is the first real adapter; a fully-simulated **Mock** adapter
lets the whole platform run with zero external dependencies. Switching a card
program to another provider is a single `card_providers.driver` change — no
controller, ledger, route, or schema change.

## Documents

| Doc | Covers |
|-----|--------|
| [architecture.md](architecture.md) | Layering, folder structure, patterns, service/DTO layer, provider selection, Mock + Marqeta adapters |
| [database.md](database.md) | Schema, ERD, indexes, idempotency |
| [flows.md](flows.md) | Sequence diagrams: issuance, JIT authorization, webhook settle/refund/reverse, wallet integration, event flow |
| [webhooks-and-jit.md](webhooks-and-jit.md) | Inbound pipeline (verify → dedupe → queue → route), JIT gateway, API endpoints |
| [configuration.md](configuration.md) | `config/card.php`, env vars, local + Marqeta sandbox setup |
| [adding-a-provider.md](adding-a-provider.md) | Future provider integration guide |
| [checklists.md](checklists.md) | Production migration, security, and testing checklists |

## Core principle

The application never depends on a provider. Controllers and the ledger talk only
to `CardService` / `CardManager`; every provider response is mapped into neutral
DTOs. Unsupported features throw `FeatureNotSupportedException` and degrade
gracefully (local state stays authoritative).

```
Controllers ─▶ CardService ─▶ CardManager ─▶ CardProviderFactory ─▶ CardProviderInterface
                    │                                                 ├─ MockCardProvider
                    │                                                 └─ MarqetaProvider ─▶ MarqetaClient
                    └─▶ Domain\Card ledger actions (Authorize/Settle/Refund/Reverse)  ← provider-agnostic
Inbound: /api/card/webhooks/{p} + /api/card/jit/{p} ─▶ verify ─▶ (dedupe+queue | AuthorizeCardAction)
```

## The JIT insight

Marqeta **Gateway JIT Funding** posts to our endpoint per authorization asking
permission to fund a transaction; we approve (200) / decline (402) against **our**
ledger. That maps directly onto the pre-existing `AuthorizeCardAction`, so the
ledger stays the source of truth — the provider never holds the balance.

## Status

Phases 1–5 complete (core, Mock, domain wiring, inbound webhooks + JIT, Marqeta
adapter, admin monitoring). 49 card feature tests passing. Marqeta payload details
that were not certain from the public docs are marked `TODO(marqeta)` in code —
verify against your program before go-live (see [checklists.md](checklists.md)).
