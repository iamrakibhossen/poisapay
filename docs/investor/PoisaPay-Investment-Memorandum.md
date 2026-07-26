# PoisaPay

## Confidential Information Memorandum

### The Financial Operating System for the Digital Economy in Emerging Markets

---

**Document type:** Series Seed / Series A Investment Memorandum
**Status:** Confidential — For Discussion Purposes Only
**Prepared for:** Prospective Investors
**Date:** 2026

---

> **Confidentiality Notice.** This document contains confidential and proprietary information of PoisaPay ("the Company"). It is provided solely for the purpose of evaluating a potential investment and may not be reproduced, distributed, or disclosed, in whole or in part, without the prior written consent of the Company.
>
> **Forward-Looking Statements.** This memorandum contains forward-looking statements, projections, and illustrative financial models. These reflect the Company's current assumptions and expectations and are subject to significant business, economic, regulatory, and competitive uncertainties. Actual results may differ materially. All financial figures presented as projections are illustrative base-case models built on the assumptions stated herein and do not constitute a guarantee of future performance. Where operating metrics are shown as targets, they represent management objectives rather than reported actuals. Nothing in this document constitutes an offer to sell or a solicitation of an offer to buy any security.

---

\newpage

## Table of Contents

1. Executive Summary
2. The Opportunity: Why Now
3. The Problem
4. The Solution: PoisaPay
5. Product Portfolio
   - 5.1 USD Wallet
   - 5.2 Crypto Wallet (USDT)
   - 5.3 P2P Marketplace
   - 5.4 Exchange
   - 5.5 Merchant Payments
   - 5.6 Sales Pages & Digital Commerce
   - 5.7 Virtual Cards
6. How PoisaPay Works: The User Journey
7. Technical Architecture
8. Security
9. Compliance & Regulatory Strategy
10. Business Model & Revenue Streams
11. Unit Economics
12. Market Opportunity (TAM / SAM / SOM)
13. Competitive Landscape
14. Why PoisaPay Wins: Moats & Network Effects
15. Go-to-Market & Growth Strategy
16. SWOT Analysis
17. Business Model Canvas
18. Financial Projections
19. Investment Highlights
20. Risk Factors & Mitigation
21. Roadmap
22. Exit Opportunities
23. The Ask: Use of Funds
24. Appendices

---

\newpage

## 1. Executive Summary

### 1.1 Company at a Glance

PoisaPay is building the financial operating system for digital businesses and independent earners in emerging markets. The platform unifies a USD wallet, a stablecoin (USDT) wallet, a peer-to-peer (P2P) exchange marketplace, a currency exchange engine, merchant payment acceptance, a digital-commerce storefront, and virtual card issuance — all settled on a single, immutable, double-entry ledger.

The Company's thesis is straightforward: hundreds of millions of freelancers, remote workers, online sellers, and digital-first small businesses in emerging markets earn in hard currency but are served by a fragmented, expensive, and often inaccessible financial stack. They stitch together informal money changers, remittance operators, marketplace escrow, and foreign neobanks they cannot legally open. PoisaPay collapses that stack into one regulated, mobile-first platform.

### 1.2 The Core Insight

The incumbent global players — Wise, Payoneer, Stripe, PayPal — were built for the developed world and treat emerging markets as an afterthought or exclude them entirely. The crypto-native players — Binance P2P, RedotPay — solve the on/off-ramp but lack the full business toolkit (invoicing, storefronts, merchant acceptance, compliant cards). PoisaPay sits at the intersection: **the accessibility and settlement rails of stablecoins, wrapped in the product completeness and compliance discipline of a modern neobank.**

### 1.3 Why This Is a Venture-Scale Business

- **Large, underserved, fast-growing market.** The global freelance and creator economy, cross-border digital services, and stablecoin settlement are each growing at double-digit rates, concentrated precisely in the geographies PoisaPay targets.
- **Multiple, compounding revenue streams.** PoisaPay monetizes across exchange spread, P2P trading fees, deposits/withdrawals, merchant acceptance, card interchange and FX margin, subscriptions, and treasury/float income — a blended take rate that strengthens as users deepen their engagement.
- **Structural network effects.** The P2P marketplace is a two-sided liquidity network: more merchants attract more takers, and vice versa. Each additional wallet, saved payment method, and completed trade increases switching costs.
- **A defensible technical foundation.** Money never bypasses an immutable double-entry ledger; balances are derived, never mutated; all money-moving behavior is idempotent, audited, and gated behind default-off feature flags. This is bank-grade correctness discipline, uncommon at seed stage.

### 1.4 The Ask

The Company is raising a **Series A round of USD 6.0 million** (structure and terms to be finalized in process) to scale its Bangladesh beachhead, expand across South Asia, obtain the payment and money-services licenses required for regional operation, and deepen the merchant and card products. Illustrative use of funds and a five-year model are presented in Sections 18 and 23.

### 1.5 Illustrative Financial Trajectory (Base Case)

| Metric (USD) | Y1 (2026) | Y2 (2027) | Y3 (2028) | Y4 (2029) | Y5 (2030) |
|---|---:|---:|---:|---:|---:|
| Registered users (M) | 0.06 | 0.30 | 1.10 | 3.20 | 7.50 |
| Monthly active users (M) | 0.02 | 0.11 | 0.44 | 1.25 | 3.00 |
| Total platform volume / GMV ($M) | 40 | 340 | 1,650 | 6,200 | 16,500 |
| Net revenue ($M) | 0.5 | 3.6 | 19 | 72 | 168 |
| Gross margin (%) | 54% | 60% | 63% | 66% | 68% |
| EBITDA ($M) | (2.0) | (4.0) | (3.0) | 6 | 14 |

*Illustrative base case. See Section 18 for assumptions. Figures are modeled, not reported actuals.*

The model implies a path to EBITDA profitability by Year 4 at a blended take rate of approximately 1.0%–1.3% of platform volume, consistent with observed blended economics of comparable wallet-plus-marketplace businesses.

---

\newpage

## 2. The Opportunity: Why Now

Four structural shifts converge to make this the right moment to build PoisaPay.

**1. The work economy has decentralized.** Income has decoupled from geography. A designer in Dhaka, a developer in Lagos, and a marketer in Manila now sell into the same global demand pool as their peers in San Francisco — but settle into a financial system built for a different era.

**2. Stablecoins have become settlement infrastructure.** Dollar-denominated stablecoins (led by USDT) have transitioned from a trading instrument toward a cross-border settlement rail. On-chain analytics providers (Visa Onchain Analytics, Allium, Chainalysis) have reported adjusted annual stablecoin transfer volumes in the multi-trillion-dollar range, with some analyses drawing comparisons to card-network throughput; these figures are methodology-dependent and should be treated as directional and verified against the latest primary source. Chainalysis's Global Crypto Adoption Index has consistently ranked several emerging markets, including in South Asia, among the highest for grassroots crypto and stablecoin usage. *[Sources: Visa Onchain Analytics; Allium; Chainalysis Global Crypto Adoption Index — verify latest editions at diligence.]*

**3. Smartphone and mobile-money penetration has reached critical mass.** The infrastructure to deliver a full-service financial platform to the last mile — smartphones, mobile data, and mobile-money rails such as bKash and Nagad — is now ubiquitous in target markets.

**4. Incumbents have left a gap.** Wise and Payoneer restrict or exclude many high-demand corridors; Stripe and PayPal are unavailable to sellers in most target countries; global neobanks cannot be opened by residents of these markets. The crypto-native alternatives solve the ramp but not the business.

```
         The Convergence Window

Decentralized     Stablecoin        Mobile-first      Incumbent
    work      +   settlement    +   distribution  +   gap
    │              │                 │                 │
    └──────────────┴────────┬────────┴─────────────────┘
                            ▼
                 A full-stack financial
                 platform for emerging-
                 market digital earners
                        (PoisaPay)
```

The window is open because no single incumbent has both the emerging-market accessibility and the product completeness required. That gap is closing; the first mover to combine compliant wallet infrastructure, P2P liquidity, and a business toolkit will accrue durable network effects.

---

\newpage

## 3. The Problem

