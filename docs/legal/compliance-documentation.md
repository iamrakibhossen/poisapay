**DRAFT — for legal review. Not legal advice.**

Version 0.1-draft
Last updated: [PLACEHOLDER: date]

# PoisaPay Compliance Documentation Overview

This document is the master compliance overview for PoisaPay. It ties together the platform's legal terms, data-protection notices, and financial-crime and risk policies into a single governance picture. It describes how the policies relate to one another, who owns them, and — importantly — how the platform's technical controls actually implement each policy commitment.

> **Licensing status: TO BE DETERMINED — obtain before launch.** PoisaPay does not currently hold, and this document does not assert, any licence, registration, authorisation, or regulatory approval in any jurisdiction. All references to a regulator, competent authority, financial intelligence unit (FIU), or licensing regime are marked [PLACEHOLDER] and must be completed by qualified counsel before any live customer launch.

---

## 1. Purpose and Scope

### 1.1 Purpose
This overview provides:
- A single index and reading order for PoisaPay's legal and compliance policy set.
- The governance and role structure that owns and enforces those policies.
- A policy register and control matrix mapping each policy commitment to the technical and operational control that satisfies it.
- The regulatory and licensing status, flagged as outstanding.
- The audit, review, and reporting cadence.

### 1.2 Scope
This document and the policy set apply to:
- The legal entity operating PoisaPay: [PLACEHOLDER: legal entity name and registered address].
- All PoisaPay products and features: custodial hosted wallets, crypto deposits and on-chain withdrawals, internal user-to-user transfers, fiat on-ramp/off-ramp over local rails (bank transfer and mobile wallets — bKash / Nagad), currency exchange/swaps, merchant payments and settlements, card issuing (virtual and physical), rewards/referrals, and credit lines.
- All employees, officers, contractors, and third-party processors acting on the platform's behalf.

### 1.3 Governing framework
The applicable legal and regulatory framework is [PLACEHOLDER: jurisdiction, statutes, regulations, and governing law]. This document does not determine that framework; it is a placeholder pending legal determination.

---

## 2. Governance and Roles

### 2.1 Three lines of defence
PoisaPay adopts a three-lines-of-defence model:
- **First line — business and operations:** product, engineering, treasury, and support teams that own and operate day-to-day controls.
- **Second line — risk and compliance:** the Compliance / AML function and the risk function, which set policy, monitor, and challenge the first line.
- **Third line — independent assurance:** internal or external audit providing independent testing of controls.

### 2.2 Key roles
| Role | Responsibility | Owner |
| --- | --- | --- |
| Board / senior management | Ultimate accountability; approves policies and risk appetite | [PLACEHOLDER] |
| Money Laundering Reporting Officer (MLRO) / Compliance Officer | Owns AML/CFT, sanctions, KYC, KYT, SAR/STR filing | [PLACEHOLDER] |
| Chief Risk Officer / Risk Committee | Owns enterprise risk framework | [PLACEHOLDER] |
| Data Protection Officer (DPO) | Owns privacy, data-subject rights | [PLACEHOLDER] |
| Head of Engineering / Security | Owns technical controls, custody, cyber security | [PLACEHOLDER] |
| Treasury | Owns solvency, reserves, reconciliation | [PLACEHOLDER] |

### 2.3 Policy ownership and approval
Each policy has a named owner (second line), is approved by senior management, and is reviewed at the cadence set in Section 6. Material changes require re-approval.

---

## 3. Policy Register

The following documents make up the PoisaPay legal and compliance set. All are DRAFT, version 0.1-draft.

