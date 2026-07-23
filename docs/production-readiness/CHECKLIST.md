# PoisaPay Production-Readiness Task Checklist

This checklist grounds every task in the real state of the PoisaPay codebase (Laravel 12, PostgreSQL, Redis/Horizon, TRON custody live, EVM simulated, card-issuing provider abstraction). The ledger core is already strong (double-entry, DB trigger, exact NUMERIC(38,0) money, idempotency, reserve-before-sign); the tasks below focus on the true gaps: EVM liveness, KMS custody, off-ramp, live rates, real screening, DevOps/DR, and legal.

Legend — Priority: P0 launch blocker / P1 pre-launch / P2 post-launch / P3 nice-to-have. Difficulty: S / M / L / XL. Risk: 🟢 low / 🟡 medium / 🔴 high.

---

## 1. Core Wallet & Custody

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| CW-001 | Replace EnvSeedSignerKeyProvider with AWS KMS-backed SignerKeyProvider behind the existing SignerKeyProvider interface | P0 | L | 1w | — | ☐ Todo | 🔴 | Security |
| CW-002 | Migrate the master HD seed out of ENV into KMS/HSM-managed envelope encryption with key rotation policy | P0 | L | 1w | CW-001 | ☐ Todo | 🔴 | Security |
| CW-003 | Implement per-request signer key decryption with in-memory-only lifetime (never log, never persist plaintext) | P0 | M | 3d | CW-001 | ☐ Todo | 🔴 | Security |
| CW-004 | Add HSM/KMS access audit trail for every signing operation with request correlation ID | P0 | M | 2d | CW-001 | ☐ Todo | 🔴 | Security |
| CW-005 | Load-test the balance-integrity DB trigger under 10k concurrent postings to confirm debit=credit enforcement holds | P1 | M | 3d | — | ☐ Todo | 🟡 | Backend |
| CW-006 | Add property-based tests for Money NUMERIC(38,0) arithmetic (no float leakage, overflow, negative-guard) | P1 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| CW-007 | Verify idempotency-key uniqueness under concurrent duplicate submits via DB-level unique constraint + race test | P1 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| CW-008 | Audit AccountResolver coin-pooling for cross-asset mixups; add invariant test that pooled balance = sum of chain sub-balances | P1 | M | 3d | — | ☐ Todo | 🔴 | Backend |
| CW-009 | Add reconciliation job comparing on-chain custody balances vs ledger treasury balances per asset daily | P0 | L | 1w | — | ☐ Todo | 🔴 | Backend |
| CW-010 | Alert + auto-freeze withdrawals when custody-vs-ledger drift exceeds configurable threshold | P0 | M | 3d | CW-009 | ☐ Todo | 🔴 | Backend |
| CW-011 | Implement cold-wallet on-chain sweep for TRON hot-wallet balances above threshold | P0 | L | 1w | CW-001 | ☐ Todo | 🔴 | Backend |
| CW-012 | Implement cold-wallet sweep policy config (threshold, destination address whitelist, dual-approval) | P0 | M | 3d | CW-011 | ☐ Todo | 🔴 | Backend |
| CW-013 | Add cold-wallet address rotation and multi-sig support for treasury destinations | P1 | L | 1w | CW-011 | ☐ Todo | 🔴 | Security |
| CW-014 | Implement reorg detection and auto-retry for TRON deposit confirmations | P0 | L | 4d | — | ☐ Todo | 🔴 | Backend |
| CW-015 | Implement orphan-transaction detection and re-scan for missed TRON deposits | P0 | M | 3d | CW-014 | ☐ Todo | 🔴 | Backend |
| CW-016 | Add configurable confirmation-depth per asset before crediting deposits | P0 | S | 1d | — | ☐ Todo | 🟡 | Backend |
| CW-017 | Add explorer-link generation (TronScan / Etherscan / BscScan) for every on-chain tx surfaced to users/admin | P2 | S | 1d | — | ☐ Todo | 🟢 | Backend |
| CW-018 | Add TronGrid failover to a secondary RPC/indexer endpoint with health-based routing | P0 | M | 3d | — | ☐ Todo | 🔴 | Backend |
| CW-019 | Add rate-limit / backoff handling and API-key rotation for TronGrid | P1 | S | 1d | — | ☐ Todo | 🟡 | Backend |
| CW-020 | Implement deposit-address gap-limit scanning to catch deposits to un-watched derived addresses | P1 | M | 3d | — | ☐ Todo | 🟡 | Backend |
| CW-021 | Add withdrawal fee-estimation (energy/bandwidth for TRON) and reserve fee at sign time | P0 | M | 3d | — | ☐ Todo | 🔴 | Backend |
| CW-022 | Handle TRON energy/bandwidth exhaustion: auto-stake or fee-in-TRX fallback for USDT withdrawals | P0 | L | 4d | CW-021 | ☐ Todo | 🔴 | Backend |
| CW-023 | Add withdrawal broadcast retry with idempotent nonce/tx-hash tracking (no double-spend) | P0 | M | 3d | — | ☐ Todo | 🔴 | Backend |
| CW-024 | Add stuck-withdrawal detection (broadcast but unconfirmed > N min) with alert and manual-rebroadcast tool | P1 | M | 3d | CW-023 | ☐ Todo | 🔴 | Backend |
| CW-025 | Verify reserve-before-sign path cannot be bypassed; add regression test for reserve→sign→broadcast ordering | P0 | M | 2d | — | ☐ Todo | 🔴 | Backend |
| CW-026 | Add per-asset hot-wallet minimum-balance monitoring with top-up alert to treasury ops | P1 | S | 1d | — | ☐ Todo | 🟡 | SRE |
| CW-027 | Implement withdrawal batching for TRON to reduce fee cost where safe | P2 | M | 3d | CW-021 | ☐ Todo | 🟢 | Backend |
| CW-028 | Add dust-deposit filtering and configurable minimum deposit credit threshold | P2 | S | 1d | — | ☐ Todo | 🟢 | Backend |
| CW-029 | Add address-derivation determinism test: same index → same address across restarts and key providers | P1 | S | 1d | CW-001 | ☐ Todo | 🟡 | Backend |
| CW-030 | Encrypt derived-address→user mapping metadata at rest and restrict PII exposure in logs | P1 | M | 2d | — | ☐ Todo | 🟡 | Security |
| CW-031 | Add custody balance snapshot job for point-in-time audit/reconciliation history | P1 | M | 2d | CW-009 | ☐ Todo | 🟡 | Backend |
| CW-032 | Add signed proof-of-reserves report generation per asset for internal/audit use | P2 | M | 3d | CW-031 | ☐ Todo | 🟡 | Backend |
| CW-033 | Add withdrawal velocity limits per user/asset with configurable windows | P0 | M | 3d | — | ☐ Todo | 🔴 | Backend |
| CW-034 | Add global daily withdrawal cap with circuit-breaker that halts signing when exceeded | P0 | M | 3d | CW-033 | ☐ Todo | 🔴 | Backend |
| CW-035 | Add manual "custody kill-switch" to freeze all signing operations instantly (feature flag + audit) | P0 | S | 1d | — | ☐ Todo | 🔴 | Security |
| CW-036 | Add unit + integration tests for the deposit watcher credit path (confirm→ledger post idempotency) | P1 | M | 3d | — | ☐ Todo | 🟡 | Backend |
| CW-037 | Add end-to-end testnet flow test (derive → deposit → confirm → withdraw → broadcast) in CI | P1 | L | 4d | OPS-010 | ☐ Todo | 🟡 | QA |
| CW-038 | Document custody key ceremony, backup, and recovery runbook (seed shards, quorum) | P0 | M | 3d | CW-002 | ☐ Todo | 🔴 | Security |
| CW-039 | Implement seed backup/restore drill and verify address continuity post-restore | P0 | M | 3d | CW-038 | ☐ Todo | 🔴 | Security |
| CW-040 | Add per-asset enable/disable feature flags for deposits and withdrawals independently | P1 | S | 1d | — | ☐ Todo | 🟡 | Backend |
| CW-041 | Add memo/tag support where required and reject deposits missing required memo | P2 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| CW-042 | Add signing-service isolation (separate process/network segment) from web tier | P0 | L | 1w | CW-001 | ☐ Todo | 🔴 | Security |
| CW-043 | Add mutual-TLS or signed-request auth between app tier and signing service | P0 | M | 3d | CW-042 | ☐ Todo | 🔴 | Security |
| CW-044 | Load-test deposit watcher throughput at expected peak block rate with backpressure | P1 | M | 3d | — | ☐ Todo | 🟡 | Backend |
| CW-045 | Add chaos test: kill watcher mid-scan and confirm no missed/double credits on restart | P1 | M | 3d | CW-036 | ☐ Todo | 🟡 | QA |
| CW-046 | Add per-user address reuse policy and rotation for privacy on deposit addresses | P3 | M | 2d | — | ☐ Todo | 🟢 | Backend |

---

## 2. Multi-chain / EVM Liveness

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| EVM-001 | Implement live EVM deposit watcher for Ethereum (block/log polling, ERC-20 Transfer events) | P0 | XL | 2w | CW-001 | ☐ Todo | 🔴 | Backend |
| EVM-002 | Implement live EVM deposit watcher for BSC (BEP-20 Transfer events) | P0 | L | 1w | EVM-001 | ☐ Todo | 🔴 | Backend |
| EVM-003 | Implement EVM withdrawal signer (secp256k1) for ETH/BSC behind the existing signer interface | P0 | L | 1w | CW-001 | ☐ Todo | 🔴 | Backend |
| EVM-004 | Implement EVM nonce management per hot-wallet with gap/stuck-nonce recovery | P0 | L | 4d | EVM-003 | ☐ Todo | 🔴 | Backend |
| EVM-005 | Implement EIP-1559 gas estimation (base+priority) with max-fee caps per chain | P0 | M | 3d | EVM-003 | ☐ Todo | 🔴 | Backend |
| EVM-006 | Handle native-gas funding for ERC-20 withdrawals (hot-wallet must hold ETH/BNB for gas) | P0 | M | 3d | EVM-003 | ☐ Todo | 🔴 | Backend |
| EVM-007 | Implement EVM reorg detection and confirmation-depth per chain | P0 | L | 4d | EVM-001 | ☐ Todo | 🔴 | Backend |
| EVM-008 | Implement EVM stuck-tx rebroadcast / fee-bump (replace-by-fee) tooling | P1 | M | 3d | EVM-004 | ☐ Todo | 🔴 | Backend |
| EVM-009 | Add RPC provider abstraction with failover across two providers (e.g. Alchemy/Infura/self-hosted) | P0 | M | 3d | EVM-001 | ☐ Todo | 🔴 | Backend |
| EVM-010 | Add RPC health checks, latency monitoring, and automatic provider switchover | P1 | M | 2d | EVM-009 | ☐ Todo | 🟡 | SRE |
| EVM-011 | Implement per-token contract-address allowlist per chain (reject unknown tokens) | P0 | S | 1d | EVM-001 | ☐ Todo | 🔴 | Backend |
| EVM-012 | Handle token decimals correctly per contract when converting to Money units | P0 | M | 2d | EVM-001 | ☐ Todo | 🔴 | Backend |
| EVM-013 | Add EVM cold-wallet sweep for hot-wallet ERC-20/native balances above threshold | P0 | L | 4d | EVM-003 | ☐ Todo | 🔴 | Backend |
| EVM-014 | Add EVM deposit-address gap scanning for missed deposits | P1 | M | 3d | EVM-001 | ☐ Todo | 🟡 | Backend |
| EVM-015 | Add explorer-link generation for Etherscan/BscScan tx and address views | P2 | S | 1d | — | ☐ Todo | 🟢 | Backend |
| EVM-016 | Add failed-transaction (reverted) handling: do not credit, surface reason, no fee loss to user | P0 | M | 3d | EVM-003 | ☐ Todo | 🔴 | Backend |
| EVM-017 | Add contract-vs-EOA detection to avoid sending to non-recoverable contract addresses without warning | P1 | M | 2d | EVM-003 | ☐ Todo | 🟡 | Backend |
| EVM-018 | Add chain-ID validation to prevent cross-chain replay of signed txns | P0 | S | 1d | EVM-003 | ☐ Todo | 🔴 | Security |
| EVM-019 | Add per-chain enable/disable feature flags for deposits and withdrawals | P1 | S | 1d | — | ☐ Todo | 🟡 | Backend |
| EVM-020 | Add EVM gas-price ceiling circuit-breaker (pause withdrawals when gas spikes) | P1 | M | 2d | EVM-005 | ☐ Todo | 🟡 | Backend |
| EVM-021 | Add EVM testnet (Sepolia/BSC-testnet) end-to-end flow test in CI | P1 | L | 4d | EVM-001 | ☐ Todo | 🟡 | QA |
| EVM-022 | Add reconciliation of EVM on-chain balances vs ledger treasury per token daily | P0 | M | 3d | EVM-001, CW-009 | ☐ Todo | 🔴 | Backend |
| EVM-023 | Remove/replace all simulated EVM code paths and add guard preventing simulated mode in production | P0 | M | 2d | EVM-001, EVM-003 | ☐ Todo | 🔴 | Backend |
| EVM-024 | Add load test for EVM watcher at mainnet block/log volume with backpressure | P1 | M | 3d | EVM-001 | ☐ Todo | 🟡 | Backend |
| EVM-025 | Add pending-block / mempool visibility for faster deposit UX (optional soft-credit) | P3 | M | 3d | EVM-001 | ☐ Todo | 🟢 | Backend |
| EVM-026 | Add batch-withdrawal (multicall/disperse) support for EVM to reduce gas | P2 | L | 4d | EVM-003 | ☐ Todo | 🟢 | Backend |
| EVM-027 | Add EVM signing-service isolation parity with TRON (segmented process, mTLS) | P0 | M | 3d | CW-042 | ☐ Todo | 🔴 | Security |
| EVM-028 | Add per-chain minimum deposit + dust filtering for EVM tokens | P2 | S | 1d | EVM-001 | ☐ Todo | 🟢 | Backend |
| EVM-029 | Add monitoring dashboards for EVM watcher lag, RPC errors, and pending withdrawals | P1 | M | 2d | OBS-001 | ☐ Todo | 🟡 | SRE |
| EVM-030 | Document EVM operational runbook (nonce recovery, gas funding, reorg response) | P1 | M | 2d | EVM-004 | ☐ Todo | 🟡 | Backend |
| EVM-031 | Add USDC/USDT multi-chain canonical asset mapping validation across TRON/ETH/BSC | P1 | M | 2d | EVM-011 | ☐ Todo | 🟡 | Backend |