The emerging-market digital earner faces a financial system that is fragmented, expensive, slow, and frequently inaccessible.

### 3.1 The Fragmented Stack Today

A typical freelancer or online seller in a target market assembles the following, none of which interoperate:

| Need | Current workaround | Pain |
|---|---|---|
| Receive USD from clients | Payoneer/Wise (if eligible), or informal | High fees; frequent account rejections; corridor restrictions |
| Convert to local currency | Informal money changers; local exchanges | Opaque spreads; counterparty risk; no recourse |
| Hold a dollar balance | Not permitted locally, or crypto self-custody | FX exposure; loss/theft risk; no protection |
| Get paid by many small buyers | Cash-on-delivery; manual bank transfer | No escrow; disputes; reconciliation burden |
| Sell a digital product | Foreign gateways (unavailable) | Cannot accept payment at all |
| Pay for global tools/subscriptions | Borrowed cards; grey-market cards | Declines; account bans; fraud exposure |
| Move money cross-border | Remittance operators | 5–10% all-in cost; multi-day settlement |

### 3.2 Quantifying the Pain

- **Cost.** All-in costs across the fragmented stack are commonly high once FX spreads, fixed fees, and intermediary margins are combined. *The 5%–10% range cited here is an illustrative Company estimate aggregating typical component charges across the workaround stack — not a single third-party statistic. As a directional anchor, the World Bank / KNOMAD Remittance Prices Worldwide database reports global average remittance cost above the UN Sustainable Development Goal target of 3%, and materially higher on some low-volume corridors. [Source: World Bank Remittance Prices Worldwide — verify latest quarter at diligence.]*
- **Access.** A large share of qualified earners are simply rejected by global platforms due to country, document, or risk policies.
- **Trust.** Informal P2P conversion carries real counterparty and fraud risk with no dispute mechanism.
- **Fragmentation.** Managing five to seven disconnected tools imposes a reconciliation and operational tax that scales poorly as a business grows.

### 3.3 The Underlying Cause

These are not isolated product gaps; they are the symptom of a missing category. There is no **financial operating system** designed natively for the emerging-market digital business — one that treats holding dollars, converting them safely, accepting payments, and spending globally as a single, coherent, compliant workflow.

---

\newpage

## 4. The Solution: PoisaPay

PoisaPay is that operating system. It replaces the fragmented stack with one regulated, mobile-first platform in which every function shares a single wallet, a single identity, and a single immutable ledger.

### 4.1 What the User Gets

- **Hold** a USD balance and a USDT balance in one place.
- **Convert** between USD and USDT at a transparent, engine-priced rate.
- **Trade** peer-to-peer with escrow protection, ratings, and dispute resolution.
- **Get paid** by clients and customers via checkout, invoices, and payment links.
- **Sell** digital products, courses, memberships, and services through hosted storefronts.
- **Spend** globally with virtual cards for subscriptions and online purchases.
- **Move** money in and out through local rails (mobile money, bank) and blockchain.

### 4.2 The Product Architecture as a Flywheel

```
        ┌───────────────────────────────────────────┐
        │              PoisaPay Wallet               │
        │      (USD + USDT, one ledger identity)     │
        └───────────────────────────────────────────┘
             ▲          ▲           ▲          ▲
   Deposit   │          │           │          │   Spend
   (ramp)    │          │           │          │  (cards)
             │      P2P marketplace │      Merchant &
        Exchange   (buy/sell USDT   │      Sales Pages
        (USD↔USDT)  with escrow)    │   (accept payments)
             │          │           │          │
             └──────────┴─────┬─────┴──────────┘
                              ▼
                  More funded wallets →
                  more liquidity + more
                  merchant volume →
                  more reasons to hold →
                  more funded wallets
```

Each product feeds the others. A user who arrives to buy USDT via P2P is one step from receiving client payments; a merchant who accepts payments accumulates a balance they will exchange, hold, and spend on a card. This cross-product gravity is the core of the business.

### 4.3 The Category

PoisaPay is not a wallet, an exchange, or a payment gateway. It is the convergence of all three into a **financial operating system** — the emerging-market analog of what Stripe became for developed-market internet businesses, delivered to a market the incumbents cannot serve.

---

\newpage

## 5. Product Portfolio

Each product is a standalone reason to join and a contributor to the shared flywheel. This section describes the seven core products.

### 5.1 USD Wallet

**What it is.** A dollar-denominated account balance held on PoisaPay's internal ledger, allowing users in markets without dollar banking access to hold, receive, and send USD value.

**How it works.**
- Every balance is *derived* from an immutable, double-entry ledger — it is never mutated directly. Each movement of value writes balanced ledger entries, ensuring the platform's books always reconcile.
- Users deposit via local rails and on-ramps, receive from other PoisaPay users instantly and free, and withdraw to supported destinations.
- Internal transfers between PoisaPay users settle instantly and at zero marginal cost, creating a closed-loop network that grows more useful with each new user.

**Why it matters.** The dollar wallet is the anchor product. It converts a one-time transactor into a balance-holding, recurring user — the single most important driver of retention and lifetime value.

### 5.2 Crypto Wallet (USDT)

**What it is.** A stablecoin wallet supporting USDT deposits and withdrawals across major blockchains.

**How it works.**
- Deposits are detected on-chain and credited to the user's ledger balance; withdrawals are signed and broadcast through a hardened custody layer.
- The custody architecture supports real on-chain settlement (HD key derivation, deposit watching, withdrawal signing) behind provider-agnostic contracts, with a hot/cold separation and reconciliation discipline.
- Because USDT is fungible with the platform's dollar accounting, the crypto wallet is not a separate silo — it is a settlement rail into the same unified balance.

**Why it matters.** USDT is the practical cross-border dollar for the target user. Native, low-friction USDT rails are the fastest, cheapest way for a user to fund a PoisaPay wallet — the top of the acquisition funnel.

### 5.3 P2P Marketplace

The P2P marketplace is PoisaPay's liquidity engine and its most powerful network-effect asset. It allows users to buy and sell USDT against local currency, peer-to-peer, with the platform providing escrow, reputation, and dispute resolution — while fiat settles directly between the two parties through local rails.

**The order lifecycle.**

```
  Buyer places order against a Seller's advertisement
                    │
                    ▼
        Escrow LOCKED (seller's USDT held on ledger)
                    │
                    ▼
        Awaiting payment  ── buyer pays fiat off-platform ──►
                    │
                    ▼
             Buyer marks "Paid"
                    │
                    ▼
        Seller verifies receipt of fiat
                    │
                    ▼
         Escrow RELEASED to buyer (net of fee)
                    │
                    ▼
                COMPLETED
                    │
       (dispute path) ─────► Operator review ─────►
        force-release to buyer / refund to seller
```

**Escrow.** When an order opens, the seller's USDT is locked into a dedicated escrow account on the ledger — the digital analog of a card authorization hold. Funds move on a strict, guarded state machine: they can only leave escrow on a valid terminal transition (release, refund, or operator ruling), under database row locks and with ledger idempotency keys that make a double-release structurally impossible. Fiat never touches PoisaPay; only the escrowed USDT moves through the ledger.

**Advertisements.** Sellers and buyers post configurable ads: fixed or floating price, minimum and maximum order size, available quantity, per-ad daily limits, trading hours, country restrictions, counterparty requirements, and payment instructions. Under-funded ads auto-pause so buyers are not routed to failing trades.

**Trust and safety.**
- **Reputation.** Merchants accrue a public profile: completion rate, average release time, average response time, total trades, total volume, merchant level, badges, and buyer feedback.
- **Reviews.** Both parties rate each completed trade, feeding a positive-feedback percentage and a star rating.
- **Risk engine.** Every order passes a pre-trade risk assessment — sanctions/denylist screening, per-tier daily volume caps, order velocity limits, and soft risk scoring that escalates suspicious activity to compliance.
- **Disputes.** Either party can open a dispute with evidence upload; operators review a full case file (timeline, chat transcript, evidence, internal notes) and rule, with the ruling settling escrow exactly once and notifying both parties.
- **KYC gating.** Trading is gated behind identity verification tiers.

