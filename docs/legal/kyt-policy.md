**DRAFT — for legal review. Not legal advice.**

Version 0.1-draft
Last updated: [PLACEHOLDER: date]

# Know-Your-Transaction (KYT) & Blockchain Analytics Policy

This document sets out the Know-Your-Transaction (KYT) and blockchain-analytics controls operated by [PLACEHOLDER: entity name] ("PoisaPay", "the Company", "we") in connection with its stablecoin payment platform. It is a working draft and must be reviewed and approved by qualified legal counsel before adoption.

---

## 1. Purpose & Scope

### 1.1 Purpose
This Policy establishes how PoisaPay screens on-chain activity — incoming deposits and outgoing withdrawal destinations — to detect and mitigate exposure to illicit finance, sanctions, and other financial-crime risk arising from the movement of virtual assets on public blockchains.

### 1.2 Scope
This Policy applies to all on-chain activity involving PoisaPay's custodial hosted wallets, specifically USDT on:

1. The TRON network (TRC20); and
2. The Ethereum and BNB Smart Chain networks (ERC20).

It covers crypto deposits (incoming funds) and on-chain withdrawals (outgoing destinations). Internal transfers, fiat rails, swaps, merchant payments, and cards are governed by the AML/CFT Policy and related controls; this Policy addresses their on-chain touchpoints where relevant.

### 1.3 Base & Display Currency
Values are measured and displayed in [PLACEHOLDER: primary base currency, BDT] and/or USD as applicable.

---

## 2. Relationship to AML/CFT & KYC

### 2.1 Part of a Broader Program
KYT is one control within PoisaPay's wider financial-crime framework. It complements, and does not replace:

1. **KYC** (customer identity verification, tiers 0/1/2, document verification, planned liveness);
2. **Sanctions & PEP screening** of persons; and
3. **AML/CFT transaction monitoring**, risk rating, and reporting.

### 2.2 Feeding Customer Risk
On-chain exposure derived under this Policy is an input to the customer risk rating described in the AML/CFT Policy. Persistent or severe on-chain exposure elevates a customer's risk rating and may trigger Enhanced Due Diligence, restrictions, or offboarding.

### 2.3 Feeding SAR/STR
KYT findings feed the SAR/STR workflow in the AML/CFT Policy where suspicion arises (see Section 10).

---

## 3. On-Chain Risk Scoring

### 3.1 Principle
PoisaPay scores blockchain transactions and addresses to estimate the likelihood and severity of exposure to illicit activity. Scoring is applied to:

1. **Deposits** — the source of incoming funds, before the funds are credited to the customer's available balance; and
2. **Withdrawal destinations** — the destination address, before an outgoing transfer is signed and broadcast.

### 3.2 Method
Scoring assesses direct and indirect exposure (counterparties one or more hops away), the proportion and value of exposed funds, and the category of exposed counterparties. Scores are recorded against the relevant deposit or withdrawal.

---

## 4. Categories of Illicit Exposure

### 4.1 Categories
The following exposure categories are assessed, without limitation:

1. **Mixers / tumblers** — services designed to obfuscate the origin of funds.
2. **Darknet markets** — marketplaces trading in illicit goods or services.
3. **Sanctioned addresses** — addresses designated by OFAC, UN, EU, or [PLACEHOLDER: local sanctions lists].
4. **Scams / fraud** — addresses linked to fraudulent schemes, phishing, or Ponzi/investment scams.
5. **Stolen funds** — proceeds of hacks, thefts, or exploits.
6. **High-risk exchanges** — exchanges or services with inadequate KYC/AML controls or known illicit-flow association.

### 4.2 Sanctions Precedence
Exposure to a sanctioned address is treated with the highest severity and handled under both this Policy and the sanctions controls in the AML/CFT Policy.

---

## 5. Scoring Thresholds, Tiers & Actions

### 5.1 Risk Tiers
Each scored deposit or withdrawal destination is assigned a risk tier. The score bands are configured in the controls configuration; where set by law they are applied as [PLACEHOLDER: legally set thresholds], otherwise as [PLACEHOLDER: configured score thresholds]:

| Tier | Indicative meaning | Default action |
|------|--------------------|----------------|
| Low | No or negligible illicit exposure | Proceed (credit / send) |
| Medium | Some indirect or lower-severity exposure | Proceed with enhanced monitoring; may alert compliance |
| High | Material exposure to illicit categories | Hold pending review; escalate to compliance |
| Severe | Direct sanctioned exposure or high-value illicit exposure | Freeze / reject; escalate to MLRO; consider SAR/STR and sanctions reporting |

### 5.2 Threshold Placeholders
The numeric score boundaries between Low, Medium, High, and Severe are [PLACEHOLDER: score thresholds], calibrated to risk appetite and reviewed periodically.

### 5.3 Action Mapping
The mapping of tier to action (proceed, hold, freeze, reject, escalate) is configurable and may be tightened for higher-risk customers, products, or corridors.

---

