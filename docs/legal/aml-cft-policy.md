**DRAFT — for legal review. Not legal advice.**

Version 0.1-draft
Last updated: [PLACEHOLDER: date]

# Anti-Money Laundering & Counter-Financing of Terrorism (AML/CFT) Policy

This document sets out the AML/CFT program operated by [PLACEHOLDER: entity name] ("PoisaPay", "the Company", "we") in connection with its stablecoin payment platform. It is a working draft and must be reviewed and approved by qualified legal counsel and the Company's board (or equivalent governing body) before adoption.

---

## 1. Purpose & Regulatory Framework

### 1.1 Purpose
This Policy establishes the minimum standards, controls, and procedures by which PoisaPay identifies, assesses, mitigates, monitors, and reports money laundering (ML), terrorist financing (TF), and proliferation financing (PF) risk arising from its products and services. Its objectives are to:

1. Prevent PoisaPay from being used, knowingly or unknowingly, as a vehicle for ML, TF, PF, sanctions evasion, or other financial crime.
2. Ensure compliance with applicable AML/CFT laws, regulations, and guidance.
3. Protect customers, the Company, and the integrity of the wider financial and virtual-asset ecosystem.
4. Define clear roles, escalation paths, and reporting obligations.

### 1.2 Regulatory Framework
This Policy is intended to comply with the applicable AML/CFT legal and regulatory framework, including but not limited to [PLACEHOLDER: applicable AML/CFT statute(s), e.g. Money Laundering Prevention Act and regulations], guidance issued by [PLACEHOLDER: competent AML/CFT authority / FIU], and the recommendations of the Financial Action Task Force (FATF), including FATF Recommendation 15 and guidance on Virtual Assets and Virtual Asset Service Providers (VASPs).

### 1.3 Licensing & Registration Status
The Company's licensing, registration, or authorization status in respect of virtual-asset, payment, or money-services activity is [PLACEHOLDER: licence/registration/approval status and issuing authority]. **This Policy makes no representation that any licence, registration, or approval has been obtained.** Nothing in this document should be read as a claim of regulatory authorization.

### 1.4 Governing Law
This Policy is governed by, and shall be construed in accordance with, the laws of [PLACEHOLDER: governing law / jurisdiction].

---

## 2. Scope

### 2.1 Covered Activities
This Policy applies to all products and services offered by PoisaPay, including:

1. Custodial hosted wallets holding USDT on the TRON network (TRC20) and on the Ethereum and BNB Smart Chain networks (ERC20).
2. On-chain crypto deposits and on-chain crypto withdrawals.
3. Internal transfers between PoisaPay customer accounts.
4. Fiat on-ramp and off-ramp over local rails, including bank transfers and mobile financial services (bKash, Nagad).
5. Asset swaps executed with a spread.
6. Merchant payment acceptance and settlement.
7. Card products issued to customers.

### 2.2 Covered Persons
This Policy applies to all employees, officers, directors, contractors, agents, and third-party service providers who perform relevant functions on behalf of PoisaPay. Adherence is a condition of engagement. Breach may result in disciplinary action, termination, and/or referral to authorities.

### 2.3 Base & Display Currency
Amounts referenced in this Policy are measured and displayed in [PLACEHOLDER: primary base currency, BDT] and/or USD as applicable. All thresholds expressed in fiat terms are set out in the Company's controls configuration.

---

## 3. Governance & Responsibilities

### 3.1 Board / Senior Management
The board (or equivalent governing body) owns AML/CFT risk appetite, approves this Policy, ensures adequate resourcing of the compliance function, and receives periodic reporting on program effectiveness.

### 3.2 Money Laundering Reporting Officer (MLRO) / Compliance Officer
The Company appoints a Money Laundering Reporting Officer / Compliance Officer:

- Name: [PLACEHOLDER: MLRO / Compliance Officer name]
- Title: [PLACEHOLDER: title]
- Contact: [PLACEHOLDER: contact details]

The MLRO is a sufficiently senior, independent, and appropriately qualified individual with authority to act without undue influence.

### 3.3 MLRO Responsibilities
The MLRO is responsible for:

1. Owning, maintaining, and periodically reviewing this Policy and supporting procedures.
2. Overseeing the risk-based approach, customer risk rating, and control calibration.
3. Overseeing sanctions/PEP screening and transaction monitoring (KYT — see the KYT Policy).
4. Receiving internal escalations, reviewing alerts, and deciding on Suspicious Activity / Transaction Reports (SAR/STR).
5. Filing SAR/STR and any other required reports with [PLACEHOLDER: FIU name].
6. Acting as the primary point of contact with [PLACEHOLDER: competent authority / FIU / law enforcement].
7. Overseeing the training program and independent testing.
8. Reporting to senior management and the board.

### 3.4 Segregation & Escalation
All staff have a duty to escalate suspicions to the MLRO. Escalations, and the MLRO's decisions, are recorded in the Company's immutable, hash-chained audit log to ensure a tamper-evident record.

---

## 4. Risk-Based Approach

### 4.1 Principle
PoisaPay applies a risk-based approach (RBA): the intensity of due diligence, monitoring, and controls is proportionate to the ML/TF/PF risk presented by a customer, product, geography, or transaction.

### 4.2 Enterprise Risk Assessment
The Company maintains a documented enterprise-wide risk assessment covering customer, product/service, geographic, delivery-channel, and transaction risk. It is reviewed at least [PLACEHOLDER: review frequency] and upon material change (new product, new corridor, new chain, regulatory change).

### 4.3 Control Calibration
Controls — including KYC tier requirements, screening frequency, KYT thresholds, and velocity limits — are calibrated to the risk assessment and adjusted as risk evolves.

---

## 5. Customer Due Diligence & Risk Rating

### 5.1 KYC Tiers
PoisaPay operates a tiered KYC model. Each tier unlocks defined product access and limits:

| Tier | Requirements | Typical access |
|------|--------------|----------------|
| Tier 0 | Minimal / unverified registration | Restricted; onboarding only |
| Tier 1 | Identity data + document verification | Standard access with lower limits |
| Tier 2 | Enhanced verification (documents, liveness), address / source-of-funds as required | Full access with higher limits |

Document verification is performed at onboarding. Liveness verification is planned via a third-party vendor ([PLACEHOLDER: liveness vendor] — planned / not yet integrated) and is marked as roadmap where not yet live.

### 5.2 Customer Risk Rating Factors
Each customer is assigned a risk rating derived from, at minimum:

1. **KYC tier** — completeness and assurance level of identity verification.
2. **Geography** — customer nationality, residence, and jurisdiction of activity, weighted against high-risk and monitored jurisdiction lists.
3. **Product** — the products used (e.g. cards, fiat off-ramp, swaps) and their inherent risk.
4. **Transaction behavior** — volume, velocity, patterns, and consistency with the customer's stated profile.
5. **On-chain exposure** — KYT-derived exposure of the customer's deposit sources and withdrawal destinations (see the KYT Policy).

### 5.3 Rating Scale
Customers are rated on the following scale, each triggering defined controls:

| Rating | Description | Indicative controls |
|--------|-------------|---------------------|
| Low | Standard risk; no adverse factors | Standard CDD, baseline monitoring |
| Medium | One or more elevated factors | Enhanced monitoring, tighter limits |
| High | Multiple elevated factors / high-risk geography or product | Enhanced Due Diligence (EDD), senior sign-off, reduced limits |
| Prohibited | Sanctioned, on blacklist, or outside risk appetite | No onboarding / offboarding, freeze, report as required |

### 5.4 Enhanced Due Diligence (EDD)
EDD applies to High-risk customers, PEPs, and other elevated-risk situations, and may include additional identity evidence, source-of-funds/source-of-wealth inquiry, senior management approval, and heightened ongoing monitoring.

### 5.5 Ongoing Due Diligence
Customer information and risk ratings are kept current and re-assessed on trigger events (screening hit, KYT alert, behavior change, tier change) and on a periodic cycle proportionate to risk.

---

## 6. Sanctions & PEP Screening

### 6.1 Screening Obligation
PoisaPay screens customers, and where applicable connected/beneficial parties, against applicable sanctions and Politically Exposed Person (PEP) reference data, including:

1. OFAC (US Office of Foreign Assets Control) lists;
2. United Nations (UN) consolidated sanctions lists;
3. European Union (EU) consolidated sanctions lists;
4. [PLACEHOLDER: local / national sanctions and designated-persons lists];
5. PEP and adverse-media data as applicable.