**Why P2P generates network effects.** The marketplace is a two-sided liquidity network with classic increasing returns:

```
   More sellers (liquidity, better prices)
            ▲                     │
            │                     ▼
   More completed trades   More buyers (demand)
            ▲                     │
            │                     ▼
     Better reputation  ◄── Faster, cheaper fills
        data & trust
```

Liquidity begets liquidity: more sellers tighten spreads and speed fills, which attracts more buyers, which attracts more sellers. Reputation data — earned over many trades — cannot be ported to a competitor, and the buyer's saved counterparties, payment methods, and favorites raise switching costs. This is the same dynamic that made Binance P2P and local exchange marketplaces durable, captured here inside a full financial platform rather than a standalone exchange.

### 5.4 Exchange

**What it is.** An in-app engine to convert USD to USDT and back, instantly, at a transparent rate.

**How it works.**
- Conversions execute against the platform's treasury/trading inventory at an engine-priced rate that embeds a spread.
- The same exchange engine is shared across use cases (in-app swap, on/off-ramp settlement, card settlement), so pricing, idempotency, KYC, limits, fees, and audit are enforced uniformly.
- The spread is the Company's compensation for providing instant, guaranteed liquidity — the user pays for certainty and speed versus the variance and effort of P2P.

**Why it matters.** Exchange is the highest-margin, most-scalable revenue line: it is a software-priced spread on a balance the user already holds, requiring no counterparty matching. It also complements P2P — P2P provides the best price for patient users; Exchange provides instant certainty for the rest.

### 5.5 Merchant Payments

**What it is.** Tools that let any digital business accept payment into its PoisaPay wallet.

**How it works.**
- **Checkout** — a hosted payment page for one-off and recurring charges.
- **Invoices** — issue a professional invoice; the customer pays into the merchant's wallet.
- **Payment links** — a shareable link that collects payment with no integration.
- **API** — programmatic acceptance for businesses that want to embed PoisaPay in their own product.

**Why it matters.** Merchant acceptance converts individual earners into businesses transacting recurring volume on the platform, dramatically increasing balance velocity and lifetime value, and seeding the merchant side of the network.

### 5.6 Sales Pages & Digital Commerce

**What it is.** A hosted storefront and page builder that lets creators and sellers monetize directly, with PoisaPay as the payment and settlement layer.

**How it works.**
- A schema-driven, block-based page builder lets a seller assemble a professional sales page or storefront with no code, publish, and version it.
- Sellers list digital products, memberships, services, and bundles; buyers check out and pay into the shop, with orders, coupons, refunds, and reviews handled natively.
- Earnings settle to the seller's PoisaPay wallet, closing the loop back into hold/convert/spend.

**Why it matters.** Commerce makes PoisaPay the place where money is *earned*, not just moved. Owning the point of sale is the deepest possible integration into a customer's business and the strongest driver of primary-account status.

### 5.7 Virtual Cards

**What it is.** Issued virtual cards that let users spend their PoisaPay balance for international online payments and subscriptions.

**How it works.**
- Cards are issued through a provider-agnostic issuing layer, settling authorizations against the user's ledger balance via the shared exchange engine.
- The architecture supports multiple issuing providers behind a common gateway, with per-card driver configuration — avoiding single-provider lock-in.

**Why it matters.** The card closes the spend loop: it gives users a reason to keep a balance on PoisaPay (to pay for the global tools their business depends on) and adds two high-margin revenue lines — interchange and card FX margin. It is also the product most associated with "primary financial account" status.

---

\newpage

## 6. How PoisaPay Works: The User Journey

The platform is designed so that each step naturally leads to the next, deepening engagement and monetization.

```
   NEW USER
      │  Sign up (mobile-first, phone/email)
      ▼
   KYC / VERIFICATION
      │  Tiered identity verification unlocks limits & features
      ▼
   WALLET CREATED
      │  USD + USDT balances on the shared ledger
      ▼
   DEPOSIT / ON-RAMP
      │  USDT on-chain, or local rails (mobile money / bank)
      ▼
   BUY USDT (P2P or Exchange)
      │  Best price via P2P, or instant certainty via Exchange
      ▼
   RECEIVE PAYMENTS
      │  Checkout, invoices, links, storefront
      ▼
   HOLD & MANAGE
      │  Dollar balance; convert as needed
      ▼
   SPEND
      │  Virtual card for global subscriptions & purchases
      ▼
   WITHDRAW / OFF-RAMP
         Local rails or on-chain, when the user chooses
```

**The retention mechanic.** The journey is intentionally circular, not linear. A user who reaches "receive payments" or "spend" has a persistent balance and a workflow reason to return — converting a transactor into a resident of the platform. Every product added to a user's footprint (a saved payment method, a card, a storefront, a trading history) increases both monetization and switching cost.

---

\newpage

## 7. Technical Architecture

PoisaPay is engineered as a domain-driven, modular platform with a financial core that enforces bank-grade correctness. This section describes the architecture at a high level; implementation details are proprietary.

### 7.1 Architectural Layers

```
┌─────────────────────────────────────────────────────────────┐
│  Client surfaces: Consumer app · Merchant tools · Admin ·   │
│                    Public API                                │
├─────────────────────────────────────────────────────────────┤
│  Product domains:                                            │
│    Wallet · P2P Marketplace · Exchange · Merchant ·          │
│    Commerce/Shop · Cards · Rewards · Support                 │
├─────────────────────────────────────────────────────────────┤
│  Financial core:                                             │
│    LEDGER (immutable, double-entry)                          │
│    Escrow engine · Settlement · Treasury · Fees · Revenue    │
├─────────────────────────────────────────────────────────────┤
│  Trust & control:                                            │
│    KYC · Compliance · Risk · Security · Audit                │
├─────────────────────────────────────────────────────────────┤
│  Rails:                                                       │
│    Blockchain/custody (multi-chain) · Local payment rails ·  │
│    Card issuing/networks · Notifications                     │
└─────────────────────────────────────────────────────────────┘
```

### 7.2 The Ledger Is the Source of Truth

The single most important architectural decision is that **money never bypasses the ledger.** Balances are *derived* from immutable double-entry journal entries, never set directly. Every movement of value — a deposit, a P2P escrow lock, a card settlement, a fee — writes balanced entries. This guarantees that the platform's books always reconcile and that any balance can be independently reconstructed from first principles. This is the discipline of a bank core, not a typical startup wallet.

### 7.3 Correctness by Construction

- **Idempotency.** Every money-moving operation carries an idempotency key, so retries and network failures can never double-spend or double-credit.
- **Concurrency safety.** State transitions occur under database row locks and guarded state machines, eliminating race conditions on shared balances and inventory.
- **Progressive rollout.** New money-moving behavior ships behind default-off feature flags, allowing safe, controlled activation.
- **Auditability.** State transitions are logged; a complete, append-only audit trail supports reconciliation, dispute resolution, and regulatory review.

### 7.4 Why the Architecture Scales

- **Modular domains.** Each product is a bounded context that can evolve, scale, and be reasoned about independently, enabling parallel team growth without architectural entropy.
- **Provider-agnostic rails.** Custody, card issuing, and payment rails sit behind contracts, so the Company can add or swap providers and geographies without rewriting the core.
- **Horizontal scalability.** The stateless application tier scales horizontally; the ledger is designed for high-throughput, partition-ready data growth; asynchronous processing handles settlement, notifications, and reconciliation off the critical path.
- **A single identity and balance** across products means new products are additive features on existing infrastructure, not new systems — each incremental product has a low marginal cost to ship and operate.

---

\newpage

## 8. Security

Security is treated as a core product attribute, not a feature. PoisaPay's design assumes it is custodying real value for users who have few alternatives and little tolerance for loss.