---

## 3. Payments & Off-ramp

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| PAY-001 | Implement Off-ramp action (crypto→fiat) end-to-end with ledger postings and reserve-before-payout | P0 | XL | 2w | — | ☐ Todo | 🔴 | Backend |
| PAY-002 | Integrate at least one fiat payout PSP/rail (e.g. bank/mobile-money provider) behind a PayoutProvider interface | P0 | XL | 2w | PAY-001 | ☐ Todo | 🔴 | Backend |
| PAY-003 | Implement PSP payout webhook handler with HMAC verification and idempotency | P0 | L | 4d | PAY-002 | ☐ Todo | 🔴 | Backend |
| PAY-004 | Wire WithdrawalMethod rails to concrete PSP configs (bank, mobile money, card-out) | P0 | L | 4d | PAY-002 | ☐ Todo | 🔴 | Backend |
| PAY-005 | Implement payout state machine (pending→processing→paid/failed/reversed) with ledger reversals | P0 | L | 4d | PAY-001 | ☐ Todo | 🔴 | Backend |
| PAY-006 | Implement payout failure/reversal handling with automatic ledger refund to user balance | P0 | M | 3d | PAY-005 | ☐ Todo | 🔴 | Backend |
| PAY-007 | Add off-ramp quote with rate + fee lock and expiry window | P0 | M | 3d | EXC-001 | ☐ Todo | 🔴 | Backend |
| PAY-008 | Add merchant cash-out (settlement to merchant fiat/crypto) end-to-end | P0 | L | 1w | PAY-002 | ☐ Todo | 🔴 | Backend |
| PAY-009 | Add off-ramp KYC-tier gating and per-tier fiat limits | P0 | M | 3d | KYC-001 | ☐ Todo | 🔴 | Compliance |
| PAY-010 | Add off-ramp beneficiary (bank/wallet) management with verification | P1 | M | 3d | PAY-004 | ☐ Todo | 🟡 | Backend |
| PAY-011 | Add P2P payment requests (request money) flow with accept/decline and expiry | P1 | L | 1w | — | ☐ Todo | 🟡 | Backend |
| PAY-012 | Add internal transfers between users (off-chain ledger transfer) with idempotency | P1 | M | 3d | — | ☐ Todo | 🟡 | Backend |
| PAY-013 | Add payment links / invoices for merchants and users | P2 | L | 1w | — | ☐ Todo | 🟢 | Backend |
| PAY-014 | Add recurring/scheduled payments framework | P3 | L | 1w | PAY-012 | ☐ Todo | 🟢 | Backend |
| PAY-015 | Add on-ramp (fiat→crypto) PSP integration parity with off-ramp | P1 | L | 1w | PAY-002 | ☐ Todo | 🟡 | Backend |
| PAY-016 | Add payout reconciliation job matching PSP settlement reports to ledger | P0 | M | 3d | PAY-003 | ☐ Todo | 🔴 | Backend |
| PAY-017 | Add payout retry with backoff for transient PSP failures | P1 | M | 2d | PAY-005 | ☐ Todo | 🟡 | Backend |
| PAY-018 | Add payout hold/review queue for flagged (AML) transactions before release | P0 | M | 3d | KYC-010 | ☐ Todo | 🔴 | Compliance |
| PAY-019 | Add per-corridor (country/currency) payout enable/disable and limits | P1 | M | 3d | PAY-004 | ☐ Todo | 🟡 | Backend |
| PAY-020 | Add off-ramp fee schedule (fixed + percentage + corridor) into fee engine | P0 | M | 3d | EXC-010 | ☐ Todo | 🔴 | Backend |
| PAY-021 | Add duplicate-payout prevention via idempotency + beneficiary+amount fingerprinting | P0 | M | 2d | PAY-005 | ☐ Todo | 🔴 | Backend |
| PAY-022 | Add payout status polling fallback when webhooks are delayed/missed | P1 | M | 2d | PAY-003 | ☐ Todo | 🟡 | Backend |
| PAY-023 | Add user-facing off-ramp receipt with rate, fee, and reference | P1 | S | 1d | PAY-001 | ☐ Todo | 🟢 | Frontend |
| PAY-024 | Add admin payout management (search, retry, force-fail, manual-mark-paid with audit) | P1 | M | 3d | PAY-005 | ☐ Todo | 🟡 | Backend |
| PAY-025 | Add payout limit velocity checks integrated with AML rules | P0 | M | 3d | KYC-010 | ☐ Todo | 🔴 | Compliance |
| PAY-026 | Add settlement account (float) balance monitoring per PSP with low-balance alerts | P1 | M | 2d | PAY-002 | ☐ Todo | 🟡 | SRE |
| PAY-027 | Add multi-PSP routing (choose cheapest/available rail per corridor) | P2 | L | 1w | PAY-004 | ☐ Todo | 🟢 | Backend |
| PAY-028 | Add refund flow for merchant payments (partial/full) with ledger reversal | P1 | M | 3d | — | ☐ Todo | 🟡 | Backend |
| PAY-029 | Add chargeback/dispute intake for fiat payouts | P2 | M | 3d | PAY-002 | ☐ Todo | 🟡 | Compliance |
| PAY-030 | Add end-to-end tests for off-ramp happy path + failure + reversal | P0 | M | 3d | PAY-006 | ☐ Todo | 🔴 | QA |
| PAY-031 | Add sandbox PSP mock provider for staging/testing | P1 | M | 2d | PAY-002 | ☐ Todo | 🟡 | Backend |
| PAY-032 | Add payout SLA tracking and stuck-payout alerting | P1 | M | 2d | PAY-005 | ☐ Todo | 🟡 | SRE |
| PAY-033 | Add currency-precision handling for fiat (2dp) vs crypto (native) conversions | P0 | M | 2d | PAY-007 | ☐ Todo | 🔴 | Backend |
| PAY-034 | Add off-ramp cancellation before PSP submission with reserve release | P1 | M | 2d | PAY-005 | ☐ Todo | 🟡 | Backend |
| PAY-035 | Load-test off-ramp queue throughput and PSP rate-limit handling | P1 | M | 3d | PAY-002 | ☐ Todo | 🟡 | QA |

---

## 4. Exchange / Rates / Fees / Revenue

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| EXC-001 | Replace StubRateProvider with a live rate feed (e.g. multiple sources + median) behind existing interface | P0 | L | 1w | — | ☐ Todo | 🔴 | Backend |
| EXC-002 | Add rate-feed redundancy with failover across two independent providers | P0 | M | 3d | EXC-001 | ☐ Todo | 🔴 | Backend |
| EXC-003 | Add rate staleness guard: block quotes/trades when feed age exceeds threshold | P0 | M | 2d | EXC-001 | ☐ Todo | 🔴 | Backend |
| EXC-004 | Add rate sanity/outlier detection (reject > X% deviation from prior tick) | P0 | M | 2d | EXC-001 | ☐ Todo | 🔴 | Backend |
| EXC-005 | Persist rate ticks for audit and dispute resolution | P1 | S | 1d | EXC-001 | ☐ Todo | 🟡 | Backend |
| EXC-006 | Add quote-lock (rate held for N seconds) with server-side expiry enforcement | P0 | M | 3d | EXC-001 | ☐ Todo | 🔴 | Backend |
| EXC-007 | Verify swap spread booking to FxSpreadIncome ledger account for every conversion | P0 | M | 2d | — | ☐ Todo | 🔴 | Backend |
| EXC-008 | Add configurable spread per asset pair and per user tier | P1 | M | 3d | EXC-007 | ☐ Todo | 🟡 | Backend |
| EXC-009 | Add slippage protection: reject fill if executable rate moved beyond tolerance | P1 | M | 2d | EXC-006 | ☐ Todo | 🟡 | Backend |
| EXC-010 | Centralize fee engine: fixed + percentage + tiered + corridor fees with precedence rules | P0 | L | 1w | — | ☐ Todo | 🔴 | Backend |
| EXC-011 | Add fee preview endpoint so UI shows exact fee before confirm | P1 | M | 2d | EXC-010 | ☐ Todo | 🟡 | Backend |
| EXC-012 | Book all fees to dedicated revenue ledger accounts (deposit/withdraw/swap/card) | P0 | M | 3d | EXC-010 | ☐ Todo | 🔴 | Backend |
| EXC-013 | Add revenue report by fee type, asset, corridor, and period | P1 | M | 3d | EXC-012 | ☐ Todo | 🟡 | Backend |
| EXC-014 | Add minimum/maximum trade size per pair with tier gating | P1 | S | 1d | EXC-006 | ☐ Todo | 🟡 | Backend |
| EXC-015 | Add rate-feed monitoring + alerting (feed down, stale, outlier) | P0 | M | 2d | EXC-001, OBS-001 | ☐ Todo | 🔴 | SRE |
| EXC-016 | Add exposure/inventory tracking per asset to manage FX risk | P2 | L | 1w | EXC-007 | ☐ Todo | 🟡 | Backend |
| EXC-017 | Add auto-hedging hooks or manual rebalance alerts for large exposure | P3 | L | 1w | EXC-016 | ☐ Todo | 🟢 | Backend |
| EXC-018 | Add historical rate charting data endpoint for UI | P2 | S | 1d | EXC-005 | ☐ Todo | 🟢 | Backend |
| EXC-019 | Add fee/spread change audit logging with effective-from timestamps | P1 | S | 1d | EXC-010 | ☐ Todo | 🟡 | Backend |
| EXC-020 | Add unit tests for fee engine precedence and rounding edge cases | P1 | M | 2d | EXC-010 | ☐ Todo | 🟡 | QA |
| EXC-021 | Add tests confirming spread income invariants (income = user-rate − market-rate) | P1 | M | 2d | EXC-007 | ☐ Todo | 🟡 | QA |
| EXC-022 | Add per-currency rounding policy configuration (bankers vs floor) with tests | P1 | M | 2d | EXC-010 | ☐ Todo | 🟡 | Backend |
| EXC-023 | Add promotional/zero-fee campaign support with expiry and eligibility | P3 | M | 3d | EXC-010 | ☐ Todo | 🟢 | Product |
| EXC-024 | Add rate-provider cost/usage monitoring to control API spend | P2 | S | 1d | EXC-001 | ☐ Todo | 🟢 | SRE |
| EXC-025 | Load-test quote/swap path for latency and consistency under burst | P1 | M | 3d | EXC-006 | ☐ Todo | 🟡 | QA |
| EXC-026 | Add reconciliation of revenue accounts vs computed fees per day | P1 | M | 3d | EXC-012 | ☐ Todo | 🟡 | Backend |