## 6. Deposit Screening

### 6.1 Pre-Credit Screening
Incoming on-chain funds are scored before the corresponding balance is made available to the customer. Screening evaluates the source of funds against the exposure categories in Section 4.

### 6.2 Actions
Based on the deposit's risk tier:

1. **Low / Medium** — credited (Medium may be flagged for monitoring).
2. **High** — held pending compliance review; funds are not made available until cleared.
3. **Severe** — held/frozen and, where required, rejected/returned or reported; sanctioned exposure is escalated to the MLRO and handled under the AML/CFT sanctions controls.

### 6.3 Documentation
The deposit score, tier, category detail, and disposition are recorded in the immutable, hash-chained audit log.

---

## 7. Withdrawal Screening

### 7.1 Pre-Send Screening
Before an on-chain withdrawal is signed and broadcast, the **destination address** is scored against the exposure categories in Section 4.

### 7.2 Actions
Based on the destination's risk tier:

1. **Low** — the withdrawal proceeds.
2. **Medium** — proceeds with enhanced monitoring or may require additional review.
3. **High** — held pending compliance review before send.
4. **Severe** — rejected/blocked; escalated to the MLRO; sanctioned destinations are blocked and reported under the AML/CFT sanctions controls.

### 7.3 Interaction with Address Whitelist
Withdrawal screening operates alongside the wallet-address whitelist and cooldown controls in Section 8; whitelisting does not exempt a destination from pre-send screening.

---

## 8. Wallet-Address Whitelist, Cooldown & Velocity

### 8.1 Address Whitelist
Customers may register withdrawal destination addresses on a whitelist. Withdrawals are permitted only to whitelisted addresses where this control is enforced for the customer or product.

### 8.2 Cooldown Period
A newly added whitelisted address is subject to a cooldown period of [PLACEHOLDER: cooldown duration] before it becomes usable for withdrawals. The cooldown mitigates account-takeover and rapid-exfiltration risk.

### 8.3 Velocity Limits
Velocity limits constrain the number and aggregate value of withdrawals (and other in-scope activity) over defined time windows, applied per customer, per product, and per risk tier. Breaches generate alerts and may automatically restrict further activity pending review.

---

## 9. Alerting & Escalation

### 9.1 Alert Generation
High and Severe tier results, velocity breaches, and other configured conditions generate alerts.

### 9.2 Routing
Alerts are routed to the compliance team for triage. High-severity and sanctions-related alerts are escalated to the MLRO / Compliance Officer ([PLACEHOLDER: MLRO / Compliance Officer name]).

### 9.3 Disposition
Alert handling, investigation notes, and dispositions are recorded and retained.

---

## 10. Integration with SAR/STR Workflow

### 10.1 Feed to SAR/STR
Where KYT findings give rise to knowledge, suspicion, or reasonable grounds to suspect ML/TF/PF, the matter is escalated into the SAR/STR workflow set out in the AML/CFT Policy: detection → internal escalation → MLRO review → filing with [PLACEHOLDER: FIU name] → record.

### 10.2 Evidence
KYT scores, exposure detail, and on-chain trails support SAR/STR preparation and any regulatory or law-enforcement request.

### 10.3 No Tipping-Off
The confidentiality and no-tipping-off requirements in the AML/CFT Policy apply equally to KYT-derived escalations and reports.

---

## 11. Vendor / Analytics Provider Adapter

### 11.1 Pluggable Provider
KYT scoring is implemented through a pluggable, adapter-based integration with a third-party blockchain-analytics provider. The active provider is [PLACEHOLDER: blockchain analytics provider], and providers may be changed or supplemented without altering the underlying screening workflow.

### 11.2 Chain Coverage
The provider integration supports the networks in scope: TRON (TRC20), Ethereum (ERC20), and BNB Smart Chain (ERC20).

### 11.3 Provider Diligence & Continuity
The Company performs due diligence on the analytics provider and maintains fallback controls (e.g. manual review, holds) where provider data is unavailable, degraded, or inconclusive.

---

## 12. Record-Keeping

### 12.1 Retained Records
PoisaPay retains KYT scores, exposure categorizations, screening decisions, alerts, investigations, whitelist/cooldown events, and related dispositions for at least [PLACEHOLDER: record-retention period], or such longer period as required by law.

### 12.2 Integrity
KYT-relevant events are recorded in the immutable, hash-chained audit log, providing tamper-evident integrity.

### 12.3 Availability
Records are made available to the MLRO, auditors, and competent authorities on request, subject to applicable law.

---

## 13. Policy Governance

### 13.1 Ownership & Review
This Policy is owned by the MLRO and reviewed at least [PLACEHOLDER: policy review frequency] and on material change (new chain, new provider, new product, regulatory change).

### 13.2 Effective Date
This Policy is effective from [PLACEHOLDER: effective date], subject to legal review and approval.

### 13.3 Approval
Approved by: [PLACEHOLDER: approver name / role] on [PLACEHOLDER: approval date].