### 6.2 Adapter-Based Architecture
Sanctions and PEP screening is implemented through a pluggable, adapter-based interface. The active reference-data / screening provider is [PLACEHOLDER: screening provider], and additional providers or lists may be added without change to the underlying workflow.

### 6.3 Screening Points
Screening is performed:

1. **At onboarding** — before a customer is granted access to funded services; and
2. **On an ongoing basis** — periodic re-screening and re-screening on list updates and on trigger events.

### 6.4 Positive & Potential Matches
Potential matches are held for review and resolution by the compliance team. Confirmed sanctions matches result in blocking, freeze, and reporting in accordance with Section 11. No sanctioned party is onboarded or serviced.

### 6.5 On-Chain Sanctions Screening
Sanctioned or designated blockchain addresses are treated as sanctions exposure and are handled through the KYT controls described in the KYT Policy and this Section.

---

## 7. Transaction Monitoring

### 7.1 Objective
PoisaPay monitors activity across deposits, withdrawals, internal transfers, fiat on/off-ramp, swaps, merchant payments, and card usage to detect activity inconsistent with a customer's profile or indicative of financial crime.

### 7.2 Monitoring Controls
Monitoring combines:

1. **Rules** — configurable detection rules across the product set.
2. **Thresholds** — value- and frequency-based thresholds. Where thresholds are set by law or regulation, they are applied as [PLACEHOLDER: legally set thresholds]; other thresholds are set in the controls configuration and calibrated to risk.
3. **Velocity limits** — limits on the number and aggregate value of transactions over defined windows, applied per customer, per product, and per risk tier.
4. **Risk scoring** — customer and transaction risk scores, incorporating KYT on-chain exposure.

### 7.3 Blacklist / Whitelist Controls
The platform maintains blacklist and whitelist controls at the account level, and a wallet-address whitelist with a cooldown period before a newly whitelisted address becomes usable for withdrawals (see the KYT Policy).

### 7.4 Alerts
Threshold breaches, rule hits, and elevated scores generate alerts routed to the compliance team for triage, investigation, and disposition. Alert handling is recorded in the immutable, hash-chained audit log.

---

## 8. Red-Flag Indicators

### 8.1 Non-Exhaustive Indicators
Staff and automated controls watch for red flags including, without limitation:

1. **Structuring** — transactions apparently broken up to fall below reporting or internal thresholds.
2. **Rapid in-out ("pass-through")** — funds deposited and rapidly withdrawn with little economic rationale.
3. **Mixer / tumbler exposure** — deposits or withdrawal destinations linked to mixing services (see KYT Policy).
4. **High-risk geographies** — activity connected to high-risk, monitored, or sanctioned jurisdictions.
5. **Profile mismatch** — transaction size, frequency, corridor, or counterparties inconsistent with the customer's stated profile or KYC tier.
6. **On-chain illicit exposure** — deposit sources or withdrawal destinations linked to darknet markets, scams/fraud, stolen funds, sanctioned addresses, or high-risk exchanges.
7. **Sanctions / PEP proximity** — links to designated persons or high-risk PEPs.
8. **Evasion behavior** — attempts to obscure identity, use of third-party funding, refusal to provide information, or use of the platform to layer funds through swaps and cards.

### 8.2 Response
A red flag does not by itself prove wrongdoing but triggers investigation, potential EDD, holds/freezes pending review, and consideration of a SAR/STR under Section 9.

---

## 9. SAR / STR Filing Workflow

### 9.1 Duty to Report
Where there is knowledge, suspicion, or reasonable grounds to suspect ML/TF/PF or related predicate offences, PoisaPay files a Suspicious Activity / Transaction Report (SAR/STR) with the relevant Financial Intelligence Unit.

### 9.2 Workflow
1. **Detection** — an alert, red flag, escalation, or external referral is raised.
2. **Internal escalation** — the handler documents the matter and escalates to the MLRO through the internal reporting channel.
3. **MLRO review** — the MLRO reviews the case, gathers supporting evidence (including KYT and audit-log records), and determines whether a report is warranted.
4. **Filing** — where warranted, the MLRO files a SAR/STR with [PLACEHOLDER: FIU name] via [PLACEHOLDER: FIU filing portal / channel] within any prescribed deadline.
5. **Record** — the decision (to file or not to file), rationale, and filing reference are recorded and retained per Section 12.