| # | Document | Type | Primary owner | Purpose |
| --- | --- | --- | --- | --- |
| 1 | `terms-of-service.md` | Legal | Legal | Contract governing customer use of PoisaPay |
| 2 | `privacy-policy.md` | Data protection | DPO | How personal data is collected, used, shared, and protected |
| 3 | `cookie-policy.md` | Data protection | DPO | Cookies and local storage used by the platform |
| 4 | `refund-policy.md` | Legal | Legal / Ops | When refunds, reversals, and disputes apply |
| 5 | `card-terms.md` | Legal | Legal / Cards | Cardholder agreement for virtual and physical cards |
| 6 | `kyc-policy.md` | Financial crime | MLRO / Compliance | Customer identification and due diligence (tiered) |
| 7 | `aml-cft-policy.md` | Financial crime | MLRO / Compliance | AML/CFT program, sanctions, monitoring, SAR/STR |
| 8 | `kyt-policy.md` | Financial crime | MLRO / Compliance | Blockchain analytics / transaction risk screening |
| 9 | `risk-policy.md` | Risk | CRO / Risk | Enterprise risk-management framework |
| 10 | `compliance-documentation.md` | Governance | Compliance | This overview and control matrix |
| 11 | `README.md` | Index | Compliance | Index, disclaimer, and placeholder checklist |

---

## 4. Control Matrix — Policy to Technical Control

This is the core of the overview: it shows how PoisaPay's actual platform controls implement each policy commitment. Controls listed reflect the platform as described in the production-readiness scope. Items marked *(planned)* are on the roadmap and not yet in production.

### 4.1 Identity and onboarding (implements `kyc-policy.md`)
| Policy commitment | Technical / operational control |
| --- | --- |
| Tiered KYC (Tier 0 Unverified → Tier 1 Basic → Tier 2 Full) | Per-account KYC tier flag gating features; Tier 0 cannot withdraw; Tier 1 has limited daily withdrawal limits; Tier 2 unlocks higher limits and card eligibility |
| Customer Identification (CIP) | Email/phone capture at Tier 0; government-ID + selfie capture at Tier 1; proof of address and source-of-funds at Tier 2 |
| Document verification | Identity-verification vendor adapter |
| Liveness / biometric check | Liveness vendor integration *(planned)* |
| Enhanced Due Diligence / PEP | Risk-scoring engine + PEP screening adapter; senior sign-off workflow |

### 4.2 Sanctions, AML/CFT and monitoring (implements `aml-cft-policy.md`)
| Policy commitment | Technical / operational control |
| --- | --- |
| Sanctions and PEP screening | Adapter-based screening (pluggable provider) against OFAC/UN/EU + local lists [PLACEHOLDER]; run at onboarding and on an ongoing basis |
| Customer risk rating | Risk-scoring engine incorporating KYC tier, geography, product, behaviour, and on-chain exposure |
| Transaction monitoring | Rules-based monitoring with thresholds and velocity limits |
| Structuring / velocity red flags | Velocity limits and rule alerts |
| SAR/STR workflow | Internal alert → escalation → MLRO review → filing with FIU [PLACEHOLDER] → record; confidentiality and no-tipping-off enforced procedurally |
| Freeze / hold pending review | Account freeze and blacklist/whitelist controls |
| Travel Rule (originator/beneficiary) | *(roadmap — not yet implemented)* |

### 4.3 Transaction / blockchain screening (implements `kyt-policy.md`)
| Policy commitment | Technical / operational control |
| --- | --- |
| On-chain risk scoring | Blockchain-analytics vendor adapter (pluggable) covering TRON, Ethereum, BSC |
| Deposit screening | Incoming funds scored before credit; high-risk holds/freezes/rejects |
| Withdrawal screening | Destination address scored pre-send |
| Address whitelist + cooldown | Withdrawal address whitelist with a cooldown period [PLACEHOLDER duration] before a new address is usable |
| Velocity limits | Per-account velocity thresholds |
| Escalation | Alerts routed to compliance/MLRO and into the SAR workflow |

### 4.4 Custody and treasury (implements `risk-policy.md`)
| Policy commitment | Technical / operational control |
| --- | --- |
| Key security | Custodial hosted wallets; BIP32/HD derivation; migration to KMS/HSM *(planned)* |
| Solvency (reserves ≥ liabilities) | Internal double-entry ledger with exact Money amounts; reserves/assets must be ≥ customer liabilities |
| Treasury sufficiency | Per-chain treasury; coin pooling per canonical asset; reconciliation |
| Withdrawal integrity | Withdrawal signer behind contracts; address whitelist + cooldown |