| Control | Purpose |
|---|---|
| Immutable double-entry ledger | Tamper-evident, always-reconcilable record of all value; balances derived, never mutated |
| Escrow engine | Value held in dedicated accounts during P2P; released only on valid, single-use transitions |
| Idempotency & row locks | Structural prevention of double-spend, replay, and race conditions |
| Encryption at rest & in transit | Sensitive data (including payment account details) encrypted; secrets isolated |
| Hot / cold wallet separation | Majority of custodial crypto held in cold storage; hot balances minimized and monitored |
| Custody hardening | Real on-chain deposit watching and withdrawal signing behind hardened contracts; reconciliation and verify-then-release discipline |
| Fraud monitoring | Behavioral and velocity monitoring; suspicious-activity detection feeding the risk engine |
| KYC / identity | Tiered verification gating limits and higher-risk features |
| AML & sanctions screening | Denylist/sanctions checks on parties before value moves |
| Audit logs | Append-only activity trail across all money paths for forensics and compliance |
| Recovery & continuity | Reconstruction of balances from ledger entries; backup and continuity procedures |

**Security posture summary.** The combination of a reconcilable ledger, escrow with single-use settlement, idempotent money paths, and hot/cold custody separation gives PoisaPay a materially stronger correctness and safety foundation than is typical for a company at this stage — a foundation that is expensive to retrofit and therefore a competitive asset.

---

\newpage

## 9. Compliance & Regulatory Strategy

Compliance is a strategic moat, not a cost center. In financial services, the ability to operate legally is itself a barrier to entry, and PoisaPay treats regulatory readiness as a core capability built into the platform from the ledger up.

### 9.1 The Compliance Stack

| Capability | Description |
|---|---|
| KYC | Tiered identity verification; higher tiers unlock higher limits and features |
| AML | Sanctions/denylist screening on counterparties before value moves |
| Transaction monitoring | Velocity limits, per-tier volume caps, and anomaly detection across money paths |
| Risk scoring | Pre-trade and per-transaction scoring that escalates to compliance review |
| Travel Rule readiness | Architecture designed to capture and transmit required originator/beneficiary information as regulations require |
| Dispute handling | Structured operator dispute resolution with evidence and audit trail |
| Audit & reporting | Complete, append-only records supporting regulatory reporting and examination |

### 9.2 Regulatory Approach

PoisaPay's strategy is to operate within the regulatory perimeter of each market it enters, sequencing licensing to match its geographic expansion. In its home market it operates under the applicable payment/e-money and money-services frameworks, and it structures products (for example, keeping fiat settlement peer-to-peer in P2P while only stablecoin value crosses the platform ledger) to align with regulatory expectations. A portion of the capital raised is earmarked specifically for licensing and compliance build-out ahead of regional expansion.

**Why this is defensible.** Every competitor entering these corridors must solve the same KYC/AML/monitoring/licensing problem. PoisaPay is building that capability as reusable infrastructure now, so that entering the next market is an incremental, not foundational, effort. Regulatory capability compounds — it is a moat that widens with each market cleared.

---

\newpage

## 10. Business Model & Revenue Streams

PoisaPay monetizes across the entire lifecycle of a user's money — earning, holding, converting, and spending — rather than depending on any single fee. This produces a resilient, blended take rate that increases as users deepen engagement, and it insulates the business from pressure on any individual line.

### 10.1 Revenue Architecture

```
    EARN ──────► HOLD ──────► CONVERT ──────► SPEND
     │            │             │              │
 Merchant     Treasury /     Exchange      Card interchange
 fees         float income   spread        + card FX margin
 Commerce     (Subscription  P2P trading   Cross-border
 take rate     & premium      fees          margin
               plans span     Deposit /
               the whole      withdrawal
               lifecycle)     fees
```

### 10.2 Revenue Streams in Detail

For each stream: mechanism, willingness to pay, indicative gross margin, and scalability. Margins are indicative of the software-driven nature of the business and will vary by geography, mix, and provider terms.

**A. Exchange spread (USD ↔ USDT).**
Mechanism: an embedded spread on instant in-app conversions against treasury inventory. Why users pay: instant, guaranteed liquidity and price certainty versus the effort and variance of P2P. Indicative gross margin: very high (software-priced). Scalability: excellent — no counterparty matching, scales with balance velocity. Future: FX across additional currency pairs as corridors open.

**B. P2P trading fees.**
Mechanism: a taker fee (in basis points) on the escrowed crypto at release. Why users pay: escrow protection, reputation, and dispute recourse they cannot get informally. Indicative gross margin: high. Scalability: scales directly with marketplace liquidity and the network effect. Future: premium seller tools, featured placement, express trade.

**C. Deposit fees.**
Mechanism: selective fees on certain funding rails (many on-ramps kept free to drive acquisition). Why users pay: convenience of specific rails. Indicative gross margin: moderate (rail costs passed partly through). Scalability: scales with funnel; deliberately minimized at top of funnel.

**D. Withdrawal fees.**
Mechanism: a percentage/fixed fee on off-ramp to local rails or on-chain. Why users pay: liquidity out of the platform. Indicative gross margin: moderate-to-high depending on rail. Scalability: scales with volume; balances retention (encouraging users to keep value in-platform).

**E. Merchant acceptance fees.**
Mechanism: a percentage fee on payments accepted via checkout, invoices, links, and API. Why users pay: the ability to accept payment at all, plus reconciliation and tooling. Indicative gross margin: high. Scalability: excellent — recurring merchant volume; grows with the merchant network.

**F. Commerce take rate (Sales Pages / Shop).**
Mechanism: a take rate on digital-product and membership sales through hosted storefronts. Why users pay: owning the storefront and settlement in one place. Indicative gross margin: high. Scalability: excellent; deepens primary-account status.

**G. Subscription plans / premium accounts.**
Mechanism: monthly/annual fees for higher limits, lower fees, advanced tools, priority support, and premium card tiers. Why users pay: professional users optimize cost and capability. Indicative gross margin: very high. Scalability: excellent — predictable recurring revenue that improves LTV and retention visibility.

**H. Virtual card fees.**
Mechanism: issuance and/or maintenance fees on cards. Why users pay: access to global online spending. Indicative gross margin: moderate-to-high. Scalability: good; anchors the spend loop.

**I. Card interchange.**
Mechanism: a share of interchange on card spend. Why it exists: standard network economics. Indicative gross margin: high (net of scheme costs). Scalability: scales directly with card spend volume.

**J. Card FX margin.**
Mechanism: a margin on currency conversion at the point of card spend. Why users pay: convenience of spending a dollar balance globally. Indicative gross margin: very high. Scalability: excellent.

**K. Treasury / float income.**
Mechanism: yield on custodial balances and treasury inventory, managed conservatively within policy. Why it exists: the economics of holding balances. Indicative gross margin: very high (net of policy constraints). Scalability: scales linearly with balances held — a direct dividend of retention.

**L. Cross-border payment margin.**
Mechanism: margin on international money movement between users and corridors. Why users pay: dramatically cheaper and faster than remittance operators. Indicative gross margin: high. Scalability: excellent as corridors expand.

**M. API usage / business accounts / enterprise.**
Mechanism: usage-based and seat-based pricing for businesses embedding PoisaPay or operating at scale. Why they pay: programmatic access and enterprise controls. Indicative gross margin: high. Scalability: excellent — expands ACV per account.

**N. Dormant account fees.**
Mechanism: modest maintenance fees on long-inactive accounts, within regulatory limits. Indicative gross margin: high. Scalability: modest; a minor line.

**O. Affiliate & referral revenue.**
Mechanism: revenue share from partners (tools, services) surfaced to the user base. Indicative gross margin: very high. Scalability: good; leverages distribution.

**P. Advertising / featured placement.**
Mechanism: sponsored placement for merchants and P2P advertisers. Indicative gross margin: very high. Scalability: good at marketplace scale.

### 10.3 Future Revenue Streams (Optionality)

These are not in the base case but represent significant expansion optionality once scale, data, and licenses are in place:

- **Lending / working capital** to merchants and sellers, underwritten on platform transaction history.
- **Payroll** for agencies and remote teams paying contributors.
- **Full banking** services (deposits, yield accounts) under appropriate licenses.
- **White-label / Banking-as-a-Service** — offering PoisaPay's rails to other platforms.
- **B2B APIs & embedded finance** — becoming the infrastructure layer for other emerging-market fintechs.

### 10.4 Revenue Mix — Illustrative Evolution

