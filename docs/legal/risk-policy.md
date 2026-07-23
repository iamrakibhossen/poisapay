**DRAFT — for legal review. Not legal advice.**

Version 0.1-draft
Last updated: [PLACEHOLDER: date]

---

# Enterprise Risk Management Framework

## 1. Purpose & Scope

### 1.1 Purpose
This Enterprise Risk Management ("ERM") Framework establishes how [PLACEHOLDER: entity name] ("the Company", "PoisaPay") identifies, assesses, measures, monitors, controls, and reports the risks arising from operating a custodial stablecoin payment platform. The Framework is intended to protect customer funds, preserve the solvency of the internal ledger, safeguard the integrity of custody operations, and ensure that the Company operates within a defined and Board-approved risk appetite.

### 1.2 Scope
This Framework applies to all of the Company's activities, systems, personnel, contractors, and third-party service providers. It covers, without limitation, the following product and platform functions:

- Custodial hosted wallets in which the Company holds private keys via BIP32/HD (hierarchical deterministic) key derivation, with a planned migration toward key management using a KMS/HSM.
- Crypto deposits and on-chain withdrawals of USDT across TRON (TRC20) and Ethereum/BSC (ERC20) networks.
- Internal (off-chain, ledger-only) transfers between customers.
- Fiat on-ramp and off-ramp over local payment rails, including bank transfers and mobile-money rails (bKash / Nagad).
- Currency swaps executed with an FX spread.
- Merchant payments, QR invoices, and merchant settlements.
- Card issuing (virtual and physical) with Just-In-Time ("JIT") authorization.
- Rewards, referrals, and credit lines.
- The internal double-entry ledger core, treasury operations (held per chain), and coin pooling (one pooled balance per canonical asset).

### 1.3 Relationship to Other Policies
This Framework is the apex risk document. It is supported by and cross-references the Company's `aml-cft-policy.md` and `kyc-policy.md`, as well as operational runbooks, the treasury and reconciliation procedures, the incident response plan, and the business continuity and disaster recovery ("BCDR") plan.

## 2. Governance

### 2.1 Board of Directors
The Board of [PLACEHOLDER: entity name] holds ultimate accountability for risk. The Board approves this Framework, sets and approves the risk appetite statement (Section 4), and reviews the Company's aggregate risk profile at least [PLACEHOLDER: cadence].

### 2.2 Risk Committee
The Board delegates day-to-day oversight to the Risk Committee ([PLACEHOLDER: risk committee name]), which meets at least [PLACEHOLDER: cadence] and is responsible for reviewing the risk taxonomy, Key Risk Indicators ("KRIs"), material incidents, limit breaches, and remediation plans.

### 2.3 Chief Risk Officer
The Chief Risk Officer ("CRO"), [PLACEHOLDER: CRO name], owns the ERM Framework, chairs the risk-management function, escalates material risks to the Risk Committee and Board, and maintains the risk register. The CRO is functionally independent from revenue-generating and operational execution functions.

### 2.4 Three Lines of Defense
- **First line — Risk owners.** Business, engineering, treasury, custody, and operations teams own and manage risk within their processes and operate the day-to-day controls (e.g., withdrawal signing, reconciliation, KYC review, transaction monitoring triage).
- **Second line — Risk & Compliance.** The CRO, risk function, and the Compliance/AML function set policy, define limits and KRIs, provide independent challenge, and monitor adherence to appetite.
- **Third line — Internal Audit.** Independent assurance over the design and operating effectiveness of controls, reporting to the Board / Risk Committee. Internal audit may be performed by [PLACEHOLDER: internal or external audit provider].

## 3. Risk Taxonomy

The Company classifies its risks into the categories set out in this Section. Each category has a designated risk owner, is tracked in the risk register, and is mapped to mitigating controls in Section 6.

### 3.1 (a) Operational Risk
The risk of loss resulting from inadequate or failed internal processes, people, and systems, or from external events. For PoisaPay this includes:

- **Process failures:** errors in deposit crediting, withdrawal processing, swap execution, settlement, or ledger postings.
- **People failures:** manual error, key-person dependency, or unauthorized/erroneous privileged action.
- **Systems failures:** application outages, degraded blockchain RPC connectivity, queue/worker backlogs affecting the deposit watcher or withdrawal signer.
- **Vendor / third-party dependency:** reliance on payment service providers (PSPs) and mobile-money rails (bKash/Nagad, banks) for fiat on/off-ramp; on RPC / node providers (e.g., TronGrid and Ethereum/BSC nodes) for chain connectivity; on KYC and KYT/blockchain-analytics vendors; and on the card program manager and card network for issuing and JIT authorization. Failure, outage, or termination of any such provider can interrupt service or trap funds in transit.

### 3.2 (b) Custody & Key-Management Risk
The risk of loss or misappropriation of customer crypto assets arising from the custodial model. PoisaPay holds private keys on behalf of customers and therefore assumes concentrated custody risk. Key drivers:

- **Private-key security:** protection of key material at rest and in use.
- **BIP32 / HD derivation:** integrity of the seed/master key and the derivation path structure that generates deposit and treasury addresses; compromise of the seed compromises all derived keys.
- **Migration to KMS/HSM:** transition risk while moving from application-managed keys toward hardware/KMS-backed signing, including dual-running and cut-over risk.
- **Hot / cold segregation:** insufficient segregation between hot (operationally signing) and cold (reserve) holdings increases exposure of the online signer.
- **Signer compromise:** compromise of the withdrawal signer or its credentials could permit unauthorized on-chain withdrawals.
- **Address generation:** incorrect derivation or address reuse leading to misattributed deposits or loss.

### 3.3 (c) Market / FX Risk
The risk of loss from movements in market prices and rates:

- **Stablecoin de-peg risk:** USDT trading away from its intended USD peg, affecting the value of pooled assets relative to customer liabilities.
- **FX rate movement:** adverse movement between a customer's funding currency and the currency in which card spend or off-ramp settlement occurs, and between quotation and settlement of swaps.
- **Spread management:** the FX spread applied to swaps must be calibrated to cover cost, volatility, and slippage without being uncompetitive; mis-set spreads create either loss or conduct risk.

### 3.4 (d) Liquidity & Treasury Solvency Risk
The risk that the Company cannot meet customer obligations as they fall due, or that reserves fall below customer liabilities:

- **Solvency principle:** the core invariant that ledger reserves / assets must be greater than or equal to aggregate customer liabilities at all times. Breach of this invariant is a critical risk event.
- **Per-chain treasury sufficiency:** treasury is held per chain; a given chain's treasury must hold sufficient USDT to service withdrawals on that network even when aggregate holdings are adequate (network-level liquidity fragmentation).
- **Off-ramp float:** funds in transit across fiat rails (bank, bKash/Nagad) create timing and counterparty float exposure.
- **Reconciliation:** failure to reconcile on-chain balances, pooled canonical-asset balances, PSP balances, and the internal double-entry ledger can mask a solvency shortfall.

### 3.5 (e) Fraud Risk
The risk of loss from fraudulent or abusive customer or third-party activity:

- **Account takeover (ATO):** unauthorized access to customer accounts.
- **Unauthorized card use:** fraudulent authorizations against issued virtual/physical cards.
- **Referral / rewards abuse:** gaming of referral, rewards, or credit-line programs (e.g., self-referral, fake accounts).
- **Mule accounts:** use of accounts to launder or move illicit proceeds.

Fraud controls include 2FA/TOTP, device management, suspicious-login detection, velocity limits, and KYT blockchain analytics (see Section 6).

### 3.6 (f) Compliance / Regulatory Risk
The risk of legal or regulatory sanction, financial loss, or reputational harm from failure to comply with applicable laws and regulations:

- **AML/CFT:** obligations around KYC tiers (0/1/2), sanctions and PEP screening, KYT, transaction monitoring, risk scoring, Suspicious Activity Report ("SAR") workflow, freeze, blacklist/whitelist, and record-keeping. See `aml-cft-policy.md` and `kyc-policy.md`.
- **Sanctions:** screening of customers and, via KYT, of on-chain counterparties/addresses.
- **Licensing / registration:** the Company's regulatory status, licences, registrations, and permissions are [PLACEHOLDER: licence/registration status] with [PLACEHOLDER: regulator]. This Framework does not assert any licence, registration, or approval; these are to be confirmed by legal.