### 4.5 Security and data protection (implements `privacy-policy.md`, `cookie-policy.md`, `risk-policy.md`)
| Policy commitment | Technical / operational control |
| --- | --- |
| Authentication | 2FA / TOTP |
| Account-takeover defence | Device management, suspicious-login detection, anti-phishing code |
| Encryption | Encryption at rest |
| Auditability | Immutable hash-chained audit logs |
| Abuse protection | API rate limiting |
| Data-subject rights | Access / rectification / erasure (subject to AML retention) / portability / objection workflows |

### 4.6 Products and customer terms (implements `terms-of-service.md`, `card-terms.md`, `refund-policy.md`)
| Policy commitment | Technical / operational control |
| --- | --- |
| Card issuing | Virtual and physical cards via program manager/issuer [PLACEHOLDER] |
| JIT authorization | Real-time debit of crypto/stablecoin balance at authorization, with time-of-authorization FX rate |
| Card limits and blocks | Spending limits, ATM limits, MCC blocks, country blocks, freeze/unfreeze |
| Irreversibility disclosure | On-chain transactions are final; disclosed in ToS and refund policy |
| Fees | Deposit %, withdrawal % + rail fee, FX spread, card fees |

---

## 5. Regulatory and Licensing Status

### 5.1 Status
**Licensing status: TO BE DETERMINED — obtain before launch.**

PoisaPay makes no claim to any of the following, all of which are outstanding and must be resolved by counsel:
- Money-services / payment-institution / e-money authorisation [PLACEHOLDER].
- Virtual Asset Service Provider (VASP) registration [PLACEHOLDER].
- Card program sponsorship/issuer and scheme membership [PLACEHOLDER].
- Cross-border and local-rail (bank / bKash / Nagad) partner authorisations [PLACEHOLDER].
- Data-protection registration with the competent authority [PLACEHOLDER].

### 5.2 Determination required
Before launch, counsel must determine the applicable jurisdiction(s), the licences required for each activity (custody, exchange, transfers, fiat on/off-ramp, card issuing, credit lines), and the timeline to obtain them. No activity requiring a licence should go live until that licence is held or a compliant partner arrangement is confirmed.

---

## 6. Audit, Review and Reporting Cadence

### 6.1 Policy review
- All policies reviewed at least annually, or on material change to products, vendors, or the regulatory framework.
- Owners are responsible for keeping their policy current and version-controlled.

### 6.2 Independent testing
- Independent audit/testing of the AML/CFT and KYC programs at least annually [PLACEHOLDER: frequency per regulator].
- Security testing (including penetration testing) on a periodic basis.

### 6.3 Management reporting
- Compliance and risk report to senior management on a regular cadence (e.g. monthly/quarterly [PLACEHOLDER]).
- Key Risk Indicators (KRIs) and financial-crime metrics reported per `risk-policy.md`.

### 6.4 Record-keeping
- All compliance records (KYC, screening, monitoring, SAR/STR, audit logs) retained for [PLACEHOLDER: statutory retention period].
- Immutable hash-chained audit logs provide a tamper-evident record of platform activity.

---

## 7. Change Control

Changes to this overview or any referenced policy follow the approval process in Section 2.3. This document must be updated whenever a new policy is added, a control changes, or the licensing status advances.

---

## 8. Cross-References

- Customer terms: `terms-of-service.md`, `card-terms.md`, `refund-policy.md`
- Data protection: `privacy-policy.md`, `cookie-policy.md`
- Financial crime: `kyc-policy.md`, `aml-cft-policy.md`, `kyt-policy.md`
- Risk: `risk-policy.md`
- Index and placeholder checklist: `README.md`

---

*End of document. DRAFT — for legal review. Not legal advice.*