| Revenue line | Early stage | At scale |
|---|---:|---:|
| Exchange spread | 30% | 20% |
| P2P trading fees | 25% | 15% |
| Withdrawal / deposit | 20% | 10% |
| Merchant + commerce | 10% | 22% |
| Cards (interchange + FX + fees) | 5% | 18% |
| Subscriptions / premium | 5% | 8% |
| Treasury / float | 3% | 5% |
| Other (API, affiliate, ads) | 2% | 2% |

*Illustrative. The mix diversifies over time from transaction-led toward recurring merchant, commerce, card, and subscription revenue — improving quality of earnings and margin.*

---

\newpage

## 11. Unit Economics

The business is designed around low-cost acquisition, high retention, and expanding revenue per user as the product footprint deepens.

### 11.1 Illustrative Unit-Economic Model

| Metric | Illustrative value | Rationale |
|---|---:|---|
| Blended CAC | USD 3 – 6 | Referral- and network-led acquisition; low paid-media dependence in target markets |
| First-year revenue / active user | USD 15 – 25 | Transaction fees + exchange + early card/merchant attach |
| Mature revenue / active user | USD 40 – 70 | Deeper mix: cards, merchant, subscriptions, commerce |
| Estimated LTV (3-yr, discounted) | USD 45 – 110 | Retention × expanding ARPU × gross margin |
| LTV / CAC | > 10x | Low CAC against multi-year, multi-product monetization |
| CAC payback | < 6 months | Early transaction revenue recovers acquisition cost quickly |
| Contribution margin (mature) | 60% – 70% | Software-priced revenue net of rail and provider costs |

*Illustrative model. Actuals to be validated with cohort data as the platform scales.*

### 11.2 The Retention Engine

Retention is structural rather than promotional:

- **A persistent balance** gives users a reason to return and reduces churn.
- **The card and storefront** embed PoisaPay into the user's spending and earning workflow.
- **P2P reputation and trading history** cannot be ported to a competitor.
- **Saved payment methods, favorites, and merchant relationships** raise switching costs.

### 11.3 The Marketplace Flywheel and Its Economic Consequence

```
   Low CAC (referral + network)
            │
            ▼
   Funded, retained wallets ──► More P2P liquidity
            ▲                          │
            │                          ▼
   Expanding ARPU per user  ◄── More products attached
   (cards, merchant, subs)       (spend, earn, hold)
            │
            ▼
   High LTV / CAC ──► Efficient, compounding growth
```

Because acquisition is network-led and monetization deepens over the customer's life, the model compounds: each retained cohort subsidizes the next, and the marketplace's liquidity advantage lowers the cost of acquiring the following cohort.

---

\newpage

## 12. Market Opportunity (TAM / SAM / SOM)

PoisaPay sits at the intersection of several large, fast-growing markets: the global freelance/creator economy, cross-border digital payments, and stablecoin settlement.

### 12.1 Market Context (Directional — Sourced)

The following are directional market anchors, each attributed to the primary public source an analyst would consult. Exact current values vary by definition, methodology, and reporting period and must be verified against the latest edition of each source during diligence. Where a precise figure is not independently verifiable here, a qualitative direction is stated rather than a fabricated number.

- **Freelance / independent-work economy.** Estimates of the global independent, gig, and platform workforce range widely by definition — from the tens of millions in formal online-platform work to over one billion when broad self-employment and informal work are included. *[Sources: International Labour Organization (ILO) reports on platform/informal work; World Bank digital-jobs research. Definitions differ; cite the specific measure at diligence.]* South and Southeast Asia hold a large and growing share of the online-freelancing supply.
- **Cross-border payments.** Global cross-border payment flows are measured in the tens of trillions of dollars annually across all segments, with the SME and consumer digital-services segments most relevant to PoisaPay being a smaller but high-margin subset. *[Sources: Bank for International Settlements (BIS) / CPMI; McKinsey Global Payments Report — verify latest editions.]*
- **Remittances to low- and middle-income countries.** The World Bank / KNOMAD reports annual remittance flows to LMICs in the several-hundred-billion-dollar range, with Bangladesh consistently among the top remittance-recipient countries globally. *[Source: World Bank / KNOMAD Migration and Development Brief — verify latest edition.]*
- **Stablecoin usage.** Reported multi-trillion-dollar annual adjusted stablecoin transfer volumes, with grassroots adoption concentrated in emerging markets. *[Sources: Chainalysis; Visa Onchain Analytics; Allium — directional, methodology-dependent.]*
- **Mobile money / distribution.** Registered mobile-money accounts number in the billions globally, led by South Asia and Sub-Saharan Africa; Bangladesh has among the highest mobile-money penetration via bKash and Nagad. *[Source: GSMA State of the Industry Report on Mobile Money — verify latest edition.]*

*All external figures above are third-party estimates provided for orientation. The Company does not warrant third-party data and recommends independent verification against the cited primary sources as part of due diligence.*

### 12.2 TAM / SAM / SOM Framework

```
   ┌───────────────────────────────────────────────┐
   │  TAM  — Global emerging-market digital earners │
   │        & SMEs needing cross-border financial   │
   │        services (hundreds of millions of       │
   │        users; multi-hundred-$B revenue pool)   │
   │   ┌───────────────────────────────────────┐    │
   │   │  SAM — South & Southeast Asia digital  │    │
   │   │  earners, sellers & SMEs reachable     │    │
   │   │  with PoisaPay's product & licensing   │    │
   │   │  roadmap (tens of millions of users)   │    │
   │   │   ┌───────────────────────────────┐    │    │
   │   │   │  SOM — Users PoisaPay can      │    │    │
   │   │   │  realistically capture in the  │    │    │
   │   │   │  5-year plan (single-digit     │    │    │
   │   │   │  millions of active users)     │    │    │
   │   │   └───────────────────────────────┘    │    │
   │   └───────────────────────────────────────┘    │
   └───────────────────────────────────────────────┘
```

| Layer | Definition | Illustrative scale |
|---|---|---|
| TAM | Global emerging-market digital earners and SMEs needing hold/convert/receive/spend financial services | Hundreds of millions of users; multi-hundred-billion-dollar annual revenue pool |
| SAM | South and Southeast Asia earners/sellers/SMEs reachable within the product and licensing roadmap | Tens of millions of users; tens of billions of dollars in annual financial-services spend |
| SOM (5-yr) | The share PoisaPay's base case targets by Year 5 | Single-digit millions of active users; the revenue modeled in Section 18 |

### 12.3 Why Bangladesh First

Bangladesh is an ideal beachhead: a very large, young, smartphone-connected population; one of the world's largest freelancer and remittance-receiving economies (per World Bank / KNOMAD); deep mobile-money penetration via bKash and Nagad (per GSMA); acute demand for dollar access; and incumbents that serve the market poorly or not at all. Success here validates the model for the broader region, which shares the same structural characteristics.

### 12.4 Sources & Methodology

This section relies on the following categories of primary public source, each of which a diligence team can independently consult. The Company presents third-party data as directional and does not represent any external figure as its own verified statistic.

| Domain | Primary public sources |
|---|---|
| Remittances & remittance cost | World Bank / KNOMAD (Migration and Development Brief; Remittance Prices Worldwide) |
| Independent/gig/informal work | International Labour Organization (ILO); World Bank digital-jobs research |
| Cross-border payments | Bank for International Settlements (BIS) / CPMI; McKinsey Global Payments Report |
| Stablecoin volume & adoption | Chainalysis (Global Crypto Adoption Index); Visa Onchain Analytics; Allium |
| Mobile money & distribution | GSMA (State of the Industry Report on Mobile Money) |

**Methodology note.** Market figures are used only to frame the opportunity, not to derive the revenue model. The financial projections in Section 18 are built bottom-up from user, activation, volume, and take-rate assumptions — not top-down from any market-size figure — so the model's validity does not depend on the precision of external market estimates.

---

\newpage

## 13. Competitive Landscape

No single competitor offers PoisaPay's combination of emerging-market accessibility and full-stack product completeness. Competitors fall into three groups: global money-movement players, payment/commerce platforms, and crypto-native ramps.