### 9.3 Confidentiality & No Tipping-Off
The existence, content, and consideration of a SAR/STR are strictly confidential. Staff must not disclose (directly or indirectly) to the customer or any third party that a report has been, is being, or may be made, or that an investigation is underway ("no tipping-off"), except as permitted by law. Breach may constitute a criminal offence.

### 9.4 Freeze / Hold Pending Review
Where necessary to prevent dissipation of funds or comply with legal obligations, the MLRO (or delegate) may freeze or hold an account or transaction pending review or instruction from authorities, consistent with applicable law and the platform's account-freeze and hold controls.

---

## 10. Training

### 10.1 Program
All covered persons receive AML/CFT training appropriate to their role:

1. At onboarding, before performing relevant functions;
2. On a recurring basis, at least [PLACEHOLDER: training frequency, e.g. annually]; and
3. Ad hoc, on material regulatory or typology changes.

### 10.2 Content
Training covers this Policy, red-flag typologies (including crypto/on-chain typologies), screening and KYT controls, escalation and SAR/STR obligations, no-tipping-off, and record-keeping. Completion is tracked and recorded.

---

## 11. Sanctions Blocking & Reporting

### 11.1 Blocking
Confirmed sanctions matches (whether a customer, counterparty, or blockchain address) result in blocking of the relevant transaction and/or account and prevention of the movement of funds.

### 11.2 Reporting
Sanctions matches and blocked/rejected transactions are reported to [PLACEHOLDER: relevant sanctions / competent authority] and to the FIU as required, within any prescribed timeframe.

### 11.3 Prohibition
PoisaPay does not onboard, transact with, or provide services to sanctioned persons or entities, and does not process transactions to or from sanctioned addresses or jurisdictions in breach of applicable sanctions.

---

## 12. Record Retention

### 12.1 Retention Period
PoisaPay retains customer due diligence records, screening results, transaction records, KYT results, alerts, investigations, SAR/STR records, and related correspondence for at least [PLACEHOLDER: record-retention period] from the end of the customer relationship or the date of the transaction, as applicable, or such longer period as required by law.

### 12.2 Integrity
Compliance-relevant events are recorded in an immutable, hash-chained audit log, providing tamper-evident integrity for the retained record.

### 12.3 Availability
Records are made available to the MLRO, auditors, and competent authorities on request, subject to applicable law.

---

## 13. Independent Audit & Testing

### 13.1 Independent Review
The AML/CFT program is subject to periodic independent testing, at least [PLACEHOLDER: audit frequency], by a function or party independent of day-to-day compliance operations.

### 13.2 Scope
Testing assesses the design and operating effectiveness of controls, including CDD, screening, transaction monitoring, KYT, SAR/STR handling, training, and record-keeping.

### 13.3 Remediation
Findings are tracked to remediation, and material findings are reported to senior management and the board.

---

## 14. Travel Rule Readiness — ROADMAP / NOT YET IMPLEMENTED

### 14.1 Status
**The FATF "Travel Rule" (Recommendation 16) capability is a ROADMAP item and is NOT yet implemented.** PoisaPay does not currently transmit or receive originator/beneficiary information for VASP-to-VASP virtual-asset transfers.

### 14.2 Planned Capability
When implemented, PoisaPay intends to collect, verify as required, transmit, and receive originator and beneficiary information for qualifying VASP transfers, in accordance with applicable Travel Rule obligations and thresholds [PLACEHOLDER: applicable Travel Rule threshold]. The messaging standard, counterparty VASP due diligence, and provider are to be determined [PLACEHOLDER: Travel Rule solution / provider].

### 14.3 Interim Controls
Pending implementation, PoisaPay relies on KYC, sanctions screening, and KYT (on-chain analytics) controls to mitigate counterparty and transfer risk.

---

## 15. Policy Governance

### 15.1 Ownership & Review
This Policy is owned by the MLRO and reviewed at least [PLACEHOLDER: policy review frequency] and on material change.

### 15.2 Effective Date
This Policy is effective from [PLACEHOLDER: effective date], subject to legal review and approval.

### 15.3 Approval
Approved by: [PLACEHOLDER: approver name / role] on [PLACEHOLDER: approval date].