### 3.7 (g) Cyber / Information-Security Risk
The risk of loss from compromise of the confidentiality, integrity, or availability of the Company's systems and data:

- **Encryption at rest:** protection of sensitive data and secrets at rest.
- **Hash-chained audit logs:** immutable, tamper-evident audit logging; risk if logging fails or chain integrity is not verified.
- **API rate limiting:** protection against abuse, scraping, and denial-of-service against platform APIs.
- **Anti-phishing code:** customer-facing anti-phishing measures to reduce credential-theft success.
- **Access controls:** RBAC, least privilege, privileged-access management, and separation of duties for signing, treasury, and admin functions.

## 4. Risk Appetite Statement

### 4.1 Overall Posture
The Company adopts a [PLACEHOLDER: overall risk appetite, e.g. "low"] appetite for risks that threaten customer funds, ledger solvency, custody integrity, or regulatory compliance, and a measured appetite for commercial/product risk within defined limits.

### 4.2 Zero-Tolerance Statements
The Company has zero tolerance for: (i) breach of the solvency invariant (reserves/assets < customer liabilities); (ii) knowing facilitation of money laundering, terrorist financing, or sanctions evasion; (iii) unauthorized movement of customer keys or funds.

### 4.3 Quantitative Thresholds (to be set by the Board)
- Maximum hot-wallet holdings as a percentage of total custody: [PLACEHOLDER: threshold].
- Minimum per-chain treasury buffer relative to pending/expected withdrawals: [PLACEHOLDER: threshold].
- Minimum reserve-to-liability coverage ratio: [PLACEHOLDER: threshold].
- Maximum stablecoin de-peg deviation tolerated before de-risking action: [PLACEHOLDER: threshold].
- Maximum acceptable single-vendor concentration (PSP / RPC / KYT / program manager): [PLACEHOLDER: threshold].
- Fraud loss tolerance as a percentage of transaction volume: [PLACEHOLDER: threshold].
- Velocity / transaction limits per KYC tier: [PLACEHOLDER: limits].

## 5. Risk Appetite & Tolerance Operation

Appetite is expressed as target thresholds; tolerance defines the buffer within which limited, temporary deviation is permitted before mandatory escalation. Breaches of tolerance are escalated to the CRO and Risk Committee, logged in the risk register, and require a documented remediation plan with an owner and due date. Limits are reviewed at least [PLACEHOLDER: cadence] and after any material incident.

## 6. Controls Mapping

| # | Risk (Section 3) | Primary Mitigating Controls |
|---|------------------|-----------------------------|
| 6.1 | (a) Operational | Documented processes and runbooks; four-eyes on privileged actions; queue/worker monitoring for deposit watcher and withdrawal signer; vendor due diligence, SLAs, and multi-provider / failover for RPC nodes; contingency for PSP and program-manager outages; reconciliation. |
| 6.2 | (b) Custody & key mgmt | BIP32/HD key management; migration to KMS/HSM; hot/cold segregation with limits; least-privilege access to signer; address-derivation validation; address whitelist + cooldown on withdrawals; monitoring of signer activity. |
| 6.3 | (c) Market / FX | FX spread calibration and monitoring; de-peg monitoring against [PLACEHOLDER: threshold]; quotation validity windows on swaps; per-chain / per-asset exposure tracking. |
| 6.4 | (d) Liquidity & solvency | Double-entry ledger with exact Money amounts; enforced reserves >= liabilities invariant; per-chain treasury buffers; off-ramp float monitoring; multi-source reconciliation (on-chain, pooled canonical balances, PSP, ledger). |
| 6.5 | (e) Fraud | 2FA/TOTP; device management; suspicious-login detection; velocity limits; KYT blockchain analytics; risk scoring; card JIT authorization controls; rewards/referral abuse detection; mule-account detection via transaction monitoring. |
| 6.6 | (f) Compliance | KYC tiers 0/1/2; sanctions/PEP screening; transaction monitoring; SAR workflow; freeze; blacklist/whitelist; record-keeping (see `aml-cft-policy.md`, `kyc-policy.md`). |
| 6.7 | (g) Cyber / infosec | Encryption at rest; immutable hash-chained audit logs; API rate limiting; anti-phishing code; RBAC and separation of duties; access reviews. |