### 13.1 Feature & Positioning Comparison

| Company | Core model | Emerging-market access | USD/USDT hold | P2P liquidity | Merchant/commerce | Cards | Key weakness vs. PoisaPay |
|---|---|---|---|---|---|---|---|
| **PoisaPay** | Financial OS (wallet + P2P + exchange + merchant + cards) | Native | Yes | Yes | Yes | Yes | Early stage / scale |
| Wise | Cross-border transfers, multi-currency | Restricted corridors | Multi-currency | No | Limited | Yes | Excludes many target users; not a business OS |
| Payoneer | Cross-border receivables for SMBs | Partial, gated | USD receiving | No | Limited | Yes | Onboarding friction; narrow; weak local ramps |
| Stripe | Developed-market payment processing | Largely unavailable in target markets | No | No | Yes | Issuing | Not available to most target sellers |
| PayPal | Consumer/merchant payments | Restricted; frozen-funds reputation | Limited | No | Yes | Yes | Poor emerging-market UX; trust issues |
| Revolut | Consumer neobank | Developed markets | Multi-currency | No | Limited | Yes | Not open to target residents |
| RedotPay | Crypto card / wallet | Yes | USDT | Limited | No | Yes | Narrow; lacks marketplace, merchant, commerce |
| Binance P2P | Crypto exchange P2P | Yes | Crypto | Yes | No | No | Exchange-only; no business toolkit; regulatory pressure |
| Mercury | Startup business banking | US-centric | USD | No | Limited | Yes | Not accessible to target market |
| Airwallex | Global business payments/treasury | Mid-market/enterprise | Multi-currency | No | Yes | Yes | Up-market; not built for individual emerging-market earners |

### 13.2 Positioning

```
   Product completeness (business OS)
        ▲
   High │            PoisaPay ●
        │        Airwallex ○      Stripe ○
        │   Mercury ○
        │
        │        Payoneer ○   Wise ○   Revolut ○
        │
        │   RedotPay ○       Binance P2P ○
    Low │
        └───────────────────────────────────────►
          Low          Emerging-market access        High
```

PoisaPay occupies the upper-right quadrant that no competitor holds: high product completeness combined with genuine emerging-market accessibility. The global platforms have completeness but not access; the crypto ramps have access but not completeness.

### 13.3 Competitive Dynamics

- **Against global platforms:** PoisaPay wins on access, local rails, and a unified business toolkit that they do not offer to these users.
- **Against crypto ramps:** PoisaPay wins on product depth (merchant acceptance, storefronts, compliant cards, invoicing) and on being a regulated financial platform rather than an exchange feature.
- **Against local players:** PoisaPay wins on breadth, correctness/compliance discipline, and a multi-product flywheel that a single-product local competitor cannot match.

---

\newpage

## 14. Why PoisaPay Wins: Moats & Network Effects

Sustainable advantage comes from four reinforcing sources.

**1. Two-sided marketplace network effects.** The P2P marketplace is a liquidity network with increasing returns. Liquidity attracts liquidity; reputation data accumulates and cannot be exported; the best-priced, fastest-filling market is self-reinforcing. This is the same dynamic that made incumbent P2P markets durable — captured inside a full platform.

**2. The wallet ecosystem and cross-sell.** Because every product shares one balance and identity, each product lowers the cost of adopting the next. A P2P buyer becomes a payment recipient becomes a cardholder becomes a merchant — with no new onboarding. Cross-sell efficiency is a structural cost advantage no single-product competitor can match.

**3. Switching costs and stickiness.** A persistent dollar balance, an issued card embedded in a user's subscriptions, a storefront that is the user's point of sale, saved payment methods and counterparties, and a hard-won P2P reputation together create high switching costs. Users do not leave their primary financial account casually.

**4. Compliance and correctness as infrastructure.** The immutable ledger, escrow discipline, KYC/AML capability, and licensing readiness are expensive and slow to build and cannot be shortcut. Every market cleared and every capability built widens the moat and lowers the marginal cost of the next market — the opposite of a commodity business.

```
   Network effects ──┐
                     ├──► Primary financial
   Wallet cross-sell ┤     account status ──► Durable
                     │     (low CAC, high         economic
   Switching costs ──┤      LTV, pricing          advantage
                     │      power)
   Compliance moat ──┘
```

**Low acquisition cost + global expandability.** Network-led acquisition keeps CAC low, and the provider-agnostic, modular architecture means the same platform expands to a new country primarily through licensing and local rails — not a rebuild. The result is a business that gets cheaper to grow and harder to displace as it scales.

---

\newpage

## 15. Go-to-Market & Growth Strategy

Growth follows a disciplined beachhead-then-expand sequence, each phase de-risking the next.

### 15.1 Phased Expansion

```
   Phase 1 (Y1–Y2)         Phase 2 (Y2–Y4)         Phase 3 (Y4+)
   ───────────────         ───────────────         ─────────────
   BANGLADESH              SOUTH ASIA              GLOBAL EMERGING
   Beachhead: prove        Adjacent corridors      MARKETS
   the flywheel, unit      sharing the same        Replicate the
   economics, and          structural profile;     playbook across
   compliance in the       reuse compliance &      Southeast Asia,
   home market.            product infra.          Africa, LatAm.
```

**Phase 1 — Bangladesh (beachhead).** Prove the full flywheel and unit economics in a single, large, high-demand market. Focus channels: freelancer and creator communities, referral loops, mobile-money integration, and merchant/creator partnerships. Objective: primary-account status for a growing base of active users and validated cohort economics.

**Phase 2 — South Asia (expansion).** Extend to adjacent corridors that share Bangladesh's characteristics — large young populations, high freelancer density, strong dollar demand, mobile-money rails. Leverage the compliance and product infrastructure already built; localize rails and language.

**Phase 3 — Global emerging markets (scale).** Replicate the playbook across Southeast Asia, Africa, and Latin America. By this phase, the compliance engine, multi-provider rails, and multi-product platform make each new market an incremental deployment rather than a new build.

### 15.2 Go-to-Market Motions

- **Community and referral-led acquisition.** The target users cluster in identifiable communities (freelancer groups, creator networks, seller communities). Referral incentives leverage the closed-loop network (free internal transfers) to drive viral, low-CAC growth.
- **Merchant and creator partnerships.** Onboarding creators and merchants brings their audiences and customers onto the platform — a built-in distribution channel.
- **Product-led growth.** Free internal transfers, free receiving, and a compelling P2P price make the product self-evidently useful; users invite counterparties to transact with them.
- **Content and education.** Establishing PoisaPay as the trusted authority on getting paid and managing money as a digital earner in these markets.

---

\newpage

## 16. SWOT Analysis

| **Strengths** | **Weaknesses** |
|---|---|
| Full-stack product no competitor matches in-market | Early stage; limited operating history and brand |
| Bank-grade ledger, escrow, and correctness discipline | Pre-scale liquidity in a two-sided marketplace |
| Multiple compounding revenue streams; resilient take rate | Capital-intensive licensing and compliance build ahead |
| Structural network effects and high switching costs | Dependence on third-party rails and providers (mitigated by abstraction) |
| Low-CAC, network-led acquisition | Requires disciplined execution across many products |

| **Opportunities** | **Threats** |
|---|---|
| Massive, underserved, fast-growing emerging-market user base | Regulatory change in crypto/stablecoins and payments |
| Stablecoins becoming mainstream settlement infrastructure | Well-capitalized incumbents entering target corridors |
| Expansion into lending, payroll, banking, BaaS | Fraud, cybersecurity, and liquidity-management risk |
| Becoming the embedded-finance layer for other fintechs | Stablecoin/counterparty (issuer) risk |
| Regional and then global replication of the playbook | Macroeconomic and FX volatility in target markets |

---

\newpage

## 17. Business Model Canvas