---

## 5. Card Issuing

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| CARD-001 | Verify and correct the ~7 unverified Marqeta field-path TODOs against live sandbox responses | P0 | M | 3d | — | ☐ Todo | 🔴 | Backend |
| CARD-002 | Complete Marqeta JIT funding gateway integration and test against sandbox | P0 | L | 1w | CARD-001 | ☐ Todo | 🔴 | Backend |
| CARD-003 | Add integration tests for AuthorizeCardAction reuse across Marqeta JIT path | P0 | M | 3d | CARD-002 | ☐ Todo | 🔴 | QA |
| CARD-004 | Make card funding asset configurable (remove hardcoded USDT) with per-program config | P0 | M | 3d | — | ☐ Todo | 🔴 | Backend |
| CARD-005 | Implement card transaction sync cron (pull settled/cleared txns, reconcile holds) | P0 | L | 4d | CARD-002 | ☐ Todo | 🔴 | Backend |
| CARD-006 | Implement authorization-hold lifecycle (hold→clear→release/expire) with ledger reserves | P0 | L | 4d | CARD-005 | ☐ Todo | 🔴 | Backend |
| CARD-007 | Implement dispute-resolution action (open, evidence, provider submit, credit/deny) | P0 | L | 1w | CARD-005 | ☐ Todo | 🔴 | Backend |
| CARD-008 | Add separate ATM withdrawal limit distinct from POS/purchase limit | P1 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| CARD-009 | Implement PIN verification (not just hashing) for PIN-required flows | P1 | M | 3d | — | ☐ Todo | 🔴 | Security |
| CARD-010 | Add PIN change / reset flow with strong auth and rate limiting | P1 | M | 3d | CARD-009 | ☐ Todo | 🟡 | Backend |
| CARD-011 | Implement standalone Visa direct issuing driver behind CardProviderInterface | P2 | XL | 3w | — | ☐ Todo | 🟡 | Backend |
| CARD-012 | Implement standalone Mastercard issuing driver behind CardProviderInterface | P2 | XL | 3w | — | ☐ Todo | 🟢 | Backend |
| CARD-013 | Add card issuance flow (KYC-gated) with virtual + physical options | P1 | L | 1w | KYC-001 | ☐ Todo | 🟡 | Backend |
| CARD-014 | Add physical card fulfillment integration (shipping, activation) | P2 | L | 1w | CARD-013 | ☐ Todo | 🟢 | Backend |
| CARD-015 | Add card freeze/unfreeze and terminate flows synced to provider | P1 | M | 3d | CARD-002 | ☐ Todo | 🟡 | Backend |
| CARD-016 | Add card spend limits (daily/monthly/per-tx) enforced at JIT authorization | P0 | M | 3d | CARD-006 | ☐ Todo | 🔴 | Backend |
| CARD-017 | Add merchant-category (MCC) controls and blocklists per card | P1 | M | 3d | CARD-006 | ☐ Todo | 🟡 | Backend |
| CARD-018 | Add country/region controls for card usage | P1 | M | 2d | CARD-006 | ☐ Todo | 🟡 | Backend |
| CARD-019 | Add 3DS / SCA support for online card transactions | P1 | L | 1w | CARD-002 | ☐ Todo | 🔴 | Backend |
| CARD-020 | Add card-provider webhook verification (signature) and idempotent processing | P0 | M | 3d | CARD-002 | ☐ Todo | 🔴 | Backend |
| CARD-021 | Handle JIT decline reasons and surface actionable messages to user | P1 | M | 2d | CARD-006 | ☐ Todo | 🟡 | Backend |
| CARD-022 | Add insufficient-funds / FX-conversion handling at authorization time | P0 | M | 3d | CARD-004, EXC-006 | ☐ Todo | 🔴 | Backend |
| CARD-023 | Add card FX fee and cross-border fee to fee engine | P1 | M | 2d | EXC-010 | ☐ Todo | 🟡 | Backend |
| CARD-024 | Add reconciliation of card provider settlement vs ledger holds/clears daily | P0 | M | 3d | CARD-005 | ☐ Todo | 🔴 | Backend |
| CARD-025 | Add card statement generation (monthly) with transactions and fees | P2 | M | 3d | CARD-005 | ☐ Todo | 🟢 | Backend |
| CARD-026 | Add card transaction notifications (auth, decline, refund) via notification channels | P1 | M | 2d | NOT-001 | ☐ Todo | 🟡 | Backend |
| CARD-027 | Add sensitive card data (PAN/CVV) handling via provider-hosted PCI-scoped views only | P0 | M | 3d | CARD-002 | ☐ Todo | 🔴 | Security |
| CARD-028 | Ensure no PAN/CVV/PIN ever logged or persisted in app DB (audit + tests) | P0 | M | 2d | CARD-027 | ☐ Todo | 🔴 | Security |
| CARD-029 | Add card program config (BIN, currency, limits) as first-class config | P1 | M | 2d | CARD-004 | ☐ Todo | 🟡 | Backend |
| CARD-030 | Add refund/credit handling from provider (reverse holds, credit balance) | P1 | M | 3d | CARD-005 | ☐ Todo | 🟡 | Backend |
| CARD-031 | Add partial-reversal and incremental-authorization handling | P1 | M | 3d | CARD-006 | ☐ Todo | 🟡 | Backend |
| CARD-032 | Add card top-up / funding flow from wallet balance with ledger transfer | P1 | M | 3d | CARD-004 | ☐ Todo | 🟡 | Backend |
| CARD-033 | Add per-card driver selection persistence validation and migration safety | P1 | S | 1d | — | ☐ Todo | 🟡 | Backend |
| CARD-034 | Add mock-provider parity tests to guarantee interface contract across drivers | P1 | M | 3d | — | ☐ Todo | 🟡 | QA |
| CARD-035 | Add card lifecycle admin panel (search, view, freeze, dispute, adjust limits) | P1 | M | 3d | CARD-015 | ☐ Todo | 🟡 | Backend |
| CARD-036 | Add card fraud/velocity rules integrated with AML/monitoring | P1 | L | 1w | KYC-010 | ☐ Todo | 🔴 | Compliance |
| CARD-037 | Add card issuance/usage KYC-tier enforcement | P0 | M | 2d | KYC-001 | ☐ Todo | 🔴 | Compliance |
| CARD-038 | Add card provider outage handling / degraded-mode messaging | P1 | M | 2d | CARD-002 | ☐ Todo | 🟡 | Backend |
| CARD-039 | Add card BIN sponsor/program-manager compliance documentation | P1 | M | 2d | LEG-001 | ☐ Todo | 🟡 | Compliance |
| CARD-040 | Add end-to-end card flow test (issue→fund→authorize→settle→dispute) | P1 | L | 4d | CARD-007 | ☐ Todo | 🟡 | QA |
| CARD-041 | Add card enable/disable global feature flag | P1 | S | 1d | — | ☐ Todo | 🟡 | Backend |
| CARD-042 | Add card expiry/renewal handling and reissue flow | P2 | M | 3d | CARD-013 | ☐ Todo | 🟢 | Backend |
| CARD-043 | Add card holder-name / billing-address management synced to provider | P2 | M | 2d | CARD-013 | ☐ Todo | 🟢 | Backend |
| CARD-044 | Add card decline analytics dashboard (reasons, rates) | P2 | M | 2d | CARD-021 | ☐ Todo | 🟢 | Backend |
| CARD-045 | Load-test JIT authorization path for latency SLA (< provider timeout) | P0 | M | 3d | CARD-006 | ☐ Todo | 🔴 | QA |
| CARD-046 | Add PCI-DSS SAQ scope assessment for card program | P0 | M | 3d | CARD-027 | ☐ Todo | 🔴 | Security |

---