## 7. Monitoring & Key Risk Indicators (KRIs)

The Company monitors KRIs with defined green/amber/red thresholds ([PLACEHOLDER: thresholds]). Example metrics by category:

- **Operational:** RPC node error/latency rate; deposit-watcher lag; withdrawal-signer queue depth; PSP/off-ramp settlement failure rate; vendor SLA breaches.
- **Custody & key mgmt:** % of custody held in hot wallets; count of signer credential rotations; anomalous signer activity alerts; KMS/HSM migration completion %.
- **Market / FX:** USDT de-peg deviation; realized vs. expected FX spread; swap slippage.
- **Liquidity & solvency:** reserve-to-liability coverage ratio; per-chain treasury buffer vs. pending withdrawals; unreconciled balance differences; off-ramp float aging.
- **Fraud:** ATO incidents; unauthorized card-authorization rate; chargeback/dispute rate; velocity-limit breach count; flagged mule/referral-abuse accounts.
- **Compliance:** open SARs / aging; screening hit rate and clearance time; % customers by KYT/risk score; frozen-account count.
- **Cyber / infosec:** audit-log chain integrity verification pass rate; API rate-limit rejection trends; failed-login / anti-phishing anomaly rate; access-review completion.

KRIs are reported to the Risk Committee at least [PLACEHOLDER: cadence]; breaches trigger escalation per Section 5.

## 8. Incident Response Process

1. **Detect & log** — identify the incident via monitoring, KRIs, alerts, or report; record it and preserve evidence (including relevant hash-chained audit logs).
2. **Triage & classify** — assign severity and category (e.g., custody/security incident, solvency breach, fraud, compliance event, vendor outage) and an incident owner.
3. **Contain** — apply containment such as account freeze, address blacklisting, withdrawal pause, signer isolation, or vendor failover.
4. **Escalate** — notify the CRO and, for material incidents, the Risk Committee/Board, and assess regulatory reporting obligations ([PLACEHOLDER: regulator]) and SAR triggers.
5. **Remediate & recover** — restore service and correct root cause.
6. **Post-incident review** — conduct a root-cause analysis, capture lessons learned, and update controls, the risk register, and this Framework as needed.

## 9. Business Continuity & Disaster Recovery

The Company maintains a BCDR plan covering platform availability, custody/signing continuity, treasury access, and third-party (PSP, RPC, KYT, program manager) failure scenarios. Recovery objectives are:

- **Recovery Time Objective (RTO):** [PLACEHOLDER: RTO target].
- **Recovery Point Objective (RPO):** [PLACEHOLDER: RPO target].

The plan addresses key-material recovery and secure backup (BIP32 seed / KMS-HSM continuity), ledger and database restore with reconciliation-on-recovery, alternate RPC/node and PSP providers, and periodic tabletop and recovery testing at least [PLACEHOLDER: cadence]. Any insurance or third-party guarantee relied upon for continuity or custody loss is [PLACEHOLDER: insurance coverage] and is not asserted by this Framework.

## 10. Reporting & Review Cadence

- The CRO reports the aggregate risk profile, KRIs, incidents, and limit breaches to the Risk Committee at least [PLACEHOLDER: cadence].
- The Risk Committee reports to the Board at least [PLACEHOLDER: cadence].
- This Framework is reviewed and re-approved by the Board at least annually and upon material change to the business, products, regulatory environment, or risk profile.
- The risk register is maintained continuously and reviewed at each Risk Committee meeting.

---

**Effective date:** [PLACEHOLDER: effective date]
**Governing law:** [PLACEHOLDER: governing law]

**DRAFT — for legal review. Not legal advice.**