```
┌──────────────────┬──────────────────┬───────────────────┬──────────────────┬──────────────────┐
│ KEY PARTNERS     │ KEY ACTIVITIES   │ VALUE             │ CUSTOMER         │ CUSTOMER         │
│                  │                  │ PROPOSITIONS      │ RELATIONSHIPS    │ SEGMENTS         │
│ • Local payment  │ • Ledger &       │                   │                  │                  │
│   rails (mobile  │   settlement ops │ Hold dollars,     │ • Self-serve app │ • Freelancers &  │
│   money, banks)  │ • P2P liquidity  │ trade safely,     │ • Community &    │   remote workers │
│ • Card issuers   │   & trust/safety │ get paid, spend   │   referral       │ • Online sellers │
│ • Blockchain /   │ • Compliance,    │ globally — one    │ • Merchant       │   & creators     │
│   custody        │   KYC/AML, risk  │ regulated,        │   success        │ • Agencies &     │
│ • KYC/identity   │ • Product        │ mobile-first      │ • Support &      │   SaaS founders  │
│   providers      │   engineering    │ platform for the  │   dispute ops    │ • Crypto users   │
│ • Compliance /   │ • Treasury &     │ emerging-market   │                  │ • Int'l SMEs     │
│   licensing      │   liquidity mgmt │ digital business  ├──────────────────┤                  │
│                  ├──────────────────┤                   │ CHANNELS         │                  │
│                  │ KEY RESOURCES    │                   │ • Mobile app     │                  │
│                  │ • The ledger &   │                   │ • Web            │                  │
│                  │   platform       │                   │ • API            │                  │
│                  │ • Licenses &     │                   │ • Partner /      │                  │
│                  │   compliance     │                   │   creator        │                  │
│                  │ • Liquidity /    │                   │   distribution   │                  │
│                  │   treasury       │                   │                  │                  │
│                  │ • Engineering    │                   │                  │                  │
│                  │   talent & data  │                   │                  │                  │
├──────────────────┴──────────────────┴───────────────────┴──────────────────┴──────────────────┤
│ COST STRUCTURE                                    │ REVENUE STREAMS                             │
│ • Engineering & product                           │ • Exchange spread  • P2P fees               │
│ • Compliance, licensing & legal                   │ • Deposit/withdrawal • Merchant & commerce  │
│ • Rail, provider & network costs                  │ • Card interchange & FX  • Subscriptions    │
│ • Customer support & risk operations              │ • Treasury/float  • Cross-border margin     │
│ • Customer acquisition (low, network-led)         │ • API/enterprise  • Affiliate/ads           │
└───────────────────────────────────────────────────┴─────────────────────────────────────────────┘
```

---

\newpage

## 18. Financial Projections

The following five-year model is an **illustrative base case** built on the assumptions below. It is intended to demonstrate the shape and scalability of the business, not to represent reported results or a forecast. A prospective investor should stress-test these assumptions against diligence data.

### 18.1 Key Assumptions

| Driver | Assumption |
|---|---|
| Geographic phasing | Bangladesh (Y1–2) → South Asia (Y2–4) → broader emerging markets (Y4+) |
| Registered → active conversion | ~33%–40% monthly active over the plan |
| Blended take rate on volume | ~1.0%–1.3% of platform GMV |
| Gross margin | Expands from ~54% to ~68% as mix shifts to higher-margin card, merchant, subscription, and treasury revenue |
| CAC | USD 3–6 blended; network- and referral-led |
| Opex | Scales with headcount (engineering, compliance, support, growth) and licensing; front-loaded ahead of revenue |

### 18.2 Base-Case Projection

| USD, unless noted | Y1 (2026) | Y2 (2027) | Y3 (2028) | Y4 (2029) | Y5 (2030) |
|---|---:|---:|---:|---:|---:|
| Registered users (M) | 0.06 | 0.30 | 1.10 | 3.20 | 7.50 |
| Monthly active users (M) | 0.02 | 0.11 | 0.44 | 1.25 | 3.00 |
| Platform volume / GMV ($M) | 40 | 340 | 1,650 | 6,200 | 16,500 |
| Blended take rate | 1.25% | 1.06% | 1.15% | 1.16% | 1.02% |
| Net revenue ($M) | 0.5 | 3.6 | 19 | 72 | 168 |
| Gross margin (%) | 54% | 60% | 63% | 66% | 68% |
| Gross profit ($M) | 0.3 | 2.2 | 12.0 | 47.5 | 114.2 |
| Operating expenses ($M) | 2.3 | 6.2 | 15.0 | 41.5 | 100.2 |
| EBITDA ($M) | (2.0) | (4.0) | (3.0) | 6.0 | 14.0 |
| EBITDA margin | — | — | — | 8% | 8% |

*Illustrative base case. Modeled figures, not actuals. Rounding applied.*

### 18.3 Revenue Bridge (Illustrative, $M)

```
   Y1  0.5  ▏
   Y2  3.6  ▏▏▏▏
   Y3  19   ▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏
   Y4  72   ▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏... (72)
   Y5  168  ▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏▏... (168)
```

### 18.4 Path to Profitability

The model front-loads investment in engineering, compliance, and licensing (Years 1–3), with the business crossing into EBITDA profitability in Year 4 as revenue scales against a more slowly growing cost base and gross margin expands with mix. This is a capital-efficient trajectory relative to the addressable opportunity: the marginal cost of serving an additional user is low because the platform and compliance infrastructure are shared across all products and geographies.

### 18.5 Scenario Sensitivity (Directional)

| Scenario | Y5 revenue vs. base | Driver |
|---|---:|---|
| Downside | ~0.5x | Slower activation, tighter regulation, thinner take rate |
| Base | 1.0x | Assumptions as stated |
| Upside | ~1.7x | Faster regional expansion, higher card/merchant attach, treasury tailwind |

---

\newpage

## 19. Investment Highlights

Framed for an investment committee, the case for PoisaPay rests on seven pillars.

**1. Why now.** Four structural shifts — decentralized work, stablecoins as settlement rails, mobile-first distribution, and an incumbent gap — have converged to open a first-mover window in a category that does not yet have a clear winner.

**2. Why this market.** The emerging-market digital earner and SME segment is enormous, fast-growing, high-margin, and profoundly underserved. It is precisely the segment global incumbents exclude and crypto ramps only half-serve.

**3. Why this product.** A full-stack financial operating system — wallet, P2P, exchange, merchant, commerce, cards — on a single ledger. Product completeness combined with emerging-market access is a quadrant no competitor occupies.

**4. Why defensible.** Two-sided network effects, wallet cross-sell, high switching costs, and compliance-as-infrastructure compound into a widening moat and a business that gets cheaper to grow and harder to displace with scale.

**5. Why scalable.** A modular, provider-agnostic architecture means new products are additive and new geographies are incremental. The blended, multi-stream revenue model strengthens as users deepen engagement.

**6. Why profitable.** Software-priced revenue lines (exchange spread, card FX, treasury, subscriptions) drive gross margin toward the high-60s, with a credible path to EBITDA profitability by Year 4 in the base case.

**7. Why this foundation.** Bank-grade correctness — an immutable double-entry ledger, idempotent money paths, escrow with single-use settlement, and hot/cold custody — is rare at this stage and expensive to retrofit. It is both a risk-mitigant and a competitive asset.

**The synthesis.** PoisaPay is a rare combination of a very large underserved market, a defensible multi-product platform, structural network effects, disciplined financial engineering, and a capital-efficient path to profitability — the profile of a business that can compound into a multi-hundred-million-dollar franchise and a category-defining outcome.

---

\newpage

## 20. Risk Factors & Mitigation

The Company assesses and actively manages the following principal risks.