## 6. Security & Auth

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| SEC-001 | Enable SESSION_ENCRYPT and enforce it in production config | P0 | S | 2h | — | ☐ Todo | 🔴 | Security |
| SEC-002 | Enforce HTTPS-only, HSTS, secure+httponly+samesite cookies in production | P0 | S | 4h | — | ☐ Todo | 🔴 | Security |
| SEC-003 | Implement passkeys (WebAuthn/FIDO2) as an MFA/login option | P1 | L | 1w | — | ☐ Todo | 🟡 | Security |
| SEC-004 | Implement suspicious-login / geo-anomaly detection (new country/IP/device) | P0 | L | 1w | SEC-020 | ☐ Todo | 🔴 | Security |
| SEC-005 | Add step-up auth on anomalous logins (force MFA / email confirm) | P0 | M | 3d | SEC-004 | ☐ Todo | 🔴 | Security |
| SEC-006 | Implement withdrawal new-address cooldown (hold N hours on first use) | P0 | M | 3d | — | ☐ Todo | 🔴 | Backend |
| SEC-007 | Implement withdrawal address whitelist with enforcement toggle per user | P0 | M | 3d | SEC-006 | ☐ Todo | 🔴 | Backend |
| SEC-008 | Add whitelist add/change cooldown + email/MFA confirmation | P0 | M | 3d | SEC-007 | ☐ Todo | 🔴 | Security |
| SEC-009 | Implement anti-phishing code shown in all outbound emails | P1 | M | 2d | — | ☐ Todo | 🟡 | Security |
| SEC-010 | Require email verification on sensitive changes (email, password, 2FA, payout method) | P0 | M | 3d | — | ☐ Todo | 🔴 | Security |
| SEC-011 | Implement user login-history view (time, IP, device, location) | P1 | M | 2d | SEC-020 | ☐ Todo | 🟡 | Backend |
| SEC-012 | Add password strength policy + breach-password check (HIBP k-anonymity) | P1 | M | 2d | — | ☐ Todo | 🟡 | Security |
| SEC-013 | Add account-lockout / progressive delay on brute force | P0 | M | 2d | — | ☐ Todo | 🔴 | Security |
| SEC-014 | Add TOTP recovery-code review: single-use, hashed, regenerate flow | P1 | S | 1d | — | ☐ Todo | 🟡 | Security |
| SEC-015 | Enforce CSRF on all state-changing routes and verify coverage | P0 | S | 1d | — | ☐ Todo | 🔴 | Security |
| SEC-016 | Add security headers (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy) | P0 | M | 2d | — | ☐ Todo | 🔴 | Security |
| SEC-017 | Add CSP nonce support for inline scripts (Alpine) and eliminate unsafe-inline | P1 | M | 3d | SEC-016 | ☐ Todo | 🟡 | Frontend |
| SEC-018 | Audit and lock down file-upload handling (KYC docs): type, size, AV scan, private storage | P0 | M | 3d | — | ☐ Todo | 🔴 | Security |
| SEC-019 | Add antivirus/malware scanning for all uploaded documents | P1 | M | 3d | SEC-018 | ☐ Todo | 🟡 | Security |
| SEC-020 | Add device fingerprint enrichment (UA, IP geo) to sessions for anomaly checks | P1 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| SEC-021 | Add session revocation "log out all devices" and per-device revoke | P1 | S | 1d | — | ☐ Todo | 🟡 | Backend |
| SEC-022 | Add idle + absolute session timeout policy with re-auth | P1 | S | 1d | — | ☐ Todo | 🟡 | Security |
| SEC-023 | Encrypt sensitive PII columns at rest (national ID, DOB, address) | P0 | M | 3d | — | ☐ Todo | 🔴 | Security |
| SEC-024 | Add secrets management (Vault/SSM) — remove all secrets from .env in prod | P0 | L | 1w | — | ☐ Todo | 🔴 | Security |
| SEC-025 | Rotate and scope all API keys/tokens; enforce least privilege | P0 | M | 3d | SEC-024 | ☐ Todo | 🔴 | Security |
| SEC-026 | Add rate limiting on auth endpoints (login, 2FA, password reset, OTP) | P0 | S | 1d | — | ☐ Todo | 🔴 | Security |
| SEC-027 | Add CAPTCHA / bot protection on signup and login | P1 | M | 2d | — | ☐ Todo | 🟡 | Security |
| SEC-028 | Add admin-panel IP allowlist / VPN-gating option | P1 | M | 2d | — | ☐ Todo | 🟡 | Security |
| SEC-029 | Enforce separate admin MFA requirement (mandatory for admin guard) | P0 | M | 2d | — | ☐ Todo | 🔴 | Security |
| SEC-030 | Add admin action re-auth for high-risk operations (freeze, payout, KMS) | P0 | M | 2d | — | ☐ Todo | 🔴 | Security |
| SEC-031 | Add RBAC review: verify config/permissions least-privilege and gap-test | P1 | M | 3d | — | ☐ Todo | 🟡 | Security |
| SEC-032 | Add dual-control (maker-checker) for withdrawals/payouts above threshold | P0 | L | 1w | — | ☐ Todo | 🔴 | Backend |
| SEC-033 | Add tamper-evident verification job for immutable AuditLog (hash chain check) | P1 | M | 3d | — | ☐ Todo | 🟡 | Security |
| SEC-034 | Add hash-chaining to AuditLog if not present (each row includes prev-hash) | P1 | M | 3d | — | ☐ Todo | 🔴 | Backend |
| SEC-035 | Add dependency vulnerability scanning (composer audit, npm audit) in CI | P0 | S | 1d | OPS-002 | ☐ Todo | 🔴 | Security |
| SEC-036 | Add SAST (static analysis / semgrep) in CI | P1 | M | 2d | OPS-002 | ☐ Todo | 🟡 | Security |
| SEC-037 | Add secret-scanning pre-commit + CI (gitleaks) | P0 | S | 1d | OPS-002 | ☐ Todo | 🔴 | Security |
| SEC-038 | Add DAST/security scan against staging | P1 | M | 3d | OPS-010 | ☐ Todo | 🟡 | Security |
| SEC-039 | Add WAF in front of production (rate limit, OWASP rules) | P0 | M | 3d | OPS-020 | ☐ Todo | 🔴 | Security |
| SEC-040 | Add DDoS protection at edge (CDN/Cloudflare) | P1 | M | 2d | OPS-020 | ☐ Todo | 🟡 | SRE |
| SEC-041 | Add security incident response plan and runbook | P0 | M | 3d | — | ☐ Todo | 🔴 | Security |
| SEC-042 | Add responsible-disclosure / bug-bounty policy and intake | P2 | M | 2d | — | ☐ Todo | 🟡 | Security |
| SEC-043 | Add mass-assignment audit on all models (fillable/guarded) | P1 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| SEC-044 | Add IDOR/authorization tests on all user-scoped resources | P0 | M | 3d | — | ☐ Todo | 🔴 | QA |
| SEC-045 | Add SSRF protection on outbound calls (RPC/webhook/PSP) with allowlists | P1 | M | 2d | — | ☐ Todo | 🟡 | Security |
| SEC-046 | Add secure error handling (no stack traces / debug in prod) verification | P0 | S | 4h | — | ☐ Todo | 🔴 | Backend |

---

## 7. KYC / AML / KYT / Compliance

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| KYC-001 | Verify KYC-tier enforcement gates all limits (deposit/withdraw/card/off-ramp) | P0 | M | 3d | — | ☐ Todo | 🔴 | Compliance |
| KYC-002 | Integrate a document-verification / IDV provider (ID scan, MRZ, authenticity) | P0 | L | 1w | — | ☐ Todo | 🔴 | Compliance |
| KYC-003 | Integrate liveness / selfie-match verification | P0 | L | 1w | KYC-002 | ☐ Todo | 🔴 | Compliance |
| KYC-004 | Replace stub screening with real sanctions (OFAC/UN/EU) screening provider | P0 | L | 1w | — | ☐ Todo | 🔴 | Compliance |
| KYC-005 | Add PEP screening integration | P0 | L | 4d | KYC-004 | ☐ Todo | 🔴 | Compliance |
| KYC-006 | Add adverse-media screening integration | P1 | M | 3d | KYC-004 | ☐ Todo | 🟡 | Compliance |
| KYC-007 | Add ongoing / periodic re-screening of existing customers | P0 | M | 3d | KYC-004 | ☐ Todo | 🔴 | Compliance |
| KYC-008 | Add screening-hit case creation and review workflow with disposition audit | P0 | M | 3d | KYC-004 | ☐ Todo | 🔴 | Compliance |
| KYC-009 | Implement persistent blacklist model (users, addresses, docs, devices) | P0 | M | 3d | — | ☐ Todo | 🔴 | Compliance |
| KYC-010 | Implement KYT (crypto transaction monitoring) provider integration for deposit/withdrawal risk scoring | P0 | L | 1w | — | ☐ Todo | 🔴 | Compliance |
| KYC-011 | Add on-chain address risk screening (mixers, sanctioned addresses, darknet) pre-credit/pre-payout | P0 | L | 1w | KYC-010 | ☐ Todo | 🔴 | Compliance |
| KYC-012 | Add auto-hold/quarantine for high-risk incoming deposits pending review | P0 | M | 3d | KYC-011 | ☐ Todo | 🔴 | Compliance |
| KYC-013 | Implement Travel Rule architecture (originator/beneficiary data exchange) | P0 | XL | 2w | — | ☐ Todo | 🔴 | Compliance |
| KYC-014 | Integrate a Travel Rule protocol/provider (e.g. TRP/IVMS101 messaging) | P0 | L | 1w | KYC-013 | ☐ Todo | 🔴 | Compliance |
| KYC-015 | Add country-risk scoring and geo-fencing (block/restrict high-risk & sanctioned jurisdictions) | P0 | M | 3d | — | ☐ Todo | 🔴 | Compliance |
| KYC-016 | Add IP/geolocation vs declared-country mismatch checks | P1 | M | 2d | KYC-015 | ☐ Todo | 🟡 | Compliance |
| KYC-017 | Add customer risk-rating engine (KYC + geography + behavior) driving EDD | P0 | L | 1w | KYC-005 | ☐ Todo | 🔴 | Compliance |
| KYC-018 | Add Enhanced Due Diligence (EDD) workflow for high-risk customers | P0 | M | 3d | KYC-017 | ☐ Todo | 🔴 | Compliance |
| KYC-019 | Add source-of-funds / source-of-wealth collection for high-value users | P1 | M | 3d | KYC-018 | ☐ Todo | 🟡 | Compliance |
| KYC-020 | Add transaction-monitoring rules engine (structuring, velocity, thresholds) | P0 | L | 1w | — | ☐ Todo | 🔴 | Compliance |
| KYC-021 | Add rule-based alerting into existing case-management with tuning | P0 | M | 3d | KYC-020 | ☐ Todo | 🔴 | Compliance |
| KYC-022 | Verify SAR/STR workflow generates regulator-ready reports with required fields | P0 | M | 3d | — | ☐ Todo | 🔴 | Compliance |
| KYC-023 | Add regulatory report export formats (goAML / local FIU schema) | P1 | M | 3d | KYC-022 | ☐ Todo | 🟡 | Compliance |
| KYC-024 | Add Currency/Cash Transaction Report (CTR) thresholds where applicable | P1 | M | 2d | KYC-020 | ☐ Todo | 🟡 | Compliance |
| KYC-025 | Add whitelist model (trusted counterparties/addresses) with governance | P1 | M | 3d | KYC-009 | ☐ Todo | 🟡 | Compliance |
| KYC-026 | Verify account-freeze propagates to all money-movement paths atomically | P0 | M | 3d | — | ☐ Todo | 🔴 | Backend |
| KYC-027 | Add asset/partial freeze (freeze specific balance while allowing others) | P2 | M | 3d | KYC-026 | ☐ Todo | 🟡 | Backend |
| KYC-028 | Add KYC document retention + secure deletion per policy | P1 | M | 2d | LEG-005 | ☐ Todo | 🟡 | Compliance |
| KYC-029 | Add KYC data-minimization review (collect only what's needed) | P1 | M | 2d | — | ☐ Todo | 🟡 | Compliance |
| KYC-030 | Add re-KYC triggers (expiry, tier upgrade, risk change) | P1 | M | 3d | KYC-002 | ☐ Todo | 🟡 | Compliance |
| KYC-031 | Add duplicate-identity detection (same doc/face across accounts) | P1 | M | 3d | KYC-002 | ☐ Todo | 🟡 | Compliance |
| KYC-032 | Add underage / age-verification gate | P0 | S | 1d | KYC-002 | ☐ Todo | 🔴 | Compliance |
| KYC-033 | Add sanctioned-nationality / dual-nationality handling in onboarding | P1 | M | 2d | KYC-004 | ☐ Todo | 🟡 | Compliance |
| KYC-034 | Add beneficial-ownership (UBO) collection for business accounts | P1 | L | 1w | — | ☐ Todo | 🟡 | Compliance |
| KYC-035 | Add business KYB (Know Your Business) flow for merchants | P1 | L | 1w | KYC-034 | ☐ Todo | 🟡 | Compliance |
| KYC-036 | Add compliance case SLA tracking and aging alerts | P1 | M | 2d | — | ☐ Todo | 🟡 | Compliance |
| KYC-037 | Add four-eyes review for freeze/unfreeze and SAR filing | P0 | M | 2d | SEC-032 | ☐ Todo | 🔴 | Compliance |
| KYC-038 | Add audit trail for all screening decisions and overrides | P0 | M | 2d | KYC-008 | ☐ Todo | 🔴 | Compliance |
| KYC-039 | Add per-jurisdiction limit configuration matrix | P1 | M | 3d | KYC-015 | ☐ Todo | 🟡 | Compliance |
| KYC-040 | Add transaction-purpose collection for large/cross-border transfers | P1 | S | 1d | — | ☐ Todo | 🟡 | Compliance |
| KYC-041 | Add screening provider redundancy/failover | P1 | M | 3d | KYC-004 | ☐ Todo | 🟡 | Compliance |
| KYC-042 | Add false-positive management and rule-tuning workflow | P1 | M | 3d | KYC-021 | ☐ Todo | 🟡 | Compliance |
| KYC-043 | Add compliance dashboard (open cases, SAR pipeline, risk distribution) | P1 | M | 3d | KYC-021 | ☐ Todo | 🟡 | Compliance |
| KYC-044 | Add sanctions-list update automation and freshness monitoring | P0 | M | 2d | KYC-004 | ☐ Todo | 🔴 | Compliance |
| KYC-045 | Add record-keeping of screening evidence for regulator audit (immutable) | P0 | M | 2d | KYC-038 | ☐ Todo | 🔴 | Compliance |
| KYC-046 | Add customer off-boarding / account-closure compliance workflow | P1 | M | 3d | — | ☐ Todo | 🟡 | Compliance |
| KYC-047 | Add compliance training / attestation tracking for staff | P2 | S | 1d | — | ☐ Todo | 🟢 | Compliance |
| KYC-048 | Add independent AML program audit readiness checklist | P1 | M | 2d | — | ☐ Todo | 🟡 | Compliance |
| KYC-049 | Add sanctions/PEP screening at payout beneficiary level (not just customer) | P0 | M | 3d | KYC-004, PAY-010 | ☐ Todo | 🔴 | Compliance |
| KYC-050 | Add end-to-end tests for freeze, screening-hit hold, and SAR generation | P1 | M | 3d | KYC-022 | ☐ Todo | 🟡 | QA |
| KYC-051 | Appoint/assign MLRO and document compliance governance responsibilities | P0 | S | 1d | — | ☐ Todo | 🔴 | Compliance |

---

## 8. Legal & Policy Documents

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| LEG-001 | Draft and publish Terms of Service | P0 | M | 3d | — | ☐ Todo | 🔴 | Legal |
| LEG-002 | Draft and publish Privacy Policy (GDPR/CCPA-aligned) | P0 | M | 3d | — | ☐ Todo | 🔴 | Legal |
| LEG-003 | Draft and publish AML/CTF Policy | P0 | M | 3d | KYC-051 | ☐ Todo | 🔴 | Legal |
| LEG-004 | Draft and publish KYC/CDD Policy | P0 | M | 2d | — | ☐ Todo | 🔴 | Legal |
| LEG-005 | Draft Data Retention & Deletion Policy | P0 | M | 2d | — | ☐ Todo | 🔴 | Legal |
| LEG-006 | Draft Cookie Policy and implement consent banner | P1 | S | 1d | — | ☐ Todo | 🟡 | Legal |
| LEG-007 | Draft Acceptable Use / Prohibited Activities Policy | P0 | M | 2d | — | ☐ Todo | 🔴 | Legal |
| LEG-008 | Draft Card Holder Agreement (aligned with BIN sponsor) | P0 | M | 3d | CARD-039 | ☐ Todo | 🔴 | Legal |
| LEG-009 | Draft Merchant Services Agreement | P1 | M | 3d | — | ☐ Todo | 🟡 | Legal |
| LEG-010 | Draft Fee Schedule / Fee Disclosure document | P0 | M | 2d | EXC-010 | ☐ Todo | 🔴 | Legal |
| LEG-011 | Draft Complaints Handling Policy and process | P1 | M | 2d | — | ☐ Todo | 🟡 | Legal |
| LEG-012 | Draft Risk Disclosure (crypto volatility, custody, regulatory) | P0 | M | 2d | — | ☐ Todo | 🔴 | Legal |
| LEG-013 | Draft E-Sign / Electronic Consent disclosure | P1 | S | 1d | — | ☐ Todo | 🟡 | Legal |
| LEG-014 | Draft Refund / Cancellation Policy | P1 | S | 1d | PAY-028 | ☐ Todo | 🟡 | Legal |
| LEG-015 | Draft Sanctions & Prohibited Jurisdictions statement | P0 | S | 1d | KYC-015 | ☐ Todo | 🔴 | Legal |
| LEG-016 | Draft Data Processing Agreement (DPA) template for vendors | P1 | M | 2d | — | ☐ Todo | 🟡 | Legal |
| LEG-017 | Add versioning + user re-acceptance flow for policy updates | P1 | M | 3d | LEG-001 | ☐ Todo | 🟡 | Backend |
| LEG-018 | Record policy acceptance (version, timestamp, IP) at signup | P0 | M | 2d | LEG-001 | ☐ Todo | 🔴 | Backend |
| LEG-019 | Legal review of licensing/registration requirements per operating jurisdiction (MSB/EMI/VASP) | P0 | L | 2w | — | ☐ Todo | 🔴 | Legal |
| LEG-020 | Draft Business Continuity / Wind-down plan for customer funds | P0 | M | 3d | — | ☐ Todo | 🔴 | Legal |
| LEG-021 | Draft DMCA / IP and content policy | P3 | S | 1d | — | ☐ Todo | 🟢 | Legal |

---

## 9. Merchant

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| MER-001 | Implement merchant onboarding with KYB and document collection | P1 | L | 1w | KYC-035 | ☐ Todo | 🟡 | Backend |
| MER-002 | Implement merchant payment-acceptance API (create charge, status) | P1 | L | 1w | — | ☐ Todo | 🟡 | Backend |
| MER-003 | Implement merchant hosted checkout / payment page | P1 | L | 1w | MER-002 | ☐ Todo | 🟡 | Frontend |
| MER-004 | Implement merchant settlement / cash-out to fiat or crypto | P0 | L | 1w | PAY-008 | ☐ Todo | 🔴 | Backend |
| MER-005 | Add merchant webhook delivery for payment events (HMAC signed) | P1 | M | 3d | MER-002 | ☐ Todo | 🟡 | Backend |
| MER-006 | Add merchant API-key management (create, rotate, revoke, scopes) | P1 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| MER-007 | Add merchant dashboard (balances, transactions, settlements) | P1 | M | 3d | MER-002 | ☐ Todo | 🟡 | Frontend |
| MER-008 | Add merchant refund flow (partial/full) | P1 | M | 3d | PAY-028 | ☐ Todo | 🟡 | Backend |
| MER-009 | Add merchant fee configuration (MDR, settlement fee) into fee engine | P1 | M | 2d | EXC-010 | ☐ Todo | 🟡 | Backend |
| MER-010 | Add merchant payout schedule (instant/daily/weekly) | P2 | M | 3d | MER-004 | ☐ Todo | 🟢 | Backend |
| MER-011 | Add merchant reserve/rolling-reserve for risk | P2 | M | 3d | MER-004 | ☐ Todo | 🟡 | Backend |
| MER-012 | Add merchant dispute/chargeback handling | P2 | M | 3d | PAY-029 | ☐ Todo | 🟡 | Compliance |
| MER-013 | Add merchant invoicing / payment links | P2 | M | 3d | PAY-013 | ☐ Todo | 🟢 | Backend |
| MER-014 | Add merchant transaction monitoring (AML) integration | P1 | M | 3d | KYC-020 | ☐ Todo | 🟡 | Compliance |
| MER-015 | Add merchant risk tiering and limits | P1 | M | 3d | MER-001 | ☐ Todo | 🟡 | Compliance |
| MER-016 | Add merchant team/roles (multi-user access) | P2 | M | 3d | — | ☐ Todo | 🟢 | Backend |
| MER-017 | Add merchant reporting / statement exports | P2 | M | 2d | MER-007 | ☐ Todo | 🟢 | Backend |
| MER-018 | Add merchant sandbox environment + test keys | P1 | M | 3d | MER-002 | ☐ Todo | 🟡 | Backend |
| MER-019 | Add merchant SDK/plugins (e.g. WooCommerce/Shopify) | P3 | L | 2w | MER-002 | ☐ Todo | 🟢 | Backend |
| MER-020 | Add merchant approval/underwriting workflow in admin | P1 | M | 3d | MER-001 | ☐ Todo | 🟡 | Backend |
| MER-021 | Add merchant suspension/termination with settlement holds | P1 | M | 2d | MER-004 | ☐ Todo | 🟡 | Backend |
| MER-022 | Add merchant callback/redirect URL validation (SSRF-safe) | P1 | S | 1d | SEC-045 | ☐ Todo | 🟡 | Security |
| MER-023 | Add merchant fraud rules and velocity controls | P2 | M | 3d | KYC-020 | ☐ Todo | 🟡 | Compliance |
| MER-024 | Add merchant notification preferences and events | P2 | S | 1d | NOT-001 | ☐ Todo | 🟢 | Backend |
| MER-025 | Add end-to-end merchant flow tests (charge→settle→refund) | P1 | M | 3d | MER-004 | ☐ Todo | 🟡 | QA |

---

## 10. Admin Panel

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| ADM-001 | Add audit-logging for all settings changes (who/what/before/after) | P0 | M | 2d | — | ☐ Todo | 🔴 | Backend |
| ADM-002 | Build support-ticket admin UI (models exist, no UI) | P1 | L | 1w | — | ☐ Todo | 🟡 | Backend |
| ADM-003 | Build referral admin UI (processing exists, no UI) | P2 | M | 3d | — | ☐ Todo | 🟢 | Backend |
| ADM-004 | Build feature-flag toggle UI (currently settings text) | P1 | M | 3d | — | ☐ Todo | 🟡 | Backend |
| ADM-005 | Add admin user/role management UI with RBAC enforcement | P1 | M | 3d | SEC-031 | ☐ Todo | 🟡 | Backend |
| ADM-006 | Add admin audit-log viewer with filters and export | P1 | M | 3d | ADM-001 | ☐ Todo | 🟡 | Backend |
| ADM-007 | Add admin withdrawal/payout approval queue (maker-checker) | P0 | M | 3d | SEC-032 | ☐ Todo | 🔴 | Backend |
| ADM-008 | Add admin user detail view (balances, KYC, cards, tx history) | P1 | M | 3d | — | ☐ Todo | 🟡 | Backend |
| ADM-009 | Add admin manual ledger adjustment tool (dual-control, audited) | P1 | M | 3d | ADM-001 | ☐ Todo | 🔴 | Backend |
| ADM-010 | Add admin freeze/unfreeze account with reason + audit | P0 | M | 2d | KYC-026 | ☐ Todo | 🔴 | Backend |
| ADM-011 | Add admin custody dashboard (hot/cold balances, reconciliation status) | P1 | M | 3d | CW-009 | ☐ Todo | 🟡 | Backend |
| ADM-012 | Add admin transaction search across all money-movement types | P1 | M | 3d | — | ☐ Todo | 🟡 | Backend |
| ADM-013 | Add admin compliance case management UI (if not fully built) | P1 | M | 3d | KYC-008 | ☐ Todo | 🟡 | Backend |
| ADM-014 | Add admin rate/fee/spread configuration UI with audit | P1 | M | 3d | EXC-019 | ☐ Todo | 🟡 | Backend |
| ADM-015 | Add admin card management UI (freeze, limits, disputes) | P1 | M | 3d | CARD-035 | ☐ Todo | 🟡 | Backend |
| ADM-016 | Add admin merchant management/underwriting UI | P1 | M | 3d | MER-020 | ☐ Todo | 🟡 | Backend |
| ADM-017 | Add admin notification/broadcast tool | P2 | M | 2d | NOT-001 | ☐ Todo | 🟢 | Backend |
| ADM-018 | Add admin CMS management (already themed) verification and audit | P2 | S | 1d | — | ☐ Todo | 🟢 | Backend |
| ADM-019 | Add admin per-asset deposit/withdraw toggle UI | P1 | S | 1d | CW-040 | ☐ Todo | 🟡 | Backend |
| ADM-020 | Add admin custody kill-switch control with confirmation + audit | P0 | S | 1d | CW-035 | ☐ Todo | 🔴 | Backend |
| ADM-021 | Add admin activity-feed / recent-actions view | P2 | S | 1d | ADM-001 | ☐ Todo | 🟢 | Backend |
| ADM-022 | Add admin export (CSV) for users, transactions, reports | P2 | M | 2d | — | ☐ Todo | 🟢 | Backend |
| ADM-023 | Add admin reconciliation/discrepancy dashboard with alerts | P1 | M | 3d | CW-009 | ☐ Todo | 🟡 | Backend |
| ADM-024 | Add admin session/security controls (force-logout, IP allowlist mgmt) | P1 | M | 2d | SEC-028 | ☐ Todo | 🟡 | Backend |
| ADM-025 | Add admin impersonation (read-only) with strict audit and consent rules | P2 | M | 3d | ADM-001 | ☐ Todo | 🟡 | Backend |
| ADM-026 | Add admin bulk-action safeguards (confirmation, rate limits) | P1 | S | 1d | — | ☐ Todo | 🟡 | Backend |
| ADM-027 | Add admin metrics dashboard (volumes, revenue, active users) | P2 | M | 3d | REP-001 | ☐ Todo | 🟢 | Backend |
| ADM-028 | Add admin webhook/PSP status monitoring panel | P1 | M | 2d | PAY-032 | ☐ Todo | 🟡 | Backend |
| ADM-029 | Add admin data-subject request (GDPR export/delete) tool | P1 | M | 3d | LEG-002 | ☐ Todo | 🟡 | Backend |
| ADM-030 | Add admin panel end-to-end smoke tests for critical flows | P1 | M | 3d | — | ☐ Todo | 🟡 | QA |
| ADM-031 | Add admin dashboard performance review (N+1, pagination) on large datasets | P1 | M | 2d | DB-010 | ☐ Todo | 🟡 | Backend |

---

## 11. User Panel

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| USR-001 | Add user login-history / security-activity page | P1 | M | 2d | SEC-011 | ☐ Todo | 🟡 | Frontend |
| USR-002 | Add withdrawal address whitelist management UI | P0 | M | 3d | SEC-007 | ☐ Todo | 🔴 | Frontend |
| USR-003 | Add anti-phishing code setup UI | P1 | S | 1d | SEC-009 | ☐ Todo | 🟡 | Frontend |
| USR-004 | Add passkey enrollment/management UI | P1 | M | 3d | SEC-003 | ☐ Todo | 🟡 | Frontend |
| USR-005 | Add device management UI (view/revoke sessions) | P1 | M | 2d | SEC-021 | ☐ Todo | 🟡 | Frontend |
| USR-006 | Add KYC upload flow with liveness capture integration | P0 | M | 3d | KYC-003 | ☐ Todo | 🔴 | Frontend |
| USR-007 | Add off-ramp / cash-out UI with quote and beneficiary | P0 | M | 3d | PAY-001 | ☐ Todo | 🔴 | Frontend |
| USR-008 | Add swap/exchange UI with quote-lock and fee preview | P1 | M | 3d | EXC-006 | ☐ Todo | 🟡 | Frontend |
| USR-009 | Add card management UI (view, freeze, limits, PIN) | P1 | M | 3d | CARD-035 | ☐ Todo | 🟡 | Frontend |
| USR-010 | Add explorer links on deposit/withdrawal history | P2 | S | 1d | CW-017 | ☐ Todo | 🟢 | Frontend |
| USR-011 | Add transaction history filters/search/export | P1 | M | 2d | — | ☐ Todo | 🟡 | Frontend |
| USR-012 | Add P2P send / request money UI | P1 | M | 3d | PAY-011 | ☐ Todo | 🟡 | Frontend |
| USR-013 | Add referral program UI (share, track, rewards) | P2 | M | 3d | ADM-003 | ☐ Todo | 🟢 | Frontend |
| USR-014 | Add support-ticket user UI (create, view, reply) | P1 | M | 3d | ADM-002 | ☐ Todo | 🟡 | Frontend |
| USR-015 | Add notification preferences center | P1 | M | 2d | NOT-005 | ☐ Todo | 🟡 | Frontend |
| USR-016 | Add policy re-acceptance prompt on updated terms | P1 | S | 1d | LEG-017 | ☐ Todo | 🟡 | Frontend |
| USR-017 | Add account statement / receipt downloads (PDF) | P2 | M | 3d | — | ☐ Todo | 🟢 | Frontend |
| USR-018 | Add GDPR data export / account deletion request UI | P1 | M | 2d | ADM-029 | ☐ Todo | 🟡 | Frontend |
| USR-019 | Add withdrawal confirmation with cooldown/warning messaging | P0 | S | 1d | SEC-006 | ☐ Todo | 🔴 | Frontend |
| USR-020 | Add real-time balance/tx updates (polling or SSE) UX | P2 | M | 3d | — | ☐ Todo | 🟢 | Frontend |
| USR-021 | Add responsive/mobile UX audit and fixes across flows | P1 | M | 3d | — | ☐ Todo | 🟡 | Frontend |
| USR-022 | Add accessibility (WCAG AA) audit and remediation | P2 | M | 3d | — | ☐ Todo | 🟢 | Frontend |
| USR-023 | Add empty/error/loading states across key flows | P1 | M | 2d | — | ☐ Todo | 🟡 | Frontend |
| USR-024 | Add localization / i18n scaffolding for target markets | P2 | L | 1w | — | ☐ Todo | 🟢 | Frontend |
| USR-025 | Add user end-to-end tests (signup→KYC→deposit→swap→withdraw) | P1 | L | 4d | — | ☐ Todo | 🟡 | QA |

---

## 12. Notifications

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| NOT-001 | Define formal ChannelAdapter interface for all notification channels | P1 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| NOT-002 | Implement SMS channel (Twilio) behind adapter | P1 | M | 3d | NOT-001 | ☐ Todo | 🟡 | Backend |
| NOT-003 | Implement Push channel (FCM/APNs) behind adapter | P2 | M | 3d | NOT-001 | ☐ Todo | 🟢 | Backend |
| NOT-004 | Implement WhatsApp channel (Business API) behind adapter | P2 | M | 3d | NOT-001 | ☐ Todo | 🟢 | Backend |
| NOT-005 | Add per-user notification preference model and enforcement | P1 | M | 2d | NOT-001 | ☐ Todo | 🟡 | Backend |
| NOT-006 | Add OTP delivery via SMS/email with rate limiting and expiry | P0 | M | 2d | NOT-002 | ☐ Todo | 🔴 | Backend |
| NOT-007 | Add transactional templates (deposit/withdraw/card/login) with localization | P1 | M | 3d | — | ☐ Todo | 🟡 | Backend |
| NOT-008 | Add notification delivery retry + dead-letter handling | P1 | M | 2d | NOT-001 | ☐ Todo | 🟡 | Backend |
| NOT-009 | Add notification delivery logging + audit | P1 | S | 1d | — | ☐ Todo | 🟡 | Backend |
| NOT-010 | Add webhook signing key rotation for outbound webhooks | P1 | S | 1d | — | ☐ Todo | 🟡 | Backend |
| NOT-011 | Add outbound-webhook retry with exponential backoff + DLQ | P1 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| NOT-012 | Add email deliverability setup (SPF/DKIM/DMARC) | P0 | M | 2d | — | ☐ Todo | 🔴 | DevOps |
| NOT-013 | Add email provider (SES/Postmark) with bounce/complaint handling | P0 | M | 2d | NOT-012 | ☐ Todo | 🟡 | DevOps |
| NOT-014 | Add anti-phishing code injection into all outbound emails | P1 | S | 1d | SEC-009 | ☐ Todo | 🟡 | Backend |
| NOT-015 | Add security-alert notifications (new device, password change, withdrawal) | P0 | M | 2d | NOT-001 | ☐ Todo | 🔴 | Backend |
| NOT-016 | Add critical-notification fallback (email if SMS fails) | P1 | S | 1d | NOT-006 | ☐ Todo | 🟡 | Backend |
| NOT-017 | Add unsubscribe/consent management for marketing vs transactional | P1 | M | 2d | NOT-005 | ☐ Todo | 🟡 | Backend |
| NOT-018 | Add notification rate-limiting to prevent spam/abuse | P1 | S | 1d | — | ☐ Todo | 🟡 | Backend |
| NOT-019 | Add provider failover for SMS/email (multi-provider) | P2 | M | 3d | NOT-002 | ☐ Todo | 🟢 | Backend |
| NOT-020 | Add notification queue monitoring and stuck-message alerting | P1 | S | 1d | OBS-001 | ☐ Todo | 🟡 | SRE |
| NOT-021 | Add tests for OTP, security alerts, and template rendering | P1 | M | 2d | — | ☐ Todo | 🟡 | QA |

---

## 13. Reports & Analytics

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| REP-001 | Build core reporting layer (volumes, users, revenue) with date ranges | P1 | L | 1w | — | ☐ Todo | 🟡 | Backend |
| REP-002 | Add daily reconciliation report (custody vs ledger, per asset) | P0 | M | 3d | CW-009 | ☐ Todo | 🔴 | Backend |
| REP-003 | Add revenue report (fees, spread, card) by period | P1 | M | 3d | EXC-013 | ☐ Todo | 🟡 | Backend |
| REP-004 | Add transaction volume + count analytics by asset/corridor | P1 | M | 2d | REP-001 | ☐ Todo | 🟡 | Backend |
| REP-005 | Add user growth / activation / retention analytics | P2 | M | 3d | REP-001 | ☐ Todo | 🟢 | Backend |
| REP-006 | Add KYC funnel analytics (start→approve→reject) | P1 | M | 2d | REP-001 | ☐ Todo | 🟡 | Backend |
| REP-007 | Add compliance MI report (cases, SARs, screening hits) | P1 | M | 2d | KYC-043 | ☐ Todo | 🟡 | Compliance |
| REP-008 | Add card-program report (spend, declines, disputes) | P2 | M | 2d | CARD-044 | ☐ Todo | 🟢 | Backend |
| REP-009 | Add off-ramp/payout report (success, failure, SLA) | P1 | M | 2d | PAY-032 | ☐ Todo | 🟡 | Backend |
| REP-010 | Add liquidity/exposure report per asset | P2 | M | 3d | EXC-016 | ☐ Todo | 🟡 | Backend |
| REP-011 | Add scheduled report generation + delivery (email/S3) | P2 | M | 2d | REP-001 | ☐ Todo | 🟢 | Backend |
| REP-012 | Add report export formats (CSV/XLSX/PDF) | P2 | M | 2d | REP-001 | ☐ Todo | 🟢 | Backend |
| REP-013 | Add data warehouse / analytics DB replica (read-only) for heavy queries | P2 | L | 1w | DB-015 | ☐ Todo | 🟡 | DevOps |
| REP-014 | Add financial close / GL export for accounting | P1 | M | 3d | EXC-012 | ☐ Todo | 🟡 | Backend |
| REP-015 | Add cohort / funnel dashboards for product | P3 | M | 3d | REP-005 | ☐ Todo | 🟢 | Product |
| REP-016 | Add anomaly-detection on volume/revenue trends with alerts | P2 | M | 3d | REP-001 | ☐ Todo | 🟢 | Backend |
| REP-017 | Add regulatory reporting export (transactions over threshold) | P1 | M | 3d | KYC-023 | ☐ Todo | 🟡 | Compliance |
| REP-018 | Add report access RBAC (finance/compliance/ops separation) | P1 | S | 1d | SEC-031 | ☐ Todo | 🟡 | Backend |
| REP-019 | Add report query performance safeguards (timeouts, pagination) | P1 | M | 2d | DB-010 | ☐ Todo | 🟡 | Backend |
| REP-020 | Add report accuracy tests against known ledger fixtures | P1 | M | 2d | REP-002 | ☐ Todo | 🟡 | QA |
| REP-021 | Add proof-of-reserves public/internal report from custody snapshots | P2 | M | 3d | CW-032 | ☐ Todo | 🟡 | Backend |

---

## 14. DevOps / Infra / DR

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| OPS-001 | Create production-grade Dockerfile (multi-stage, non-root, PHP-FPM + opcache) | P0 | M | 3d | — | ☐ Todo | 🔴 | DevOps |
| OPS-002 | Set up CI pipeline (lint, test, static analysis, build) | P0 | M | 3d | — | ☐ Todo | 🔴 | DevOps |
| OPS-003 | Set up CD pipeline with approvals and rollback | P0 | L | 4d | OPS-002 | ☐ Todo | 🔴 | DevOps |
| OPS-004 | Add docker-compose for local dev (app, pgsql, redis, horizon) | P1 | M | 2d | OPS-001 | ☐ Todo | 🟡 | DevOps |
| OPS-005 | Provision infrastructure-as-code (Terraform) for all environments | P0 | L | 1w | — | ☐ Todo | 🔴 | DevOps |
| OPS-006 | Set up Horizon under supervisor/systemd with auto-restart | P0 | M | 2d | OPS-001 | ☐ Todo | 🔴 | DevOps |
| OPS-007 | Set up scheduler (cron) reliability with monitoring / heartbeat | P0 | S | 1d | OPS-006 | ☐ Todo | 🔴 | DevOps |
| OPS-008 | Configure queue worker scaling policy and concurrency per queue | P1 | M | 2d | OPS-006 | ☐ Todo | 🟡 | DevOps |
| OPS-009 | Separate critical queues (custody, payouts) from general queues | P0 | M | 2d | OPS-006 | ☐ Todo | 🔴 | DevOps |
| OPS-010 | Stand up a staging environment mirroring production | P0 | L | 1w | OPS-005 | ☐ Todo | 🔴 | DevOps |
| OPS-011 | Set up automated PostgreSQL backups (daily full + WAL/PITR) | P0 | M | 3d | — | ☐ Todo | 🔴 | DevOps |
| OPS-012 | Test backup restore end-to-end and document RPO/RTO | P0 | M | 3d | OPS-011 | ☐ Todo | 🔴 | DevOps |
| OPS-013 | Set up encrypted off-site/cross-region backup storage | P0 | M | 2d | OPS-011 | ☐ Todo | 🔴 | DevOps |
| OPS-014 | Write Disaster Recovery plan and run a DR drill | P0 | L | 1w | OPS-012 | ☐ Todo | 🔴 | SRE |
| OPS-015 | Set up database high availability (primary + standby, failover) | P0 | L | 1w | OPS-005 | ☐ Todo | 🔴 | DevOps |
| OPS-016 | Set up Redis HA / persistence for queues (avoid job loss) | P0 | M | 3d | OPS-005 | ☐ Todo | 🔴 | DevOps |
| OPS-017 | Configure auto-scaling for web tier | P1 | M | 3d | OPS-005 | ☐ Todo | 🟡 | DevOps |
| OPS-018 | Configure load balancer with health checks and TLS termination | P0 | M | 2d | OPS-005 | ☐ Todo | 🔴 | DevOps |
| OPS-019 | Set up TLS certificates + auto-renewal | P0 | S | 1d | OPS-018 | ☐ Todo | 🔴 | DevOps |
| OPS-020 | Set up CDN / edge (static assets, caching, DDoS) | P1 | M | 2d | OPS-018 | ☐ Todo | 🟡 | DevOps |
| OPS-021 | Externalize secrets to Vault/SSM in all environments | P0 | M | 3d | SEC-024 | ☐ Todo | 🔴 | DevOps |
| OPS-022 | Add zero-downtime deploy strategy (blue-green/rolling) with migrations gating | P0 | L | 4d | OPS-003 | ☐ Todo | 🔴 | DevOps |
| OPS-023 | Add migration safety (expand-contract, no destructive in single deploy) | P0 | M | 2d | OPS-022 | ☐ Todo | 🔴 | DevOps |
| OPS-024 | Add feature-flag driven progressive rollout capability | P1 | M | 3d | ADM-004 | ☐ Todo | 🟡 | DevOps |
| OPS-025 | Isolate signing/custody service infra (dedicated subnet, no public ingress) | P0 | L | 4d | CW-042 | ☐ Todo | 🔴 | DevOps |
| OPS-026 | Lock down network security groups / firewall (least exposure) | P0 | M | 2d | OPS-005 | ☐ Todo | 🔴 | DevOps |
| OPS-027 | Add bastion/SSM-only access (no direct SSH), MFA on cloud console | P0 | M | 2d | OPS-005 | ☐ Todo | 🔴 | Security |
| OPS-028 | Add environment config validation on boot (fail fast on missing/invalid) | P1 | S | 1d | — | ☐ Todo | 🟡 | Backend |
| OPS-029 | Enforce APP_DEBUG=false, APP_ENV=production, cache config in prod | P0 | S | 2h | — | ☐ Todo | 🔴 | DevOps |
| OPS-030 | Add opcache + JIT tuning and preloading for PHP | P1 | M | 2d | OPS-001 | ☐ Todo | 🟡 | DevOps |
| OPS-031 | Add scheduled job for cache/route/config/view caching on deploy | P1 | S | 1d | OPS-003 | ☐ Todo | 🟡 | DevOps |
| OPS-032 | Set up log aggregation (centralized, structured JSON) | P0 | M | 3d | — | ☐ Todo | 🔴 | SRE |
| OPS-033 | Add log retention + PII redaction in logs | P0 | M | 2d | OPS-032 | ☐ Todo | 🔴 | Security |
| OPS-034 | Add container image scanning in CI | P1 | S | 1d | OPS-001 | ☐ Todo | 🟡 | Security |
| OPS-035 | Add infra drift detection and IaC plan review gate | P1 | M | 2d | OPS-005 | ☐ Todo | 🟡 | DevOps |
| OPS-036 | Add secrets rotation automation and expiry alerts | P1 | M | 3d | OPS-021 | ☐ Todo | 🟡 | Security |
| OPS-037 | Add maintenance-mode / graceful-degradation strategy | P1 | S | 1d | — | ☐ Todo | 🟡 | DevOps |
| OPS-038 | Add health/readiness/liveness probes wired to orchestrator | P0 | S | 1d | — | ☐ Todo | 🔴 | DevOps |
| OPS-039 | Add dependency (pgsql/redis/RPC/PSP) health checks to health endpoint | P1 | M | 2d | OPS-038 | ☐ Todo | 🟡 | Backend |
| OPS-040 | Add rate-limit / cost budgets and alerting on cloud spend | P2 | S | 1d | — | ☐ Todo | 🟢 | DevOps |
| OPS-041 | Document infrastructure architecture and runbooks | P1 | M | 3d | OPS-005 | ☐ Todo | 🟡 | SRE |
| OPS-042 | Add on-call rotation and paging setup | P1 | M | 2d | OBS-010 | ☐ Todo | 🟡 | SRE |
| OPS-043 | Add change-management / deploy-approval process for prod | P1 | S | 1d | OPS-003 | ☐ Todo | 🟡 | SRE |
| OPS-044 | Add environment parity checks (staging vs prod config diff) | P1 | M | 2d | OPS-010 | ☐ Todo | 🟡 | DevOps |
| OPS-045 | Add automated dependency update pipeline (Dependabot/Renovate) | P2 | S | 1d | OPS-002 | ☐ Todo | 🟢 | DevOps |
| OPS-046 | Add build reproducibility + artifact signing | P2 | M | 2d | OPS-002 | ☐ Todo | 🟢 | DevOps |

---

## 15. Database / Performance / Retention

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| DB-001 | Add soft deletes to financial/PII entities where retention requires (non-ledger) | P1 | M | 3d | — | ☐ Todo | 🟡 | Backend |
| DB-002 | Ensure ledger entries remain append-only/immutable (no soft delete) with guard | P0 | S | 1d | — | ☐ Todo | 🔴 | Backend |
| DB-003 | Define + implement data retention policy per table (legal + minimization) | P0 | M | 3d | LEG-005 | ☐ Todo | 🔴 | Backend |
| DB-004 | Implement archiving strategy for old transactions/logs (cold storage) | P1 | L | 1w | DB-003 | ☐ Todo | 🟡 | Backend |
| DB-005 | Implement partitioning for high-volume tables (ledger, audit, tx) | P1 | L | 1w | — | ☐ Todo | 🟡 | Backend |
| DB-006 | Review and add missing indexes based on query analysis (pg_stat_statements) | P1 | M | 3d | — | ☐ Todo | 🟡 | Backend |
| DB-007 | Add connection pooling (PgBouncer) and tune pool sizes | P0 | M | 2d | OPS-015 | ☐ Todo | 🔴 | DevOps |
| DB-008 | Set statement timeout + lock timeout defaults for app connections | P1 | S | 1d | — | ☐ Todo | 🟡 | Backend |
| DB-009 | Audit transaction isolation for money-movement (SELECT FOR UPDATE correctness) | P0 | M | 3d | — | ☐ Todo | 🔴 | Backend |
| DB-010 | Eliminate N+1 queries in hot paths (eager loading audit) | P1 | M | 3d | — | ☐ Todo | 🟡 | Backend |
| DB-011 | Add read replica and route read-heavy queries where safe | P2 | M | 3d | OPS-015 | ☐ Todo | 🟡 | DevOps |
| DB-012 | Add DB migration rollback tests and forward-only enforcement | P1 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| DB-013 | Add FK/constraint audit ensuring referential integrity on all relations | P1 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| DB-014 | Add unique constraints for all idempotency/business keys | P0 | M | 2d | — | ☐ Todo | 🔴 | Backend |
| DB-015 | Set up logical replication / CDC for analytics offload | P2 | M | 3d | OPS-015 | ☐ Todo | 🟡 | DevOps |
| DB-016 | Add DB performance load test at projected 12-month volume | P1 | M | 3d | — | ☐ Todo | 🟡 | QA |
| DB-017 | Add autovacuum / bloat monitoring and tuning | P1 | M | 2d | OPS-015 | ☐ Todo | 🟡 | DevOps |
| DB-018 | Encrypt DB at rest (storage-level) and enforce TLS in transit | P0 | S | 1d | OPS-015 | ☐ Todo | 🔴 | Security |
| DB-019 | Add column-level encryption for sensitive PII (verify SEC-023) | P0 | M | 2d | SEC-023 | ☐ Todo | 🔴 | Security |
| DB-020 | Add slow-query logging + alerting | P1 | S | 1d | OPS-032 | ☐ Todo | 🟡 | SRE |
| DB-021 | Add DB capacity planning + disk-usage alerting | P1 | S | 1d | OBS-001 | ☐ Todo | 🟡 | SRE |
| DB-022 | Add data-integrity check job (orphan rows, negative balances, invariant checks) | P0 | M | 3d | — | ☐ Todo | 🔴 | Backend |
| DB-023 | Add GDPR erasure implementation (anonymize vs delete per retention) | P1 | M | 3d | DB-003 | ☐ Todo | 🟡 | Backend |
| DB-024 | Add seed/fixture strategy separated from production data | P1 | S | 1d | — | ☐ Todo | 🟡 | Backend |
| DB-025 | Add query timeouts and circuit-breakers on report/analytics queries | P1 | M | 2d | REP-019 | ☐ Todo | 🟡 | Backend |
| DB-026 | Verify balance-trigger performance impact under load and optimize if needed | P1 | M | 3d | CW-005 | ☐ Todo | 🟡 | Backend |

---

## 16. API / Webhooks / Docs

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| API-001 | Generate OpenAPI/Swagger spec for all public API v1 endpoints | P1 | M | 3d | — | ☐ Todo | 🟡 | Backend |
| API-002 | Publish interactive API docs (Swagger UI / Redoc) | P1 | S | 1d | API-001 | ☐ Todo | 🟡 | Backend |
| API-003 | Define API versioning strategy and deprecation policy | P1 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| API-004 | Review + lock down CORS configuration (explicit origins) | P0 | S | 1d | — | ☐ Todo | 🔴 | Security |
| API-005 | Add OAuth2 client-credentials flow for partner/merchant APIs | P2 | L | 1w | — | ☐ Todo | 🟡 | Backend |
| API-006 | Verify Idempotency-Key handling on all mutating endpoints | P0 | M | 2d | — | ☐ Todo | 🔴 | Backend |
| API-007 | Verify HMAC webhook signing + replay protection (timestamp/nonce) | P0 | M | 2d | — | ☐ Todo | 🔴 | Backend |
| API-008 | Add webhook delivery logs + ret/replay tooling | P1 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| API-009 | Add per-key / per-endpoint rate-limit tiers | P1 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| API-010 | Add consistent error schema + error codes across API | P1 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| API-011 | Add request/response validation and strict input sanitization | P0 | M | 3d | — | ☐ Todo | 🔴 | Backend |
| API-012 | Add pagination + filtering standards across list endpoints | P1 | M | 2d | — | ☐ Todo | 🟡 | Backend |
| API-013 | Add API changelog + SDK generation from OpenAPI | P2 | M | 2d | API-001 | ☐ Todo | 🟢 | Backend |
| API-014 | Add sandbox API environment with test data | P1 | M | 3d | MER-018 | ☐ Todo | 🟡 | Backend |
| API-015 | Add Sanctum token scopes + expiry + revocation review | P1 | M | 2d | — | ☐ Todo | 🟡 | Security |
| API-016 | Add webhook endpoint SSRF/allowlist validation | P1 | S | 1d | SEC-045 | ☐ Todo | 🟡 | Security |
| API-017 | Add API request/response logging (redacted) for debugging | P1 | M | 2d | OPS-033 | ☐ Todo | 🟡 | Backend |
| API-018 | Add contract tests for public API (breaking-change detection) | P1 | M | 3d | API-001 | ☐ Todo | 🟡 | QA |
| API-019 | Add API authentication failure alerting (brute force detection) | P1 | S | 1d | OBS-001 | ☐ Todo | 🟡 | Security |
| API-020 | Add idempotent webhook consumer guidance + example handlers in docs | P2 | S | 1d | API-002 | ☐ Todo | 🟢 | Backend |
| API-021 | Add request-size limits and payload validation caps | P1 | S | 1d | — | ☐ Todo | 🟡 | Backend |
| API-022 | Add API status page / uptime endpoint | P2 | S | 1d | OBS-015 | ☐ Todo | 🟢 | SRE |
| API-023 | Add webhook secret per-merchant with rotation | P1 | M | 2d | MER-006 | ☐ Todo | 🟡 | Backend |
| API-024 | Add API abuse detection (anomalous usage patterns) | P2 | M | 3d | API-009 | ☐ Todo | 🟢 | Security |
| API-025 | Add developer portal (keys, docs, sandbox, webhooks) | P2 | L | 1w | API-002 | ☐ Todo | 🟢 | Frontend |

---

## 17. Testing / QA / Security testing

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| QA-001 | Add PHPStan/Larastan static analysis at a strict level in CI | P0 | M | 3d | OPS-002 | ☐ Todo | 🔴 | Backend |
| QA-002 | Add code-coverage reporting with a minimum threshold gate | P1 | M | 2d | OPS-002 | ☐ Todo | 🟡 | QA |
| QA-003 | Expand unit tests (currently only 1) for domain/services/actions | P1 | L | 1w | — | ☐ Todo | 🟡 | QA |
| QA-004 | Add load/performance testing (k6/Gatling) for critical flows | P0 | L | 1w | OPS-010 | ☐ Todo | 🔴 | QA |
| QA-005 | Commission external penetration test | P0 | L | 2w | OPS-010 | ☐ Todo | 🔴 | Security |
| QA-006 | Add fuzz testing for money math and input parsing | P1 | M | 3d | — | ☐ Todo | 🟡 | QA |
| QA-007 | Add concurrency/race tests for ledger postings and idempotency | P0 | M | 3d | — | ☐ Todo | 🔴 | QA |
| QA-008 | Add end-to-end custody tests on testnet (TRON + EVM) | P1 | L | 4d | EVM-021 | ☐ Todo | 🟡 | QA |
| QA-009 | Add off-ramp/payout integration tests with mock PSP | P0 | M | 3d | PAY-031 | ☐ Todo | 🔴 | QA |
| QA-010 | Add card issuing integration tests with mock + sandbox provider | P0 | M | 3d | CARD-034 | ☐ Todo | 🔴 | QA |
| QA-011 | Add compliance flow tests (screening hit, freeze, SAR) | P1 | M | 3d | KYC-050 | ☐ Todo | 🟡 | QA |
| QA-012 | Add security regression suite (authz, IDOR, CSRF, headers) | P0 | M | 3d | SEC-044 | ☐ Todo | 🔴 | QA |
| QA-013 | Add browser E2E tests (Dusk/Playwright) for user + admin flows | P1 | L | 1w | — | ☐ Todo | 🟡 | QA |
| QA-014 | Add contract tests for external providers (rate/PSP/card/screening) | P1 | M | 3d | — | ☐ Todo | 🟡 | QA |
| QA-015 | Add smoke test suite runnable post-deploy against any env | P0 | M | 2d | OPS-003 | ☐ Todo | 🔴 | QA |
| QA-016 | Add mutation testing on critical money/ledger code | P2 | M | 3d | QA-003 | ☐ Todo | 🟢 | QA |
| QA-017 | Add test data factories/seeders for reproducible scenarios | P1 | M | 2d | — | ☐ Todo | 🟡 | QA |
| QA-018 | Add flaky-test detection and quarantine in CI | P2 | S | 1d | OPS-002 | ☐ Todo | 🟢 | QA |
| QA-019 | Add DAST scan in CI against staging (OWASP ZAP) | P1 | M | 2d | SEC-038 | ☐ Todo | 🟡 | Security |
| QA-020 | Add dependency/license compliance scan | P2 | S | 1d | OPS-002 | ☐ Todo | 🟢 | QA |
| QA-021 | Add chaos/failover tests (DB failover, queue outage, RPC down) | P1 | L | 4d | OPS-015 | ☐ Todo | 🟡 | SRE |
| QA-022 | Add reconciliation test harness with injected drift scenarios | P0 | M | 3d | CW-009 | ☐ Todo | 🔴 | QA |
| QA-023 | Add accessibility automated checks in CI | P2 | S | 1d | USR-022 | ☐ Todo | 🟢 | QA |
| QA-024 | Add performance budgets for key API latencies (assert in CI) | P1 | M | 2d | QA-004 | ☐ Todo | 🟡 | QA |
| QA-025 | Add regression test for reserve-before-sign withdrawal ordering | P0 | M | 2d | CW-025 | ☐ Todo | 🔴 | QA |
| QA-026 | Add negative tests for KYC-tier limit bypass attempts | P0 | M | 2d | KYC-001 | ☐ Todo | 🔴 | QA |
| QA-027 | Add webhook signature/replay test coverage | P1 | S | 1d | API-007 | ☐ Todo | 🟡 | QA |
| QA-028 | Add data-migration dry-run testing against prod-like snapshot | P1 | M | 2d | OPS-010 | ☐ Todo | 🟡 | QA |
| QA-029 | Add test environment for external-provider sandboxes wiring | P1 | M | 2d | OPS-010 | ☐ Todo | 🟡 | QA |
| QA-030 | Define and document release regression checklist | P1 | S | 1d | — | ☐ Todo | 🟡 | QA |
| QA-031 | Add threat model + STRIDE review of custody and payout paths | P0 | M | 3d | — | ☐ Todo | 🔴 | Security |

---

## 18. Observability / Monitoring / Alerting

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| OBS-001 | Set up metrics pipeline (Prometheus/CloudWatch) with app + infra metrics | P0 | M | 3d | OPS-005 | ☐ Todo | 🔴 | SRE |
| OBS-002 | Integrate error tracking (Sentry) for backend + frontend | P0 | M | 2d | — | ☐ Todo | 🔴 | SRE |
| OBS-003 | Integrate APM / distributed tracing for request + queue paths | P0 | M | 3d | OBS-001 | ☐ Todo | 🔴 | SRE |
| OBS-004 | Add Horizon queue metrics + alerting (backlog, failed jobs, wait time) | P0 | M | 2d | OPS-006 | ☐ Todo | 🔴 | SRE |
| OBS-005 | Add custody-specific alerts (reconciliation drift, stuck withdrawals, watcher lag) | P0 | M | 2d | CW-009 | ☐ Todo | 🔴 | SRE |
| OBS-006 | Add rate-feed / PSP / provider health alerts | P0 | M | 2d | EXC-015 | ☐ Todo | 🔴 | SRE |
| OBS-007 | Add business-metric alerts (payout failure rate, decline spikes) | P1 | M | 2d | OBS-001 | ☐ Todo | 🟡 | SRE |
| OBS-008 | Add SLO/SLI definitions and error-budget tracking | P1 | M | 3d | OBS-001 | ☐ Todo | 🟡 | SRE |
| OBS-009 | Build operational dashboards (system, queues, custody, payments) | P1 | M | 3d | OBS-001 | ☐ Todo | 🟡 | SRE |
| OBS-010 | Set up alerting/paging (PagerDuty/Opsgenie) with severity routing | P0 | M | 2d | OBS-001 | ☐ Todo | 🔴 | SRE |
| OBS-011 | Add log-based alerts for security events (auth failures, freezes) | P1 | M | 2d | OPS-032 | ☐ Todo | 🟡 | Security |
| OBS-012 | Add synthetic monitoring for critical user journeys | P1 | M | 2d | OPS-010 | ☐ Todo | 🟡 | SRE |
| OBS-013 | Add uptime monitoring for public endpoints + webhooks | P1 | S | 1d | — | ☐ Todo | 🟡 | SRE |
| OBS-014 | Add correlation IDs propagated across requests/jobs/logs | P1 | M | 2d | OPS-032 | ☐ Todo | 🟡 | Backend |
| OBS-015 | Build public/internal status page | P2 | S | 1d | OBS-013 | ☐ Todo | 🟢 | SRE |
| OBS-016 | Add anomaly detection on key metrics (auto-baseline) | P2 | M | 3d | OBS-001 | ☐ Todo | 🟢 | SRE |
| OBS-017 | Add audit-log monitoring/alerting for privileged admin actions | P1 | M | 2d | ADM-001 | ☐ Todo | 🟡 | Security |
| OBS-018 | Add cost/usage dashboards for third-party providers | P2 | S | 1d | — | ☐ Todo | 🟢 | SRE |
| OBS-019 | Add runbook links embedded in alerts | P1 | S | 1d | OPS-041 | ☐ Todo | 🟡 | SRE |
| OBS-020 | Add alert noise review / tuning process | P1 | S | 1d | OBS-010 | ☐ Todo | 🟡 | SRE |
| OBS-021 | Add on-chain confirmation-lag and RPC-error dashboards | P1 | M | 2d | EVM-029 | ☐ Todo | 🟡 | SRE |

---

## 19. Go-Live / Launch checklist

| ID | Task | Priority | Difficulty | Est. Time | Dependencies | Status | Risk | Owner |
|----|------|----------|------------|-----------|--------------|--------|------|-------|
| GO-001 | Complete pre-launch security sign-off (pen test remediated, no P0/P1 open) | P0 | M | 3d | QA-005 | ☐ Todo | 🔴 | Security |
| GO-002 | Complete compliance sign-off (screening/Travel Rule/KYC live, MLRO approved) | P0 | M | 3d | KYC-014 | ☐ Todo | 🔴 | Compliance |
| GO-003 | Complete legal sign-off (all policies published, licensing confirmed) | P0 | M | 2d | LEG-019 | ☐ Todo | 🔴 | Legal |
| GO-004 | Verify custody KMS live and env-seed provider removed from prod | P0 | S | 1d | CW-001 | ☐ Todo | 🔴 | Security |
| GO-005 | Verify EVM liveness (no simulated paths) on mainnet | P0 | S | 1d | EVM-023 | ☐ Todo | 🔴 | Backend |
| GO-006 | Verify live rate feed active with staleness/outlier guards | P0 | S | 1d | EXC-003 | ☐ Todo | 🔴 | Backend |
| GO-007 | Verify backups + DR drill passed with documented RPO/RTO | P0 | S | 1d | OPS-014 | ☐ Todo | 🔴 | SRE |
| GO-008 | Verify monitoring/alerting/on-call live and tested | P0 | S | 1d | OBS-010 | ☐ Todo | 🔴 | SRE |
| GO-009 | Run production reconciliation dry-run and confirm zero drift | P0 | M | 2d | REP-002 | ☐ Todo | 🔴 | Backend |
| GO-010 | Execute go-live runbook with rollback plan and comms | P0 | M | 2d | OPS-022 | ☐ Todo | 🔴 | SRE |
| GO-011 | Set conservative launch limits (per-user/global caps) for soft launch | P0 | S | 1d | CW-034 | ☐ Todo | 🔴 | Product |
| GO-012 | Enable custody kill-switch readiness and rehearse activation | P0 | S | 1d | CW-035 | ☐ Todo | 🔴 | Security |
| GO-013 | Complete data-privacy readiness (DSAR, retention, consent live) | P0 | S | 1d | ADM-029 | ☐ Todo | 🔴 | Legal |
| GO-014 | Verify all P0 feature flags default to safe/off where appropriate | P1 | S | 1d | ADM-004 | ☐ Todo | 🟡 | Product |
| GO-015 | Complete accessibility + browser/device compatibility pass | P1 | M | 2d | USR-022 | ☐ Todo | 🟡 | QA |
| GO-016 | Confirm support processes/tooling ready (tickets, escalation) | P1 | S | 1d | ADM-002 | ☐ Todo | 🟡 | Product |
| GO-017 | Confirm incident-response + comms plan rehearsed | P0 | S | 1d | SEC-041 | ☐ Todo | 🔴 | Security |
| GO-018 | Run staged rollout / canary to a limited cohort first | P1 | M | 2d | OPS-024 | ☐ Todo | 🟡 | Product |
| GO-019 | Define launch KPIs and monitoring for first 72 hours | P1 | S | 1d | OBS-009 | ☐ Todo | 🟡 | Product |
| GO-020 | Post-launch retrospective + hardening backlog triage | P2 | S | 1d | — | ☐ Todo | 🟢 | Product |
| GO-021 | Establish post-launch on-call war-room for first week | P1 | S | 1d | OPS-042 | ☐ Todo | 🟡 | SRE |

---

**Total tasks: 595**