| Risk | Description | Mitigation |
|---|---|---|
| **Regulatory** | Evolving rules for stablecoins, crypto, e-money, and cross-border payments in target markets | Operate within each market's regulatory perimeter; sequence licensing with expansion; structure products (e.g., peer-to-peer fiat settlement) to align with rules; dedicated compliance capital and counsel |
| **Compliance / financial crime** | KYC/AML failures, sanctions exposure, illicit use | Tiered KYC, sanctions/denylist screening, transaction monitoring, risk scoring, audit trails, Travel-Rule-ready architecture; ongoing program investment |
| **Liquidity / treasury** | Insufficient exchange/treasury liquidity; asset-liability mismatch | Conservative treasury policy; inventory management; hot/cold custody separation; matching of custodial obligations |
| **Stablecoin / counterparty** | Reliance on third-party stablecoin issuers and their peg/solvency | Diversification over time; conservative reserves; monitoring; ability to support multiple assets/chains |
| **Fraud** | P2P fraud, chargeback-style disputes, account takeover | Escrow with single-use settlement; risk engine; velocity/volume limits; dispute operations; device and behavioral monitoring |
| **Cybersecurity** | Breach, key compromise, funds theft | Encryption, secrets isolation, hot/cold separation, hardened custody, audit logging, continuity/recovery procedures |
| **Competition** | Incumbents or well-funded entrants targeting the same corridors | Network-effect and switching-cost moats; speed to liquidity; compliance lead; product completeness |
| **Execution** | Delivering many products across multiple markets | Modular architecture; disciplined phasing; experienced engineering with bank-grade practices |
| **Macro / FX** | Currency volatility and macro instability in target markets | Dollar-denominated core; hedging where appropriate; geographic diversification over time |

**Risk posture.** No fintech is risk-free; PoisaPay's differentiator is that its core risks are addressed structurally — in the ledger, the escrow engine, the compliance stack, and the custody design — rather than bolted on. This materially lowers the probability and severity of the failure modes that most damage financial startups.

---

\newpage

## 21. Roadmap

| Horizon | Objectives |
|---|---|
| **12 months** | Scale Bangladesh; deepen P2P liquidity and merchant acceptance; broaden card issuance; validate cohort unit economics; advance home-market licensing; harden fraud/risk operations |
| **24 months** | Enter first South Asia expansion corridors; launch business/enterprise accounts and API; expand subscription/premium tiers; build treasury capability; obtain expansion-market licenses |
| **36 months** | Multi-market South Asia presence; mature merchant and commerce ecosystem; introduce first credit/working-capital pilots; reach EBITDA breakeven trajectory |
| **5 years** | Broad emerging-market footprint (Southeast Asia, Africa, LatAm entry); lending, payroll, and banking-adjacent products; white-label / embedded-finance offering; category leadership in target corridors |

```
  Y1 ─────────► Y2 ─────────► Y3 ─────────► Y5
  Beachhead     Regional      Ecosystem     Multi-region
  & liquidity   expansion     & first       platform +
  depth         + business    credit        embedded
                accounts      products      finance
```

---

\newpage

## 22. Exit Opportunities

PoisaPay's asset profile — a licensed, multi-product financial platform with proprietary liquidity, a large user base, and defensible network effects in high-growth corridors — is attractive to multiple strategic acquirers and viable as a public company at scale.

### 22.1 Strategic Acquirers

| Acquirer archetype | Examples | Strategic rationale |
|---|---|---|
| Global money-movement platforms | Wise, Payoneer, Airwallex | Instant emerging-market access, local rails, and a user base they cannot easily reach organically |
| Payments & commerce networks | Stripe, PayPal | Distribution into markets they do not serve; a ready merchant and creator ecosystem |
| Crypto / stablecoin infrastructure | Coinbase, Circle | A compliant, real-economy stablecoin distribution and settlement franchise in emerging markets |
| Business banking / neobanks | Mercury, Revolut | Emerging-market expansion and a full SME financial stack |
| Regional & global banks | Large banks in Asia/Gulf | Digital-native customer acquisition and modern rails in high-growth geographies |

### 22.2 IPO Potential

At scale — millions of active users, hundreds of millions in revenue, positive EBITDA, and a diversified, high-margin revenue mix — PoisaPay would fit the profile of a publicly listed fintech. Emerging-market financial platforms with genuine network effects and a path to durable profitability have attracted premium public-market valuations. A regional listing or a major-exchange IPO becomes a credible path in the later phases of the plan.

### 22.3 Why Acquirers Pay a Premium

Acquirers pay for what is hard to build: a licensed footprint, proprietary P2P liquidity and reputation data, a multi-product user base with high switching costs, and bank-grade financial infrastructure. These are precisely the assets PoisaPay is compounding — and precisely what a strategic buyer cannot quickly replicate.

---

\newpage

## 23. The Ask: Use of Funds

The Company is raising a **Series A round of USD 6.0 million** (final structure, valuation, and terms to be agreed in process) to convert its technical foundation and beachhead into regional scale.

### 23.1 Illustrative Use of Funds

| Allocation | % | Purpose |
|---|---:|---|
| Product & engineering | 35% | Deepen the platform; scale reliability and throughput; ship merchant, card, and commerce enhancements |
| Licensing & compliance | 20% | Home-market and expansion-market licensing; compliance program, monitoring, and legal |
| Growth & marketplace liquidity | 20% | Community/referral acquisition, creator/merchant partnerships, and liquidity/treasury seeding |
| Operations, risk & support | 15% | Fraud/risk operations, dispute handling, customer support at scale |
| Treasury & working capital | 10% | Liquidity buffer and general working capital |

### 23.2 What This Round Achieves

- Establishes a validated, liquidity-rich flywheel and cohort economics in Bangladesh.
- Secures the licensing and compliance foundation required for regional expansion.
- Advances merchant, commerce, and card products from foundation to scale.
- Positions the Company to raise a subsequent growth round from a position of demonstrated traction and a credible path to profitability.

### 23.3 Milestones to Next Round

| Milestone | Target |
|---|---|
| Active-user base | Materially grown, retained cohorts with validated LTV/CAC |
| Marketplace liquidity | Self-sustaining P2P depth and competitive pricing |
| Revenue | On the base-case trajectory toward Year 3 |
| Compliance | Home-market license progress and expansion-market groundwork |
| Product | Merchant, commerce, and card products at scale in-market |

---

\newpage

## 24. Appendices

### Appendix A — Glossary

| Term | Definition |
|---|---|
| Ledger | Immutable, double-entry record from which all balances are derived |
| Escrow | Value held in a dedicated account during a P2P trade, released only on a valid terminal transition |
| GMV / platform volume | Total value transacted across the platform (P2P, exchange, merchant, transfers) |
| Take rate | Net revenue as a percentage of platform volume |
| On/off-ramp | Moving value between fiat/local rails and the platform (and blockchain) |
| Spread | The margin embedded in an exchange conversion price |
| Interchange | The share of card transaction fees accruing to the issuer side |
| KYC / AML | Know-Your-Customer identity verification / Anti-Money-Laundering controls |
| Hot / cold wallet | Online (operational) vs. offline (secure) custody of crypto assets |
| CAC / LTV | Customer acquisition cost / lifetime value |

### Appendix B — Basis of Preparation

To support due-diligence scrutiny, information in this document is presented in three clearly distinguished tiers:

1. **Third-party public data (external facts).** Market-context figures (Sections 2, 3, 12) are attributed inline to named reputable primary sources — World Bank / KNOMAD, ILO, BIS/CPMI, McKinsey, Chainalysis, Visa Onchain Analytics, Allium, and GSMA. These are third-party estimates, are methodology- and period-dependent, and should be independently verified against the latest editions of the cited sources. The Company does not warrant third-party data and does not present any external figure as its own.
2. **Directional Company estimates.** A small number of ranges (for example, the illustrative all-in cost of the fragmented stack) are the Company's own estimates, explicitly labeled as such, and are not represented as cited statistics.
3. **Company assumptions and projections.** The financial model (Section 18), unit economics (Section 11), and use of funds (Section 23) are forward-looking constructions built bottom-up on the assumptions stated in-line. They are illustrative, are not reported actuals or a forecast, and should be stress-tested against Company diligence data. Operating metrics presented as targets represent management objectives, not results.

The financial model does not depend on the precision of any external market figure; it is derived bottom-up from user, activation, volume, and take-rate assumptions. This document does not constitute an offer of securities, investment advice, or a guarantee of performance.

### Appendix C — Diligence Materials Available on Request

- Detailed financial model and assumptions workbook
- Product and technical architecture deep-dive
- Compliance and licensing status and roadmap
- Cohort and unit-economics data (as available)
- Cap table and proposed round terms
- Security and custody design documentation

---

*End of Memorandum. Confidential.*
